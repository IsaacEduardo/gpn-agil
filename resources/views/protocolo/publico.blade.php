<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Consulta de Protocolo {{ $codigo }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; }
        .cartao { max-width: 30rem; margin: 3rem auto; }
        .codigo { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; letter-spacing: 1px; }
    </style>
</head>
<body>
    <main class="container">
        <div class="card shadow-sm border-0 rounded-3 cartao">
            <div class="card-body p-4 text-center">
                <h1 class="h5 fw-bold text-dark mb-1">Consulta de Protocolo</h1>
                <p class="text-muted small mb-4">Estado do documento entregue</p>

                <div class="bg-body-tertiary rounded-3 py-3 px-2 mb-4">
                    <div class="text-uppercase text-muted" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                        Código de protocolo
                    </div>
                    <div class="codigo fw-bold fs-5 text-dark">{{ $codigo }}</div>
                </div>

                <div class="mb-4">
                    <span class="badge bg-{{ $estadoCor }}-subtle text-{{ $estadoCor }}-emphasis border border-{{ $estadoCor }}-subtle rounded-pill px-3 py-2 fs-6">
                        {{ $estadoRotulo }}
                    </span>
                </div>

                <dl class="row small text-start mb-0">
                    <dt class="col-6 text-muted fw-normal">Entregue em</dt>
                    <dd class="col-6 text-end fw-semibold">
                        {{ optional($dataEntrada)->format('d/m/Y') ?? '—' }}
                    </dd>

                    <dt class="col-6 text-muted fw-normal">Última atualização</dt>
                    <dd class="col-6 text-end fw-semibold mb-0">
                        {{ optional($atualizadoEm)->format('d/m/Y') ?? '—' }}
                    </dd>
                </dl>
            </div>

            <div class="card-footer bg-white border-top text-center py-3">
                <p class="small text-muted mb-0">
                    Para detalhes sobre o tratamento do documento, dirija-se ao balcão de atendimento
                    com este código.
                </p>
            </div>
        </div>
    </main>
</body>
</html>
