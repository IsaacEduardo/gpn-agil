<?php

namespace App\Models;

use App\Enums\TipoDocumentoVinculo;
use App\Enums\TipoRelacaoDocumento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoVinculo extends Model
{
    use HasFactory;

    protected $table = 'documento_vinculos';

    protected $fillable = [
        'origem_tipo',
        'origem_id',
        'destino_tipo',
        'destino_id',
        'tipo_relacao',
        'vinculado_por_id',
        'justificativa',
    ];

    protected $casts = [
        'origem_tipo' => TipoDocumentoVinculo::class,
        'destino_tipo' => TipoDocumentoVinculo::class,
        'tipo_relacao' => TipoRelacaoDocumento::class,
    ];

    /**
     * Utilizador que efetuou a associação
     */
    public function vinculadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vinculado_por_id');
    }

    /**
     * Recupera a instância do documento de origem
     */
    public function getOrigemDocumentoAttribute(): DocumentoEntrada|DocumentoInterno|null
    {
        $tipo = is_string($this->origem_tipo) ? $this->origem_tipo : $this->origem_tipo->value;
        if ($tipo === 'EXTERNO') {
            return DocumentoEntrada::find($this->origem_id);
        }

        return DocumentoInterno::find($this->origem_id);
    }

    /**
     * Recupera a instância do documento de destino
     */
    public function getDestinoDocumentoAttribute(): DocumentoEntrada|DocumentoInterno|null
    {
        $tipo = is_string($this->destino_tipo) ? $this->destino_tipo : $this->destino_tipo->value;
        if ($tipo === 'EXTERNO') {
            return DocumentoEntrada::find($this->destino_id);
        }

        return DocumentoInterno::find($this->destino_id);
    }

    /**
     * Dado o lado atual do relacionamento, retorna o documento do outro lado
     */
    public function getOutroDocumento(string $meuTipo, int $meuId): array
    {
        $meuTipoUpper = strtoupper($meuTipo);
        $origemTipoVal = is_string($this->origem_tipo) ? $this->origem_tipo : $this->origem_tipo->value;
        $destinoTipoVal = is_string($this->destino_tipo) ? $this->destino_tipo : $this->destino_tipo->value;

        if ($origemTipoVal === $meuTipoUpper && (int) $this->origem_id === (int) $meuId) {
            return [
                'tipo' => $destinoTipoVal,
                'id' => (int) $this->destino_id,
                'documento' => $this->destinoDocumento,
                'papel' => 'DESTINO',
            ];
        }

        return [
            'tipo' => $origemTipoVal,
            'id' => (int) $this->origem_id,
            'documento' => $this->origemDocumento,
            'papel' => 'ORIGEM',
        ];
    }

    /**
     * Scope para buscar todos os vínculos onde o documento é origem OU destino
     */
    public function scopeParaDocumento($query, string $tipo, int $id)
    {
        $tipoUpper = strtoupper($tipo);

        return $query->where(function ($q) use ($tipoUpper, $id) {
            $q->where(function ($sub) use ($tipoUpper, $id) {
                $sub->where('origem_tipo', $tipoUpper)
                    ->where('origem_id', $id);
            })->orWhere(function ($sub) use ($tipoUpper, $id) {
                $sub->where('destino_tipo', $tipoUpper)
                    ->where('destino_id', $id);
            });
        });
    }

    /**
     * Scope para verificar existência de vínculo entre dois documentos específicos em qualquer sentido
     */
    public function scopeEntreDocumentos($query, string $tipoA, int $idA, string $tipoB, int $idB)
    {
        $tipoAUpper = strtoupper($tipoA);
        $tipoBUpper = strtoupper($tipoB);

        return $query->where(function ($q) use ($tipoAUpper, $idA, $tipoBUpper, $idB) {
            $q->where(function ($sub) use ($tipoAUpper, $idA, $tipoBUpper, $idB) {
                $sub->where('origem_tipo', $tipoAUpper)
                    ->where('origem_id', $idA)
                    ->where('destino_tipo', $tipoBUpper)
                    ->where('destino_id', $idB);
            })->orWhere(function ($sub) use ($tipoAUpper, $idA, $tipoBUpper, $idB) {
                $sub->where('origem_tipo', $tipoBUpper)
                    ->where('origem_id', $idB)
                    ->where('destino_tipo', $tipoAUpper)
                    ->where('destino_id', $idA);
            });
        });
    }
}
