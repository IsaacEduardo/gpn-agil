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

];
