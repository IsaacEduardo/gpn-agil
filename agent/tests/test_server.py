"""Testes do servidor HTTP local, com enfase na superficie de seguranca.

Sobem um agente real em loopback, numa porta efemera, e falam com ele por HTTP.
O adaptador e o mock, por isso a suite corre em qualquer maquina, sem scanner.
"""

import base64
import json
import threading
import unittest
from http.client import HTTPConnection

from webscan_bridge.config import AgentConfig
from webscan_bridge.scanners.base import Capabilities, ScannerInfo
from webscan_bridge.scanners.mock import MockScannerAdapter
from webscan_bridge.server import build_server

ORIGIN = 'https://edms.gov.ao'
TOKEN = 'token-de-teste'


class ServerTestCase(unittest.TestCase):
    """Base que arranca e desliga o agente para cada teste."""

    pages = 2
    fault = None
    max_pages = 100

    def setUp(self):
        self.config = AgentConfig(
            port=0,
            allowed_origins=(ORIGIN,),
            pairing_token=TOKEN,
            driver='mock',
            max_pages=self.max_pages,
            max_body_bytes=4096,
            scan_timeout_seconds=30,
        )
        adapter = MockScannerAdapter(pages=self.pages, fault=self.fault)
        self.server = build_server(self.config, adapter)
        self.port = self.server.server_address[1]
        self.thread = threading.Thread(target=self.server.serve_forever, daemon=True)
        self.thread.start()
        self.addCleanup(self._stop)

    def _stop(self):
        self.server.shutdown()
        self.server.server_close()
        self.thread.join(timeout=5)

    def call(self, method, path, *, origin=ORIGIN, token=TOKEN, body=None, host=None, content_type='application/json'):
        connection = HTTPConnection('127.0.0.1', self.port, timeout=10)
        headers = {}
        if origin is not None:
            headers['Origin'] = origin
        if token is not None:
            headers['X-WebScan-Pairing'] = token
        if body is not None:
            headers['Content-Type'] = content_type
        if host is not None:
            headers['Host'] = host
        payload = json.dumps(body).encode() if body is not None else None
        connection.request(method, path, body=payload, headers=headers)
        response = connection.getresponse()
        raw = response.read()
        connection.close()
        try:
            return response.status, json.loads(raw or b'{}'), response
        except json.JSONDecodeError:
            return response.status, {}, response


class StatusTests(ServerTestCase):
    def test_status_online_com_token_valido(self):
        status, body, _ = self.call('GET', '/status')
        self.assertEqual(status, 200)
        self.assertEqual(body['status'], 'online')
        self.assertEqual(body['drivers_available'], ['MOCK'])
        self.assertIn('version', body)

    def test_status_exige_pareamento(self):
        status, body, _ = self.call('GET', '/status', token=None)
        self.assertEqual(status, 401)
        self.assertEqual(body['error_code'], 'PAIRING_REQUIRED')

    def test_status_recusa_token_errado(self):
        status, body, _ = self.call('GET', '/status', token='errado')
        self.assertEqual(status, 401)
        self.assertEqual(body['error_code'], 'PAIRING_REQUIRED')


