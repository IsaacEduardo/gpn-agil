<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
foreach (App\Models\ModeloDocumento::all(['id', 'nome', 'campos_dinamicos']) as $model) {
    echo "MODELO: {$model->id} - {$model->nome} | Campos: {$model->campos_dinamicos}\n";
}
