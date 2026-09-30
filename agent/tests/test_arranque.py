"""Arranque do agente num PC com varias contas do Windows.

So um agente pode ter a porta. Os das outras sessoes nao podem desistir (ficaria
sem scanner quem entrasse depois de a primeira sessao terminar): esperam e
assumem quando a porta fica livre.
"""

import errno
import unittest

from webscan_bridge.__main__ import escutar_quando_livre, porta_ocupada


def _ocupada() -> OSError:
    return OSError(errno.EADDRINUSE, 'Address already in use')


class EsperaPelaPortaTests(unittest.TestCase):
    def test_assume_a_porta_quando_fica_livre(self):
        tentativas = iter([_ocupada(), _ocupada(), 'servidor'])
        esperas = []

        def criar():
            resultado = next(tentativas)
            if isinstance(resultado, OSError):
                raise resultado
            return resultado

        with self.assertLogs(level='WARNING') as registos:
            servidor = escutar_quando_livre(criar, espera=10, dormir=esperas.append, porta=18090)

        self.assertEqual('servidor', servidor)
        self.assertEqual([10, 10], esperas)
        self.assertEqual(1, len(registos.records), 'avisa uma vez, nao a cada tentativa')

    def test_outros_erros_nao_ficam_em_espera(self):
        def criar():
            raise PermissionError(errno.EACCES, 'acesso negado')

        with self.assertRaises(PermissionError):
            escutar_quando_livre(criar, dormir=lambda _: self.fail('nao devia esperar'))

    def test_reconhece_o_codigo_do_windows(self):
        self.assertTrue(porta_ocupada(OSError(10048, 'Endereco em uso')))
        self.assertFalse(porta_ocupada(OSError(errno.EACCES, 'acesso negado')))


if __name__ == '__main__':
    unittest.main()
