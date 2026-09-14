<?php

use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\AssistenteController;
use App\Http\Controllers\CredencialController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Controllers\DepartamentoDashboardController;
use App\Http\Controllers\DocumentoColaboracaoController;
use App\Http\Controllers\DocumentoEntradaController;
use App\Http\Controllers\DocumentoEntradaEncaminhamentoController;
use App\Http\Controllers\DocumentoEntradaProtocoloController;
use App\Http\Controllers\DocumentoEntradaTarefaController;
use App\Http\Controllers\DocumentoInternoController;
use App\Http\Controllers\EdmsController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\GabineteController;
use App\Http\Controllers\GabineteDashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstituicaoController;
use App\Http\Controllers\ModeloDocumentoController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PastaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReservaEspacoController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TarefaController;
use App\Http\Controllers\TermoEntregaController;
use App\Http\Controllers\ViaturaController;
use App\Http\Controllers\ViaturaReportController;
use App\Http\Controllers\AnaliseTecnicaController;
use App\Http\Controllers\LoteController;
use App\Http\Controllers\RequerenteController;
use App\Http\Controllers\SolicitacaoAtribuicaoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();
Route::match(['get', 'post'], '/logout', [\App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');

// Rota pública de verificação de autenticidade de documentos (acesso externo via hash)
// throttle impede enumeração/brute-force de hashes
Route::get('/verificar/documento/{hash}', [DocumentoInternoController::class, 'verificarPublico'])
    ->middleware('throttle:30,1')
    ->name('documentos-internos.verificar');

// A partir daqui, todas as rotas exigem autenticação
Route::middleware(['auth'])->group(function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::get('/inicio', [HomeController::class, 'index'])->name('inicio');
    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard.main');
    Route::get('/global-search', [SearchController::class, 'index'])->name('global.search');

    // Rotas para o módulo de Feedbacks
    Route::resource('feedbacks', FeedbackController::class);

    // Rotas para o módulo de Viaturas
    Route::resource('viaturas', ViaturaController::class);
    Route::get('viaturas-export/pdf', [ViaturaReportController::class, 'exportPDF'])->middleware('throttle:heavy-exports')->name('viaturas.export.pdf');
    Route::get('viaturas-export/excel', [ViaturaReportController::class, 'exportExcel'])->middleware('throttle:heavy-exports')->name('viaturas.export.excel');

    // Rotas para o módulo de Empresas
    Route::resource('empresas', EmpresaController::class);

    // Rotas para o módulo de Departamentos
    Route::resource('departamentos', DepartamentoController::class);
    Route::resource('gabinetes', GabineteController::class);

    // Rotas para o Módulo de Gestão de Lotes de Terra e Atribuição
    Route::resource('requerentes', RequerenteController::class);
    Route::get('lotes/geojson', [LoteController::class, 'geoJson'])->name('lotes.geojson');
    Route::resource('lotes', LoteController::class);
    Route::post('solicitacoes/{solicitacao}/vincular-lote', [SolicitacaoAtribuicaoController::class, 'vincularLote'])->name('solicitacoes.vincular-lote');
    Route::patch('solicitacoes/{solicitacao}/transicionar', [SolicitacaoAtribuicaoController::class, 'transicionarStatus'])->name('solicitacoes.transicionar');
    Route::post('solicitacoes/{solicitacao}/emitir-termo', [SolicitacaoAtribuicaoController::class, 'emitirTermo'])->name('solicitacoes.emitir-termo');
    // A solicitação não tem edição livre: evolui pelos endpoints de workflow
    // acima (vincular-lote, transicionar, emitir-termo), pelo que edit/update/
    // destroy não são registados — não existem no controller.
    Route::resource('solicitacoes', SolicitacaoAtribuicaoController::class)
        ->only(['index', 'create', 'store', 'show'])
        ->parameters(['solicitacoes' => 'solicitacao'])
        ->names('solicitacoes');
    Route::get('solicitacoes/{solicitacao}/analise-tecnica/create', [AnaliseTecnicaController::class, 'create'])->name('analises-tecnicas.create');
    Route::post('solicitacoes/{solicitacao}/analise-tecnica', [AnaliseTecnicaController::class, 'store'])->name('analises-tecnicas.store');
});
Route::middleware(['auth'])->group(function () {
    Route::post('documentos-entradas/batch/receber', [DocumentoEntradaEncaminhamentoController::class, 'batchReceber'])
        ->name('documentos-entradas.batch.receber');
    Route::post('documentos-entradas/batch/encaminhar', [DocumentoEntradaEncaminhamentoController::class, 'batchEncaminhar'])
        ->name('documentos-entradas.batch.encaminhar');
    Route::post('documentos-entradas/batch/despachar', [DocumentoEntradaEncaminhamentoController::class, 'batchDespachar'])
        ->name('documentos-entradas.batch.despachar');
    Route::get('documentos-entradas/search/json', [DocumentoEntradaController::class, 'searchJson'])
        ->name('documentos-entradas.search.json');
    Route::resource('documentos-entradas', DocumentoEntradaController::class)->names('documentos-entradas');
    Route::get('documentos-entradas/{documento}/preview-ajax', [DocumentoEntradaController::class, 'previewAjax'])
        ->name('documentos-entradas.preview-ajax');
    Route::post('documentos-entradas/{documento}/quick-action', [DocumentoEntradaController::class, 'quickAction'])
        ->name('documentos-entradas.quick-action');
    Route::post('documentos-entradas/{documento}/despachar', [DocumentoEntradaController::class, 'despachar'])
        ->name('documentos-entradas.despachar');
    Route::post('documentos-entradas/{documento}/encaminhar-tratado', [DocumentoEntradaController::class, 'encaminhar'])
        ->name('documentos-entradas.encaminhar-tratado');
    Route::get('documentos-entradas/{documento}/protocolo', [DocumentoEntradaProtocoloController::class, 'protocolo'])
        ->name('documentos-entradas.protocolo');
    Route::get('documentos-entradas/{documento}/protocolo/pdf', [DocumentoEntradaProtocoloController::class, 'protocoloPdf'])
        ->name('documentos-entradas.protocolo.pdf');
    Route::get('documentos-entradas/{documento}/protocolo/etiqueta', [DocumentoEntradaProtocoloController::class, 'protocoloEtiqueta'])
        ->name('documentos-entradas.protocolo.etiqueta');
    Route::patch('documentos-entradas/{documento}/protocolo/impresso', [DocumentoEntradaProtocoloController::class, 'marcarImpresso'])
        ->name('documentos-entradas.protocolo.impresso');
    Route::post('documentos-entradas/{documento}/encaminhar', [DocumentoEntradaEncaminhamentoController::class, 'encaminhar'])
        ->name('documentos-entradas.encaminhar');
    Route::post('documentos-entradas/{documento}/saida-gabinete', [DocumentoEntradaEncaminhamentoController::class, 'saidaGabinete'])
        ->name('documentos-entradas.saida-gabinete');
    Route::patch('documentos-entradas/{documento}/encaminhamentos/{encaminhamento}/receber', [DocumentoEntradaEncaminhamentoController::class, 'receber'])
        ->name('documentos-entradas.encaminhamentos.receber');
    Route::delete('documentos-entradas/{documento}/encaminhamentos/{encaminhamento}', [DocumentoEntradaEncaminhamentoController::class, 'cancelar'])
        ->name('documentos-entradas.encaminhamentos.cancelar');
    Route::delete('documentos-entradas/{documento}/anexos/{anexo}', [DocumentoEntradaController::class, 'destroyAnexo'])
        ->name('documentos-entradas.anexos.destroy');
    Route::get('documentos-entradas/{documento}/arquivo/download', [DocumentoEntradaController::class, 'downloadArquivo'])
        ->name('documentos-entradas.arquivo.download');
    Route::get('documentos-entradas/{documento}/anexos/{anexo}/download', [DocumentoEntradaController::class, 'downloadAnexo'])
        ->name('documentos-entradas.anexos.download');
    Route::get('documentos-entradas/{documento}/anexos/{anexo}/ocr', [DocumentoEntradaController::class, 'getOcrText'])
        ->name('documentos-entradas.anexos.ocr');
    Route::post('documentos-entradas/{documento}/anexos/{anexo}/reprocessar-ocr', [DocumentoEntradaController::class, 'reprocessOcr'])
        ->middleware('throttle:ocr-processing')
        ->name('documentos-entradas.anexos.reprocessar-ocr');
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
    Route::post('documentos-entradas/{documento}/tarefas', [DocumentoEntradaTarefaController::class, 'store'])
        ->name('documentos-entradas.tarefas.store');
    Route::match(['post', 'patch'], 'documentos-entradas/{documento}/tarefas/{tarefa}/concluir', [DocumentoEntradaTarefaController::class, 'concluir'])
        ->name('documentos-entradas.tarefas.concluir');
    Route::patch('documentos-entradas/{documento}/tarefas/{tarefa}/cancelar', [DocumentoEntradaTarefaController::class, 'cancelar'])
        ->name('documentos-entradas.tarefas.cancelar');
    Route::get('documentos-entradas-export/pdf', [DocumentoEntradaProtocoloController::class, 'exportPDF'])
        ->middleware('throttle:heavy-exports')
        ->name('documentos-entradas.export.pdf');
    Route::get('documentos-entradas-export/excel', [DocumentoEntradaProtocoloController::class, 'exportExcel'])
        ->middleware('throttle:heavy-exports')
        ->name('documentos-entradas.export.excel');

    // Minhas Tarefas
    Route::get('/tarefas', [TarefaController::class, 'index'])->name('tarefas.index');

    // EDMS (Novo Sistema de Arquivos)
    Route::get('/api/edms/arvore-pastas', [EdmsController::class, 'arvorePastas'])->name('api.edms.arvore-pastas');
    Route::get('/edms/{folder?}', [EdmsController::class, 'index'])->name('edms.index');
    Route::post('/edms/folder/create', [EdmsController::class, 'createFolder'])->name('edms.create-folder');
    Route::post('/edms/file/upload', [EdmsController::class, 'uploadFile'])->name('edms.upload-file');
    Route::get('/edms/version/{version}/stream', [EdmsController::class, 'streamVersion'])->name('edms.stream-version');
    Route::get('/edms/attachment/{anexo}/stream', [EdmsController::class, 'streamAttachment'])->name('edms.stream-attachment');
    Route::post('/edms/folder/{folder}/share', [EdmsController::class, 'shareFolder'])->name('edms.share-folder');
    Route::get('/edms/folder/{folder}/history', [EdmsController::class, 'folderHistory'])->name('edms.folder-history');
    Route::get('/edms/retention/config', [EdmsController::class, 'retentionConfig'])->name('edms.retention');
    Route::post('/edms/retention/store', [EdmsController::class, 'retentionStore'])->name('edms.retention-store');

    // Rotas para Arquivo e Pastas
    Route::get('pastas/search', [PastaController::class, 'search'])->name('pastas.search');
    // Rota unificada para arquivamento (Entrada e Interno)
    Route::post('pastas/{documento}/arquivar', [PastaController::class, 'arquivar'])->name('pastas.arquivar');
    Route::post('documentos-entradas/{documento}/desarquivar', [PastaController::class, 'desarquivar'])->name('documentos-entradas.desarquivar');

    // Redirecionamento de compatibilidade
    Route::get('/pastas', function () {
        return redirect()->route('edms.index');
    })->name('pastas.index');

    // Mantemos resource apenas para create/store/update/destroy via API/Modal se necessário,
    // mas a index é redirecionada. O 'except' garante que 'index' não sobrescreva o redirect acima se definido depois,
    // mas como definimos antes ou manualmente, melhor usar 'except' index.
    Route::resource('pastas', PastaController::class)->except(['index']);
});

// Rotas para termos de entrega, credenciais e reservas (exigem autenticação)
Route::middleware(['auth'])->group(function () {
    Route::get('termos', [TermoEntregaController::class, 'index'])->name('termos.index');
    Route::get('termos/create', [TermoEntregaController::class, 'create'])->name('termos.create');
    Route::post('termos', [TermoEntregaController::class, 'store'])->name('termos.store');
    Route::get('termos/{requisicao}/{tipo}', [TermoEntregaController::class, 'gerar'])->name('termos.gerar');
    Route::get('termos/{termo}', [TermoEntregaController::class, 'show'])->name('termos.show');
    Route::get('termos/{termo}/pdf', [TermoEntregaController::class, 'pdf'])->name('termos.pdf');
    Route::delete('termos/{termo}', [TermoEntregaController::class, 'destroy'])->name('termos.destroy');

    // Rotas para credenciais (módulo separado)
    Route::resource('credenciais', CredencialController::class)->except(['edit', 'update']);

    // Rotas para reservas de espaços
    Route::get('reservas', [ReservaEspacoController::class, 'index'])->name('reservas.index');
    Route::get('reservas/create', [ReservaEspacoController::class, 'create'])->name('reservas.create');
    Route::post('reservas', [ReservaEspacoController::class, 'store'])->name('reservas.store');
    Route::get('reservas/{reserva}', [ReservaEspacoController::class, 'show'])->name('reservas.show');
    Route::get('reservas/{reserva}/edit', [ReservaEspacoController::class, 'edit'])->name('reservas.edit');
    Route::put('reservas/{reserva}', [ReservaEspacoController::class, 'update'])->name('reservas.update');
    Route::delete('reservas/{reserva}', [ReservaEspacoController::class, 'destroy'])->name('reservas.destroy');

    // Rotas específicas para reservas
    Route::get('reservas-calendar', [ReservaEspacoController::class, 'calendar'])->name('reservas.calendar');
    Route::get('reservas-calendario', [ReservaEspacoController::class, 'calendar'])->name('reservas.calendario');
    Route::post('reservas/verificar-disponibilidade', [ReservaEspacoController::class, 'verificarDisponibilidade'])->name('reservas.verificar-disponibilidade');
    Route::patch('reservas/{reserva}/aprovar', [ReservaEspacoController::class, 'aprovar'])->name('reservas.aprovar');
    Route::patch('reservas/{reserva}/rejeitar', [ReservaEspacoController::class, 'rejeitar'])->name('reservas.rejeitar');
    Route::patch('reservas/{reserva}/cancelar', [ReservaEspacoController::class, 'cancelar'])->name('reservas.cancelar');
    // Rotas para visto do departamento em reservas
    Route::patch('reservas/{reserva}/visto/aprovar', [ReservaEspacoController::class, 'vistoAprovar'])->name('reservas.visto.aprovar');
    Route::patch('reservas/{reserva}/visto/rejeitar', [ReservaEspacoController::class, 'vistoRejeitar'])->name('reservas.visto.rejeitar');
});

// Rotas de Administração de Usuários (apenas admin)
Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::resource('users', UserAdminController::class)->names('admin.users');

    // Configurações da Instituição
    Route::get('instituicao', [InstituicaoController::class, 'edit'])->name('admin.instituicao.edit');
    Route::put('instituicao', [InstituicaoController::class, 'update'])->name('admin.instituicao.update');

    // Espécies de documento e respetivos prazos de tratamento (comandam o SLA)
    Route::resource('documento-especies', \App\Http\Controllers\Admin\DocumentoEspecieController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['documento-especies' => 'documento_especie'])
        ->names('admin.documento-especies');

    // Gestão de Permissões (RBAC)
    Route::prefix('permissoes')->name('configuracoes.permissoes.')->group(function () {
        Route::get('/', [RolePermissionController::class, 'index'])->name('index');
        Route::post('/update', [RolePermissionController::class, 'update'])->name('update');
        Route::post('/toggle', [RolePermissionController::class, 'toggle'])->name('toggle');
        Route::post('/role', [RolePermissionController::class, 'storeRole'])->name('role.store');
        Route::post('/permission', [RolePermissionController::class, 'storePermission'])->name('permission.store');
    });
});

