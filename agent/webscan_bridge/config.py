"""Configuracao do agente, lida de ficheiro JSON e do ambiente.

O agente nunca guarda credenciais da aplicacao: o unico segredo que conhece e o
token de pareamento, gerado localmente na primeira execucao e valido apenas
para falar com este agente, nesta maquina.
"""

from __future__ import annotations

import json
import logging
import os
import secrets
from dataclasses import dataclass, field
from pathlib import Path

logger = logging.getLogger(__name__)

#: O agente so pode escutar em loopback. Nao e configuravel de proposito:
#: expor o scanner a rede transformaria cada posto num servico de captura.
LOOPBACK_HOST = '127.0.0.1'

DEFAULT_PORT = 18090
DEFAULT_MAX_PAGES = 100
DEFAULT_MAX_BODY_BYTES = 64 * 1024
DEFAULT_SCAN_TIMEOUT = 180
CONFIG_FILENAME = 'webscan-agent.json'


def default_config_path() -> Path:
    """Local do ficheiro de configuracao, por utilizador do Windows."""
    override = os.environ.get('WEBSCAN_CONFIG')
    if override:
        return Path(override)
    base = os.environ.get('APPDATA') or os.environ.get('XDG_CONFIG_HOME') or str(Path.home())
    return Path(base) / 'WebScanBridge' / CONFIG_FILENAME


@dataclass
class AgentConfig:
    port: int = DEFAULT_PORT
    #: Origens exactas do EDMS autorizadas a falar com o agente.
    allowed_origins: tuple[str, ...] = ()
    pairing_token: str = ''
    driver: str = 'wia'
    max_pages: int = DEFAULT_MAX_PAGES
    max_body_bytes: int = DEFAULT_MAX_BODY_BYTES
    scan_timeout_seconds: int = DEFAULT_SCAN_TIMEOUT
    preview_max_edge: int = 320
    path: Path | None = field(default=None, compare=False)

    @property
    def host(self) -> str:
        return LOOPBACK_HOST

    def origin_allowed(self, origin: str | None) -> bool:
        if not origin:
            return False
        return origin.rstrip('/') in {item.rstrip('/') for item in self.allowed_origins}

    def to_file_payload(self) -> dict:
        return {
            'port': self.port,
            'allowed_origins': list(self.allowed_origins),
            'pairing_token': self.pairing_token,
            'driver': self.driver,
            'max_pages': self.max_pages,
            'max_body_bytes': self.max_body_bytes,
            'scan_timeout_seconds': self.scan_timeout_seconds,
            'preview_max_edge': self.preview_max_edge,
        }

    def save(self, path: Path | None = None) -> Path:
        target = Path(path or self.path or default_config_path())
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(json.dumps(self.to_file_payload(), indent=2, ensure_ascii=False), encoding='utf-8')
        self.path = target
        return target


def _as_tuple(value) -> tuple[str, ...]:
    if isinstance(value, str):
        items = [part.strip() for part in value.split(',')]
    elif isinstance(value, (list, tuple)):
        items = [str(part).strip() for part in value]
    else:
        return ()
    return tuple(item for item in items if item)


def load_config(path: Path | None = None, *, create_missing: bool = True) -> AgentConfig:
    """Le a configuracao; gera token e ficheiro na primeira execucao."""
    target = Path(path or default_config_path())
    data: dict = {}
    if target.is_file():
        try:
            data = json.loads(target.read_text(encoding='utf-8'))
        except (json.JSONDecodeError, OSError) as error:
            logger.error('Configuracao ilegivel em %s (%s); a usar valores por omissao.', target, error)
            data = {}

    config = AgentConfig(
        port=int(os.environ.get('WEBSCAN_PORT') or data.get('port') or DEFAULT_PORT),
        allowed_origins=_as_tuple(os.environ.get('WEBSCAN_ALLOWED_ORIGINS') or data.get('allowed_origins')),
        pairing_token=str(os.environ.get('WEBSCAN_PAIRING_TOKEN') or data.get('pairing_token') or ''),
        driver=str(os.environ.get('WEBSCAN_DRIVER') or data.get('driver') or 'wia').lower(),
        max_pages=int(data.get('max_pages') or DEFAULT_MAX_PAGES),
        max_body_bytes=int(data.get('max_body_bytes') or DEFAULT_MAX_BODY_BYTES),
        scan_timeout_seconds=int(data.get('scan_timeout_seconds') or DEFAULT_SCAN_TIMEOUT),
        preview_max_edge=int(data.get('preview_max_edge') or 320),
        path=target,
    )

    changed = False
    if not config.pairing_token:
        # Sem token, qualquer pagina aberta no browser falaria com o scanner.
        config.pairing_token = secrets.token_urlsafe(24)
        changed = True
        logger.info('Token de pareamento gerado. Consulte-o em %s', target)

    if changed and create_missing:
        try:
            config.save(target)
        except OSError as error:
            logger.error('Nao foi possivel gravar a configuracao em %s: %s', target, error)

    if not config.allowed_origins:
        logger.warning(
            'Nenhuma origem autorizada configurada: o agente vai recusar todos os pedidos do browser. '
            'Defina "allowed_origins" em %s', target,
        )
    return config
