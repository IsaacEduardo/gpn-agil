"""Servidor HTTP local do WebScan Bridge.

Escuta apenas em loopback e so aceita pedidos que passem quatro filtros
independentes: Host esperado, Origin na allowlist, token de pareamento valido e
payload dentro dos limites. Um site externo falha logo no Origin - o browser
envia sempre esse cabecalho em pedidos cross-origin e nao permite falsifica-lo.
"""

from __future__ import annotations

import base64
import hmac
import json
import logging
import threading
import time
from dataclasses import dataclass
from http import HTTPStatus
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from typing import Any

from . import __version__
from .config import AgentConfig
from .errors import (
    InvalidRequest, NotAuthorised, OriginRejected, ScanBusy, ScannerNotFound,
    ScanTimeout, WebScanError,
)
from .pdf import build_pdf
from .scanners.base import ScannerAdapter, ScanRequest

logger = logging.getLogger(__name__)

PLATFORM_NAME = 'windows_x64'
COLOUR_MODES = ('bw', 'gray', 'color')
SOURCES = ('adf', 'flatbed')
ORIENTATIONS = ('portrait', 'landscape')


@dataclass
class ScanProgress:
    """Estado do trabalho em curso, lido pelo browser enquanto o POST decorre."""

    active: bool = False
    current_page: int = 0
    total_pages: int = 0
    message: str = ''
    percent: int = 0

    def to_payload(self) -> dict:
        return {
            'status': 'success',
            'active': self.active,
            'current_page': self.current_page,
            'total_pages': self.total_pages,
            'message': self.message,
            'percent': self.percent,
        }


def _thumbnail(jpeg: bytes, max_edge: int) -> bytes | None:
    """Reduz a pagina para pre-visualizacao. Sem Pillow, devolve None."""
    try:
        from io import BytesIO

        from PIL import Image
    except ImportError:
        return None
    try:
        image = Image.open(BytesIO(jpeg))
        image.thumbnail((max_edge, max_edge))
        if image.mode not in ('L', 'RGB'):
            image = image.convert('RGB')
        buffer = BytesIO()
        image.save(buffer, 'JPEG', quality=70)
        return buffer.getvalue()
    except Exception as error:  # noqa: BLE001 - a miniatura nunca deve partir o scan
        logger.debug('Falha a gerar miniatura: %s', error)
        return None


