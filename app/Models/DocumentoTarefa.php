<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentoTarefa extends Model
{
    use HasFactory;

    protected $table = 'documento_tarefas';

    protected $fillable = [
        'documento_entrada_id',
        'titulo',
        'descricao',
        'assigned_by_id',
        'assigned_to_user_id',
        'assigned_to_departamento_id',
        'responsavel_user_id',
        'prazo_at',
        'status',
        'grupo_tarefa_uuid',
    ];

    protected $casts = [
        'prazo_at' => 'datetime',
    ];

    public function documento()
    {
        return $this->belongsTo(DocumentoEntrada::class, 'documento_entrada_id');
    }

    public function documentoEntrada()
    {
        return $this->belongsTo(DocumentoEntrada::class, 'documento_entrada_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    public function assignedToUser()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assignedToDepartamento()
    {
        return $this->belongsTo(Departamento::class, 'assigned_to_departamento_id');
    }

    public function responsavelAtual()
    {
        return $this->belongsTo(User::class, 'responsavel_user_id');
    }
}
