<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentoVersao extends Model
{
    use HasFactory;

    protected $table = 'documento_versaos';

    protected $fillable = [
        'documento_interno_id',
        'versao',
        'major',
        'minor',
        'patch',
        'change_log',
        'titulo',
        'conteudo_final',
        'criado_por',
        // EDMS Fields
        'caminho_arquivo',
        'checksum',
        'tamanho_bytes',
        'mime_type',
        'is_signed',
        'assinatura_hash',
    ];

    public function getVersaoSemanticaAttribute(): string
    {
        return "{$this->major}.{$this->minor}.{$this->patch}";
    }

    public function documento()
    {
        return $this->belongsTo(DocumentoInterno::class, 'documento_interno_id');
    }

    public function documentoInterno()
    {
        return $this->belongsTo(DocumentoInterno::class, 'documento_interno_id');
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'criado_por');
    }
}