class ScanService:
    """Orquestra o adaptador, os limites e a montagem do PDF."""

    def __init__(self, adapter: ScannerAdapter, config: AgentConfig):
        self.adapter = adapter
        self.config = config
        self.progress = ScanProgress()
        self._lock = threading.Lock()

    def list_scanners(self) -> list[dict]:
        return [scanner.to_payload() for scanner in self.adapter.list_scanners()]

    def status(self) -> dict:
        return {
            'status': 'online',
            'version': __version__,
            'agent': 'WebScanBridgeDaemon',
            'os': PLATFORM_NAME,
            'drivers_available': [self.adapter.driver],
        }

    def scan(self, payload: dict) -> dict:
        request = self._build_request(payload)
        # Uma digitalizacao de cada vez: o driver e o hardware nao sao reentrantes.
        if not self._lock.acquire(blocking=False):
            raise ScanBusy('Ja existe uma digitalizacao em curso neste computador.')
        try:
            return self._run(request)
        finally:
            self.progress = ScanProgress()
            self._lock.release()

    def _build_request(self, payload: dict) -> ScanRequest:
        if not isinstance(payload, dict):
            raise InvalidRequest('O corpo do pedido tem de ser um objecto JSON.')

        scanner_id = str(payload.get('scanner_id') or '').strip()
        if not scanner_id:
            raise InvalidRequest('Indique o scanner a utilizar.')

        available = {scanner.id: scanner for scanner in self.adapter.list_scanners()}
        scanner = available.get(scanner_id)
        if scanner is None:
            raise ScannerNotFound('O scanner selecionado ja nao esta disponivel.')

        capabilities = scanner.capabilities
        try:
            dpi = int(payload.get('dpi') or 200)
        except (TypeError, ValueError):
            raise InvalidRequest('A resolucao indicada nao e um numero.') from None
        if dpi not in capabilities.dpis:
            raise InvalidRequest('O scanner nao suporta a resolucao pedida.')

        colour_mode = str(payload.get('color_mode') or 'color').lower()
        if colour_mode not in COLOUR_MODES or colour_mode not in capabilities.colour_modes:
            raise InvalidRequest('O scanner nao suporta o modo de cor pedido.')

        source = str(payload.get('source') or 'flatbed').lower()
        if source not in SOURCES or source not in capabilities.sources:
            raise InvalidRequest('O scanner nao suporta a origem de papel pedida.')

        duplex = bool(payload.get('duplex'))
        if duplex and not capabilities.duplex_supported:
            raise InvalidRequest('O scanner nao suporta digitalizacao frente e verso.')

        orientation = str(payload.get('orientation') or 'portrait').lower()
        if orientation not in ORIENTATIONS:
            raise InvalidRequest('Orientacao invalida.')

        return ScanRequest(
            scanner_id=scanner_id,
            dpi=dpi,
            colour_mode=colour_mode,
            source=source,
            duplex=duplex,
            orientation=orientation,
            auto_deskew=bool(payload.get('auto_deskew', True)),
            auto_crop=bool(payload.get('auto_crop', True)),
            max_pages=self.config.max_pages,
        )

    def _run(self, request: ScanRequest) -> dict:
        deadline = time.monotonic() + self.config.scan_timeout_seconds
        self.progress = ScanProgress(active=True, message='A preparar o scanner...', percent=5)

        def report(current: int, total: int, message: str) -> None:
            # O adaptador chama-nos a cada pagina: e aqui que o limite de tempo
            # se torna efectivo, sem precisar de matar a thread do driver.
            if time.monotonic() > deadline:
                raise ScanTimeout('A digitalizacao excedeu o tempo maximo permitido.')
            percent = 10 if not total else min(95, 10 + int(current * 85 / max(total, 1)))
            self.progress = ScanProgress(
                active=True, current_page=current, total_pages=total, message=message, percent=percent
            )

        started = time.monotonic()
        pages = self.adapter.scan(request, progress=report)
        if not pages:
            raise ScannerNotFound('O scanner nao devolveu nenhuma pagina.')
        if len(pages) > self.config.max_pages:
            pages = pages[:self.config.max_pages]

        pdf = build_pdf(pages, dpi=request.dpi, title='Documento digitalizado')
        previews = []
        for number, page in enumerate(pages, start=1):
            thumbnail = _thumbnail(page, self.config.preview_max_edge)
            if thumbnail is None:
                continue
            previews.append({
                'page_number': number,
                'mime_type': 'image/jpeg',
                'preview_base64': base64.b64encode(thumbnail).decode('ascii'),
            })

        elapsed = time.monotonic() - started
        # Nao registamos conteudo nem nome de documento: apenas metrica tecnica.
        logger.info('Digitalizacao concluida: %d pagina(s), %d bytes, %.1fs', len(pages), len(pdf), elapsed)

        return {
            'status': 'success',
            'page_count': len(pages),
            'filename': f'digitalizacao_{time.strftime("%Y%m%d_%H%M%S")}.pdf',
            'pdf_base64': base64.b64encode(pdf).decode('ascii'),
            'pages': previews,
        }


