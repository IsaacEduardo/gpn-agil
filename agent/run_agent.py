"""Ponto de entrada para o executavel empacotado.

O PyInstaller executa o alvo como script de topo, sem pacote pai, o que parte os
imports relativos de `webscan_bridge/__main__.py`. Este ficheiro importa o pacote
pelo nome absoluto e delega, mantendo `python -m webscan_bridge` a funcionar
tal como estava em desenvolvimento.
"""

import sys

from webscan_bridge.__main__ import main

if __name__ == '__main__':
    sys.exit(main())
