<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable {
        hasPermissionTo as spatieHasPermissionTo;
    }

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
        'delegado_id',
        'delegacao_inicio',
        'delegacao_fim',
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
            'delegacao_inicio' => 'datetime',
            'delegacao_fim' => 'datetime',
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
     * Departamento principal do utilizador: o vínculo único (departamento_id),
     * com fallback para o primeiro departamento da relação múltipla.
     */
    public function departamentoPrincipal(): ?Departamento
    {
        return $this->departamento ?? $this->departamentos()->first();
    }

    /**
     * Gabinete a que o utilizador pertence, derivado do seu departamento
     * principal. Não existe vínculo direto users→gabinetes: a relação é
     * sempre indireta (User → Departamento → Gabinete).
     */
    public function gabinete(): ?Gabinete
    {
        return $this->departamentoPrincipal()?->gabinete;
    }

    /**
     * Relação com o certificado digital do usuário
     */
    public function certificate()
    {
        return $this->hasOne(UserCertificate::class);
    }

    /**
     * Obter os feedbacks enviados pelo usuário
     */
    public function feedbacks()
    {
        return $this->hasMany(Feedback::class);
    }

    /**
    /**
     * Verificar se o usuário tem uma permissão específica
     * Mantido para compatibilidade, mas usando Spatie agora.
     */
    public function hasPermission($permissionName)
    {
        return $this->hasPermissionTo($permissionName);
    }

    /**
     * Relação com o utilizador delegado
     */
    public function delegado()
    {
        return $this->belongsTo(User::class, 'delegado_id');
    }

    /**
     * Relação inversa: utilizadores que delegaram poderes a este utilizador
     */
    public function delegadores()
    {
        return $this->hasMany(User::class, 'delegado_id');
    }

    /**
     * Verifica se o utilizador possui delegação de funções ativa no momento
     */
    public function isDelegadoAtivo(): bool
    {
        if (! $this->delegado_id || ! $this->delegacao_inicio || ! $this->delegacao_fim) {
            return false;
        }

        return now()->between($this->delegacao_inicio, $this->delegacao_fim);
    }

    /**
     * Ponte de migração para Spatie: mantém o papel Spatie sincronizado com o
     * legado role_id na criação e sempre que role_id muda. Escritas Spatie
     * diretas (assignRole/syncRoles) não são afetadas.
     */
    protected static function booted(): void
    {
        static::saved(function (self $user) {
            $roleChanged = $user->wasRecentlyCreated
                ? (bool) $user->role_id
                : $user->wasChanged('role_id');

            if (! $roleChanged) {
                return;
            }

            $newName = $user->role_id ? Role::whereKey($user->role_id)->value('name') : null;

            if (! $user->wasRecentlyCreated) {
                $oldId = $user->getOriginal('role_id');
                $oldName = $oldId ? Role::whereKey($oldId)->value('name') : null;
                if ($oldName && $oldName !== $newName && $user->hasRole($oldName)) {
                    $user->removeRole($oldName);
                }
            }

            if ($newName) {
                $spatieRole = \Spatie\Permission\Models\Role::findOrCreate($newName, 'web');
                if (! $user->hasRole($spatieRole)) {
                    $user->assignRole($spatieRole);
                }
            }
        });
    }

    /**
     * Sobrescreve hasPermissionTo para incluir herança temporária de delegação.
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        // 1. Permissões diretas ou por papéis do próprio utilizador
        if ($this->hasDirectOrRolePermissionTo($permission, $guardName)) {
            return true;
        }

        // 2. Delegação ativa (herança temporária de poderes de chefia)
        $delegadoresAtivos = $this->delegadores()
            ->where('delegacao_inicio', '<=', now())
            ->where('delegacao_fim', '>=', now())
            ->get();

        foreach ($delegadoresAtivos as $delegador) {
            if ($delegador->hasDirectOrRolePermissionTo($permission, $guardName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verificação base de permissões (Spatie puro). O papel 'admin' passa em
     * todas as verificações, preservando a semântica do sistema anterior.
     */
    public function hasDirectOrRolePermissionTo($permission, $guardName = null): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        try {
            return $this->spatieHasPermissionTo($permission, $guardName);
        } catch (PermissionDoesNotExist $e) {
            return false;
        }
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Documentos internos favoritos do usuário
     */
    public function documentosFavoritos()
    {
        return $this->belongsToMany(DocumentoInterno::class, 'documento_interno_favoritos', 'user_id', 'documento_interno_id')
            ->withTimestamps();
    }

    /**
     * Verifica se o usuário é Chefe de Gabinete (responsável por algum gabinete)
     */
    public function isChefeGabinete(): bool
    {
        return $this->gabineteGerenciado()->exists();
    }

    /**
     * Retorna o gabinete gerenciado pelo usuário (se houver)
     */
    public function gabineteGerenciado()
    {
        return $this->hasOne(Gabinete::class, 'responsavel_id');
    }

    /**
     * Verifica se o usuário é Super Chefe de Gabinete
     */
    public function isSuperChefeGabinete(): bool
    {
        return $this->gabineteSuperGerenciado()->exists();
    }

    /**
     * Retorna o gabinete super gerenciado pelo usuário (se houver)
     */
    public function gabineteSuperGerenciado()
    {
        return $this->hasOne(Gabinete::class, 'super_chefe_id');
    }

    /**
     * Verifica de forma contextual se o usuário é Super Chefe de um gabinete específico
     */
    public function isSuperChefeDoGabinete($gabinete): bool
    {
        $gabineteId = $gabinete instanceof Gabinete ? $gabinete->id : (int) $gabinete;
        $superGabinete = $this->gabineteSuperGerenciado;

        return $superGabinete && (int) $superGabinete->id === $gabineteId;
    }
}
