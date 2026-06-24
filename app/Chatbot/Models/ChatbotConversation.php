<?php

namespace App\Chatbot\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ChatbotConversation extends Model
{
    protected $table = 'chatbot_conversations';

    protected $fillable = [
        'user_id',
        'escopo',
        'documentable_type',
        'documentable_id',
        'titulo',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatbotMessage::class, 'conversation_id')->orderBy('id');
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }
}
