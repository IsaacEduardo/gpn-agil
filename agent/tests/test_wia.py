"""Testes da tradução de erros WIA e da listagem que não bloqueia.

Não tocam em COM: exercitam a lógica pura que decide o que o operador vê quando
o hardware falha. Os valores vêm de uma captura real numa máquina com MFPs de
rede registados e desligados.
"""

import threading
import time
import unittest

from webscan_bridge.errors import (
    DriverUnavailable, FeederEmpty, PaperJam, ScanCancelled, ScannerNotFound, ScannerOffline,
)
from webscan_bridge.scanners.base import Capabilities
from webscan_bridge.scanners.wia import (
    DISP_E_EXCEPTION, FALLBACK_CAPABILITIES, LIST_TIMEOUT_SECONDS, WiaScannerAdapter, _hresult, _translate,
)


class ComError(Exception):
    """Imita o `com_error` do pywin32, incluindo o excepinfo de 6 posições."""

    def __init__(self, hresult, scode=None, description='falha'):
        self.hresult = hresult - 0x100000000 if hresult > 0x7FFFFFFF else hresult
        self.excepinfo = (0, None, description, None, 0, scode) if scode is not None else None
        super().__init__(self.hresult)


class HresultTests(unittest.TestCase):
    def test_desembrulha_o_codigo_wia_de_dentro_do_disp_e_exception(self):
        """O pywin32 embrulha tudo em DISP_E_EXCEPTION; o código real fica no scode."""
        error = ComError(DISP_E_EXCEPTION, scode=0x8021000A)
        self.assertEqual(_hresult(error), 0x8021000A)

    def test_usa_o_hresult_directo_quando_nao_e_embrulho(self):
        self.assertEqual(_hresult(ComError(0x80210003)), 0x80210003)

    def test_sem_informacao_nenhuma_devolve_none(self):
        self.assertIsNone(_hresult(RuntimeError('sem hresult')))


class TranslateTests(unittest.TestCase):
    def test_equipamento_offline_chega_ao_operador_como_offline(self):
        """Era o caso real: MFP de rede desligado dava "erro inesperado do driver"."""
        traduzido = _translate(ComError(DISP_E_EXCEPTION, scode=0x8021000A))
        self.assertIsInstance(traduzido, ScannerOffline)
        self.assertEqual(traduzido.code, 'SCANNER_OFFLINE')

    def test_mapeia_as_falhas_de_papel(self):
        casos = {
            0x80210003: (FeederEmpty, 'ADF_EMPTY'),
            0x80210002: (PaperJam, 'PAPER_JAM'),
            0x800704C7: (ScanCancelled, 'SCAN_CANCELLED'),
            0x80210015: (ScannerNotFound, 'SCANNER_NOT_FOUND'),
        }
        for scode, (classe, codigo) in casos.items():
            with self.subTest(scode=hex(scode)):
                traduzido = _translate(ComError(DISP_E_EXCEPTION, scode=scode))
                self.assertIsInstance(traduzido, classe)
                self.assertEqual(traduzido.code, codigo)

    def test_codigo_desconhecido_nao_rebenta(self):
        traduzido = _translate(ComError(DISP_E_EXCEPTION, scode=0x80044444))
        self.assertIsInstance(traduzido, DriverUnavailable)


class ListagemNaoBloqueanteTests(unittest.TestCase):
    """A listagem tem de devolver mesmo com equipamentos que nunca respondem."""

    def _adaptador(self) -> WiaScannerAdapter:
        adapter = WiaScannerAdapter.__new__(WiaScannerAdapter)  # sem exigir pywin32
        adapter._capabilities = {}
        return adapter

    def test_devolve_dentro_do_prazo_quando_a_enumeracao_pendura(self):
        adapter = self._adaptador()

        def enumerar_pendurado(found, listed):
            found.append(('id-lento', 'Scanner que nao responde'))
            listed.set()
            time.sleep(30)  # imita o Connect() de um MFP de rede desligado

        adapter._enumerate = enumerar_pendurado

        inicio = time.monotonic()
        devices = adapter.list_scanners()
        decorrido = time.monotonic() - inicio

        self.assertLess(decorrido, LIST_TIMEOUT_SECONDS + 3,
                        'A listagem nao pode esperar pelo equipamento que nao responde.')
        self.assertEqual(len(devices), 1)
        self.assertEqual(devices[0].name, 'Scanner que nao responde')

    def test_sem_capacidades_confirmadas_nao_anuncia_adf_nem_duplex(self):
        adapter = self._adaptador()
        adapter._enumerate = lambda found, listed: (found.append(('id', 'Scanner')), listed.set())

        capacidades = adapter.list_scanners()[0].capabilities

        self.assertEqual(capacidades, FALLBACK_CAPABILITIES)
        self.assertNotIn('adf', capacidades.sources)
        self.assertFalse(capacidades.duplex_supported)

    def test_usa_as_capacidades_ja_apuradas_quando_existem(self):
        adapter = self._adaptador()
        reais = Capabilities(sources=('adf', 'flatbed'), colour_modes=('color',),
                             dpis=(300,), duplex_supported=True)
        adapter._capabilities['id'] = reais
        adapter._enumerate = lambda found, listed: (found.append(('id', 'Scanner')), listed.set())

        self.assertEqual(adapter.list_scanners()[0].capabilities, reais)

    def test_o_primeiro_equipamento_fica_marcado_como_predefinido(self):
        adapter = self._adaptador()
        adapter._enumerate = lambda found, listed: (
            found.extend([('a', 'Primeiro'), ('b', 'Segundo')]), listed.set()
        )

        devices = adapter.list_scanners()
        self.assertTrue(devices[0].is_default)
        self.assertFalse(devices[1].is_default)


if __name__ == '__main__':
    unittest.main()
