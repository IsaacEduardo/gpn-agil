<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registo durável de um update Yjs (CRDT) de um Documento Interno.
 * Apenas created_at é relevante (append-only log).
 */
class DocumentoCollabUpdate extends Model
{
    protected $table = 'documento_collab_updates';

    public $timestamps = false;

    protected $fillable = [
        'documento_interno_id',
        'user_id',
        'update',
        'is_snapshot',
        'created_at',
    ];

    protected $casts = [
        'is_snapshot' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function documento()
    {
        return $this->belongsTo(DocumentoInterno::class, 'documento_interno_id');
    }
}
