<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Limites de operações em massa
    |--------------------------------------------------------------------------
    |
    | As exportações carregavam o resultado filtrado inteiro em memória e
    | entregavam-no ao DomPDF; as ações em lote aceitavam um array de ids sem
    | limite de cardinalidade e iteravam com queries e notificações por
    | documento. Ambos degradam sem aviso à medida que o arquivo cresce.
    |
    */

    // Nº máximo de linhas que uma exportação (PDF/Excel) aceita produzir de uma vez.
    'limite_exportacao' => (int) env('DOCS_LIMITE_EXPORTACAO', 5000),

    // Nº máximo de documentos por ação em lote (receber/encaminhar).
    'limite_lote' => (int) env('DOCS_LIMITE_LOTE', 200),

    // Sigla do departamento cujos utilizadores emitem credenciais de viatura
    // (Logística e Património). Decisão do cliente, 2026-09-29.
    'credenciais_departamento_sigla' => env('CREDENCIAIS_DEPARTAMENTO_SIGLA', 'DLP'),

    /*
    |--------------------------------------------------------------------------
    | Prazos de tratamento (SLA)
    |--------------------------------------------------------------------------
    |
    | Prazo em dias até um documento de entrada ficar em incumprimento. Vale
    | para as espécies sem prazo próprio em documento_especies.prazo_tratamento_dias.
    |
    | O alerta de atenção dispara a uma fração do prazo. Os valores por omissão
    | (5 dias, 40%) reproduzem exatamente os limiares fixos anteriores — 2 dias
    | para 'warning' e 5 para 'critical' — para que nada mude sem intenção.
    |
    */

    'prazo_tratamento_dias' => (int) env('DOCS_PRAZO_TRATAMENTO_DIAS', 5),

    'fracao_aviso_prazo' => (float) env('DOCS_FRACAO_AVISO_PRAZO', 0.4),

    /*
    |--------------------------------------------------------------------------
    | Compressão de PDF no upload (rede de segurança)
    |--------------------------------------------------------------------------
    |
    | O agente de digitalização 1.2.0 já entrega PDF leve; isto apanha os postos
    | com o agente antigo e os PDF pesados carregados à mão. Só PDF acima do
    | limite, nunca assinados nem cifrados, e só substitui o ficheiro se o
    | resultado for válido, com as mesmas páginas e pelo menos 10% menor.
    | Sem Ghostscript no servidor, o ficheiro segue como veio.
    |
    */

    'compressao_pdf' => [
        'ativa' => filter_var(env('DOCS_COMPRESSAO_PDF', true), FILTER_VALIDATE_BOOL),
        'limite_kb' => (int) env('DOCS_COMPRESSAO_PDF_LIMITE_KB', 1024),
        // Resolução das imagens a cores/cinzento depois de comprimir; 200 mantém o OCR fiável.
        'resolucao_dpi' => (int) env('DOCS_COMPRESSAO_PDF_DPI', 200),
        'timeout_segundos' => (int) env('DOCS_COMPRESSAO_PDF_TIMEOUT', 90),
    ],

];
