<?php

namespace App\Providers;

use App\Models\DadosInstituicao;
use App\Models\DocumentoEntrada;
use App\Models\Empresa;
use App\Models\Gabinete;
use App\Models\Requisicao;
use App\Models\ReservaEspaco;
use App\Models\User;
use App\Models\Viatura;
use App\Observers\RequisicaoObserver;
use App\Support\CatalogCache;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Provedor de IA do Assistente (isolado por interface para permitir troca por Kimi / Anthropic / Fake).
        $this->app->singleton(\App\Services\Ai\LlmClient::class, function () {
            $driver = env('ASSISTENTE_DRIVER', config('services.anthropic.driver', 'anthropic'));
            if (in_array(strtolower($driver), ['kimi', 'moonshot'])) {
                return new \App\Services\Ai\KimiLlmClient(config('services.kimi', []));
            }
            if ($driver === 'fake') {
                return new \App\Services\Ai\FakeLlmClient;
            }

            return new \App\Services\Ai\AnthropicLlmClient(config('services.anthropic', []));
        });

        // Ligação do Repositório de Domínio (DDD Clean Architecture)
        $this->app->bind(
            \App\Domain\DocumentManagement\Repositories\DocumentoEntradaRepositoryInterface::class,
            \App\Infrastructure\Persistence\Eloquent\EloquentDocumentoEntradaRepository::class
        );

        $this->app->bind(
            \App\Domain\LandManagement\Repositories\LoteRepositoryInterface::class,
            \App\Infrastructure\Persistence\Eloquent\EloquentLoteRepository::class
        );

        $this->app->bind(
            \App\Domain\RequisitionFleet\Repositories\ViaturaRepositoryInterface::class,
            \App\Infrastructure\Persistence\Eloquent\EloquentViaturaRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // O Laravel gera a paginação com marcação Tailwind por omissão, mas o
        // portal é servido apenas por Bootstrap 5 (resources/sass/app.scss não
        // importa Tailwind e o plugin não está no vite.config.js). Sem isto, os
        // controlos de paginação saem com classes sem CSS — legíveis no DOM mas
        // invisíveis no ecrã, o que faz as listagens parecerem não paginadas.
        Paginator::useBootstrapFive();

        // Compartilhar dados da instituição com todas as views (com cache de alta performance)
        try {
            $dados = Cache::rememberForever('dados_instituicao_global', function () {
                $inst = Schema::hasTable('dados_instituicao')
                    ? (DadosInstituicao::first() ?? new DadosInstituicao)
                    : new DadosInstituicao;

                // Fallbacks padrão para Namibe / Angola
                if (empty($inst->nome_oficial)) {
                    $inst->nome_oficial = 'Governo Provincial do Namibe';
                }
                if (empty($inst->sigla)) {
                    $inst->sigla = 'GPN';
                }
                if (empty($inst->cidade)) {
                    $inst->cidade = 'Moçâmedes';
                }
                if (empty($inst->cabecalho_linha1)) {
                    $inst->cabecalho_linha1 = 'REPÚBLICA DE ANGOLA';
                }
                if (empty($inst->cabecalho_linha2)) {
                    $inst->cabecalho_linha2 = 'GOVERNO PROVINCIAL DO NAMIBE';
                }

                return $inst;
            });
        } catch (\Exception $e) {
            $dados = new DadosInstituicao;
            $dados->nome_oficial = 'Governo Provincial do Namibe';
            $dados->sigla = 'GPN';
            $dados->cidade = 'Moçâmedes';
            $dados->cabecalho_linha1 = 'REPÚBLICA DE ANGOLA';
            $dados->cabecalho_linha2 = 'GOVERNO PROVINCIAL DO NAMIBE';
        }

        View::share('dadosInstituicao', $dados);

        // Invalidar o cache da instituição sempre que os dados forem alterados ou removidos
        DadosInstituicao::saved(function () {
            Cache::forget('dados_instituicao_global');
        });
        DadosInstituicao::deleted(function () {
            Cache::forget('dados_instituicao_global');
        });

        // Registrar Observers
        Requisicao::observe(RequisicaoObserver::class);
        \App\Models\DocumentoInterno::observe(\App\Observers\DocumentoInternoObserver::class);

        // Limpar caches de permissões do utilizador
        User::saved(function ($user) {
            Cache::forget("user_{$user->id}_departments");
            Cache::forget("user_{$user->id}_responsible_gabinetes");
            Cache::forget("menu_counts_user_{$user->id}");
        });
        User::deleted(function ($user) {
            Cache::forget("user_{$user->id}_departments");
            Cache::forget("user_{$user->id}_responsible_gabinetes");
            Cache::forget("menu_counts_user_{$user->id}");
        });

        Gabinete::saved(function ($gabinete) {
            if ($gabinete->responsavel_id) {
                Cache::forget("user_{$gabinete->responsavel_id}_responsible_gabinetes");
            }
            if ($gabinete->isDirty('responsavel_id') && $gabinete->getOriginal('responsavel_id')) {
                Cache::forget('user_'.$gabinete->getOriginal('responsavel_id').'_responsible_gabinetes');
            }
        });
        Gabinete::deleted(function ($gabinete) {
            if ($gabinete->responsavel_id) {
                Cache::forget("user_{$gabinete->responsavel_id}_responsible_gabinetes");
            }
        });

        // Invalidar caches de catálogos quando modelos forem alterados
        Viatura::saved(function () {

            CatalogCache::forgetViaturas();
        });
        Viatura::deleted(function () {
            CatalogCache::forgetViaturas();
        });

        Empresa::saved(function () {
            CatalogCache::forgetEmpresas();
        });
        Empresa::deleted(function () {
            CatalogCache::forgetEmpresas();
        });

        View::composer('layouts.app', function ($view) {
            $counts = [
                'pend_requisicoes' => 0,
                'pend_reservas' => 0,
                'can_review_requisicoes' => false,
                'can_review_reservas' => false,
            ];

            if (Auth::check()) {
                $user = Auth::user();

                // Cache curto por utilizador: evita ~8 queries de contagem a cada renderização
                // do layout. Auto-expira em 30s, suficiente para badges de navegação.
                $counts = Cache::remember("menu_counts_user_{$user->id}", 30, function () use ($user, $counts) {
                    $counts['can_review_requisicoes'] = (bool) ($user->role && $user->hasPermission('visto_departamento_requisicoes'));
                    $counts['can_review_reservas'] = (bool) ($user->role && $user->hasPermission('visto_departamento_reservas'));

                    $deptIds = $user->departamentos()->pluck('departamentos.id')->all();
                    if (empty($deptIds) && $user->departamento_id) {
                        $deptIds = [$user->departamento_id];
                    }
                    if (! empty($deptIds)) {
                        $counts['pend_requisicoes'] = Requisicao::query()
                            ->where('status', Requisicao::STATUS_PENDENTE)
                            ->where(function ($q) {
                                $q->whereNull('visto_departamento_status')
                                    ->orWhere('visto_departamento_status', 'pendente');
                            })
                            ->whereHas('usuario.departamentos', function ($q) use ($deptIds) {
                                $q->whereIn('departamentos.id', $deptIds);
                            })
                            ->count();

                        $counts['pend_reservas'] = ReservaEspaco::query()
                            ->where('status', ReservaEspaco::STATUS_PENDENTE)
                            ->where(function ($q) {
                                $q->whereNull('visto_departamento_status')
                                    ->orWhere('visto_departamento_status', 'pendente');
                            })
                            ->whereHas('usuario.departamentos', function ($q) use ($deptIds) {
                                $q->whereIn('departamentos.id', $deptIds);
                            })
                            ->count();

                        $counts['pend_documentos_por_receber'] = DocumentoEntrada::query()
                            ->whereExists(function ($sub) use ($deptIds) {
                                $sub->selectRaw(1)
                                    ->from('documento_encaminhamentos as de')
                                    ->whereColumn('de.documento_entrada_id', 'documentos_entradas.id')
                                    ->whereNull('de.recebido_em')
                                    ->whereIn('de.destino_departamento_id', $deptIds);
                            })
                            ->count();

                        $counts['pend_documentos_visto_departamento'] = DocumentoEntrada::query()
                            ->whereNull('visto_departamento_status')
                            ->where('status', 'recebido')
                            ->whereIn('departamento_id', $deptIds)
                            ->count();

                        $counts['aprov_documentos_visto_departamento'] = DocumentoEntrada::query()
                            ->where('visto_departamento_status', 'aprovado')
                            ->whereIn('departamento_id', $deptIds)
                            ->count();

                        $counts['rej_documentos_visto_departamento'] = DocumentoEntrada::query()
                            ->where('visto_departamento_status', 'rejeitado')
                            ->whereIn('departamento_id', $deptIds)
                            ->count();

                        $gabIds = Gabinete::where('responsavel_id', $user->id)->pluck('id')->all();
                        if (! empty($gabIds)) {
                            $counts['pend_documentos_visto_gabinete'] = DocumentoEntrada::query()
                                ->whereNull('visto_gabinete_status')
                                ->whereHas('departamento', function ($q) use ($gabIds) {
                                    $q->whereIn('gabinete_id', $gabIds);
                                })
                                ->count();
                        } else {
                            $counts['pend_documentos_visto_gabinete'] = 0;
                        }
                    }

                    return $counts;
                });
            }

            $view->with('menuCounts', $counts);
        });
    }
}
