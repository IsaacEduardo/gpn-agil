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

];
