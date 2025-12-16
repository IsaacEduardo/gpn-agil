<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gabinete extends Model
{
    use HasFactory;

    protected $table = 'gabinetes';

    protected $fillable = [
        'nome',
        'sigla',
        'responsavel_id',
    ];

    public function responsavel()
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }

    public function departamentos()
    {
        return $this->hasMany(Departamento::class, 'gabinete_id');
    }
}
