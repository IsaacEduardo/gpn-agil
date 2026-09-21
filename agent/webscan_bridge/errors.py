"""Erros estruturados partilhados pelo agente.

O frontend distingue as situacoes pelo `error_code`, nunca pela mensagem: a
mensagem e' texto para o operador e pode ser traduzida ou reescrita.
"""


class WebScanError(Exception):
    """Falha que o agente sabe explicar ao operador."""

    #: Codigo estavel consumido pelo browser.
    code = 'AGENT_ERROR'
    #: Estado HTTP devolvido quando a falha sobe ate' a camada HTTP.
    http_status = 500

    def __init__(self, message: str, code: str | None = None, http_status: int | None = None):
        super().__init__(message)
        self.message = message
        if code is not None:
            self.code = code
        if http_status is not None:
            self.http_status = http_status

    def to_payload(self) -> dict:
        return {'status': 'error', 'error_code': self.code, 'message': self.message}


class DriverUnavailable(WebScanError):
    code = 'DRIVER_UNAVAILABLE'
    http_status = 503


class ScannerNotFound(WebScanError):
    code = 'SCANNER_NOT_FOUND'
    http_status = 404


class ScannerOffline(WebScanError):
    code = 'SCANNER_OFFLINE'
    http_status = 503


class FeederEmpty(WebScanError):
    code = 'ADF_EMPTY'
    http_status = 409


class PaperJam(WebScanError):
    code = 'PAPER_JAM'
    http_status = 409


class ScanCancelled(WebScanError):
    code = 'SCAN_CANCELLED'
    http_status = 409


class ScanBusy(WebScanError):
    code = 'SCAN_BUSY'
    http_status = 429


class ScanTimeout(WebScanError):
    code = 'SCAN_TIMEOUT'
    http_status = 504


class InvalidRequest(WebScanError):
    code = 'INVALID_REQUEST'
    http_status = 400


class NotAuthorised(WebScanError):
    code = 'PAIRING_REQUIRED'
    http_status = 401


class OriginRejected(WebScanError):
    code = 'ORIGIN_REJECTED'
    http_status = 403
