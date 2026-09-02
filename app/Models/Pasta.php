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
        'parent_id',
        'is_system',
        'type',
        'path',
    ];

    protected $casts = [
        'is_system' => 'boolean',
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

    public function documentosInternos()
    {
        return $this->hasMany(DocumentoInterno::class);
    }

    public function parent()
    {
        return $this->belongsTo(Pasta::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Pasta::class, 'parent_id')->orderBy('nome');
    }

    public function metadata()
    {
        return $this->morphMany(FileMetadata::class, 'metadatable');
    }

    public function getFullPathAttribute()
    {
        if ($this->parent) {
            return $this->parent->full_path.' > '.$this->nome;
        }

        return $this->nome;
    }

    /**
     * Scope to filter folders accessible by the user with strict RBAC rules.
     */
    public function scopeAccessibleBy($query, User $user)
    {
        // 1. Admin tem acesso irrestrito
        if ($user->isAdmin() || $user->hasRole('admin')) {
            return $query;
        }

        $permissionService = app(\App\Services\DocumentoPermissionService::class);

        // 2. Super Chefe de Gabinete
        if (method_exists($user, 'isSuperChefeGabinete') && $user->isSuperChefeGabinete() && $user->gabineteSuperGerenciado) {
            $gabId = $user->gabineteSuperGerenciado->id;

            return $query->where(function ($q) use ($gabId, $user) {
                $q->where('gabinete_id', $gabId)
                    ->orWhere('type', 'public')
                    ->orWhere('departamento_id', $user->departamento_id);
            });
        }

        // 3. Chefe de Gabinete (Visualiza pastas de todo o Gabinete)
        if (method_exists($user, 'isChefeGabinete') && $user->isChefeGabinete()) {
            $gabId = optional($user->gabineteGerenciado)->id;
            if ($gabId) {
                return $query->where(function ($q) use ($gabId, $user) {
                    $q->where('gabinete_id', $gabId)
                        ->orWhere('type', 'public')
                        ->orWhere('departamento_id', $user->departamento_id);
                });
            }
        }

        // 4. Área de Expediente (Protocolo)
        if ($permissionService->isUserInAreaExpediente($user)) {
            return $query->where(function ($q) use ($user) {
                $q->where('departamento_id', $user->departamento_id)
                    ->orWhere('type', 'public')
                    ->orWhere('is_system', true);
            });
        }

        // 5. Técnico de Departamento / Chefe de Departamento (Estritamente restrito ao seu departamento)
        $userDeps = $permissionService->getUserDepartments($user);

        return $query->where(function ($q) use ($user, $userDeps) {
            if (count($userDeps)) {
                $q->whereIn('departamento_id', $userDeps);
            } else {
                $q->where('departamento_id', $user->departamento_id);
            }
            $q->orWhere('type', 'public')
                ->orWhereHas('metadata', function ($subQ) use ($userDeps, $user) {
                    $subQ->where('key', 'shared_departments');
                    if (count($userDeps)) {
                        $subQ->where(function ($sq) use ($userDeps) {
                            foreach ($userDeps as $dId) {
                                $sq->orWhere('value', 'like', "%\"{$dId}\"%");
                            }
                        });
                    } else {
                        $subQ->where('value', 'like', "%\"{$user->departamento_id}\"%");
                    }
                });
        });
    }
}
