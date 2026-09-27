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
        'codigo_oficios',
        'responsavel_id',
        'super_chefe_id',
        'sla_despacho_dias',
    ];

    /**
     * Código de ofícios (ex.: SEC.GOV.PROV.HLA): maiúsculas, sem espaços nem pontos nas pontas.
     */
    public function setCodigoOficiosAttribute($value): void
    {
        $codigo = mb_strtoupper(trim((string) $value, " .\t\n\r\0\x0B"));

        $this->attributes['codigo_oficios'] = $codigo !== '' ? $codigo : null;
    }

    public function responsavel()
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }

    public function superChefe()
    {
        return $this->belongsTo(User::class, 'super_chefe_id');
    }

    public function departamentos()
    {
        return $this->hasMany(Departamento::class, 'gabinete_id');
    }
}
