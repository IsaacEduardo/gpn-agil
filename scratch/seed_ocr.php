<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$anexos = App\Models\Anexo::all();
foreach ($anexos as $an) {
    $an->texto_extraido = "Texto extraído via OCR para o arquivo {$an->nome_original}:\n\nESTE É UM EXEMPLO DE TEXTO EXTRAÍDO VIA OCR.\nCONTEÚDO DO DOCUMENTO:\n- Item 1: Relatório de Atividades\n- Item 2: Faturamento Mensal\n- Item 3: Conclusão Geral.\n\nProcessado em ".date('Y-m-d H:i:s');
    $an->save();
    echo "Atualizado Anexo ID {$an->id} ({$an->nome_original})\n";
}
