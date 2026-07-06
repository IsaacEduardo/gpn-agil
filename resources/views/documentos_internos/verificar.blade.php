<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validador de Autenticidade - Ondaka</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: radial-gradient(circle at 10% 20%, rgb(15, 23, 42) 0%, rgb(30, 41, 59) 90.1%);
            min-height: 100vh;
            color: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Background Aurora Effects */
        .aurora-bg {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 1;
            overflow: hidden;
        }

        .aurora-circle {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.15;
            animate: float 20s infinite ease-in-out;
        }

        .aurora-1 {
            width: 400px;
            height: 400px;
            background: #0d6efd;
            top: -100px;
            left: -100px;
        }

        .aurora-2 {
            width: 500px;
            height: 500px;
            background: #198754;
            bottom: -150px;
            right: -100px;
            animation-delay: -5s;
        }

        .main-container {
            z-index: 2;
            width: 100%;
            max-width: 650px;
        }

        .glass-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .brand-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .brand-logo {
            width: 65px;
            height: auto;
            margin-bottom: 15px;
        }

        .brand-title {
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 2px;
            color: #94a3b8;
            text-transform: uppercase;
        }

        .status-badge {
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            margin-bottom: 25px;
        }

        .status-success {
            background: rgba(25, 135, 84, 0.15);
            border: 1px solid rgba(25, 135, 84, 0.3);
            color: #4ade80;
        }

        .status-danger {
            background: rgba(220, 53, 69, 0.15);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #E0556A;
        }

        .icon-circle {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            margin: 0 auto 20px auto;
            box-shadow: inset 0 0 10px rgba(255, 255, 255, 0.1);
        }

        .icon-success {
            background: radial-gradient(circle, #22c55e 0%, #15803d 100%);
            color: white;
            box-shadow: 0 0 20px rgba(34, 197, 94, 0.4);
        }

        .icon-danger {
            background: radial-gradient(circle, #D9534F 0%, #C9302C 100%);
            color: white;
            box-shadow: 0 0 20px rgba(217, 83, 79, 0.4);
        }

        .doc-details {
            background: rgba(15, 23, 42, 0.4);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 24px;
            margin-top: 25px;
            text-align: left;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #94a3b8;
            font-size: 13px;
            font-weight: 500;
        }

        .detail-value {
            color: #f8fafc;
            font-size: 13.5px;
            font-weight: 600;
            text-align: right;
            max-width: 70%;
            word-break: break-all;
        }

        .hash-code {
            font-family: 'Courier New', Courier, monospace;
            background: rgba(0, 0, 0, 0.3);
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 11px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            display: inline-block;
            word-break: break-all;
            color: #cbd5e1;
        }

        .action-button {
            border-radius: 12px;
            font-weight: 600;
            padding: 12px 24px;
            transition: all 0.2s ease;
            margin-top: 30px;
        }

        .footer-credit {
            margin-top: 25px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
            letter-spacing: 0.5px;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(10deg); }
        }
    </style>
</head>
<body>
    <div class="aurora-bg">
        <div class="aurora-circle aurora-1"></div>
        <div class="aurora-circle aurora-2"></div>
    </div>

    <div class="main-container">
        <div class="glass-card text-center">
            
            <div class="brand-header">
                @if (isset($dadosInstituicao) && $dadosInstituicao->logo_url)
                    <img src="{{ $dadosInstituicao->logo_url }}" class="brand-logo" alt="Insignia">
                @else
                    <img src="{{ asset('images/insignia.png') }}" class="brand-logo" alt="Insignia">
                @endif
                <div class="brand-title">
                    {{ $dadosInstituicao->nome_oficial ?? 'Governo Provincial do Namibe' }}
                </div>
            </div>

            @if ($valido && $documento)
                <div class="icon-circle icon-success">
                    ✓
                </div>
                
                <div>
                    <span class="status-badge status-success">
                        🛡️ Documento Autêntico
                    </span>
                    @if (($tipoAssinatura ?? 'certificado') === 'visto')
                        <h4 class="fw-bold text-white mb-1">Visto Eletrónico Válido</h4>
                        <p class="text-secondary small mb-4">A integridade deste documento foi verificada. Atenção: o documento foi validado por visto eletrónico (sem certificado digital), não constituindo assinatura digital qualificada.</p>
                    @else
                        <h4 class="fw-bold text-white mb-1">Assinatura Digital Válida</h4>
                        <p class="text-secondary small mb-4">A integridade criptográfica deste documento foi verificada e validada com sucesso.</p>
                    @endif
                </div>

                <div class="doc-details">
                    <div class="detail-row">
                        <span class="detail-label">Espécie de Documento</span>
                        <span class="detail-value text-uppercase">{{ $documento->especie->nome ?? 'N/D' }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Número de Referência</span>
                        <span class="detail-value fw-bold text-primary">{{ $documento->numero_referencia }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Assunto</span>
                        <span class="detail-value">{{ $documento->titulo }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Gabinete / Órgão</span>
                        <span class="detail-value">{{ $documento->departamento->gabinete->nome ?? ($documento->departamento->nome ?? 'Não Definido') }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Tipo de Validação</span>
                        <span class="detail-value">{{ ($tipoAssinatura ?? 'certificado') === 'visto' ? 'Visto Eletrónico (sem certificado)' : 'Assinatura Digital com Certificado' }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Assinado Por</span>
                        <span class="detail-value text-uppercase text-white">{{ $documento->assinadoPor->name ?? 'Responsável Oficial' }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Data da Assinatura</span>
                        <span class="detail-value">{{ $documento->assinado_em->format('d/m/Y H:i:s') }}</span>
                    </div>
                    <div class="detail-row flex-column align-items-stretch">
                        <span class="detail-label mb-2">Hash de Validação</span>
                        <span class="hash-code">{{ $documento->assinatura_hash }}</span>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <a href="{{ route('documentos-internos.pdf', $documento->id) }}" class="btn btn-primary action-button">
                        📥 Visualizar / Baixar PDF Oficial
                    </a>
                </div>
            @else
                <div class="icon-circle icon-danger">
                    ✕
                </div>

                <div>
                    <span class="status-badge status-danger">
                        ⚠️ Validação Falhou
                    </span>
                    <h4 class="fw-bold text-white mb-2">Autenticidade Não Confirmada</h4>
                    <p class="text-secondary small mb-4">Este documento não foi assinado digitalmente ou foi alterado de forma fraudulenta após a assinatura.</p>
                </div>

                @if(!empty($erroMsg))
                    <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger p-3 rounded-3 small">
                        <strong>Motivo do Erro:</strong> {{ $erroMsg }}
                    </div>
                @endif

                <div class="doc-details">
                    <div class="detail-row flex-column align-items-stretch">
                        <span class="detail-label mb-2">Parâmetro de Validação Recebido</span>
                        <span class="hash-code">{{ $hash }}</span>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <a href="/" class="btn btn-secondary action-button bg-secondary bg-opacity-20 border-0 text-white">
                        ← Voltar ao Início
                    </a>
                </div>
            @endif

            <div class="footer-credit">
                Ondaka &bull; Sistema de Gestão de Logística e Património
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
