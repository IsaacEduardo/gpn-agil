<?php

use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\AssistenteController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Controllers\DocumentoEntradaController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\GabineteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ViaturaController;
use App\Http\Controllers\ViaturaReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

// Rota pública de verificação de autenticidade de documentos (acesso externo via hash)
// throttle impede enumeração/brute-force de hashes
Route::get('/verificar/documento/{hash}', [App\Http\Controllers\DocumentoInternoController::class, 'verificarPublico'])
    ->middleware('throttle:30,1')
    ->name('documentos-internos.verificar');

// A partir daqui, todas as rotas exigem autenticação
Route::middleware(['auth'])->group(function () {
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
});
Route::middleware(['auth'])->group(function () {
    Route::post('documentos-entradas/batch/receber', [DocumentoEntradaController::class, 'batchReceber'])
        ->name('documentos-entradas.batch.receber');
    Route::post('documentos-entradas/batch/encaminhar', [DocumentoEntradaController::class, 'batchEncaminhar'])
        ->name('documentos-entradas.batch.encaminhar');
    Route::get('documentos-entradas/search/json', [DocumentoEntradaController::class, 'searchJson'])
        ->name('documentos-entradas.search.json');
    Route::resource('documentos-entradas', DocumentoEntradaController::class)->names('documentos-entradas');
    Route::get('documentos-entradas/{documento}/preview-ajax', [DocumentoEntradaController::class, 'previewAjax'])
        ->name('documentos-entradas.preview-ajax');
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
    Route::delete('documentos-entradas/{documento}/encaminhamentos/{encaminhamento}', [DocumentoEntradaController::class, 'cancelarEncaminhamento'])
        ->name('documentos-entradas.encaminhamentos.cancelar');
    Route::delete('documentos-entradas/{documento}/anexos/{anexo}', [DocumentoEntradaController::class, 'destroyAnexo'])
        ->name('documentos-entradas.anexos.destroy');
    Route::get('documentos-entradas/{documento}/arquivo/download', [DocumentoEntradaController::class, 'downloadArquivo'])
        ->name('documentos-entradas.arquivo.download');
    Route::get('documentos-entradas/{documento}/anexos/{anexo}/download', [DocumentoEntradaController::class, 'downloadAnexo'])
        ->name('documentos-entradas.anexos.download');
    Route::get('documentos-entradas/{documento}/anexos/{anexo}/ocr', [DocumentoEntradaController::class, 'getOcrText'])
        ->name('documentos-entradas.anexos.ocr');
    Route::patch('documentos-entradas/{documento}/visto/aprovar', [DocumentoEntradaController::class, 'vistoAprovar'])
        ->name('documentos-entradas.visto.aprovar');
    Route::patch('documentos-entradas/{documento}/visto/rejeitar', [DocumentoEntradaController::class, 'vistoRejeitar'])
        ->name('documentos-entradas.visto.rejeitar');
    Route::patch('documentos-entradas/{documento}/visto-gabinete/aprovar', [DocumentoEntradaController::class, 'vistoGabineteAprovar'])
        ->name('documentos-entradas.visto-gabinete.aprovar');
    Route::patch('documentos-entradas/{documento}/visto-gabinete/rejeitar', [DocumentoEntradaController::class, 'vistoGabineteRejeitar'])
        ->name('documentos-entradas.visto-gabinete.rejeitar');
    Route::post('documentos-entradas/{documento}/relacionar', [DocumentoEntradaController::class, 'relacionar'])->name('documentos-entradas.relacionar');
    Route::delete('documentos-entradas/{documento}/relacionar/{relacionado}', [DocumentoEntradaController::class, 'desrelacionar'])->name('documentos-entradas.desrelacionar');
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

    // Minhas Tarefas
    Route::get('/tarefas', [\App\Http\Controllers\TarefaController::class, 'index'])->name('tarefas.index');

    // EDMS (Novo Sistema de Arquivos)
    Route::get('/edms/{folder?}', [App\Http\Controllers\EdmsController::class, 'index'])->name('edms.index');
    Route::post('/edms/folder/create', [App\Http\Controllers\EdmsController::class, 'createFolder'])->name('edms.create-folder');
    Route::post('/edms/file/upload', [App\Http\Controllers\EdmsController::class, 'uploadFile'])->name('edms.upload-file');
    Route::get('/edms/version/{version}/stream', [App\Http\Controllers\EdmsController::class, 'streamVersion'])->name('edms.stream-version');
    Route::get('/edms/attachment/{anexo}/stream', [App\Http\Controllers\EdmsController::class, 'streamAttachment'])->name('edms.stream-attachment');
    Route::post('/edms/folder/{folder}/share', [App\Http\Controllers\EdmsController::class, 'shareFolder'])->name('edms.share-folder');
    Route::get('/edms/folder/{folder}/history', [App\Http\Controllers\EdmsController::class, 'folderHistory'])->name('edms.folder-history');
    Route::get('/edms/retention/config', [App\Http\Controllers\EdmsController::class, 'retentionConfig'])->name('edms.retention');
    Route::post('/edms/retention/store', [App\Http\Controllers\EdmsController::class, 'retentionStore'])->name('edms.retention-store');

    // Rotas para Arquivo e Pastas
    Route::get('pastas/search', [\App\Http\Controllers\PastaController::class, 'search'])->name('pastas.search');
    // Rota unificada para arquivamento (Entrada e Interno)
    Route::post('pastas/{documento}/arquivar', [\App\Http\Controllers\PastaController::class, 'arquivar'])->name('pastas.arquivar');
    Route::post('documentos-entradas/{documento}/desarquivar', [\App\Http\Controllers\PastaController::class, 'desarquivar'])->name('documentos-entradas.desarquivar');

    // Redirecionamento de compatibilidade
    Route::get('/pastas', function () {
        return redirect()->route('edms.index');
    })->name('pastas.index');

    // Mantemos resource apenas para create/store/update/destroy via API/Modal se necessário,
    // mas a index é redirecionada. O 'except' garante que 'index' não sobrescreva o redirect acima se definido depois,
    // mas como definimos antes ou manualmente, melhor usar 'except' index.
    Route::resource('pastas', \App\Http\Controllers\PastaController::class)->except(['index']);
});