class OriginAndHostTests(ServerTestCase):
    def test_recusa_origem_nao_autorizada(self):
        status, body, _ = self.call('GET', '/scanners', origin='https://site-malicioso.example')
        self.assertEqual(status, 403)
        self.assertEqual(body['error_code'], 'ORIGIN_REJECTED')

    def test_scan_exige_cabecalho_origin(self):
        status, body, _ = self.call('POST', '/scan', origin=None, body={'scanner_id': 'mock_duplex_adf'})
        self.assertEqual(status, 403)
        self.assertEqual(body['error_code'], 'ORIGIN_REJECTED')

    def test_recusa_host_estranho_dns_rebinding(self):
        status, body, _ = self.call('GET', '/status', host='site-malicioso.example')
        self.assertEqual(status, 403)
        self.assertEqual(body['error_code'], 'ORIGIN_REJECTED')

    def test_resposta_autorizada_devolve_cors_da_origem_exacta(self):
        _, _, response = self.call('GET', '/status')
        self.assertEqual(response.getheader('Access-Control-Allow-Origin'), ORIGIN)
        self.assertNotEqual(response.getheader('Access-Control-Allow-Origin'), '*')
        self.assertEqual(response.getheader('Vary'), 'Origin')

    def test_resposta_recusada_nao_devolve_cors(self):
        _, _, response = self.call('GET', '/scanners', origin='https://site-malicioso.example')
        self.assertIsNone(response.getheader('Access-Control-Allow-Origin'))

    def test_preflight_autoriza_rede_privada(self):
        _, _, response = self.call('OPTIONS', '/scan', body=None)
        self.assertEqual(response.status, 204)
        self.assertEqual(response.getheader('Access-Control-Allow-Private-Network'), 'true')
        self.assertIn('X-WebScan-Pairing', response.getheader('Access-Control-Allow-Headers'))

    def test_preflight_de_origem_estranha_e_recusado(self):
        status, body, _ = self.call('OPTIONS', '/scan', origin='https://site-malicioso.example')
        self.assertEqual(status, 403)
        self.assertEqual(body['error_code'], 'ORIGIN_REJECTED')


class ScannerListTests(ServerTestCase):
    def test_lista_scanners_com_capacidades(self):
        status, body, _ = self.call('GET', '/scanners')
        self.assertEqual(status, 200)
        self.assertEqual(len(body['scanners']), 1)
        scanner = body['scanners'][0]
        self.assertEqual(scanner['driver'], 'MOCK')
        self.assertTrue(scanner['capabilities']['duplex_supported'])
        self.assertIn('adf', scanner['capabilities']['sources'])
        self.assertIn(200, scanner['capabilities']['dpis'])


class ScannerListVaziaTests(ServerTestCase):
    def setUp(self):
        super().setUp()
        self.server.RequestHandlerClass.service.adapter = MockScannerAdapter(devices=[])

    def test_lista_vazia_e_resposta_valida(self):
        status, body, _ = self.call('GET', '/scanners')
        self.assertEqual(status, 200)
        self.assertEqual(body['scanners'], [])


