<?php

namespace App\Models;

use App\Enums\NivelColaboracao;
use Illuminate\Database\Eloquent\Model;

/**
 * Colaborador convidado para editar um Documento Interno em tempo real.
 */
class DocumentoColaborador extends Model
{
    protected $table = 'documento_colaboradores';

    protected $fillable = [
        'documento_interno_id',
        'user_id',
        'nivel',
        'convidado_por',
    ];

    protected $casts = [
        'nivel' => NivelColaboracao::class,
    ];

    public function documento()
    {
        return $this->belongsTo(DocumentoInterno::class, 'documento_interno_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function convidadoPor()
    {
        return $this->belongsTo(User::class, 'convidado_por');
    }
}
