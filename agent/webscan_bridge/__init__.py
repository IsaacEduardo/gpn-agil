"""Agente local WebScan Bridge.

Ponte entre o browser e os drivers de digitalizacao do posto de trabalho.
"""

# 1.1.0 (2026-09-30): instalacao por PC, sem codigo de pareamento, porta nao
# partilhada entre sessoes, conversao BMP/TIFF -> JPEG. Os postos com 1.0.0
# precisam de reinstalar.
# 1.2.0 (2026-10-14): PDF leve - "Preto e branco" em CCITT G4 de 1 bit,
# cinzento/cores recomprimidos (jpeg_quality) e reduzidos a max_output_dpi.
__version__ = '1.2.0'