// Rotas para gerenciamento de perfil
Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::post('/profile/certificate', [ProfileController::class, 'uploadCertificate'])->name('profile.certificate.upload');
    Route::delete('/profile/certificate', [ProfileController::class, 'destroyCertificate'])->name('profile.certificate.destroy');
});

// Webhook de bounces/reclamações de e-mail (autenticado por token; isento de CSRF em bootstrap/app.php)
Route::post('/webhooks/mail/bounce', [\App\Http\Controllers\MailBounceWebhookController::class, 'handle'])
    ->name('webhooks.mail.bounce');

// Rotas de Notificações e Web Push
Route::middleware(['auth'])->group(function () {
    // Página dedicada de notificações
    Route::get('/notificacoes', [NotificationController::class, 'page'])->name('notificacoes.index');
    Route::get('/notifications/all', [NotificationController::class, 'page'])->name('notifications.page');

    // API JSON do dropdown
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read_all');
    Route::post('/notifications/read/{id}', [NotificationController::class, 'markAsRead'])->name('notifications.read');

    // Preferências de subscrição (opt-in/opt-out por categoria e canal)
    Route::get('/notifications/preferences', [\App\Http\Controllers\NotificationPreferenceController::class, 'edit'])->name('notifications.preferences.edit');
    Route::put('/notifications/preferences', [\App\Http\Controllers\NotificationPreferenceController::class, 'update'])->name('notifications.preferences.update');

    // Web Push
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');

    // Documentos Internos e Modelos
    Route::resource('modelos', ModeloDocumentoController::class);
    Route::get('documentos-internos-export/pdf', [DocumentoInternoController::class, 'exportPdf'])
        ->middleware('throttle:heavy-exports')
        ->name('documentos-internos.export.pdf');
    Route::get('documentos-internos-export/excel', [DocumentoInternoController::class, 'exportExcel'])
        ->middleware('throttle:heavy-exports')
        ->name('documentos-internos.export.excel');
    Route::post('documentos-internos/preview', [DocumentoInternoController::class, 'preview'])->name('documentos-internos.preview');
    Route::post('documentos-internos/{documentoInterno}/sign', [DocumentoInternoController::class, 'sign'])->name('documentos-internos.sign');
    Route::post('documentos-internos/{documentoInterno}/restore/{version}', [DocumentoInternoController::class, 'restore'])->name('documentos-internos.restore');
    Route::post('documentos-internos/batch-zip', [DocumentoInternoController::class, 'batchDownloadZip'])
        ->middleware('throttle:heavy-exports')
        ->name('documentos-internos.batch-zip');
    Route::post('documentos-internos/auto-save/{documentoInterno?}', [DocumentoInternoController::class, 'autoSave'])->name('documentos-internos.auto-save');
    Route::get('/empresas/{empresa}/json', [EmpresaController::class, 'apiDetails'])->name('empresas.json');
    Route::get('documentos-internos/{documentoInterno}/pdf', [DocumentoInternoController::class, 'downloadPdf'])->name('documentos-internos.pdf');

    // Workflow Routes
    Route::post('documentos-internos/{documentoInterno}/submit', [DocumentoInternoController::class, 'submit'])->name('documentos-internos.submit');
    Route::post('documentos-internos/{documentoInterno}/approve', [DocumentoInternoController::class, 'approve'])->name('documentos-internos.approve');
    Route::post('documentos-internos/{documentoInterno}/reject', [DocumentoInternoController::class, 'reject'])->name('documentos-internos.reject');

    Route::resource('documentos-internos', DocumentoInternoController::class)
        ->parameters(['documentos-internos' => 'documentoInterno']);

    // Vínculos N:N de Documentos
    Route::post('documentos/{tipo}/{id}/vincular', [\App\Http\Controllers\DocumentoVinculoController::class, 'store'])->name('documentos.vincular');
    Route::delete('documentos/vinculos/{vinculo_id}', [\App\Http\Controllers\DocumentoVinculoController::class, 'destroy'])->name('documentos.desvincular');


    // Gabinete Dashboard
    Route::get('/gabinete/dashboard', [GabineteDashboardController::class, 'index'])
        ->name('gabinete.dashboard')
        ->middleware('auth');

    Route::post('/gabinete/batch-sign', [GabineteDashboardController::class, 'batchSign'])
        ->name('gabinete.batch-sign')
        ->middleware('auth');

    Route::post('/gabinete/batch-approve', [GabineteDashboardController::class, 'batchApprove'])
        ->name('gabinete.batch-approve')
        ->middleware('auth');

    // Departamento Dashboard
    Route::prefix('departamento')->name('departamento.')->middleware(['auth'])->group(function () {
        Route::get('/dashboard', [DepartamentoDashboardController::class, 'index'])->name('dashboard');
        Route::post('/batch-approve-docs', [DepartamentoDashboardController::class, 'batchApproveDocuments'])->name('batch.approve.docs');
        Route::post('/batch-approve-reqs', [DepartamentoDashboardController::class, 'batchApproveRequisicoes'])->name('batch.approve.reqs');
        Route::post('/batch-sign-docs', [DepartamentoDashboardController::class, 'batchSignDocuments'])->name('batch.sign.docs');
        Route::post('/batch-sign-reqs', [DepartamentoDashboardController::class, 'batchSignRequisicoes'])->name('batch.sign.reqs');
    });
});