class ScanTests(ServerTestCase):
    pages = 3

    def payload(self, **overrides):
        base = {
            'scanner_id': 'mock_duplex_adf',
            'dpi': 200,
            'color_mode': 'color',
            'source': 'adf',
            'duplex': False,
        }
        base.update(overrides)
        return base

    def test_devolve_pdf_verdadeiro_multipagina(self):
        status, body, _ = self.call('POST', '/scan', body=self.payload())
        self.assertEqual(status, 200)
        self.assertEqual(body['page_count'], 3)
        pdf = base64.b64decode(body['pdf_base64'])
        self.assertTrue(pdf.startswith(b'%PDF-'))
        self.assertEqual(pdf.count(b'/Subtype /Image'), 3)
        self.assertTrue(body['filename'].endswith('.pdf'))

    def test_duplex_duplica_as_paginas(self):
        _, body, _ = self.call('POST', '/scan', body=self.payload(duplex=True))
        self.assertEqual(body['page_count'], 6)

    def test_devolve_miniaturas_para_previsualizacao(self):
        _, body, _ = self.call('POST', '/scan', body=self.payload())
        # As miniaturas sao opcionais (dependem do Pillow), mas se existirem
        # tem de ser uma por pagina e em JPEG.
        if body['pages']:
            self.assertEqual(len(body['pages']), 3)
            self.assertEqual(body['pages'][0]['mime_type'], 'image/jpeg')
            miniatura = base64.b64decode(body['pages'][0]['preview_base64'])
            self.assertTrue(miniatura.startswith(b'\xff\xd8'))

    def test_recusa_dpi_fora_das_capacidades(self):
        status, body, _ = self.call('POST', '/scan', body=self.payload(dpi=1200))
        self.assertEqual(status, 400)
        self.assertEqual(body['error_code'], 'INVALID_REQUEST')

    def test_recusa_duplex_em_scanner_sem_suporte(self):
        simples = ScannerInfo(
            id='scanner_simples', name='Planar sem ADF', driver='MOCK', is_default=True,
            capabilities=Capabilities(sources=('flatbed',), colour_modes=('gray', 'color'),
                                      dpis=(200,), duplex_supported=False),
        )
        self.server.RequestHandlerClass.service.adapter = MockScannerAdapter(devices=[simples])

        status, body, _ = self.call('POST', '/scan', body=self.payload(
            scanner_id='scanner_simples', source='flatbed', duplex=True))
        self.assertEqual(status, 400)
        self.assertIn('frente e verso', body['message'].lower())

    def test_recusa_origem_de_papel_que_o_scanner_nao_tem(self):
        simples = ScannerInfo(
            id='scanner_simples', name='Planar sem ADF', driver='MOCK', is_default=True,
            capabilities=Capabilities(sources=('flatbed',), colour_modes=('color',),
                                      dpis=(200,), duplex_supported=False),
        )
        self.server.RequestHandlerClass.service.adapter = MockScannerAdapter(devices=[simples])

        status, body, _ = self.call('POST', '/scan', body=self.payload(
            scanner_id='scanner_simples', source='adf'))
        self.assertEqual(status, 400)
        self.assertIn('origem de papel', body['message'].lower())

    def test_recusa_scanner_inexistente(self):
        status, body, _ = self.call('POST', '/scan', body=self.payload(scanner_id='nao-existe'))
        self.assertEqual(status, 404)
        self.assertEqual(body['error_code'], 'SCANNER_NOT_FOUND')

    def test_recusa_pedido_sem_scanner(self):
        status, body, _ = self.call('POST', '/scan', body={'dpi': 200})
        self.assertEqual(status, 400)
        self.assertEqual(body['error_code'], 'INVALID_REQUEST')

    def test_recusa_corpo_que_nao_e_json(self):
        connection = HTTPConnection('127.0.0.1', self.port, timeout=10)
        connection.request('POST', '/scan', body=b'nao sou json', headers={
            'Origin': ORIGIN, 'X-WebScan-Pairing': TOKEN, 'Content-Type': 'text/plain',
        })
        response = connection.getresponse()
        body = json.loads(response.read())
        connection.close()
        self.assertEqual(response.status, 400)
        self.assertEqual(body['error_code'], 'INVALID_REQUEST')

    def test_recusa_corpo_acima_do_limite(self):
        status, body, _ = self.call('POST', '/scan', body=self.payload(assunto='x' * 8000))
        self.assertEqual(status, 400)
        self.assertEqual(body['error_code'], 'INVALID_REQUEST')

    def test_rota_inexistente_devolve_404(self):
        status, body, _ = self.call('GET', '/vamos-ver')
        self.assertEqual(status, 404)
        self.assertEqual(body['error_code'], 'NOT_FOUND')


class ScanLimiteDePaginasTests(ServerTestCase):
    pages = 10
    max_pages = 4

    def test_respeita_o_limite_de_paginas_configurado(self):
        _, body, _ = self.call('POST', '/scan', body={'scanner_id': 'mock_duplex_adf', 'dpi': 200,
                                                      'color_mode': 'color', 'source': 'adf'})
        self.assertEqual(body['page_count'], 4)


class ScanFalhaDeHardwareTests(ServerTestCase):
    fault = 'adf_empty'

    def test_alimentador_vazio_devolve_erro_estruturado(self):
        status, body, _ = self.call('POST', '/scan', body={'scanner_id': 'mock_duplex_adf', 'dpi': 200,
                                                           'color_mode': 'color', 'source': 'adf'})
        self.assertEqual(status, 409)
        self.assertEqual(body['error_code'], 'ADF_EMPTY')
        self.assertIn('alimentador', body['message'].lower())


