<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ViaturaFoto extends Model
{
    use HasFactory;

    protected $fillable = ['viatura_id', 'caminho_arquivo', 'tipo'];

    public function viatura()
    {
        return $this->belongsTo(Viatura::class);
    }
}