// Edição colaborativa em tempo real de Documentos Internos (gated por feature flag).
// Requer Laravel Reverb a correr; em cPanel/shared hosting a flag fica desligada (fallback clássico).
if (config('app.feature_collab')) {
    Route::middleware(['auth'])
        ->prefix('documentos-internos/{documentoInterno}/collab')
        ->name('documentos-internos.collab.')
        ->group(function () {
            Route::get('/', [DocumentoColaboracaoController::class, 'editor'])->name('editor');
            Route::get('/state', [DocumentoColaboracaoController::class, 'state'])->name('state');
            Route::post('/sync', [DocumentoColaboracaoController::class, 'sync'])->name('sync');
            Route::post('/checkpoint', [DocumentoColaboracaoController::class, 'checkpoint'])->name('checkpoint');
            Route::post('/titulo', [DocumentoColaboracaoController::class, 'salvarTitulo'])->name('titulo');
            Route::get('/colaboradores', [DocumentoColaboracaoController::class, 'colaboradores'])->name('colaboradores');
            Route::post('/colaboradores', [DocumentoColaboracaoController::class, 'convidar'])->name('convidar');
            Route::patch('/colaboradores/{user}', [DocumentoColaboracaoController::class, 'atualizarColaborador'])->name('colaborador.update');
            Route::delete('/colaboradores/{user}', [DocumentoColaboracaoController::class, 'removerColaborador'])->name('colaborador.destroy');
        });
}

