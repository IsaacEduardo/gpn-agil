<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoEncaminhamento extends Model
{
    use HasFactory;

    protected $table = 'documento_encaminhamentos';

    protected $fillable = [
        'documento_entrada_id',
        'origem_departamento_id',
        'destino_departamento_id',
        'usuario_id',
        'recebido_por_id',
        'encaminhado_em',
        'recebido_em',
        'observacao',
        'status',
    ];

    protected $casts = [
        'encaminhado_em' => 'datetime',
        'recebido_em' => 'datetime',
    ];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoEntrada::class, 'documento_entrada_id');
    }

    public function origemDepartamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'origem_departamento_id');
    }

    public function destinoDepartamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'destino_departamento_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function recebidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recebido_por_id');
    }
}
