"""Ponto de entrada do agente: `python -m webscan_bridge`."""

from __future__ import annotations

import argparse
import logging
import sys
from pathlib import Path

from . import __version__
from .config import load_config
from .errors import WebScanError
from .scanners.base import ScannerAdapter


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
    parser.add_argument('--print-token', action='store_true', help='Mostra o codigo de pareamento e sai.')
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
        print(config.pairing_token)
        return 0

    try:
        adapter = build_adapter(config.driver)
    except WebScanError as error:
        logging.error('%s', error.message)
        return 2

    from .server import build_server

    try:
        server = build_server(config, adapter)
    except OSError as error:
        logging.error('Nao foi possivel escutar em %s:%s (%s). O agente ja esta a correr?',
                      config.host, config.port, error)
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
