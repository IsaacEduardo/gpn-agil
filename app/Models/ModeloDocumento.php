<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModeloDocumento extends Model
{
    use HasFactory;

    /**
     * Modelos que só a Secretaria Geral (ou Admin) pode usar.
     */
    public const CODIGOS_EXCLUSIVOS_SEC_GERAL = [
        'ORDEM_DE_SERVICO_SEC_GERAL',
        'OFICIO_SEC_GERAL',
        'NOTA_SEC_GERAL',
        'INFORMACAO_PARECER_SEC_GERAL',
    ];

    protected $fillable = [
        'nome',
        'codigo',
        'documento_especie_id',
        'conteudo',
        'campos_dinamicos',
        'gabinete_id',
        'user_id',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'campos_dinamicos' => 'array',
    ];

    public function especie()
    {
        return $this->belongsTo(DocumentoEspecie::class, 'documento_especie_id');
    }

    public function gabinete()
    {
        return $this->belongsTo(Gabinete::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
