"""Adaptador sem hardware, para testes automaticos e demonstracoes.

Permite exercitar todo o percurso - seguranca, progresso, montagem do PDF e
anexacao no formulario - em maquinas sem scanner, incluindo a integracao
continua, que nunca tera' um equipamento ligado.
"""

from __future__ import annotations

import base64

from ..errors import FeederEmpty, PaperJam, ScannerNotFound, ScannerOffline
from .base import Capabilities, ProgressCallback, ScannerInfo, ScanRequest

# JPEG minimo valido (16x22, tons de cinza). Evita depender do Pillow nos testes.
_FALLBACK_JPEG = base64.b64decode(
    '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDACAWGBwYFCAcGhwkIiAmMFA0MCwsMGJGSjpQdGZ6eHJmcG6AkLicgIiu'
    'im5woNqirr7EztDOfJri8uDI8LjKzsb/wAALCAAWABABAREA/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcI'
    'CQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcY'
    'GRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKj'
    'pKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/9oACAEBAAA/AOgo'
    'oooor//Z'
)

#: Falhas que o modo de teste sabe simular, para exercitar o tratamento de erros.
FAULTS = {
    'adf_empty': FeederEmpty('O alimentador esta vazio. Coloque as folhas e repita.'),
    'paper_jam': PaperJam('Papel encravado no alimentador. Liberte as folhas e repita.'),
    'offline': ScannerOffline('O scanner esta desligado ou desconectado.'),
}


def _render_page(number: int, dpi: int, colour_mode: str) -> bytes:
    """Desenha uma folha sintetica; recorre ao JPEG embebido se nao houver Pillow."""
    try:
        from PIL import Image, ImageDraw  # dependencia opcional
    except ImportError:
        return _FALLBACK_JPEG

    width = max(64, int(8.27 * dpi) // 8)
    height = max(90, int(11.69 * dpi) // 8)
    mode = 'L' if colour_mode in ('bw', 'gray') else 'RGB'
    image = Image.new(mode, (width, height), 255 if mode == 'L' else (255, 255, 255))
    draw = ImageDraw.Draw(image)
    margin = max(4, width // 20)
    draw.rectangle([margin, margin, width - margin, height - margin], outline=0 if mode == 'L' else (90, 90, 90))
    draw.text((margin * 2, margin * 2), f'WebScan Bridge\nPagina {number}\n{dpi} DPI / {colour_mode}',
              fill=0 if mode == 'L' else (20, 20, 20))

    from io import BytesIO
    buffer = BytesIO()
    image.save(buffer, 'JPEG', quality=80)
    return buffer.getvalue()


class MockScannerAdapter:
    """Scanner falso, determinista e configuravel."""

    driver = 'MOCK'

    def __init__(self, pages: int = 2, devices: list[ScannerInfo] | None = None, fault: str | None = None):
        self.pages = pages
        self.fault = fault
        self.devices = devices if devices is not None else [
            ScannerInfo(
                id='mock_duplex_adf',
                name='Scanner de demonstracao',
                driver=self.driver,
                is_default=True,
                capabilities=Capabilities(
                    sources=('adf', 'flatbed'),
                    colour_modes=('bw', 'gray', 'color'),
                    dpis=(150, 200, 300),
                    duplex_supported=True,
                ),
            )
        ]

    def list_scanners(self) -> list[ScannerInfo]:
        return list(self.devices)

    def scan(self, request: ScanRequest, progress: ProgressCallback | None = None) -> list[bytes]:
        if self.fault:
            raise FAULTS.get(self.fault, ScannerOffline('Falha simulada no scanner.'))
        if not any(device.id == request.scanner_id for device in self.devices):
            raise ScannerNotFound('O scanner selecionado ja nao esta disponivel.')

        total = self.pages * (2 if request.duplex else 1)
        total = min(total, request.max_pages)
        captured: list[bytes] = []
        for number in range(1, total + 1):
            if progress:
                progress(number, total, f'A digitalizar pagina {number} de {total}…')
            captured.append(_render_page(number, request.dpi, request.colour_mode))
        return captured
