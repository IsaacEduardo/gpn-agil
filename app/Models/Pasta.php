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
     * Scope to filter folders accessible by the user.
     */
    public function scopeAccessibleBy($query, User $user)
    {
        // Admin sees all
        if ($user->hasRole('admin') || $user->hasRole('Admin')) {
            return $query;
        }

        // Super Chefe de Gabinete sees all folders in their cabinet
        if (method_exists($user, 'isSuperChefeGabinete') && $user->isSuperChefeGabinete()) {
            $gabinete = $user->gabineteSuperGerenciado;
            if ($gabinete) {
                return $query->where('gabinete_id', $gabinete->id);
            }
        }

        // Chefe de Gabinete sees all folders in their cabinet (across all departments)
        // assuming User model has isChefeGabinete()
        if (method_exists($user, 'isChefeGabinete') && $user->isChefeGabinete()) {
            $gabinete = $user->gabineteGerenciado;
            if ($gabinete) {
                return $query->where('gabinete_id', $gabinete->id);
            }
        }

        // Standard user sees their department's folders + Public folders + Shared folders
        return $query->where(function ($q) use ($user) {
            $q->where('departamento_id', $user->departamento_id)
                ->orWhere('type', 'public')
                ->orWhereHas('metadata', function ($subQ) use ($user) {
                    $subQ->where('key', 'shared_departments')
                        ->where('value', 'like', "%\"{$user->departamento_id}\"%");
                });
        });
    }
}
