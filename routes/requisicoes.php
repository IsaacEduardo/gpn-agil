<?php

use App\Http\Controllers\RequisicaoController;
use App\Http\Controllers\RequisicaoOficinaController;
use App\Http\Controllers\RequisicaoPassagemController;
use App\Http\Controllers\RequisicaoProdutoController;
use App\Http\Controllers\RequisicaoServicoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas de Requisições
|--------------------------------------------------------------------------
|
| Aqui estão todas as rotas relacionadas aos módulos de requisições.
| Elas são carregadas automaticamente pelo RouteServiceProvider.
|
*/

Route::middleware(['auth'])->group(function () {
    
    Route::pattern('requisicao', '[0-9]+');

    // --- Rotas Gerais de Requisições ---
    Route::get('requisicoes/pendentes', [RequisicaoController::class, 'pendentes'])->name('requisicoes.pendentes');
    Route::post('requisicoes/aprovar-em-massa', [RequisicaoController::class, 'aprovarEmMassa'])->name('requisicoes.aprovar_em_massa');
    Route::resource('requisicoes', RequisicaoController::class)->parameters([
        'requisicoes' => 'requisicao',
    ]);
    
    // Ações de Aprovação/Rejeição/Visto
    Route::patch('requisicoes/{requisicao}/aprovar', [RequisicaoController::class, 'aprovar'])->name('requisicoes.aprovar');
    Route::post('requisicoes/{requisicao}/rejeitar', [RequisicaoController::class, 'rejeitar'])->name('requisicoes.rejeitar');
    Route::patch('requisicoes/{requisicao}/visto/aprovar', [RequisicaoController::class, 'vistoAprovar'])->name('requisicoes.visto.aprovar');
    Route::patch('requisicoes/{requisicao}/visto/rejeitar', [RequisicaoController::class, 'vistoRejeitar'])->name('requisicoes.visto.rejeitar');


    // --- Requisições de Produtos ---
    Route::prefix('requisicoes/produtos')->name('requisicoes.produtos.')->group(function () {
        Route::get('/', [RequisicaoProdutoController::class, 'index'])->name('index');
        Route::get('/novo', [RequisicaoProdutoController::class, 'create'])->name('create.novo'); // Mantendo alias antigo
        Route::post('/novo', [RequisicaoProdutoController::class, 'store'])->name('store.novo');  // Mantendo alias antigo
        
        Route::get('/{requisicao}/pdf', [RequisicaoProdutoController::class, 'pdf'])->name('pdf');
        Route::get('/{requisicao}/print', [RequisicaoProdutoController::class, 'print'])->name('print');
        Route::get('/{requisicao}/edit', [RequisicaoProdutoController::class, 'edit'])->name('edit');
        Route::put('/{requisicao}', [RequisicaoProdutoController::class, 'update'])->name('update');
    });

    // --- Requisições de Oficinas ---
    Route::prefix('requisicoes/oficinas')->name('requisicoes.oficina.')->group(function () {
        Route::get('/', [RequisicaoOficinaController::class, 'index'])->name('index');
        Route::get('/novo', [RequisicaoOficinaController::class, 'create'])->name('create.novo');
        Route::post('/novo', [RequisicaoOficinaController::class, 'store'])->name('store.novo');
        
        // Rotas com ID opcional (legado/auxiliar) e com ID obrigatório
        Route::get('/{requisicao?}/pdf', [RequisicaoOficinaController::class, 'pdf'])->name('pdf');
        Route::get('/{requisicao?}/print', [RequisicaoOficinaController::class, 'print'])->name('print');
        
        Route::get('/{requisicao}/edit', [RequisicaoOficinaController::class, 'edit'])->name('edit');
        Route::put('/{requisicao}', [RequisicaoOficinaController::class, 'update'])->name('update');
        
        // Alias específicos para evitar conflitos de parâmetros opcionais
        Route::get('/pdf', [RequisicaoOficinaController::class, 'pdf'])->name('pdf.noid');
        Route::get('/print', [RequisicaoOficinaController::class, 'print'])->name('print.noid');
    });

    // --- Requisições de Serviços ---
    Route::prefix('requisicoes/servicos')->name('requisicoes.servico.')->group(function () {
        Route::get('/', [RequisicaoServicoController::class, 'index'])->name('index');
        Route::get('/novo', [RequisicaoServicoController::class, 'create'])->name('create.novo');
        Route::post('/novo', [RequisicaoServicoController::class, 'store'])->name('store.novo');

        Route::get('/{requisicao?}/pdf', [RequisicaoServicoController::class, 'pdf'])->name('pdf');
        Route::get('/{requisicao?}/print', [RequisicaoServicoController::class, 'print'])->name('print');
        
        Route::get('/{requisicao}/edit', [RequisicaoServicoController::class, 'edit'])->name('edit');
        Route::put('/{requisicao}', [RequisicaoServicoController::class, 'update'])->name('update');

        Route::get('/pdf', [RequisicaoServicoController::class, 'pdf'])->name('pdf.noid');
        Route::get('/print', [RequisicaoServicoController::class, 'print'])->name('print.noid');
    });

    // --- Requisições de Passagens ---
    Route::prefix('requisicoes/passagens')->name('requisicoes.passagem.')->group(function () {
        Route::get('/', [RequisicaoPassagemController::class, 'index'])->name('index');
        Route::get('/novo', [RequisicaoPassagemController::class, 'create'])->name('create.novo');
        Route::post('/novo', [RequisicaoPassagemController::class, 'store'])->name('store.novo');

        Route::get('/{requisicao?}/pdf', [RequisicaoPassagemController::class, 'pdf'])->name('pdf');
        Route::get('/{requisicao?}/print', [RequisicaoPassagemController::class, 'print'])->name('print');
        
        Route::get('/{requisicao}/edit', [RequisicaoPassagemController::class, 'edit'])->name('edit');
        Route::put('/{requisicao}', [RequisicaoPassagemController::class, 'update'])->name('update');

        Route::get('/pdf', [RequisicaoPassagemController::class, 'pdf'])->name('pdf.noid');
        Route::get('/print', [RequisicaoPassagemController::class, 'print'])->name('print.noid');
    });

});
