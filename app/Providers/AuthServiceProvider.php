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
