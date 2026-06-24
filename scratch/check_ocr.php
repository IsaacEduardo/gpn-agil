<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
foreach (App\Models\DocumentoEntrada::all() as $doc) {
    echo "DOC: {$doc->id} | Anexos count: ".$doc->anexos()->count()."\n";
    foreach ($doc->anexos as $an) {
        echo "  ANEXO ID: {$an->id} | Nome: {$an->nome_original} | Texto Extraido length: ".strlen($an->texto_extraido)."\n";
        if (! empty($an->texto_extraido)) {
            echo '    Texto: '.substr($an->texto_extraido, 0, 50)."...\n";
        }
    }
}
