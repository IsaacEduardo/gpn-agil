<?php

use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Controllers\DocumentoEntradaController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\GabineteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\RequisicaoController;
use App\Http\Controllers\RequisicaoOficinaController;
use App\Http\Controllers\RequisicaoPassagemController;
use App\Http\Controllers\RequisicaoProdutoController;
use App\Http\Controllers\RequisicaoServicoController;
use App\Http\Controllers\ViaturaController;
use App\Http\Controllers\ViaturaReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', [HomeController::class, 'index'])->name('home');
Route::get('/global-search', [App\Http\Controllers\SearchController::class, 'index'])->name('global.search');

// Rotas para o módulo de Feedbacks
Route::resource('feedbacks', FeedbackController::class);

// Rotas para o módulo de Viaturas
Route::resource('viaturas', ViaturaController::class);
Route::get('viaturas-export/pdf', [ViaturaReportController::class, 'exportPDF'])->name('viaturas.export.pdf');
Route::get('viaturas-export/excel', [ViaturaReportController::class, 'exportExcel'])->name('viaturas.export.excel');

// Rotas para o módulo de Empresas
Route::resource('empresas', EmpresaController::class);

// Rotas para o módulo de Departamentos
Route::resource('departamentos', DepartamentoController::class);
Route::resource('gabinetes', GabineteController::class);
Route::middleware(['auth'])->group(function () {
    Route::resource('documentos-entradas', DocumentoEntradaController::class)->names('documentos-entradas');
    Route::get('documentos-entradas/{documento}/protocolo', [DocumentoEntradaController::class, 'protocolo'])
        ->name('documentos-entradas.protocolo');
    Route::get('documentos-entradas/{documento}/protocolo/pdf', [DocumentoEntradaController::class, 'protocoloPdf'])
        ->name('documentos-entradas.protocolo.pdf');
    Route::patch('documentos-entradas/{documento}/protocolo/impresso', [DocumentoEntradaController::class, 'marcarProtocoloImpresso'])
        ->name('documentos-entradas.protocolo.impresso');
    Route::post('documentos-entradas/{documento}/encaminhar', [DocumentoEntradaController::class, 'encaminhar'])
        ->name('documentos-entradas.encaminhar');
    Route::post('documentos-entradas/{documento}/saida-gabinete', [DocumentoEntradaController::class, 'saidaGabinete'])
        ->name('documentos-entradas.saida-gabinete');
    Route::patch('documentos-entradas/{documento}/encaminhamentos/{encaminhamento}/receber', [DocumentoEntradaController::class, 'receberEncaminhamento'])
        ->name('documentos-entradas.encaminhamentos.receber');
    Route::delete('documentos-entradas/{documento}/anexos/{anexo}', [DocumentoEntradaController::class, 'destroyAnexo'])
        ->name('documentos-entradas.anexos.destroy');
    Route::get('documentos-entradas/{documento}/arquivo/download', [DocumentoEntradaController::class, 'downloadArquivo'])
        ->name('documentos-entradas.arquivo.download');
    Route::get('documentos-entradas/{documento}/anexos/{anexo}/download', [DocumentoEntradaController::class, 'downloadAnexo'])
        ->name('documentos-entradas.anexos.download');
    Route::patch('documentos-entradas/{documento}/visto/aprovar', [DocumentoEntradaController::class, 'vistoAprovar'])
        ->name('documentos-entradas.visto.aprovar');
    Route::patch('documentos-entradas/{documento}/visto/rejeitar', [DocumentoEntradaController::class, 'vistoRejeitar'])
        ->name('documentos-entradas.visto.rejeitar');
    Route::patch('documentos-entradas/{documento}/visto-gabinete/aprovar', [DocumentoEntradaController::class, 'vistoGabineteAprovar'])
        ->name('documentos-entradas.visto-gabinete.aprovar');
    Route::patch('documentos-entradas/{documento}/visto-gabinete/rejeitar', [DocumentoEntradaController::class, 'vistoGabineteRejeitar'])
        ->name('documentos-entradas.visto-gabinete.rejeitar');
    Route::post('documentos-entradas/{documento}/tarefas', [DocumentoEntradaController::class, 'tarefasStore'])
        ->name('documentos-entradas.tarefas.store');
    Route::patch('documentos-entradas/{documento}/tarefas/{tarefa}/concluir', [DocumentoEntradaController::class, 'tarefasConcluir'])
        ->name('documentos-entradas.tarefas.concluir');
    Route::patch('documentos-entradas/{documento}/tarefas/{tarefa}/cancelar', [DocumentoEntradaController::class, 'tarefasCancelar'])
        ->name('documentos-entradas.tarefas.cancelar');
    Route::get('documentos-entradas-export/pdf', [DocumentoEntradaController::class, 'exportPDF'])
        ->name('documentos-entradas.export.pdf');
    Route::get('documentos-entradas-export/excel', [DocumentoEntradaController::class, 'exportExcel'])
        ->name('documentos-entradas.export.excel');

    // Rotas para Arquivo e Pastas
    Route::get('pastas/search', [\App\Http\Controllers\PastaController::class, 'search'])->name('pastas.search');
    Route::post('documentos-entradas/{documento}/arquivar', [\App\Http\Controllers\PastaController::class, 'arquivar'])->name('documentos-entradas.arquivar');
    Route::post('documentos-entradas/{documento}/desarquivar', [\App\Http\Controllers\PastaController::class, 'desarquivar'])->name('documentos-entradas.desarquivar');
    Route::resource('pastas', \App\Http\Controllers\PastaController::class);
});

