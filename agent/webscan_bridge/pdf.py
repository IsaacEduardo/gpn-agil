"""Montagem de PDF multipagina a partir das folhas digitalizadas.

Cada folha passa por `prepare_page` antes de entrar no PDF:

- "Preto e branco" fica em 1 bit com compressao CCITT G4 (`/CCITTFaxDecode`), a
  mesma que os scanners usam nos seus proprios PDF: uma A4 a 200 DPI ocupa
  dezenas de KB. Antes era convertida em JPEG cinzento de 8 bits e pesava 10 a
  20 vezes mais.
- "Tons de cinza" e "Colorido" sao recomprimidos em JPEG com qualidade
  controlada (`/DCTDecode`). O driver HP por WIA entrega JPEG de qualidade
  maxima, que era embebido tal e qual e dava PDF de varios MB.
- Acima de `max_dpi` a folha e reduzida: para correspondencia, mais resolucao
  so aumenta o ficheiro, nao a leitura nem o OCR.

Sem Pillow (so em desenvolvimento), o JPEG do driver segue intacto.
"""

from __future__ import annotations

import logging
from dataclasses import dataclass
from io import BytesIO

logger = logging.getLogger(__name__)

DEFAULT_JPEG_QUALITY = 65
DEFAULT_MAX_DPI = 200
#: Limiar de binarizacao do "Preto e branco" quando o driver entrega cinzento.
BW_THRESHOLD = 160

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


def ensure_jpeg(data: bytes, quality: int = 85) -> bytes:
    """Devolve a pagina em JPEG embebivel, convertendo-a se o driver nao o fez.

    Pedimos JPEG ao WIA, mas varios drivers (HP incluidos) ignoram o pedido em
    "Preto e branco": uma imagem de 1 bit nao cabe em JPEG e chega BMP ou TIFF.
    Um JPEG valido segue intacto; o resto e convertido pelo Pillow.
    """
    try:
        read_jpeg_info(data).colour_space
        return data
    except InvalidJpeg:
        pass

    try:
        from io import BytesIO

        from PIL import Image
    except ImportError:
        raise InvalidJpeg('O scanner devolveu uma imagem que nao e JPEG e o Pillow nao esta disponivel.') from None

    try:
        image = Image.open(BytesIO(data))
        image.load()
    except Exception as error:  # noqa: BLE001 - qualquer falha de leitura e imagem invalida
        raise InvalidJpeg('O scanner devolveu uma imagem num formato desconhecido.') from error

    if image.mode in ('1', 'L', 'LA', 'I', 'I;16', 'F'):
        image = image.convert('L')
    elif image.mode != 'RGB':
        image = image.convert('RGB')
    buffer = BytesIO()
    image.save(buffer, 'JPEG', quality=quality)
    return buffer.getvalue()


@dataclass(frozen=True)
class Page:
    """Folha pronta a embeber: os bytes ja no formato do filtro do PDF."""

    data: bytes
    width: int
    height: int
    #: Resolucao efectiva, depois de uma eventual reducao; define o MediaBox.
    dpi: int
    #: 'jpeg' (/DCTDecode) ou 'ccitt' (/CCITTFaxDecode, 1 bit).
    encoding: str = 'jpeg'
    components: int = 1
    #: So no CCITT: o bit 1 representa preto (depende de quem codificou).
    black_is_1: bool = False


def _jpeg_page(data: bytes, dpi: int) -> Page:
    info = read_jpeg_info(data)
    info.colour_space  # valida cedo, antes de escrever seja o que for
    return Page(data=data, width=info.width, height=info.height, dpi=dpi,
                encoding='jpeg', components=info.components)


def _reduce(image, dpi: int, max_dpi: int):
    """Reduz a imagem para `max_dpi`; devolve (imagem, dpi efectivo)."""
    if not max_dpi or dpi <= max_dpi:
        return image, dpi
    from PIL import Image

    factor = max_dpi / float(dpi)
    size = (max(1, round(image.width * factor)), max(1, round(image.height * factor)))
    return image.resize(size, Image.LANCZOS), max_dpi


def _ccitt_g4(image) -> tuple[bytes, bool]:
    """Codifica uma imagem de 1 bit em CCITT G4; devolve (bytes, black_is_1).

    O Pillow so escreve G4 dentro de um TIFF. Pedimos uma unica faixa
    (RowsPerStrip = altura): faixas G4 sao codificadas uma a uma e nao podem ser
    concatenadas num so fluxo. A polaridade sai do proprio TIFF, para nao
    depender da versao do Pillow ou do libtiff.
    """
    from PIL import Image

    buffer = BytesIO()
    image.save(buffer, 'TIFF', compression='group4', tiffinfo={278: image.height})
    tiff = buffer.getvalue()
    tags = Image.open(BytesIO(tiff)).tag_v2
    offsets, counts = tags[273], tags[279]
    if len(offsets) != 1:
        raise InvalidJpeg('O codificador CCITT devolveu mais do que uma faixa.')
    # FillOrder 2 (bits invertidos) nao e o que o PDF espera.
    if tags.get(266, 1) != 1:
        raise InvalidJpeg('O codificador CCITT usou uma ordem de bits nao suportada.')
    return tiff[offsets[0]:offsets[0] + counts[0]], tags.get(262, 0) == 1


