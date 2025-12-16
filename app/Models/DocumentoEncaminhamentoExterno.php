<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentoEncaminhamentoExterno extends Model
{
    use HasFactory;

    protected $table = 'documento_encaminhamentos_externos';

    protected $fillable = [
        'documento_entrada_id',
        'origem_gabinete_id',
        'destino_gabinete_id',
        'usuario_id',
        'oficio_numero',
        'enviado_em',
        'observacao',
        'status',
    ];

    protected $casts = [
        'enviado_em' => 'datetime',
    ];

    public function documento()
    {
        return $this->belongsTo(DocumentoEntrada::class, 'documento_entrada_id');
    }

    public function origemGabinete()
    {
        return $this->belongsTo(Gabinete::class, 'origem_gabinete_id');
    }

    public function destinoGabinete()
    {
        return $this->belongsTo(Gabinete::class, 'destino_gabinete_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
