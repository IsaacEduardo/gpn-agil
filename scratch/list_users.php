<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$gabinetes = App\Models\Gabinete::with('responsavel')->get();
foreach ($gabinetes as $g) {
    echo "Gabinete: {$g->nome} ({$g->sigla}) | Responsável: ".($g->responsavel ? "ID {$g->responsavel->id} - {$g->responsavel->name}" : 'None')."\n";
}