// Rotas para termos de entrega, credenciais e reservas (exigem autenticação)
Route::middleware(['auth'])->group(function () {
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
});

// Rotas de Administração de Usuários (apenas admin)
Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::resource('users', UserAdminController::class)->names('admin.users');

    // Configurações da Instituição
    Route::get('instituicao', [App\Http\Controllers\InstituicaoController::class, 'edit'])->name('admin.instituicao.edit');
    Route::put('instituicao', [App\Http\Controllers\InstituicaoController::class, 'update'])->name('admin.instituicao.update');

    // Gestão de Permissões (RBAC)
    Route::prefix('permissoes')->name('configuracoes.permissoes.')->group(function () {
        Route::get('/', [App\Http\Controllers\RolePermissionController::class, 'index'])->name('index');
        Route::post('/update', [App\Http\Controllers\RolePermissionController::class, 'update'])->name('update');
        Route::post('/toggle', [App\Http\Controllers\RolePermissionController::class, 'toggle'])->name('toggle');
        Route::post('/role', [App\Http\Controllers\RolePermissionController::class, 'storeRole'])->name('role.store');
        Route::post('/permission', [App\Http\Controllers\RolePermissionController::class, 'storePermission'])->name('permission.store');
    });
});

// Rotas para gerenciamento de perfil
Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::post('/profile/certificate', [App\Http\Controllers\ProfileController::class, 'uploadCertificate'])->name('profile.certificate.upload');
    Route::delete('/profile/certificate', [App\Http\Controllers\ProfileController::class, 'destroyCertificate'])->name('profile.certificate.destroy');
});

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

    // Documentos Internos e Modelos
    Route::resource('modelos', App\Http\Controllers\ModeloDocumentoController::class);
    Route::get('documentos-internos-export/pdf', [App\Http\Controllers\DocumentoInternoController::class, 'exportPdf'])->name('documentos-internos.export.pdf');
    Route::get('documentos-internos-export/excel', [App\Http\Controllers\DocumentoInternoController::class, 'exportExcel'])->name('documentos-internos.export.excel');
    Route::post('documentos-internos/preview', [App\Http\Controllers\DocumentoInternoController::class, 'preview'])->name('documentos-internos.preview');
    Route::post('documentos-internos/{documentoInterno}/sign', [App\Http\Controllers\DocumentoInternoController::class, 'sign'])->name('documentos-internos.sign');
    Route::post('documentos-internos/{documentoInterno}/restore/{version}', [App\Http\Controllers\DocumentoInternoController::class, 'restore'])->name('documentos-internos.restore');
    Route::post('documentos-internos/{documentoInterno}/favorite', [App\Http\Controllers\DocumentoInternoController::class, 'toggleFavorite'])->name('documentos-internos.favorite');
    Route::get('documentos-internos/{documentoInterno}/pdf', [App\Http\Controllers\DocumentoInternoController::class, 'downloadPdf'])->name('documentos-internos.pdf');

    // Workflow Routes
    Route::post('documentos-internos/{documentoInterno}/submit', [App\Http\Controllers\DocumentoInternoController::class, 'submit'])->name('documentos-internos.submit');
    Route::post('documentos-internos/{documentoInterno}/approve', [App\Http\Controllers\DocumentoInternoController::class, 'approve'])->name('documentos-internos.approve');
    Route::post('documentos-internos/{documentoInterno}/reject', [App\Http\Controllers\DocumentoInternoController::class, 'reject'])->name('documentos-internos.reject');

    Route::resource('documentos-internos', App\Http\Controllers\DocumentoInternoController::class)
        ->parameters(['documentos-internos' => 'documentoInterno']);

    // Gabinete Dashboard
    Route::get('/gabinete/dashboard', [App\Http\Controllers\GabineteDashboardController::class, 'index'])
        ->name('gabinete.dashboard')
        ->middleware('auth');

    Route::post('/gabinete/batch-sign', [App\Http\Controllers\GabineteDashboardController::class, 'batchSign'])
        ->name('gabinete.batch-sign')
        ->middleware('auth');

    Route::post('/gabinete/batch-approve', [App\Http\Controllers\GabineteDashboardController::class, 'batchApprove'])
        ->name('gabinete.batch-approve')
        ->middleware('auth');

    // Departamento Dashboard
    Route::prefix('departamento')->name('departamento.')->middleware(['auth'])->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\DepartamentoDashboardController::class, 'index'])->name('dashboard');
        Route::post('/batch-approve-docs', [App\Http\Controllers\DepartamentoDashboardController::class, 'batchApproveDocuments'])->name('batch.approve.docs');
        Route::post('/batch-approve-reqs', [App\Http\Controllers\DepartamentoDashboardController::class, 'batchApproveRequisicoes'])->name('batch.approve.reqs');
        Route::post('/batch-sign-docs', [App\Http\Controllers\DepartamentoDashboardController::class, 'batchSignDocuments'])->name('batch.sign.docs');
        Route::post('/batch-sign-reqs', [App\Http\Controllers\DepartamentoDashboardController::class, 'batchSignRequisicoes'])->name('batch.sign.reqs');
    });
});

