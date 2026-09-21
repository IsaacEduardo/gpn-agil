"""Verificador de posto de trabalho do WebScan Bridge.

Corre os casos do roteiro que nao precisam de papel: comunicacao, pareamento,
seguranca de origem, deteccao de equipamento e recusa de sites externos. No fim
lista os casos que exigem uma pessoa junto ao scanner.

    python verificar-posto.py --origem http://162.35.116.198
    python verificar-posto.py --origem http://localhost --porta 18090
"""

from __future__ import annotations

import argparse
import json
import socket
import sys
from http.client import HTTPConnection

VERDE, VERMELHO, AMARELO, CINZA, FIM = '\033[92m', '\033[91m', '\033[93m', '\033[90m', '\033[0m'

# A consola do Windows em portugues usa cp1252 e rebenta ao escrever caracteres
# fora dessa tabela. Este verificador corre em postos de trabalho reais, por isso
# forcamos UTF-8 quando o Python o permite e degradamos sem partir quando nao.
for _fluxo in (sys.stdout, sys.stderr):
    try:
        _fluxo.reconfigure(encoding='utf-8', errors='replace')
    except (AttributeError, OSError):
        pass

resultados: list[tuple[str, bool, str]] = []


def registar(caso: str, passou: bool | None, detalhe: str = '') -> None:
    resultados.append((caso, passou, detalhe))
    marca = f'{VERDE}PASSA{FIM}' if passou else (f'{VERMELHO}FALHA{FIM}' if passou is False else f'{AMARELO}AVISO{FIM}')
    print(f'  [{marca}] {caso}' + (f' {CINZA}— {detalhe}{FIM}' if detalhe else ''))


def pedir(porta: int, metodo: str, caminho: str, origem: str | None, token: str | None,
          corpo: dict | None = None, tempo: float = 30.0):
    ligacao = HTTPConnection('127.0.0.1', porta, timeout=tempo)
    cabecalhos = {}
    if origem is not None:
        cabecalhos['Origin'] = origem
    if token is not None:
        cabecalhos['X-WebScan-Pairing'] = token
    if corpo is not None:
        cabecalhos['Content-Type'] = 'application/json'
    ligacao.request(metodo, caminho, body=json.dumps(corpo).encode() if corpo else None, headers=cabecalhos)
    resposta = ligacao.getresponse()
    dados = resposta.read()
    ligacao.close()
    try:
        return resposta.status, json.loads(dados or b'{}'), resposta
    except json.JSONDecodeError:
        return resposta.status, {}, resposta


def agente_responde(porta: int) -> bool:
    with socket.socket() as sock:
        sock.settimeout(2)
        return sock.connect_ex(('127.0.0.1', porta)) == 0


