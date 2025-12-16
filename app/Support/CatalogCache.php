<?php

namespace App\Support;

use App\Models\Empresa;
use App\Models\Viatura;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CatalogCache
{
    protected const VIATURAS_KEY = 'catalog:viaturas:list';

    protected const VIATURAS_OP_KEY = 'catalog:viaturas:operacionais';

    protected const EMPRESAS_KEY = 'catalog:empresas:list';

    /**
     * Lista de viaturas (colunas essenciais) em cache por 10 minutos.
     */
    public static function viaturasList(): Collection
    {
        return Cache::remember(self::VIATURAS_KEY, 600, function () {
            return Viatura::orderBy('identificacao')
                ->get(['id', 'identificacao', 'placa', 'modelo', 'marca', 'status_operacional']);
        });
    }

    /**
     * Lista de viaturas operacionais (não inoperantes) em cache por 10 minutos.
     */
    public static function viaturasOperacionais(): Collection
    {
        return Cache::remember(self::VIATURAS_OP_KEY, 600, function () {
            return Viatura::where('status_operacional', '!=', 'Inoperante')
                ->orderBy('identificacao')
                ->get(['id', 'identificacao', 'placa', 'modelo', 'marca', 'status_operacional']);
        });
    }

    /**
     * Lista de empresas (id, nome) em cache por 10 minutos.
     */
    public static function empresasList(): Collection
    {
        return Cache::remember(self::EMPRESAS_KEY, 600, function () {
            return Empresa::orderBy('nome')->get(['id', 'nome']);
        });
    }

    /**
     * Invalidar caches de catálogos.
     */
    public static function forgetViaturas(): void
    {
        Cache::forget(self::VIATURAS_KEY);
        Cache::forget(self::VIATURAS_OP_KEY);
    }

    public static function forgetEmpresas(): void
    {
        Cache::forget(self::EMPRESAS_KEY);
    }
}
