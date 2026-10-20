"""Testes do montador de PDF.

Garantem que o ficheiro entregue ao EDMS e um PDF verdadeiro, com uma pagina por
folha digitalizada, e que lixo nao passa por JPEG.
"""

import unittest

from webscan_bridge.pdf import InvalidJpeg, build_pdf, ensure_jpeg, prepare_page, read_jpeg_info
from webscan_bridge.scanners.mock import MockScannerAdapter
from webscan_bridge.scanners.base import ScanRequest


def sample_pages(count: int, dpi: int = 200) -> list[bytes]:
    adapter = MockScannerAdapter(pages=count)
    return adapter.scan(ScanRequest(scanner_id='mock_duplex_adf', dpi=dpi))


class ReadJpegInfoTests(unittest.TestCase):
    def test_le_dimensoes_do_cabecalho(self):
        info = read_jpeg_info(sample_pages(1)[0])
        self.assertGreater(info.width, 0)
        self.assertGreater(info.height, 0)
        self.assertIn(info.components, (1, 3))

    def test_recusa_conteudo_que_nao_e_jpeg(self):
        with self.assertRaises(InvalidJpeg):
            read_jpeg_info(b'%PDF-1.4 isto nao e uma imagem')

    def test_recusa_jpeg_truncado_antes_do_sof(self):
        with self.assertRaises(InvalidJpeg):
            read_jpeg_info(b'\xff\xd8\xff\xe0\x00\x10JFIF\x00')


class BuildPdfTests(unittest.TestCase):
    def test_gera_pdf_com_uma_pagina_por_folha(self):
        pdf = build_pdf(sample_pages(3), dpi=200)
        self.assertTrue(pdf.startswith(b'%PDF-'))
        self.assertTrue(pdf.rstrip().endswith(b'%%EOF'))
        self.assertEqual(pdf.count(b'/Type /Page\n') + pdf.count(b'/Type /Page '), 3)
        self.assertEqual(pdf.count(b'/Subtype /Image'), 3)

    def test_embebe_o_jpeg_original_sem_recomprimir(self):
        pages = sample_pages(2)
        pdf = build_pdf(pages, dpi=200)
        # O ficheiro tem de conter os bytes exactos que sairam do scanner.
        for page in pages:
            self.assertIn(page, pdf)
        self.assertIn(b'/DCTDecode', pdf)

    def test_tabela_xref_aponta_para_os_objectos(self):
        pdf = build_pdf(sample_pages(2), dpi=200)
        start = pdf.rindex(b'startxref')
        offset = int(pdf[start:].split(b'\n')[1])
        self.assertEqual(pdf[offset:offset + 4], b'xref')

    def test_dpi_define_o_tamanho_da_pagina(self):
        pages = sample_pages(1, dpi=300)
        baixa = build_pdf(pages, dpi=100)
        alta = build_pdf(pages, dpi=300)
        # A mesma imagem a 300 DPI ocupa um terco da area declarada.
        self.assertNotEqual(
            baixa[baixa.index(b'/MediaBox'):baixa.index(b'/MediaBox') + 40],
            alta[alta.index(b'/MediaBox'):alta.index(b'/MediaBox') + 40],
        )

    def test_recusa_lista_vazia(self):
        with self.assertRaises(InvalidJpeg):
            build_pdf([], dpi=200)

    def test_recusa_dpi_invalido(self):
        with self.assertRaises(ValueError):
            build_pdf(sample_pages(1), dpi=0)


def _imagem(modo: str, formato: str) -> bytes:
    from io import BytesIO

    from PIL import Image
    buffer = BytesIO()
    Image.new(modo, (170, 220), 1 if modo == '1' else 200).save(buffer, formato)
    return buffer.getvalue()


class EnsureJpegTests(unittest.TestCase):
    """Drivers WIA (HP) devolvem BMP/TIFF em "Preto e branco" apesar de pedirmos JPEG."""

    def test_jpeg_valido_segue_intacto(self):
        page = sample_pages(1)[0]
        self.assertIs(ensure_jpeg(page), page)

    def test_converte_bmp_de_1_bit_em_jpeg_cinzento(self):
        info = read_jpeg_info(ensure_jpeg(_imagem('1', 'BMP')))
        self.assertEqual((info.width, info.height, info.components), (170, 220, 1))

    def test_converte_tiff_a_cores(self):
        info = read_jpeg_info(ensure_jpeg(_imagem('RGB', 'TIFF')))
        self.assertEqual(info.components, 3)

    def test_pagina_convertida_entra_no_pdf(self):
        pdf = build_pdf([ensure_jpeg(_imagem('1', 'BMP'))], dpi=200)
        self.assertIn(b'/DeviceGray', pdf)

    def test_lixo_continua_a_ser_recusado(self):
        with self.assertRaises(InvalidJpeg):
            ensure_jpeg(b'isto nao e uma imagem')


