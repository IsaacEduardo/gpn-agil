<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Departamento extends Model
{
    use HasFactory;

    protected $table = 'departamentos';

    protected $fillable = [
        'nome',
        'sigla',
        'gabinete_id',
    ];

    public function usuarios()
    {
        return $this->hasMany(User::class, 'departamento_id');
    }

    public function chefe()
    {
        return $this->hasOne(User::class, 'departamento_id')
            ->whereHas('role', function ($q) {
                $q->where('name', 'chefe-departamento');
            });
    }

    public function gabinete()
    {
        return $this->belongsTo(Gabinete::class, 'gabinete_id');
    }
}
