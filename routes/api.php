<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// API REST do Chatbot RAG. Gated em runtime por config('chatbot.enabled') no controller.
// Acesso por documento é re-verificado (canViewDocument / Policy) dentro do controller/serviço.
Route::middleware(['web', 'auth', 'can:assistente.usar', 'throttle:ai-assistant'])
    ->prefix('chatbot')
    ->name('api.chatbot.')
    ->group(function () {
        Route::post('/perguntar', [\App\Http\Controllers\ChatbotController::class, 'perguntar'])->name('perguntar');
        Route::post('/documentos-entradas/{documento}/perguntar', [\App\Http\Controllers\ChatbotController::class, 'perguntarEntrada'])->name('entrada');
        Route::post('/documentos-internos/{documentoInterno}/perguntar', [\App\Http\Controllers\ChatbotController::class, 'perguntarInterno'])->name('interno');
        Route::get('/conversas', [\App\Http\Controllers\ChatbotController::class, 'conversas'])->name('conversas');
        Route::get('/conversas/{conversa}', [\App\Http\Controllers\ChatbotController::class, 'conversa'])->name('conversa');
        Route::delete('/conversas/{conversa}', [\App\Http\Controllers\ChatbotController::class, 'apagarConversa'])->name('conversa.delete');
    });

// API de Inteligência Artificial e Sumarização Contextual
Route::middleware(['web', 'auth', 'can:assistente.usar', 'throttle:ai-assistant'])
    ->prefix('ia')
    ->name('api.ia.')
    ->group(function () {
        Route::post('/resumir-documento', [\App\Http\Controllers\AssistenteController::class, 'resumirDocumento'])->name('resumir');
    });

// Rotas de Arquivamento Drag-and-Drop
Route::middleware(['web', 'auth', 'throttle:global-api'])
    ->prefix('documents/archive')
    ->name('documents.archive.')
    ->group(function () {
        Route::post('/', [\App\Http\Controllers\DocumentArchiveController::class, 'store'])->name('store');
        Route::get('/destinations', [\App\Http\Controllers\DocumentArchiveController::class, 'destinations'])->name('destinations');
    });

// Rotas da API de Catálogo de Procedências
Route::middleware(['web', 'auth', 'throttle:global-api'])
    ->prefix('procedencias')
    ->name('api.procedencias.')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\ProcedenciaController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\ProcedenciaController::class, 'store'])->name('store');
    });

// Rotas da API de Despacho e Encaminhamento de Documentos
Route::middleware(['web', 'auth', 'throttle:global-api'])
    ->prefix('documentos')
    ->name('api.documentos.')
    ->group(function () {
        Route::post('/{documentos_entrada}/despachar', [\App\Http\Controllers\DocumentoEntradaController::class, 'despachar'])->name('despachar');
        Route::post('/{documentos_entrada}/encaminhar', [\App\Http\Controllers\DocumentoEntradaController::class, 'encaminhar'])->name('encaminhar');
    });

// Rotas da API de Vínculos de Documentos N:N
Route::middleware(['web', 'auth', 'throttle:global-api'])
    ->prefix('documentos')
    ->name('api.documentos.vinculos.')
    ->group(function () {
        Route::get('/{tipo}/{id}/vinculos', [\App\Http\Controllers\DocumentoVinculoController::class, 'index'])->name('index');
        Route::post('/{tipo}/{id}/vincular', [\App\Http\Controllers\DocumentoVinculoController::class, 'store'])->name('store');
        Route::delete('/vinculos/{vinculo_id}', [\App\Http\Controllers\DocumentoVinculoController::class, 'destroy'])->name('destroy');
        Route::get('/{tipo}/{id}/pesquisar-vinculos', [\App\Http\Controllers\DocumentoVinculoController::class, 'pesquisar'])->name('pesquisar');
    });

// Rotas da API de Dashboard Operacional
Route::middleware(['web', 'auth', 'throttle:global-api'])
    ->prefix('dashboard')
    ->name('api.dashboard.')
    ->group(function () {
        Route::get('/estatisticas', [\App\Http\Controllers\DashboardApiController::class, 'estatisticas'])->name('estatisticas');
    });

// Rotas da API de Notificações
Route::middleware(['web', 'auth', 'throttle:global-api'])
    ->prefix('notificacoes')
    ->name('api.notificacoes.')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\NotificationController::class, 'index'])->name('index');
        Route::post('/ler-todas', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('ler-todas');
        Route::post('/{id}/ler', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('ler');
        Route::delete('/{id}', [\App\Http\Controllers\NotificationController::class, 'destroy'])->name('destroy');
    });

