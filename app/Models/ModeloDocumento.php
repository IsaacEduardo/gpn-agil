<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModeloDocumento extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'documento_especie_id',
        'conteudo',
        'campos_dinamicos',
        'gabinete_id',
        'user_id',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'campos_dinamicos' => 'array',
    ];

    public function especie()
    {
        return $this->belongsTo(DocumentoEspecie::class, 'documento_especie_id');
    }

    public function gabinete()
    {
        return $this->belongsTo(Gabinete::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
