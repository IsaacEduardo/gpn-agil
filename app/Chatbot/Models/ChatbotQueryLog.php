<?php

namespace App\Chatbot\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotQueryLog extends Model
{
    protected $table = 'chatbot_query_logs';

    protected $fillable = [
        'user_id',
        'conversation_id',
        'pergunta',
        'escopo',
        'documentable_type',
        'documentable_id',
        'modelo_llm',
        'modelo_embedding',
        'latencia_ms',
        'chunks_usados',
        'ip_address',
        'user_agent',
    ];
}
