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
        'responsavel_id',
        'parent_id',
        'is_area_expediente',
    ];

    protected $casts = [
        'is_area_expediente' => 'boolean',
    ];

    public function usuarios()
    {
        return $this->hasMany(User::class, 'departamento_id');
    }

    public function responsavel()
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }

    public function parent()
    {
        return $this->belongsTo(Departamento::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Departamento::class, 'parent_id');
    }

    public function chefe()
    {
        return $this->hasOne(User::class, 'departamento_id')
            ->whereHas('role', function ($q) {
                $q->where('name', 'chefe-departamento');
            });
    }

    public function getChefeAttribute()
    {
        if ($this->relationLoaded('responsavel') && $this->responsavel_id) {
            return $this->responsavel;
        }
        if ($this->responsavel_id) {
            return $this->responsavel;
        }

        return $this->getRelationValue('chefe');
    }

    public function gabinete()
    {
        return $this->belongsTo(Gabinete::class, 'gabinete_id');
    }

    public function documentosEntrada()
    {
        return $this->hasMany(DocumentoEntrada::class, 'departamento_id');
    }

    public function documentosInternos()
    {
        return $this->hasMany(DocumentoInterno::class, 'departamento_id');
    }
}
