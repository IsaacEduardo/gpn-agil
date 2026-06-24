<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
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
     * Sobrescreve hasPermissionTo para incluir herança temporária de delegação e compatibilidade híbrida
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        // 1. Tenta verificar permissões diretas ou por cargos do próprio utilizador
        if ($this->hasDirectOrRolePermissionTo($permission, $guardName)) {
            return true;
        }

        // 2. Tenta verificar via delegação ativa (herança de poderes de chefia)
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
     * Helper interno para verificação base de permissões (Spatie + legado)
     */
    public function hasDirectOrRolePermissionTo($permission, $guardName = null): bool
    {
        // 1. Tenta verificar via Spatie
        try {
            if ($this->spatieHasPermissionTo($permission, $guardName)) {
                return true;
            }
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            // Permissão não existe no Spatie, continua para a checagem legada
        }

        // 2. Fallback para checagem legada (compatibilidade de testes)
        $role = $this->role;
        if ($role) {
            if ($role->name === 'admin') {
                return true;
            }
            if (method_exists($role, 'permissions')) {
                $permissionName = is_string($permission) ? $permission : ($permission->name ?? null);
                if ($permissionName) {
                    // Mapeamento de compatibilidade de nomes antigos/novos
                    $aliases = [
                        'reservas.aprovar' => ['aprovar_reservas'],
                        'requisicoes.aprovar' => ['aprovar_requisicoes'],
                        'requisicoes.listar_todas' => ['requisicoes.view_any'],
                        'requisicoes.listar' => ['requisicoes.view'],
                        'reservas.listar' => ['reservas.view', 'reservas.view_any'],
                        'documentos_entrada.encaminhar' => ['encaminhar_documentos_entrada'],
                        'documentos_entrada.listar' => ['listar_documentos_entrada'],
                    ];

                    $namesToCheck = [$permissionName];
                    if (isset($aliases[$permissionName])) {
                        $namesToCheck = array_merge($namesToCheck, $aliases[$permissionName]);
                    }

                    $perms = $role->permissions;
                    if ($perms) {
                        foreach ($namesToCheck as $name) {
                            if ($perms->contains('name', $name)) {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
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

        return $superGabinete && (int)$superGabinete->id === $gabineteId;
    }
}
