"""Adaptador WIA (Windows Image Acquisition) atraves de COM.

WIA e a camada que o proprio Windows expoe para scanners; os fabricantes
entregam driver WIA mesmo quando o equipamento tambem fala TWAIN. Falamos com
ela por COM (pywin32), o que dispensa binarios de terceiros no posto.

Cada digitalizacao corre na thread do pedido HTTP, por isso inicializamos e
libertamos o COM a volta de cada operacao.
"""

from __future__ import annotations

import logging
import threading

from ..errors import (
    DriverUnavailable, FeederEmpty, PaperJam, ScanCancelled, ScannerNotFound,
    ScannerOffline, WebScanError,
)
from .base import Capabilities, ProgressCallback, ScannerInfo, ScanRequest

logger = logging.getLogger(__name__)

WIA_FORMAT_JPEG = '{B96B3CAE-0728-11D3-9D7B-0000F81EF32E}'
WIA_DEVICE_TYPE_SCANNER = 1

# Identificadores de propriedades WIA usados abaixo.
WIA_DPS_DOCUMENT_HANDLING_CAPABILITIES = 3086
WIA_DPS_DOCUMENT_HANDLING_STATUS = 3087
WIA_DPS_DOCUMENT_HANDLING_SELECT = 3088
WIA_IPS_XRES, WIA_IPS_YRES = 6147, 6148
WIA_IPA_DATATYPE = 4103

# Bits de WIA_DPS_DOCUMENT_HANDLING_*
HANDLING_FEEDER, HANDLING_FLATBED, HANDLING_DUPLEX = 0x001, 0x002, 0x004
STATUS_FEED_READY = 0x001

DATA_TYPE = {'bw': 0, 'gray': 2, 'color': 3}
CANDIDATE_DPIS = (100, 150, 200, 240, 300, 400, 600)

#: O pywin32 embrulha as falhas COM neste HRESULT generico.
DISP_E_EXCEPTION = 0x80020009

# Ler capacidades obriga a Connect(), e um MFP de rede desligado so' falha ao fim
# de dezenas de segundos - com varios registados, listar demorava minutos. A
# listagem passou a ser instantanea (so' metadados) e as capacidades sao apuradas
# em pano de fundo, dentro deste orcamento, ficando em cache para a proxima vez.
LIST_TIMEOUT_SECONDS = 6.0
PROBE_BUDGET_SECONDS = 2.5

#: Assumido enquanto nao conseguimos falar com o equipamento. Conservador de
#: proposito: nunca anunciamos ADF nem duplex que nao tenhamos confirmado.
FALLBACK_CAPABILITIES = Capabilities(
    sources=('flatbed',), colour_modes=('bw', 'gray', 'color'), dpis=(200, 300), duplex_supported=False
)

# HRESULTs WIA mapeados para o vocabulario de erros do agente.
WIA_ERRORS: dict[int, tuple[type[WebScanError], str]] = {
    0x80210002: (PaperJam, 'Papel encravado no alimentador. Liberte as folhas e repita.'),
    0x80210003: (FeederEmpty, 'O alimentador esta vazio. Coloque as folhas e repita.'),
    0x80210004: (PaperJam, 'Problema com o papel no alimentador.'),
    0x80210005: (ScannerOffline, 'O scanner esta desligado ou fora de linha.'),
    0x80210006: (ScannerOffline, 'O scanner esta ocupado com outra tarefa.'),
    0x80210007: (ScannerOffline, 'O scanner ainda esta a aquecer. Repita dentro de momentos.'),
    0x80210008: (ScannerOffline, 'O scanner precisa de intervencao manual (tampa, tinta ou bandeja).'),
    0x8021000A: (ScannerOffline, 'Falha de comunicacao com o scanner.'),
    0x8021000D: (ScannerOffline, 'O scanner esta bloqueado por outra aplicacao.'),
    0x8021000E: (DriverUnavailable, 'O driver do scanner gerou uma excecao.'),
    0x8021000F: (DriverUnavailable, 'O driver do scanner devolveu uma resposta invalida.'),
    0x80210015: (ScannerNotFound, 'Nao ha scanner disponivel no sistema.'),
    0x800704C7: (ScanCancelled, 'A digitalizacao foi cancelada.'),
}


def _hresult(error: Exception) -> int | None:
    """Extrai o HRESULT de uma excecao COM, normalizado para 32 bits.

    O pywin32 embrulha quase tudo em DISP_E_EXCEPTION e guarda o codigo WIA
    verdadeiro no `scode` do excepinfo. Sem desembrulhar, cada falha de hardware
    chegava ao operador como "erro inesperado do driver" em vez de "scanner
    desligado" ou "papel encravado".
    """
    nested = None
    info = getattr(error, 'excepinfo', None)
    if isinstance(info, tuple) and len(info) > 5 and isinstance(info[5], int) and info[5]:
        nested = info[5] & 0xFFFFFFFF

    code = getattr(error, 'hresult', None)
    if isinstance(code, int):
        code &= 0xFFFFFFFF
        if code == DISP_E_EXCEPTION and nested is not None:
            return nested
        return code

    if nested is not None:
        return nested

    for item in getattr(error, 'args', ()) or ():
        if isinstance(item, int) and item < 0:
            return item & 0xFFFFFFFF
    return None


