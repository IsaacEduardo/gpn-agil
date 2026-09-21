"""Montagem de PDF multipagina a partir de JPEG, sem dependencias externas.

Os bytes JPEG entregues pelo scanner sao embebidos tal e qual, atraves do filtro
`/DCTDecode`. Nao ha descodificacao nem recompressao: o ficheiro fica menor, a
imagem nao perde qualidade entre o vidro e o arquivo, e o OCR trabalha sobre o
mesmo pixel que o operador viu na pre-visualizacao.
"""

from __future__ import annotations

from dataclasses import dataclass

# SOF0..SOF15, excepto DHT (C4), JPG reservado (C8) e DAC (CC).
_SOF_MARKERS = {0xC0, 0xC1, 0xC2, 0xC3, 0xC5, 0xC6, 0xC7, 0xC9, 0xCA, 0xCB, 0xCD, 0xCE, 0xCF}
_COLOUR_SPACES = {1: b'/DeviceGray', 3: b'/DeviceRGB', 4: b'/DeviceCMYK'}


class InvalidJpeg(ValueError):
    """O bloco recebido nao e' um JPEG que saibamos embeber."""


@dataclass(frozen=True)
class JpegInfo:
    width: int
    height: int
    components: int

    @property
    def colour_space(self) -> bytes:
        try:
            return _COLOUR_SPACES[self.components]
        except KeyError:
            raise InvalidJpeg(f'JPEG com {self.components} componentes nao e suportado.') from None


def read_jpeg_info(data: bytes) -> JpegInfo:
    """Le dimensoes e numero de componentes do cabecalho JPEG."""
    if len(data) < 4 or data[0:2] != b'\xff\xd8':
        raise InvalidJpeg('Bloco recebido nao comeca com a assinatura JPEG.')

    index = 2
    total = len(data)
    while index < total:
        if data[index] != 0xFF:
            index += 1
            continue
        while index < total and data[index] == 0xFF:
            index += 1  # bytes de enchimento entre segmentos
        if index >= total:
            break
        marker = data[index]
        index += 1
        if marker == 0xD9 or marker == 0xDA:  # fim da imagem ou inicio dos dados
            break
        if marker == 0x01 or 0xD0 <= marker <= 0xD7:
            continue  # marcadores sem payload
        if index + 2 > total:
            break
        length = int.from_bytes(data[index:index + 2], 'big')
        if marker in _SOF_MARKERS:
            if index + 8 > total:
                raise InvalidJpeg('Cabecalho JPEG truncado no segmento SOF.')
            height = int.from_bytes(data[index + 3:index + 5], 'big')
            width = int.from_bytes(data[index + 5:index + 7], 'big')
            components = data[index + 7]
            if width <= 0 or height <= 0:
                raise InvalidJpeg('JPEG declara dimensoes invalidas.')
            return JpegInfo(width=width, height=height, components=components)
        if length < 2:
            raise InvalidJpeg('Segmento JPEG com comprimento invalido.')
        index += length

    raise InvalidJpeg('Nao foi encontrado o segmento SOF com as dimensoes.')


def _points(pixels: int, dpi: int) -> float:
    """Converte pixeis para pontos PDF (1 ponto = 1/72 polegada)."""
    return pixels * 72.0 / float(dpi)


def _format_number(value: float) -> bytes:
    return f'{value:.4f}'.rstrip('0').rstrip('.').encode('ascii') or b'0'


def build_pdf(pages: list[bytes], dpi: int = 200, title: str | None = None) -> bytes:
    """Devolve um PDF 1.4 com uma pagina por JPEG recebido."""
    if not pages:
        raise InvalidJpeg('Nao ha paginas para compor o PDF.')
    if dpi <= 0:
        raise ValueError('DPI tem de ser positivo.')

    infos = [read_jpeg_info(page) for page in pages]
    for info in infos:
        info.colour_space  # valida cedo, antes de escrever seja o que for

    # 1 catalogo + 1 arvore de paginas + 3 objectos por pagina.
    page_count = len(pages)
    objects: dict[int, bytes] = {}
    page_object_ids = [3 + index * 3 for index in range(page_count)]

    kids = b' '.join(b'%d 0 R' % object_id for object_id in page_object_ids)
    objects[1] = b'<< /Type /Catalog /Pages 2 0 R >>'
    objects[2] = b'<< /Type /Pages /Count %d /Kids [%s] >>' % (page_count, kids)

    for index, (jpeg, info) in enumerate(zip(pages, infos)):
        page_id = page_object_ids[index]
        content_id = page_id + 1
        image_id = page_id + 2
        width_pt = _points(info.width, dpi)
        height_pt = _points(info.height, dpi)

        # A imagem ocupa a pagina inteira: escala pela matriz, sem reamostrar.
        content = b'q %s 0 0 %s 0 0 cm /Im0 Do Q\n' % (_format_number(width_pt), _format_number(height_pt))

        objects[page_id] = (
            b'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %s %s] '
            b'/Resources << /XObject << /Im0 %d 0 R >> /ProcSet [/PDF /ImageC /ImageB] >> '
            b'/Contents %d 0 R >>'
            % (_format_number(width_pt), _format_number(height_pt), image_id, content_id)
        )
        objects[content_id] = b'<< /Length %d >>\nstream\n%s\nendstream' % (len(content), content)
        objects[image_id] = (
            b'<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace %s '
            b'/BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n'
            % (info.width, info.height, info.colour_space, len(jpeg))
        ) + jpeg + b'\nendstream'

    info_id = None
    if title:
        info_id = max(objects) + 1
        safe = ''.join(c for c in title if c.isprintable() and c not in '()' + chr(92))
        objects[info_id] = b'<< /Title (%s) /Producer (WebScan Bridge) >>' % safe.encode('latin-1', 'replace')

    out = bytearray(b'%PDF-1.4\n%\xe2\xe3\xcf\xd3\n')
    offsets: dict[int, int] = {}
    for object_id in sorted(objects):
        offsets[object_id] = len(out)
        out += b'%d 0 obj\n' % object_id + objects[object_id] + b'\nendobj\n'

    highest = max(objects)
    xref_offset = len(out)
    out += b'xref\n0 %d\n' % (highest + 1)
    out += b'0000000000 65535 f \n'
    for object_id in range(1, highest + 1):
        # Objectos nao usados ficam livres; a numeracao acima nunca deixa buracos,
        # mas o formato exige a entrada na mesma.
        if object_id in offsets:
            out += b'%010d 00000 n \n' % offsets[object_id]
        else:
            out += b'0000000000 65535 f \n'

    trailer = b'<< /Size %d /Root 1 0 R' % (highest + 1)
    if info_id is not None:
        trailer += b' /Info %d 0 R' % info_id
    trailer += b' >>'
    out += b'trailer\n' + trailer + b'\nstartxref\n%d\n%%%%EOF\n' % xref_offset
    return bytes(out)
