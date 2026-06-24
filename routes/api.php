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
// NOTA: esta app é baseada em sessão (sem Sanctum instalado); usamos o guard 'web' (sessão).
// Para uma API por token, instalar laravel/sanctum e trocar para ['auth:sanctum', ...].
Route::middleware(['web', 'auth', 'can:assistente.usar', 'throttle:60,1'])
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

// Rotas de Arquivamento Drag-and-Drop.
// App baseada em sessão (sem Sanctum); usamos guard 'web' (sessão), como o chatbot.
Route::middleware(['web', 'auth', 'throttle:30,1'])
    ->prefix('documents/archive')
    ->name('documents.archive.')
    ->group(function () {
        Route::post('/', [\App\Http\Controllers\DocumentArchiveController::class, 'store'])->name('store');
        Route::get('/destinations', [\App\Http\Controllers\DocumentArchiveController::class, 'destinations'])->name('destinations');
    });

