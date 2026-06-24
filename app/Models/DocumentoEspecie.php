<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentoEspecie extends Model
{
    use HasFactory;

    protected $table = 'documento_especies';

    protected $fillable = [
        'nome',
        'ativo',
        'ordem',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'ordem' => 'integer',
    ];

    public function retentionSchedule()
    {
        return $this->hasOne(RetentionSchedule::class, 'documento_especie_id');
    }
}