// Assistente de IA sobre documentos (gated por feature flag + permissão; acesso por documento é re-verificado no controller)
if (config('app.feature_assistente')) {
    Route::middleware(['auth', 'can:assistente.usar', 'throttle:ai-assistant'])->group(function () {
        Route::get('/assistente', [AssistenteController::class, 'index'])->name('assistente.index');
        Route::post('/assistente/perguntar', [AssistenteController::class, 'perguntarGlobal'])->name('assistente.perguntar');
        Route::post('/assistente/resumir', [AssistenteController::class, 'resumirDocumento'])->name('assistente.resumir');
        Route::post('/api/ia/resumir-documento', [AssistenteController::class, 'resumirDocumento'])->name('api.ia.resumir');
        Route::post('documentos-entradas/{documento}/assistente', [AssistenteController::class, 'perguntarEntrada'])->name('assistente.entrada');
        Route::post('documentos-entradas/{documento}/gerar-nota-gab', [DocumentoEntradaController::class, 'generateCabinetNote'])->name('documentos-entradas.gerar-nota-gab');
        Route::post('documentos-entradas/{documento}/sugerir-acoes', [DocumentoEntradaController::class, 'suggestActions'])->name('documentos-entradas.sugerir-acoes');
        Route::post('documentos-internos/{documentoInterno}/assistente', [AssistenteController::class, 'perguntarInterno'])->name('assistente.interno');
    });
}

// Rota utilitária para deploy em hospedagem compartilhada (cPanel)
Route::get('/deploy-setup', function (Request $request) {
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
        Artisan::call('optimize:clear');
        $output[] = 'Caches limpos (optimize:clear)';

        // 2. Rodar migrações (se houver banco configurado)
        try {
            Artisan::call('migrate', ['--force' => true]);
            $output[] = 'Migrações executadas com sucesso.';
        } catch (Exception $e) {
            $output[] = 'Aviso na migração: '.$e->getMessage();
        }

        // 3. Linkar storage (simula o link simbólico)
        try {
            Artisan::call('storage:link');
            $output[] = 'Storage linkado com sucesso.';
        } catch (Exception $e) {
            $output[] = 'Aviso no storage:link: '.$e->getMessage();
        }

        // 4. Cachear configurações para produção
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');
        $output[] = 'Config, Rotas e Views cacheadas.';

        return implode('<br>', $output);
    } catch (Exception $e) {
        return 'Erro crítico durante o setup: '.$e->getMessage();
    }
})->middleware('throttle:5,1');
