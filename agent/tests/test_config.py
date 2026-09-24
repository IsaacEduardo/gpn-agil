"""Testes do carregamento da configuracao do posto.

Dois defeitos reais, encontrados a instalar um posto: o instalador escrevia o
JSON com BOM (o `Set-Content -Encoding utf8` do Windows PowerShell 5.1 poe-no
sempre) e o agente lia em utf-8 estrito, pelo que rejeitava a configuracao que o
proprio instalador acabara de escrever. Pior: ao nao conseguir ler, gravava por
cima com os valores por omissao — as origens autorizadas desapareciam e o agente
passava a recusar todos os pedidos do browser, sem que ninguem percebesse porque.
"""

import json
import tempfile
import unittest
from pathlib import Path

from webscan_bridge.config import load_config


class ConfigComBomTests(unittest.TestCase):
    def setUp(self):
        self._dir = tempfile.TemporaryDirectory()
        self.caminho = Path(self._dir.name) / 'webscan-agent.json'

    def tearDown(self):
        self._dir.cleanup()

    def escrever(self, texto: str, encoding: str) -> None:
        self.caminho.write_text(texto, encoding=encoding)

    def payload(self) -> str:
        return json.dumps({
            'port': 18090,
            'allowed_origins': ['http://localhost:8000'],
            'pairing_token': 'token-do-posto',
            'driver': 'mock',
        })

    def test_le_configuracao_escrita_com_bom(self):
        self.escrever(self.payload(), 'utf-8-sig')

        config = load_config(self.caminho)

        self.assertEqual(('http://localhost:8000',), config.allowed_origins)
        self.assertEqual('token-do-posto', config.pairing_token)

    def test_le_configuracao_escrita_sem_bom(self):
        self.escrever(self.payload(), 'utf-8')

        config = load_config(self.caminho)

        self.assertEqual(('http://localhost:8000',), config.allowed_origins)

    def test_configuracao_ilegivel_nao_e_substituida(self):
        """Um erro de leitura tem de ser reparavel, nao destrutivo."""
        original = '{ isto nao e JSON valido'
        self.escrever(original, 'utf-8')

        with self.assertLogs('webscan_bridge.config', level='ERROR'):
            config = load_config(self.caminho)

        # O agente arranca com valores por omissao...
        self.assertEqual((), config.allowed_origins)
        # ...mas o ficheiro do posto fica intacto, para poder ser corrigido.
        self.assertEqual(original, self.caminho.read_text(encoding='utf-8'))

    def test_primeira_execucao_cria_ficheiro_com_token(self):
        self.assertFalse(self.caminho.exists())

        config = load_config(self.caminho)

        self.assertTrue(self.caminho.is_file())
        self.assertTrue(config.pairing_token)
        gravado = json.loads(self.caminho.read_text(encoding='utf-8-sig'))
        self.assertEqual(config.pairing_token, gravado['pairing_token'])


if __name__ == '__main__':
    unittest.main()