def prepare_page(data: bytes, colour_mode: str, dpi: int, *,
                 jpeg_quality: int = DEFAULT_JPEG_QUALITY, max_dpi: int = DEFAULT_MAX_DPI) -> Page:
    """Converte a folha do driver para o formato mais leve que o modo admite."""
    try:
        from PIL import Image
    except ImportError:
        # Sem Pillow nao ha como recomprimir; um JPEG valido segue como veio.
        return _jpeg_page(ensure_jpeg(data), dpi)

    try:
        image = Image.open(BytesIO(data))
        image.load()
    except Exception as error:  # noqa: BLE001 - qualquer falha de leitura e imagem invalida
        raise InvalidJpeg('O scanner devolveu uma imagem num formato desconhecido.') from error

    if colour_mode == 'bw':
        # Cinzento intermedio para reduzir sem serrilhar; depois limiar fixo,
        # sem pontilhado: o pontilhado estraga o texto e a compressao G4.
        grey = image.convert('L')
        grey, efectivo = _reduce(grey, dpi, max_dpi)
        mono = grey.point(lambda value: 255 if value >= BW_THRESHOLD else 0).convert('1')
        try:
            encoded, black_is_1 = _ccitt_g4(mono)
        except Exception as error:  # noqa: BLE001 - Pillow sem libtiff ou codificador recusou
            # Mais pesado, mas a folha nunca se perde por causa da compressao.
            logger.warning('CCITT G4 indisponivel (%s); a pagina segue em JPEG cinzento.', error)
            buffer = BytesIO()
            mono.convert('L').save(buffer, 'JPEG', quality=jpeg_quality, optimize=True)
            return _jpeg_page(buffer.getvalue(), efectivo)
        return Page(data=encoded, width=mono.width, height=mono.height, dpi=efectivo,
                    encoding='ccitt', components=1, black_is_1=black_is_1)

    target_mode = 'L' if colour_mode == 'gray' else 'RGB'
    converted = image.convert(target_mode) if image.mode != target_mode else image
    converted, efectivo = _reduce(converted, dpi, max_dpi)
    buffer = BytesIO()
    converted.save(buffer, 'JPEG', quality=jpeg_quality, optimize=True)
    recompressed = buffer.getvalue()

    # Um JPEG do driver ja mais leve, sem reducao nem mudanca de modo, fica como veio.
    if (efectivo == dpi and image.format == 'JPEG' and image.mode == target_mode
            and len(data) <= len(recompressed)):
        return _jpeg_page(data, dpi)
    return _jpeg_page(recompressed, efectivo)


def _points(pixels: int, dpi: int) -> float:
    """Converte pixeis para pontos PDF (1 ponto = 1/72 polegada)."""
    return pixels * 72.0 / float(dpi)


def _format_number(value: float) -> bytes:
    return f'{value:.4f}'.rstrip('0').rstrip('.').encode('ascii') or b'0'


def _image_object(page: Page) -> bytes:
    if page.encoding == 'ccitt':
        header = (
            b'<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceGray '
            b'/BitsPerComponent 1 /Filter /CCITTFaxDecode '
            b'/DecodeParms << /K -1 /Columns %d /Rows %d /BlackIs1 %s >> /Length %d >>\nstream\n'
            % (page.width, page.height, page.width, page.height,
               b'true' if page.black_is_1 else b'false', len(page.data))
        )
    else:
        colour_space = _COLOUR_SPACES.get(page.components)
        if colour_space is None:
            raise InvalidJpeg(f'JPEG com {page.components} componentes nao e suportado.')
        header = (
            b'<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace %s '
            b'/BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n'
            % (page.width, page.height, colour_space, len(page.data))
        )
    return header + page.data + b'\nendstream'


def build_pdf(pages: list[Page | bytes], dpi: int = 200, title: str | None = None) -> bytes:
    """Devolve um PDF 1.4 com uma pagina por folha.

    Aceita folhas ja preparadas (`Page`) ou JPEG em bytes, que entram tal e qual
    com a resolucao `dpi`.
    """
    if not pages:
        raise InvalidJpeg('Nao ha paginas para compor o PDF.')
    if dpi <= 0:
        raise ValueError('DPI tem de ser positivo.')

    # Valida tudo cedo, antes de escrever seja o que for.
    prepared = [page if isinstance(page, Page) else _jpeg_page(page, dpi) for page in pages]

    # 1 catalogo + 1 arvore de paginas + 3 objectos por pagina.
    page_count = len(prepared)
    objects: dict[int, bytes] = {}
    page_object_ids = [3 + index * 3 for index in range(page_count)]

    kids = b' '.join(b'%d 0 R' % object_id for object_id in page_object_ids)
    objects[1] = b'<< /Type /Catalog /Pages 2 0 R >>'
    objects[2] = b'<< /Type /Pages /Count %d /Kids [%s] >>' % (page_count, kids)

    for index, page in enumerate(prepared):
        page_id = page_object_ids[index]
        content_id = page_id + 1
        image_id = page_id + 2
        width_pt = _points(page.width, page.dpi)
        height_pt = _points(page.height, page.dpi)

        # A imagem ocupa a pagina inteira: escala pela matriz, sem reamostrar.
        content = b'q %s 0 0 %s 0 0 cm /Im0 Do Q\n' % (_format_number(width_pt), _format_number(height_pt))

        objects[page_id] = (
            b'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %s %s] '
            b'/Resources << /XObject << /Im0 %d 0 R >> /ProcSet [/PDF /ImageC /ImageB] >> '
            b'/Contents %d 0 R >>'
            % (_format_number(width_pt), _format_number(height_pt), image_id, content_id)
        )
        objects[content_id] = b'<< /Length %d >>\nstream\n%s\nendstream' % (len(content), content)
        objects[image_id] = _image_object(page)

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
