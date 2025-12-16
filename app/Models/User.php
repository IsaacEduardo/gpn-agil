<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'departamento_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Obter o papel (role) do usuário
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Obter as requisições criadas pelo usuário
     */
    public function requisicoes()
    {
        return $this->hasMany(Requisicao::class, 'usuario_id');
    }

    /**
     * Obter as requisições aprovadas pelo usuário
     */
    public function requisicoesAprovadas()
    {
        return $this->hasMany(Requisicao::class, 'aprovado_por');
    }

    /**
     * Relação com Departamento
     */
    public function departamento()
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }

    /**
     * Relação muitos-para-muitos com Departamentos
     */
    public function departamentos()
    {
        return $this->belongsToMany(Departamento::class, 'departamento_user', 'user_id', 'departamento_id');
    }

    /**
     * Obter os feedbacks enviados pelo usuário
     */
    public function feedbacks()
    {
        return $this->hasMany(Feedback::class);
    }

    /**
     * Verificar se o usuário tem uma permissão específica
     */
    public function hasPermission($permissionName)
    {
        $role = $this->role;
        if (! $role) {
            return false;
        }
        $perms = method_exists($role, 'permissions') ? $role->permissions : null;
        if (! $perms) {
            return false;
        }

        return $perms->contains('name', $permissionName);
    }
}
