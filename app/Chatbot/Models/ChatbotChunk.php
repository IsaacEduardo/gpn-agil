<?php

namespace App\Chatbot\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ChatbotChunk extends Model
{
    protected $table = 'chatbot_chunks';

    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'tipo',
        'anexo_id',
        'pagina',
        'indice',
        'conteudo',
        'conteudo_hash',
        'departamento_id',
        'gabinete_id',
        'embedding',
        'modelo_embedding',
    ];

    protected $casts = [
        'embedding' => 'array',
        'pagina' => 'integer',
        'indice' => 'integer',
        'anexo_id' => 'integer',
        'departamento_id' => 'integer',
        'gabinete_id' => 'integer',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }
}