def _translate(error: Exception) -> WebScanError:
    code = _hresult(error)
    if code in WIA_ERRORS:
        factory, message = WIA_ERRORS[code]
        return factory(message)
    logger.warning('Erro WIA nao mapeado: %s', hex(code) if code else error.__class__.__name__)
    return DriverUnavailable('O driver do scanner devolveu um erro inesperado.')


def _read_property(properties, property_id: int):
    """Le uma propriedade WIA pelo identificador; devolve None se nao existir."""
    try:
        for index in range(1, properties.Count + 1):
            item = properties(index)
            if int(item.PropertyID) == property_id:
                return item
    except Exception:  # noqa: BLE001 - driver pode recusar a enumeracao
        return None
    return None


def _set_property(properties, property_id: int, value) -> bool:
    item = _read_property(properties, property_id)
    if item is None:
        return False
    try:
        item.Value = value
        return True
    except Exception:  # noqa: BLE001 - propriedade apenas de leitura neste driver
        logger.debug('Driver recusou definir a propriedade %s', property_id)
        return False


def _supported_dpis(item) -> tuple[int, ...]:
    """Intersecta os DPI que oferecemos com o que o driver aceita."""
    prop = _read_property(item.Properties, WIA_IPS_XRES)
    if prop is None:
        return (200, 300)
    try:
        if getattr(prop, 'SubType', 0) == 3:  # WIA_PROP_LIST
            values = [int(prop.SubTypeValues(i)) for i in range(1, prop.SubTypeValues.Count + 1)]
            allowed = tuple(sorted(set(values) & set(CANDIDATE_DPIS)))
            return allowed or tuple(sorted(values))[:5]
        minimum = int(getattr(prop, 'SubTypeMin', 100))
        maximum = int(getattr(prop, 'SubTypeMax', 600))
        return tuple(dpi for dpi in CANDIDATE_DPIS if minimum <= dpi <= maximum) or (200,)
    except Exception:  # noqa: BLE001
        return (200, 300)


