"""Contrato comum aos adaptadores de digitalizacao.

A camada HTTP so' conhece esta interface. Trocar WIA por TWAIN, ou por um mock
nos testes, nao obriga a tocar no servidor nem na seguranca.
"""

from __future__ import annotations

from dataclasses import dataclass, field
from typing import Callable, Protocol

#: Assinatura do callback de progresso (pagina actual, total estimado, mensagem).
ProgressCallback = Callable[[int, int, str], None]

COLOUR_MODES = ('bw', 'gray', 'color')
SOURCES = ('adf', 'flatbed')


@dataclass(frozen=True)
class Capabilities:
    sources: tuple[str, ...] = SOURCES
    colour_modes: tuple[str, ...] = COLOUR_MODES
    dpis: tuple[int, ...] = (150, 200, 300)
    duplex_supported: bool = False

    def to_payload(self) -> dict:
        return {
            'sources': list(self.sources),
            'color_modes': list(self.colour_modes),
            'dpis': list(self.dpis),
            'duplex_supported': self.duplex_supported,
        }


@dataclass(frozen=True)
class ScannerInfo:
    id: str
    name: str
    driver: str
    is_default: bool = False
    capabilities: Capabilities = field(default_factory=Capabilities)

    def to_payload(self) -> dict:
        return {
            'id': self.id,
            'name': self.name,
            'driver': self.driver,
            'is_default': self.is_default,
            'capabilities': self.capabilities.to_payload(),
        }


@dataclass(frozen=True)
class ScanRequest:
    scanner_id: str
    dpi: int = 200
    colour_mode: str = 'color'
    source: str = 'flatbed'
    duplex: bool = False
    orientation: str = 'portrait'
    auto_deskew: bool = True
    auto_crop: bool = True
    max_pages: int = 100


class ScannerAdapter(Protocol):
    """Adaptador concreto para um sistema de drivers."""

    driver: str

    def list_scanners(self) -> list[ScannerInfo]:
        """Equipamentos visiveis agora. Lista vazia e' resposta valida."""

    def scan(self, request: ScanRequest, progress: ProgressCallback | None = None) -> list[bytes]:
        """Devolve uma lista de paginas JPEG, pela ordem de captura."""
