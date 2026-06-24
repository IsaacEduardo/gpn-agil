<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RetentionSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'documento_especie_id',
        'temporalidade_anos',
        'acao_final',
        'observacoes',
    ];

    public function documentoEspecie()
    {
        return $this->belongsTo(DocumentoEspecie::class);
    }
}