def main() -> int:
    parser = argparse.ArgumentParser(description='Verifica a instalacao do WebScan Bridge neste posto.')
    parser.add_argument('--origem', required=True, help='Origem exacta do EDMS, ex.: http://162.35.116.198')
    parser.add_argument('--porta', type=int, default=18090)
    parser.add_argument('--token', default=None, help='Codigo de pareamento (omitir le da configuracao)')
    args = parser.parse_args()

    origem = args.origem.rstrip('/')
    token = args.token
    if token is None:
        sys.path.insert(0, str(__import__('pathlib').Path(__file__).parent))
        from webscan_bridge.config import load_config
        token = load_config(create_missing=False).pairing_token

    print(f'\nWebScan Bridge — verificacao do posto')
    print(f'{CINZA}origem={origem}  porta={args.porta}{FIM}\n')

    # Caso 1/2 — comunicacao
    print('Comunicacao com o agente')
    if not agente_responde(args.porta):
        registar('Agente a escutar no loopback', False, f'nada responde em 127.0.0.1:{args.porta}')
        print(f'\n{VERMELHO}O agente nao esta a correr. Arranque-o e repita.{FIM}\n')
        return 1
    registar('Agente a escutar no loopback', True, f'127.0.0.1:{args.porta}')

    estado, corpo, _ = pedir(args.porta, 'GET', '/status', origem, token)
    registar('Responde online com o codigo correcto', estado == 200 and corpo.get('status') == 'online',
             f"versao {corpo.get('version', '?')}, driver {','.join(corpo.get('drivers_available', []))}")

    # Caso 3 — pareamento
    print('\nPareamento')
    estado, corpo, _ = pedir(args.porta, 'GET', '/status', origem, 'codigo-errado')
    registar('Recusa codigo de pareamento errado', estado == 401 and corpo.get('error_code') == 'PAIRING_REQUIRED')
    estado, corpo, _ = pedir(args.porta, 'GET', '/scanners', origem, None)
    registar('Recusa pedido sem codigo', estado == 401)

    # Caso 18 — origem
    print('\nSeguranca de origem')
    estado, corpo, resposta = pedir(args.porta, 'GET', '/scanners', 'https://site-malicioso.example', token)
    registar('Bloqueia site externo', estado == 403 and corpo.get('error_code') == 'ORIGIN_REJECTED')
    registar('Nao devolve CORS a origem recusada', resposta.getheader('Access-Control-Allow-Origin') is None)
    estado, _, _ = pedir(args.porta, 'GET', '/status', None, token, tempo=10)
    registar('Bloqueia host inesperado (DNS rebinding)',
             pedir(args.porta, 'GET', '/scanners', origem.replace('http://', 'http://x.'), token)[0] == 403)

    _, _, resposta = pedir(args.porta, 'OPTIONS', '/scan', origem, token)
    pna = resposta.getheader('Access-Control-Allow-Private-Network')
    registar('Preflight autoriza rede privada', pna == 'true', f'Allow-Private-Network: {pna}')
    if origem.startswith('http://') and not origem.startswith(('http://localhost', 'http://127.0.0.1')):
        registar('EDMS servido sobre HTTPS', None,
                 'origem em HTTP simples: o Chrome bloqueia pedidos ao loopback fora de contexto seguro')

    # Casos 4/5/6 — equipamento
    print('\nEquipamento')
    estado, corpo, _ = pedir(args.porta, 'GET', '/scanners', origem, token, tempo=60)
    scanners = corpo.get('scanners', []) if estado == 200 else []
    registar('Lista de scanners obtida', estado == 200, f'{len(scanners)} equipamento(s)')
    if not scanners:
        registar('Ha pelo menos um scanner detetado', None, 'nenhum equipamento WIA visivel neste posto')
    for scanner in scanners:
        capacidades = scanner.get('capabilities', {})
        detalhe = (f"origens={','.join(capacidades.get('sources', []))} "
                   f"dpi={','.join(str(d) for d in capacidades.get('dpis', []))} "
                   f"duplex={'sim' if capacidades.get('duplex_supported') else 'nao'}")
        registar(f"  {scanner.get('name', '?')[:40]}", True, detalhe)

    # Caso 17 — concorrencia
    print('\nLimites')
    estado, corpo, _ = pedir(args.porta, 'GET', '/progress', origem, token)
    registar('Progresso disponivel e inactivo', estado == 200 and not corpo.get('active'))
    estado, corpo, _ = pedir(args.porta, 'POST', '/scan', origem, token, corpo={'scanner_id': 'nao-existe'})
    registar('Recusa scanner inexistente', estado == 404 and corpo.get('error_code') == 'SCANNER_NOT_FOUND')
    estado, corpo, _ = pedir(args.porta, 'POST', '/scan', origem, token, corpo={'nada': 'aqui'})
    registar('Recusa pedido sem scanner', estado == 400)

    falhas = [caso for caso, passou, _ in resultados if passou is False]
    avisos = [caso for caso, passou, _ in resultados if passou is None]

    print('\n' + '-' * 62)
    print(f'{len([r for r in resultados if r[1]])} passaram, {len(falhas)} falharam, {len(avisos)} avisos')
    if falhas:
        print(f'{VERMELHO}Falhas:{FIM} ' + '; '.join(falhas))

    print(f'\n{AMARELO}Estes casos exigem uma pessoa junto ao scanner e nao podem ser automatizados:{FIM}')
    for linha in (
        'Colocar 3 folhas no ADF e digitalizar: progresso por pagina e 3 miniaturas',
        'Anexar o PDF e confirmar que abre no Acrobat/Edge com todas as paginas',
        'Retirar as folhas e digitalizar: mensagem legivel de alimentador vazio',
        'Provocar encravamento: mensagem de papel encravado e repeticao funcional',
        'Duplex com 2 folhas impressas dos dois lados: 4 paginas pela ordem certa',
        'Vidro (flatbed) com 1 folha: exactamente 1 pagina',
        'Lote de 30+ folhas: completa ou falha com mensagem clara, nunca PDF truncado',
    ):
        print(f'  [ ] {linha}')
    print()
    return 1 if falhas else 0


if __name__ == '__main__':
    sys.exit(main())
