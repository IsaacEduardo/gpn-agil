"""Testes do montador de PDF.

Garantem que o ficheiro entregue ao EDMS e um PDF verdadeiro, com uma pagina por
folha digitalizada, e que lixo nao passa por JPEG.
"""

import unittest

from webscan_bridge.pdf import InvalidJpeg, build_pdf, read_jpeg_info
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


if __name__ == '__main__':
    unittest.main()
