<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentoProtocolo extends Model
{
    use HasFactory;

    protected $table = 'documento_protocolos';

    protected $fillable = [
        'documento_entrada_id',
        'codigo',
        'url_consulta',
        'gerado_em',
        'impresso_em',
    ];

    protected $casts = [
        'gerado_em' => 'datetime',
        'impresso_em' => 'datetime',
    ];

    public function documentoEntrada()
    {
        return $this->belongsTo(DocumentoEntrada::class, 'documento_entrada_id');
    }
}
