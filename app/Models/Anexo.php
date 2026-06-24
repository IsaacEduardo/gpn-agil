<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Anexo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'anexos';

    protected $fillable = [
        'anexavel_type',
        'anexavel_id',
        'nome_original',
        'caminho_arquivo',
        'mime_type',
        'tamanho_bytes',
        'descricao',
        'ordem',
        'user_id',
        'texto_extraido',
    ];

    protected $casts = [
        'tamanho_bytes' => 'integer',
        'ordem' => 'integer',
    ];

    public function anexavel()
    {
        return $this->morphTo();
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getUrlAttribute(): ?string
    {
        return $this->caminho_arquivo ? Storage::disk('public')->url($this->caminho_arquivo) : null;
    }

    public function isImage(): bool
    {
        return $this->mime_type ? str_starts_with($this->mime_type, 'image/') : false;
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }
}
