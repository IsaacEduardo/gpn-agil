<!-- Insígnia/Logo da Instituição (Similar à Requisição) -->
<div class="text-center mb-4">
    <img src="{{ $dadosInstituicao->logo_url }}" alt="Insígnia"
        style="width: 22mm; height: auto;">
    <div
        style="margin-top: 10px; font-family: 'Times New Roman', serif; font-size: 12pt; font-weight: bold; text-transform: uppercase;">
        {{ $dadosInstituicao->cabecalho_linha1 }}<br>
        {{ $dadosInstituicao->cabecalho_linha2 }}<br>
        @if($dadosInstituicao->cabecalho_linha3)
            {{ $dadosInstituicao->cabecalho_linha3 }}<br>
        @endif
        {{ mb_strtoupper($documentoInterno->departamento->gabinete->nome ?? ($documentoInterno->departamento->nome ?? 'GABINETE NÃO DEFINIDO')) }}
    </div>
</div>

<div class="paper-content">
    @if ($documentoInterno->conteudo_final)
        {!! \App\Support\Sanitizer::clean($documentoInterno->conteudo_final) !!}

        @if ($documentoInterno->assinado_em)
            <div style="margin-top: 50px; page-break-inside: avoid;">
                <div
                    style="border: 2px solid #198754; padding: 15px; border-radius: 5px; background-color: #f8fff9; display: flex; align-items: center;">
                    <div style="margin-right: 20px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48"
                            fill="#198754" viewBox="0 0 16 16">
                            <path
                                d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z" />
                            <path
                                d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z" />
                        </svg>
                    </div>
                    <div>
                        <div
                            style="color: #198754; font-weight: bold; font-size: 1.1em; text-transform: uppercase;">
                            Assinado Digitalmente</div>
                        <div style="font-size: 0.9em; color: #555; margin-top: 5px;">
                            <strong>Por:</strong>
                            {{ $documentoInterno->assinadoPor->name ?? 'Usuário' }}<br>
                            <strong>Data:</strong>
                            {{ $documentoInterno->assinado_em->format('d/m/Y H:i:s') }}<br>
                            <div
                                style="margin-top: 3px; font-family: monospace; font-size: 0.8em; overflow-wrap: break-word;">
                                <strong>Hash:</strong> {{ $documentoInterno->assinatura_hash }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @else
        <div class="alert alert-warning text-center my-5 d-print-none">
            <i class="fas fa-exclamation-triangle fa-2x mb-3"></i><br>
            O conteúdo deste documento está vazio ou não pôde ser carregado.
        </div>
    @endif
</div>

<!-- Footer Estacionário (Similar às Requisições) -->
<div class="paper-footer">
    @php
        $rodapeImg = $dadosInstituicao->rodape_url;
        if (!$rodapeImg) {
            $rodapeCandidates = [
                'rodape_estacionario.png',
                'rodape_estacionario.jpg',
                'Estacionariodoc.jpg',
                'Estacionariodoc.jpeg',
                'estacionario.png',
                'estacionario.jpg',
            ];
            foreach ($rodapeCandidates as $candidate) {
                if (file_exists(public_path('images/' . $candidate))) {
                    $rodapeImg = asset('images/' . $candidate);
                    break;
                }
            }
        }
    @endphp

    @if ($rodapeImg)
        <img src="{{ $rodapeImg }}" alt="Rodapé Oficial"
            style="width: 100%; height: auto; max-height: 12mm; display: block; margin: 0 auto;">
    @elseif ($dadosInstituicao->rodape_texto)
        <div style="text-align: center; font-size: 8pt; color: #6c757d; font-family: sans-serif; border-top: 1px solid #dee2e6; padding-top: 5px;">
            {{ $dadosInstituicao->rodape_texto }}
        </div>
    @endif
</div>