// Assistente de IA sobre documentos (gated por feature flag + permissão; acesso por documento é re-verificado no controller)
if (config('app.feature_assistente')) {
    Route::middleware(['auth', 'can:assistente.usar'])->group(function () {
        Route::get('/assistente', [AssistenteController::class, 'index'])->name('assistente.index');
        Route::post('/assistente/perguntar', [AssistenteController::class, 'perguntarGlobal'])->name('assistente.perguntar');
        Route::post('documentos-entradas/{documento}/assistente', [AssistenteController::class, 'perguntarEntrada'])->name('assistente.entrada');
        Route::post('documentos-entradas/{documento}/gerar-nota-gab', [DocumentoEntradaController::class, 'generateCabinetNote'])->name('documentos-entradas.gerar-nota-gab');
        Route::post('documentos-entradas/{documento}/sugerir-acoes', [DocumentoEntradaController::class, 'suggestActions'])->name('documentos-entradas.sugerir-acoes');
        Route::post('documentos-internos/{documentoInterno}/assistente', [AssistenteController::class, 'perguntarInterno'])->name('assistente.interno');
    });
}

// Rota utilitária para deploy em hospedagem compartilhada (cPanel)
Route::get('/deploy-setup', function (\Illuminate\Http\Request $request) {
    $expectedKey = config('app.deploy_key');

    if (empty($expectedKey)) {
        abort(403, 'Acesso negado. Chave de deploy não configurada no ambiente.');
    }

    // Impede o uso da chave padrão em produção
    if (app()->environment('production') && $expectedKey === 'InfinityDeploy2024!') {
        abort(403, 'Acesso negado. A chave de deploy padrão não pode ser utilizada em ambiente de produção por razões de segurança.');
    }

    // Aceita a chave por header (preferencial, não vaza em logs/Referer) com
    // fallback para query string por compatibilidade. Comparação timing-safe.
    $providedKey = $request->header('X-Deploy-Key') ?? $request->input('key');
    if (! is_string($providedKey) || ! hash_equals((string) $expectedKey, $providedKey)) {
        abort(403, 'Acesso negado. Chave inválida.');
    }

    try {
        $output = [];

        // 1. Limpar caches antigos
        \Illuminate\Support\Facades\Artisan::call('optimize:clear');
        $output[] = 'Caches limpos (optimize:clear)';

        // 2. Rodar migrações (se houver banco configurado)
        try {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            $output[] = 'Migrações executadas com sucesso.';
        } catch (\Exception $e) {
            $output[] = 'Aviso na migração: '.$e->getMessage();
        }

        // 3. Linkar storage (simula o link simbólico)
        try {
            \Illuminate\Support\Facades\Artisan::call('storage:link');
            $output[] = 'Storage linkado com sucesso.';
        } catch (\Exception $e) {
            $output[] = 'Aviso no storage:link: '.$e->getMessage();
        }

        // 4. Cachear configurações para produção
        \Illuminate\Support\Facades\Artisan::call('config:cache');
        \Illuminate\Support\Facades\Artisan::call('route:cache');
        \Illuminate\Support\Facades\Artisan::call('view:cache');
        $output[] = 'Config, Rotas e Views cacheadas.';

        return implode('<br>', $output);
    } catch (\Exception $e) {
        return 'Erro crítico durante o setup: '.$e->getMessage();
    }
})->middleware('throttle:6,1');

