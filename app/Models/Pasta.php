<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pasta extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nome',
        'descricao',
        'departamento_id',
        'gabinete_id',
        'created_by',
    ];

    public function departamento()
    {
        return $this->belongsTo(Departamento::class);
    }

    public function gabinete()
    {
        return $this->belongsTo(Gabinete::class);
    }

    public function criador()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documentos()
    {
        return $this->hasMany(DocumentoEntrada::class);
    }
}