// Rotas para termos de entrega
Route::get('termos', [App\Http\Controllers\TermoEntregaController::class, 'index'])->name('termos.index');
Route::get('termos/create', [App\Http\Controllers\TermoEntregaController::class, 'create'])->name('termos.create');
Route::post('termos', [App\Http\Controllers\TermoEntregaController::class, 'store'])->name('termos.store');
Route::get('termos/{requisicao}/{tipo}', [App\Http\Controllers\TermoEntregaController::class, 'gerar'])->name('termos.gerar');
Route::get('termos/{termo}', [App\Http\Controllers\TermoEntregaController::class, 'show'])->name('termos.show');
Route::get('termos/{termo}/pdf', [App\Http\Controllers\TermoEntregaController::class, 'pdf'])->name('termos.pdf');
Route::delete('termos/{termo}', [App\Http\Controllers\TermoEntregaController::class, 'destroy'])->name('termos.destroy');

// Rotas para credenciais (módulo separado)
Route::resource('credenciais', App\Http\Controllers\CredencialController::class)->except(['edit', 'update']);

// Rotas para reservas de espaços
Route::get('reservas', [App\Http\Controllers\ReservaEspacoController::class, 'index'])->name('reservas.index');
Route::get('reservas/create', [App\Http\Controllers\ReservaEspacoController::class, 'create'])->name('reservas.create');
Route::post('reservas', [App\Http\Controllers\ReservaEspacoController::class, 'store'])->name('reservas.store');
Route::get('reservas/{reserva}', [App\Http\Controllers\ReservaEspacoController::class, 'show'])->name('reservas.show');
Route::get('reservas/{reserva}/edit', [App\Http\Controllers\ReservaEspacoController::class, 'edit'])->name('reservas.edit');
Route::put('reservas/{reserva}', [App\Http\Controllers\ReservaEspacoController::class, 'update'])->name('reservas.update');
Route::delete('reservas/{reserva}', [App\Http\Controllers\ReservaEspacoController::class, 'destroy'])->name('reservas.destroy');

// Rotas específicas para reservas
Route::get('reservas-calendar', [App\Http\Controllers\ReservaEspacoController::class, 'calendar'])->name('reservas.calendar');
Route::get('reservas-calendario', [App\Http\Controllers\ReservaEspacoController::class, 'calendar'])->name('reservas.calendario');
Route::post('reservas/verificar-disponibilidade', [App\Http\Controllers\ReservaEspacoController::class, 'verificarDisponibilidade'])->name('reservas.verificar-disponibilidade');
Route::patch('reservas/{reserva}/aprovar', [App\Http\Controllers\ReservaEspacoController::class, 'aprovar'])->name('reservas.aprovar');
Route::patch('reservas/{reserva}/rejeitar', [App\Http\Controllers\ReservaEspacoController::class, 'rejeitar'])->name('reservas.rejeitar');
Route::patch('reservas/{reserva}/cancelar', [App\Http\Controllers\ReservaEspacoController::class, 'cancelar'])->name('reservas.cancelar');
// Rotas para visto do departamento em reservas
Route::patch('reservas/{reserva}/visto/aprovar', [App\Http\Controllers\ReservaEspacoController::class, 'vistoAprovar'])->name('reservas.visto.aprovar');
Route::patch('reservas/{reserva}/visto/rejeitar', [App\Http\Controllers\ReservaEspacoController::class, 'vistoRejeitar'])->name('reservas.visto.rejeitar');

// Rotas de Administração de Usuários (apenas admin)
Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::resource('users', UserAdminController::class)->names('admin.users');
});

// Rotas para gerenciamento de perfil
Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
Route::patch('/profile', [App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
Route::put('/profile/password', [App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('profile.password.update');

// Rotas de Notificações e Web Push
Route::middleware(['auth'])->group(function () {
    // Página dedicada de notificações
    Route::get('/notifications/all', [NotificationController::class, 'page'])->name('notifications.page');

    // API JSON do dropdown
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read_all');
    Route::post('/notifications/read/{id}', [NotificationController::class, 'markAsRead'])->name('notifications.read');

    // Web Push
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
});