def _folha_a4(modo: str, dpi: int, formato: str, **opcoes) -> bytes:
    """Folha A4 com linhas de texto e um carimbo, como o driver a entregaria."""
    from io import BytesIO

    from PIL import Image, ImageDraw
    largura, altura = round(8.27 * dpi), round(11.69 * dpi)
    branco = 1 if modo == '1' else (255 if modo == 'L' else (250, 248, 240))
    preto = 0 if modo in ('1', 'L') else (20, 20, 30)
    imagem = Image.new(modo, (largura, altura), branco)
    desenho = ImageDraw.Draw(imagem)
    passo = max(12, dpi // 8)
    for y in range(dpi, altura - dpi, passo):
        desenho.text((dpi // 2, y), 'Oficio n.o 123/GPH/2026 - assunto do documento de entrada ' * 2, fill=preto)
    carimbo = (dpi * 5, altura - dpi * 3, dpi * 7, altura - dpi)
    desenho.ellipse(carimbo, outline=preto if modo in ('1', 'L') else (30, 60, 160), width=max(2, dpi // 50))
    buffer = BytesIO()
    imagem.save(buffer, formato, **opcoes)
    return buffer.getvalue()


class PreparePageTests(unittest.TestCase):
    """O driver HP por WIA entrega JPEG de qualidade maxima e BMP de 1 bit no P&B;
    embebidos tal e qual davam PDF de varios MB por folha."""

    def test_preto_e_branco_fica_em_ccitt_g4_de_1_bit(self):
        driver = _folha_a4('1', 200, 'BMP')
        page = prepare_page(driver, 'bw', 200)
        pdf = build_pdf([page])

        self.assertEqual('ccitt', page.encoding)
        self.assertIn(b'/CCITTFaxDecode', pdf)
        self.assertIn(b'/BitsPerComponent 1', pdf)
        self.assertNotIn(b'/DCTDecode', pdf)
        self.assertLess(len(pdf), 100 * 1024, 'Uma A4 em P&B a 200 DPI deve ficar abaixo de 100 KB.')

    def test_sem_codificador_g4_a_folha_segue_em_jpeg(self):
        from unittest import mock
        with mock.patch('webscan_bridge.pdf._ccitt_g4', side_effect=OSError('sem libtiff')):
            page = prepare_page(_folha_a4('1', 100, 'BMP'), 'bw', 100)
        self.assertEqual(('jpeg', 1), (page.encoding, page.components))

    def test_preto_e_branco_a_partir_de_cinzento_tambem_binariza(self):
        page = prepare_page(_folha_a4('L', 200, 'JPEG', quality=95), 'bw', 200)
        self.assertEqual('ccitt', page.encoding)

    def test_tons_de_cinza_recomprimido_fica_mais_leve_que_o_do_driver(self):
        driver = _folha_a4('L', 200, 'JPEG', quality=98, subsampling=0)
        page = prepare_page(driver, 'gray', 200)

        self.assertEqual(('jpeg', 1), (page.encoding, page.components))
        self.assertLess(len(page.data), len(driver))
        self.assertLess(len(page.data), 400 * 1024)

    def test_cinzento_pedido_mas_driver_entrega_cores_fica_em_cinzento(self):
        page = prepare_page(_folha_a4('RGB', 150, 'JPEG', quality=95), 'gray', 150)
        self.assertEqual(1, page.components)

    def test_cores_acima_do_limite_sao_reduzidas_e_a_pagina_continua_a4(self):
        page = prepare_page(_folha_a4('RGB', 300, 'JPEG', quality=95), 'color', 300)
        pdf = build_pdf([page], dpi=300)

        self.assertEqual((200, 3), (page.dpi, page.components))
        self.assertAlmostEqual(1654, page.width, delta=2)
        # O MediaBox continua A4 (595 x 842 pt): reduzir nao encolhe o papel.
        caixa = pdf[pdf.index(b'/MediaBox [') + 11:].split(b']')[0].split()
        self.assertAlmostEqual(595, float(caixa[2]), delta=2)
        self.assertAlmostEqual(842, float(caixa[3]), delta=2)

    def test_max_dpi_zero_desliga_a_reducao(self):
        page = prepare_page(_folha_a4('L', 300, 'PNG'), 'gray', 300, max_dpi=0)
        self.assertEqual(300, page.dpi)

    def test_jpeg_do_driver_ja_leve_segue_intacto(self):
        driver = _folha_a4('L', 100, 'JPEG', quality=30)
        page = prepare_page(driver, 'gray', 100, jpeg_quality=90)
        self.assertEqual(driver, page.data)

    def test_lixo_continua_a_ser_recusado(self):
        with self.assertRaises(InvalidJpeg):
            prepare_page(b'isto nao e uma imagem', 'bw', 200)

    def test_preto_sai_preto_e_branco_sai_branco(self):
        """A polaridade do CCITT depende do codificador; confirmada a desenhar o PDF."""
        import shutil
        import subprocess
        import tempfile
        from io import BytesIO
        from pathlib import Path

        from PIL import Image, ImageDraw
        if not shutil.which('pdftoppm'):
            self.skipTest('pdftoppm nao instalado')

        imagem = Image.new('1', (400, 200), 1)
        ImageDraw.Draw(imagem).rectangle([20, 20, 120, 80], fill=0)
        buffer = BytesIO()
        imagem.save(buffer, 'BMP')
        pdf = build_pdf([prepare_page(buffer.getvalue(), 'bw', 72)], dpi=72)

        # No Windows o antivirus pode reter o ficheiro gerado um instante; a
        # pasta temporaria nao deve partir o teste por isso.
        with tempfile.TemporaryDirectory(ignore_cleanup_errors=True) as pasta:
            origem = Path(pasta) / 'p.pdf'
            origem.write_bytes(pdf)
            subprocess.run(['pdftoppm', '-r', '72', '-gray', str(origem), str(Path(pasta) / 'p')], check=True)
            desenho = Image.open(BytesIO(next(Path(pasta).glob('p*.pgm')).read_bytes()))
            fundo, rectangulo = desenho.getpixel((5, 5)), desenho.getpixel((50, 50))
        self.assertGreater(fundo, 200, 'O fundo tem de sair branco.')
        self.assertLess(rectangulo, 50, 'O rectangulo tem de sair preto.')


if __name__ == '__main__':
    unittest.main()