class WiaScannerAdapter:
    """Implementacao do contrato ScannerAdapter sobre WIA."""

    driver = 'WIA'

    def __init__(self):
        try:
            import pythoncom  # noqa: F401
            import win32com.client  # noqa: F401
        except ImportError as error:  # pragma: no cover - depende do posto
            raise DriverUnavailable(
                'O suporte WIA nao esta instalado (falta pywin32). Reinstale o WebScan Bridge.'
            ) from error
        # Capacidades ja confirmadas, por DeviceID. Nao mudam enquanto o
        # equipamento for o mesmo, por isso sobrevivem entre pedidos.
        self._capabilities: dict[str, Capabilities] = {}

    def _manager(self):
        import win32com.client
        try:
            return win32com.client.Dispatch('WIA.DeviceManager')
        except Exception as error:  # noqa: BLE001
            raise DriverUnavailable('O servico WIA do Windows nao respondeu.') from error

    def list_scanners(self) -> list[ScannerInfo]:
        """Lista os equipamentos sem nunca bloquear a interface.

        A enumeracao (metadados) e instantanea. O apuramento de capacidades exige
        Connect(), que num equipamento de rede desligado demora dezenas de
        segundos, por isso corre numa thread com orcamento: o que nao chegar a
        tempo fica com valores conservadores e entra em cache para a proxima.
        """
        found: list[tuple[str, str]] = []
        listed = threading.Event()
        worker = threading.Thread(target=self._enumerate, args=(found, listed), daemon=True)
        worker.start()

        if not listed.wait(LIST_TIMEOUT_SECONDS):
            logger.warning('A enumeracao WIA excedeu %.1fs; a devolver o que foi recolhido.',
                           LIST_TIMEOUT_SECONDS)

        snapshot = list(found)
        # Janela curta para apanhar capacidades dos equipamentos que respondem
        # depressa; os restantes continuam a ser apurados em pano de fundo.
        worker.join(PROBE_BUDGET_SECONDS)

        return [
            ScannerInfo(
                id=device_id,
                name=name,
                driver=self.driver,
                is_default=(position == 0),
                capabilities=self._capabilities.get(device_id, FALLBACK_CAPABILITIES),
            )
            for position, (device_id, name) in enumerate(snapshot)
        ]

    def _enumerate(self, found: list[tuple[str, str]], listed: threading.Event) -> None:
        """Corre numa thread propria: enumera primeiro, apura capacidades depois."""
        import pythoncom
        pythoncom.CoInitialize()
        try:
            infos = self._manager().DeviceInfos
            pending = []
            for index in range(1, infos.Count + 1):
                info = infos(index)
                try:
                    if int(info.Type) != WIA_DEVICE_TYPE_SCANNER:
                        continue
                except Exception:  # noqa: BLE001
                    continue
                device_id = str(info.DeviceID)
                found.append((device_id, self._name(info)))
                pending.append((device_id, info))
        except Exception as error:  # noqa: BLE001
            logger.warning('Falha a enumerar dispositivos WIA: %s', error)
            return
        finally:
            listed.set()  # liberta o pedido HTTP mesmo que o resto falhe

        try:
            for device_id, info in pending:
                if device_id not in self._capabilities:
                    probed = self._probe_capabilities(info)
                    if probed is not None:
                        self._capabilities[device_id] = probed
        finally:
            pythoncom.CoUninitialize()

    @staticmethod
    def _name(info) -> str:
        for key in ('Name', 'Description'):
            try:
                prop = info.Properties(key)
            except Exception:  # noqa: BLE001
                continue
            if prop is not None and str(prop.Value).strip():
                return str(prop.Value).strip()
        return 'Scanner'

    def _probe_capabilities(self, info) -> Capabilities | None:
        """Liga-se ao equipamento para ler o que ele suporta. None se nao responder."""
        try:
            device = info.Connect()
        except Exception as error:  # noqa: BLE001
            logger.debug('Sem capacidades de %s: %s', self._name(info), _translate(error).message)
            return None

        sources: tuple[str, ...] = ('flatbed',)
        duplex = False
        try:
            handling = _read_property(device.Properties, WIA_DPS_DOCUMENT_HANDLING_CAPABILITIES)
            if handling is not None:
                flags = int(handling.Value)
                detected = []
                if flags & HANDLING_FEEDER:
                    detected.append('adf')
                if flags & HANDLING_FLATBED:
                    detected.append('flatbed')
                sources = tuple(detected) or sources
                duplex = bool(flags & HANDLING_DUPLEX)
            dpis = _supported_dpis(device.Items(1))
        except Exception as error:  # noqa: BLE001
            logger.debug('Capacidades parciais: %s', error)
            return None

        return Capabilities(
            sources=sources, colour_modes=('bw', 'gray', 'color'), dpis=dpis, duplex_supported=duplex
        )

    def scan(self, request: ScanRequest, progress: ProgressCallback | None = None) -> list[bytes]:
        import pythoncom
        pythoncom.CoInitialize()
        try:
            return self._scan(request, progress)
        finally:
            pythoncom.CoUninitialize()

    def _connect(self, request: ScanRequest):
        manager = self._manager()
        infos = manager.DeviceInfos
        for index in range(1, infos.Count + 1):
            info = infos(index)
            if str(info.DeviceID) == request.scanner_id:
                try:
                    return info.Connect()
                except Exception as error:  # noqa: BLE001
                    raise _translate(error) from error
        raise ScannerNotFound('O scanner selecionado ja nao esta disponivel.')

    def _scan(self, request: ScanRequest, progress: ProgressCallback | None) -> list[bytes]:
        device = self._connect(request)
        use_feeder = request.source == 'adf'

        if use_feeder:
            handling = HANDLING_FEEDER | (HANDLING_DUPLEX if request.duplex else 0)
            _set_property(device.Properties, WIA_DPS_DOCUMENT_HANDLING_SELECT, handling)
            status = _read_property(device.Properties, WIA_DPS_DOCUMENT_HANDLING_STATUS)
            if status is not None and not (int(status.Value) & STATUS_FEED_READY):
                raise FeederEmpty('O alimentador esta vazio. Coloque as folhas e repita.')
        else:
            _set_property(device.Properties, WIA_DPS_DOCUMENT_HANDLING_SELECT, HANDLING_FLATBED)

        item = device.Items(1)
        _set_property(item.Properties, WIA_IPS_XRES, request.dpi)
        _set_property(item.Properties, WIA_IPS_YRES, request.dpi)
        _set_property(item.Properties, WIA_IPA_DATATYPE, DATA_TYPE.get(request.colour_mode, 3))

        pages: list[bytes] = []
        while len(pages) < request.max_pages:
            expected = len(pages) + 1
            if progress:
                total = request.max_pages if use_feeder else 1
                progress(expected, total, 'A digitalizar pagina %d...' % expected)
            try:
                image = item.Transfer(WIA_FORMAT_JPEG)
            except Exception as error:  # noqa: BLE001
                translated = _translate(error)
                # Alimentador esgotado depois de ja termos folhas e o fim normal do lote.
                if isinstance(translated, FeederEmpty) and pages:
                    break
                raise translated from error

            pages.append(bytes(image.FileData.BinaryData))
            if not use_feeder:
                break  # o vidro produz uma folha de cada vez

        if not pages:
            raise FeederEmpty('Nao foi capturada nenhuma pagina.')
        return pages
