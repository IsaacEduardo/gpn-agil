<?php

namespace App\Chatbot\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotMessage extends Model
{
    protected $table = 'chatbot_messages';

    protected $fillable = [
        'conversation_id',
        'role',
        'conteudo',
        'citacoes',
        'tokens_prompt',
        'tokens_completion',
    ];

    protected $casts = [
        'citacoes' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatbotConversation::class, 'conversation_id');
    }
}