class ScanPapelEncravadoTests(ServerTestCase):
    fault = 'paper_jam'

    def test_papel_encravado_devolve_erro_estruturado(self):
        status, body, _ = self.call('POST', '/scan', body={'scanner_id': 'mock_duplex_adf', 'dpi': 200,
                                                           'color_mode': 'color', 'source': 'adf'})
        self.assertEqual(status, 409)
        self.assertEqual(body['error_code'], 'PAPER_JAM')


class ConcorrenciaTests(ServerTestCase):
    pages = 2

    def test_apenas_uma_digitalizacao_de_cada_vez(self):
        """A segunda chamada tem de ser recusada enquanto a primeira decorre."""
        service = self.server.RequestHandlerClass.service
        libertar = threading.Event()
        original = service.adapter.scan

        def lento(request, progress=None):
            libertar.wait(timeout=5)
            return original(request, progress)

        service.adapter.scan = lento
        corpo = {'scanner_id': 'mock_duplex_adf', 'dpi': 200, 'color_mode': 'color', 'source': 'adf'}
        resultados = {}

        def primeira():
            resultados['primeira'] = self.call('POST', '/scan', body=corpo)[0]

        thread = threading.Thread(target=primeira)
        thread.start()
        # Espera activa curta ate a primeira digitalizacao segurar o cadeado.
        for _ in range(200):
            if service.progress.active:
                break
            threading.Event().wait(0.01)

        status, body, _ = self.call('POST', '/scan', body=corpo)
        libertar.set()
        thread.join(timeout=10)

        self.assertEqual(status, 429)
        self.assertEqual(body['error_code'], 'SCAN_BUSY')
        self.assertEqual(resultados['primeira'], 200)


class ProgressoTests(ServerTestCase):
    def test_progresso_inactivo_quando_nao_ha_digitalizacao(self):
        status, body, _ = self.call('GET', '/progress')
        self.assertEqual(status, 200)
        self.assertFalse(body['active'])
        self.assertEqual(body['percent'], 0)

    def test_progresso_exige_pareamento(self):
        status, body, _ = self.call('GET', '/progress', token=None)
        self.assertEqual(status, 401)


class MultiplasOrigensTests(ServerTestCase):
    """Producao e desenvolvimento convivem na mesma allowlist."""

    PRODUCAO = 'http://162.35.116.198'
    DEV_LOCALHOST = 'http://localhost'
    DEV_LOOPBACK = 'http://127.0.0.1:8000'

    def setUp(self):
        super().setUp()
        self.config.allowed_origins = (self.PRODUCAO, self.DEV_LOCALHOST, self.DEV_LOOPBACK)

    def test_aceita_cada_origem_configurada(self):
        for origem in (self.PRODUCAO, self.DEV_LOCALHOST, self.DEV_LOOPBACK):
            with self.subTest(origem=origem):
                status, _, response = self.call('GET', '/status', origin=origem)
                self.assertEqual(status, 200)
                self.assertEqual(response.getheader('Access-Control-Allow-Origin'), origem)

    def test_barra_final_nao_impede_o_reconhecimento(self):
        status, _, _ = self.call('GET', '/scanners', origin=self.PRODUCAO + '/')
        self.assertEqual(status, 200)

    def test_esquema_e_porta_continuam_a_distinguir_a_origem(self):
        """Uma allowlist maior nao pode virar correspondencia por host."""
        for impostor in ('https://162.35.116.198', 'http://162.35.116.198:8080', 'http://127.0.0.1:9999'):
            with self.subTest(impostor=impostor):
                status, body, _ = self.call('GET', '/scanners', origin=impostor)
                self.assertEqual(status, 403)
                self.assertEqual(body['error_code'], 'ORIGIN_REJECTED')


if __name__ == '__main__':
    unittest.main()
