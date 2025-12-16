<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['nome', 'slug'];

    public function documentosEntradas()
    {
        return $this->belongsToMany(DocumentoEntrada::class, 'documento_entrada_tag');
    }
}
