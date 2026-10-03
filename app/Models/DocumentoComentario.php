<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Comentário na edição colaborativa de um Documento Interno. Um comentário de topo
 * cita o trecho a que se refere; as respostas pendem dele (parent_id) e são resolvidas
 * com ele.
 */
class DocumentoComentario extends Model
{
    protected $table = 'documento_comentarios';

    protected $fillable = [
        'documento_interno_id',
        'user_id',
        'parent_id',
        'trecho',
        'texto',
        'resolvido_em',
        'resolvido_por',
    ];

    protected $casts = [
        'resolvido_em' => 'datetime',
    ];

    public function documento()
    {
        return $this->belongsTo(DocumentoInterno::class, 'documento_interno_id');
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function respostas()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('id');
    }

    public function resolvidoPor()
    {
        return $this->belongsTo(User::class, 'resolvido_por');
    }
}
