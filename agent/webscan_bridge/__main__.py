"""Ponto de entrada do agente: `python -m webscan_bridge`."""

from __future__ import annotations

import argparse
import errno
import logging
import sys
import time
from pathlib import Path
from typing import Callable, TypeVar

from . import __version__
from .config import load_config
from .errors import WebScanError
from .scanners.base import ScannerAdapter

T = TypeVar('T')

#: Intervalo entre tentativas quando a porta esta ocupada por outra sessao.
ESPERA_PORTA_SEGUNDOS = 10
_WSAEADDRINUSE = 10048


def porta_ocupada(error: OSError) -> bool:
    """A porta pertence a outro processo (no Windows chega como WinError 10048)."""
    return error.errno in (errno.EADDRINUSE, _WSAEADDRINUSE) or getattr(error, 'winerror', None) == _WSAEADDRINUSE


def escutar_quando_livre(criar: Callable[[], T], *, espera: float = ESPERA_PORTA_SEGUNDOS,
                         dormir: Callable[[float], None] = time.sleep, porta: int | None = None) -> T:
    """Abre o servidor; se a porta estiver ocupada, espera e volta a tentar.

    Num PC com varias contas do Windows, o agente arranca em cada sessao mas so
    um pode ter a porta. Quem a tem serve todas as contas (a configuracao e da
    maquina); os outros ficam de reserva e assumem quando essa sessao terminar,
    sem que ninguem tenha de reiniciar nada.
    """
    avisado = False
    while True:
        try:
            return criar()
        except OSError as error:
            if not porta_ocupada(error):
                raise
            if not avisado:
                logging.warning('A porta %s esta ocupada por outro agente (outra sessao do Windows?). '
                                'Este fica de reserva e assume quando ela ficar livre.', porta)
                avisado = True
            dormir(espera)


def build_adapter(name: str) -> ScannerAdapter:
    """Escolhe o adaptador. `mock` permite validar o posto sem hardware."""
    if name == 'mock':
        from .scanners.mock import MockScannerAdapter
        return MockScannerAdapter()
    from .scanners.wia import WiaScannerAdapter
    return WiaScannerAdapter()


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(prog='webscan-bridge', description='Agente local de digitalizacao.')
    parser.add_argument('--config', type=Path, default=None, help='Ficheiro de configuracao alternativo.')
    parser.add_argument('--driver', choices=('wia', 'mock'), default=None, help='Forca o adaptador a usar.')
    parser.add_argument('--port', type=int, default=None, help='Porta loopback a escutar.')
    parser.add_argument('--verbose', action='store_true', help='Mostra detalhe tecnico no log.')
    parser.add_argument('--print-token', action='store_true',
                        help='Mostra o codigo de pareamento (so existe se o posto o exigir) e sai.')
    parser.add_argument('--version', action='version', version=__version__)
    args = parser.parse_args(argv)

    logging.basicConfig(
        level=logging.DEBUG if args.verbose else logging.INFO,
        format='%(asctime)s %(levelname)-7s %(name)s: %(message)s',
    )

    config = load_config(args.config)
    if args.driver:
        config.driver = args.driver
    if args.port:
        config.port = args.port

    if args.print_token:
        print(config.pairing_token if config.require_pairing else '')
        return 0

    try:
        adapter = build_adapter(config.driver)
    except WebScanError as error:
        logging.error('%s', error.message)
        return 2

    from .server import build_server

    try:
        server = escutar_quando_livre(lambda: build_server(config, adapter), porta=config.port)
    except OSError as error:
        logging.error('Nao foi possivel escutar em %s:%s (%s).', config.host, config.port, error)
        return 3

    logging.info('WebScan Bridge %s a escutar em http://%s:%s (driver %s)',
                 __version__, config.host, config.port, config.driver)
    logging.info('Origens autorizadas: %s', ', '.join(config.allowed_origins) or '(nenhuma configurada)')
    logging.info('Configuracao: %s', config.path)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        logging.info('A terminar a pedido do utilizador.')
    finally:
        server.server_close()
    return 0


if __name__ == '__main__':
    sys.exit(main())
