<?php

namespace App\Providers;

use App\Models\DocumentoEntrada;
use App\Models\Empresa;
use App\Models\Gabinete;
use App\Models\Requisicao;
use App\Models\ReservaEspaco;
use App\Models\Viatura;
use App\Observers\RequisicaoObserver;
use App\Support\CatalogCache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registrar Observers
        Requisicao::observe(RequisicaoObserver::class);

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
            }

            $view->with('menuCounts', $counts);
        });
    }
}
