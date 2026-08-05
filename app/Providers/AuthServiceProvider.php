<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\Requisicao::class => \App\Policies\RequisicaoPolicy::class,
        \App\Models\ReservaEspaco::class => \App\Policies\ReservaEspacoPolicy::class,
        \App\Models\DocumentoEntrada::class => \App\Policies\DocumentoEntradaPolicy::class,
        \App\Models\Viatura::class => \App\Policies\ViaturaPolicy::class,
        \App\Models\TermoEntrega::class => \App\Policies\TermoEntregaPolicy::class,
        \App\Models\DocumentoInterno::class => \App\Policies\DocumentoInternoPolicy::class,
        \App\Models\Lote::class => \App\Policies\LotePolicy::class,
        \App\Models\Requerente::class => \App\Policies\RequerentePolicy::class,
        \App\Models\SolicitacaoAtribuicao::class => \App\Policies\SolicitacaoAtribuicaoPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