class WebScanRequestHandler(BaseHTTPRequestHandler):
    """Encaminha os pedidos depois de os validar."""

    protocol_version = 'HTTP/1.1'
    server_version = 'WebScanBridge/' + __version__
    sys_version = ''

    config: AgentConfig
    service: ScanService

    # --- utilitarios de resposta -------------------------------------------------

    def log_message(self, fmt: str, *args: Any) -> None:  # noqa: A003
        logger.debug('%s - %s', self.address_string(), fmt % args)

    def _origin(self) -> str | None:
        return self.headers.get('Origin')

    def _cors_headers(self) -> None:
        origin = self._origin()
        if self.config.origin_allowed(origin):
            self.send_header('Access-Control-Allow-Origin', origin)
            self.send_header('Access-Control-Allow-Credentials', 'false')
        # O Chrome exige este cabecalho para deixar uma pagina publica falar
        # com um servico da rede privada (Private Network Access).
        self.send_header('Access-Control-Allow-Private-Network', 'true')
        self.send_header('Vary', 'Origin')

    def _send_json(self, payload: dict, status: int = 200) -> None:
        body = json.dumps(payload, ensure_ascii=False).encode('utf-8')
        self.send_response(status)
        self.send_header('Content-Type', 'application/json; charset=utf-8')
        self.send_header('Content-Length', str(len(body)))
        self.send_header('Cache-Control', 'no-store')
        self.send_header('X-Content-Type-Options', 'nosniff')
        self._cors_headers()
        self.end_headers()
        self.wfile.write(body)

    def _send_error(self, error: WebScanError) -> None:
        logger.info('Pedido recusado (%s): %s', error.code, error.message)
        self._send_json(error.to_payload(), status=error.http_status)

    # --- validacao ---------------------------------------------------------------

    def _check_host(self) -> None:
        """Bloqueia DNS rebinding: o Host tem de ser o loopback literal."""
        host = (self.headers.get('Host') or '').strip().lower()
        hostname = host.rsplit(':', 1)[0] if host.count(':') == 1 else host
        if hostname not in ('127.0.0.1', 'localhost', '[::1]', '::1'):
            raise OriginRejected('Pedido dirigido a um host inesperado.')

    def _check_origin(self, *, required: bool) -> None:
        origin = self._origin()
        if origin is None:
            if required:
                raise OriginRejected('Pedido sem origem identificada.')
            return
        if not self.config.origin_allowed(origin):
            raise OriginRejected('Esta origem nao esta autorizada a usar o scanner.')

    def _check_pairing(self) -> None:
        expected = self.config.pairing_token
        if not expected:
            return
        provided = self.headers.get('X-WebScan-Pairing') or ''
        if not hmac.compare_digest(provided, expected):
            raise NotAuthorised('Introduza o codigo de pareamento do scanner.')

    def _read_json(self) -> dict:
        try:
            length = int(self.headers.get('Content-Length') or 0)
        except ValueError:
            raise InvalidRequest('Comprimento do pedido invalido.') from None
        if length <= 0:
            raise InvalidRequest('O pedido nao tem corpo.')
        if length > self.config.max_body_bytes:
            raise InvalidRequest('O pedido excede o tamanho maximo permitido.')
        if 'json' not in (self.headers.get('Content-Type') or '').lower():
            raise InvalidRequest('O corpo do pedido tem de ser JSON.')
        try:
            return json.loads(self.rfile.read(length).decode('utf-8'))
        except (UnicodeDecodeError, json.JSONDecodeError):
            raise InvalidRequest('O corpo do pedido nao e JSON valido.') from None

    # --- rotas -------------------------------------------------------------------

    def do_OPTIONS(self) -> None:  # noqa: N802
        try:
            self._check_host()
            self._check_origin(required=True)
        except WebScanError as error:
            self._send_error(error)
            return
        self.send_response(HTTPStatus.NO_CONTENT)
        self.send_header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', 'Content-Type, X-WebScan-Pairing')
        self.send_header('Access-Control-Max-Age', '600')
        self.send_header('Content-Length', '0')
        self._cors_headers()
        self.end_headers()

    def do_GET(self) -> None:  # noqa: N802
        route = self.path.split('?', 1)[0].rstrip('/') or '/'
        try:
            self._check_host()
            if route == '/status':
                self._check_origin(required=False)
                self._check_pairing()
                self._send_json(self.service.status())
            elif route == '/scanners':
                self._check_origin(required=True)
                self._check_pairing()
                self._send_json({'status': 'success', 'scanners': self.service.list_scanners()})
            elif route == '/progress':
                self._check_origin(required=True)
                self._check_pairing()
                self._send_json(self.service.progress.to_payload())
            else:
                self._send_error(InvalidRequest('Recurso inexistente.', code='NOT_FOUND', http_status=404))
        except WebScanError as error:
            self._send_error(error)
        except Exception as error:  # noqa: BLE001
            logger.exception('Falha inesperada em GET %s', route)
            self._send_error(WebScanError('Erro interno do agente: %s' % type(error).__name__))

    def do_POST(self) -> None:  # noqa: N802
        route = self.path.split('?', 1)[0].rstrip('/') or '/'
        try:
            self._check_host()
            self._check_origin(required=True)
            self._check_pairing()
            if route != '/scan':
                self._send_error(InvalidRequest('Recurso inexistente.', code='NOT_FOUND', http_status=404))
                return
            self._send_json(self.service.scan(self._read_json()))
        except WebScanError as error:
            self._send_error(error)
        except Exception as error:  # noqa: BLE001
            logger.exception('Falha inesperada em POST %s', route)
            self._send_error(WebScanError('Erro interno do agente: %s' % type(error).__name__))


def build_server(config: AgentConfig, adapter: ScannerAdapter) -> ThreadingHTTPServer:
    """Cria o servidor ligado exclusivamente ao loopback."""
    service = ScanService(adapter, config)
    handler = type('BoundWebScanHandler', (WebScanRequestHandler,), {'config': config, 'service': service})
    server = ThreadingHTTPServer((config.host, config.port), handler)
    server.daemon_threads = True
    return server
