<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class DocumentoEspecie extends Model
{
    use HasFactory;

    protected $table = 'documento_especies';

    protected $fillable = [
        'nome',
        'ativo',
        'ordem',
        'prazo_tratamento_dias',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'ordem' => 'integer',
        'prazo_tratamento_dias' => 'integer',
    ];

    /**
     * Chave de cache do mapa nome -> prazo.
     */
    public const CACHE_PRAZOS = 'documento_especies_prazos';

    protected static function booted(): void
    {
        // O mapa é lido uma vez por pedido e usado em todas as linhas da
        // listagem; qualquer alteração às espécies tem de o invalidar.
        $esquecer = function () {
            Cache::forget(self::CACHE_PRAZOS);
            Cache::forget('documento_especies_names');
        };

        static::saved($esquecer);
        static::deleted($esquecer);
    }

    /**
     * Mapa nome da espécie -> prazo de tratamento em dias.
     *
     * DocumentoEntrada guarda a espécie pelo nome (classificacao_especie), não
     * por chave estrangeira, e sla_status é avaliado por linha na listagem —
     * daí o mapa em cache em vez de uma consulta por documento.
     *
     * @return array<string, int>
     */
    public static function prazosPorNome(): array
    {
        return Cache::remember(self::CACHE_PRAZOS, 600, function () {
            return static::query()
                ->whereNotNull('prazo_tratamento_dias')
                ->pluck('prazo_tratamento_dias', 'nome')
                ->map(fn ($dias) => (int) $dias)
                ->all();
        });
    }

    public function retentionSchedule()
    {
        return $this->hasOne(RetentionSchedule::class, 'documento_especie_id');
    }
}
