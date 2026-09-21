<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use App\Models\Gabinete;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DepartamentoController extends Controller
{
    /**
     * Gestão da estrutura orgânica é ato de administração, não de expediente.
     *
     * Estas rotas estavam sem qualquer verificação: qualquer sessão autenticada
     * — incluindo um técnico — listava, criava, editava e apagava departamentos
     * de qualquer gabinete da instituição. A permissão 'departamentos.gerir' já
     * existia na matriz de acesso mas nunca era consultada.
     *
     * Quem pode gerir:
     *  - admin, ou quem tenha a permissão 'departamentos.gerir' — sem restrição;
     *  - o responsável (ou super chefe) de um gabinete — apenas dentro dele.
     *
     * $departamento, quando dado, é ainda verificado contra o gabinete do gestor
     * restrito, para que a posse do registo seja validada e não só o papel.
     */
    protected function autorizarGestao(?Departamento $departamento = null): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(403, 'Acesso restrito.');
        }

        if ($this->gestorSemRestricao($user)) {
            return;
        }

        $gabinete = $this->gabineteDoGestor($user);

        if (! $gabinete) {
            abort(403, 'Acesso restrito à gestão da estrutura orgânica.');
        }

        if ($departamento && (int) $departamento->gabinete_id !== (int) $gabinete->id) {
            abort(403, 'Este departamento pertence a outro gabinete.');
        }
    }

    /**
     * Gestores sem restrição de gabinete: o administrador e quem detenha
     * explicitamente 'departamentos.gerir'.
     */
    protected function gestorSemRestricao(User $user): bool
    {
        return $user->podeGerirDepartamentosSemRestricao();
    }

    /**
     * Gabinete dentro do qual um chefe de gabinete pode gerir departamentos,
     * ou null se o utilizador não chefia nenhum.
     */
    protected function gabineteDoGestor(User $user): ?Gabinete
    {
        return $user->gabineteGerenciado ?? $user->gabineteSuperGerenciado;
    }

    public function index(Request $request)
    {
        $this->autorizarGestao();

        $perPage = (int) ($request->input('per_page', 15));
        if ($perPage < 5) {
            $perPage = 5;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $query = Departamento::with(['chefe', 'gabinete'])
            ->orderBy('nome');

        // O gestor restrito a um gabinete só vê os departamentos desse gabinete.
        $user = Auth::user();
        if (! $this->gestorSemRestricao($user)) {
            $query->where('gabinete_id', $this->gabineteDoGestor($user)->id);
        }

        $departamentos = $query->paginate($perPage)
            ->withQueryString();

        return view('departamentos.index', compact('departamentos'));
    }

    public function create()
    {
        $this->autorizarGestao();

        $user = Auth::user();
        $gabinetesQuery = Gabinete::orderBy('nome');

        // O gestor restrito só pode criar dentro do gabinete que chefia.
        if (! $this->gestorSemRestricao($user)) {
            $gabinetesQuery->whereKey($this->gabineteDoGestor($user)->id);
        }

        $gabinetes = $gabinetesQuery->get();

        return view('departamentos.create', compact('gabinetes'));
    }

    public function store(Request $request)
    {
        $this->autorizarGestao();

        $user = Auth::user();

        // O gabinete de destino é verificado contra o do gestor restrito: sem
        // isto, o formulário restrito seria contornável por POST direto.
        if (! $this->gestorSemRestricao($user)
            && (int) $request->input('gabinete_id') !== (int) $this->gabineteDoGestor($user)->id) {
            abort(403, 'Você só pode criar departamentos no seu próprio gabinete.');
        }

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255', 'unique:departamentos,nome'],
            'sigla' => ['nullable', 'string', 'max:10'],
            'gabinete_id' => ['required', 'integer', 'exists:gabinetes,id'],
            'is_area_expediente' => ['nullable', 'boolean'],
        ]);
        $data['is_area_expediente'] = $request->boolean('is_area_expediente');

        Departamento::create($data);

        return redirect()->route('departamentos.index')->with('success', 'Departamento criado com sucesso.');
    }

    public function edit(Departamento $departamento)
    {
        $this->autorizarGestao($departamento);

        $user = Auth::user();

        // Carregar usuários do departamento e o chefe atual, se existir
        $userRole = Role::where('name', 'user')->first();
        $chefeRole = Role::where('name', 'chefe-departamento')->first();
        $usuariosQuery = User::where('departamento_id', $departamento->id)
            ->with('role')
            ->orderBy('name');
        if ($userRole && $chefeRole) {
            $usuariosQuery->whereIn('role_id', [$userRole->id, $chefeRole->id]);
        } elseif ($userRole) {
            $usuariosQuery->where('role_id', $userRole->id);
        } elseif ($chefeRole) {
            $usuariosQuery->where('role_id', $chefeRole->id);
        }
        $usuarios = $usuariosQuery->get();

        $chefeAtual = null;
        if ($chefeRole) {
            $chefeAtual = User::where('departamento_id', $departamento->id)
                ->where('role_id', $chefeRole->id)
                ->first();
        }

        $gabinetesQuery = Gabinete::orderBy('nome');

        // O gestor restrito só vê o seu próprio gabinete na lista.
        if (! $this->gestorSemRestricao($user)) {
            $gabinetesQuery->whereKey($this->gabineteDoGestor($user)->id);
        }

        $gabinetes = $gabinetesQuery->get();

        // Carregar todas as permissões para a aba de gestão de acesso
        $permissions = Permission::all();

        // Tenta encontrar ou criar a Role associada a este departamento
        // Isso garante que "Olhar para o departamento" signifique "Olhar para a Role dele"
        $role = Role::firstOrCreate(['name' => $departamento->nome], ['guard_name' => 'web']);

        return view('departamentos.edit', compact('departamento', 'usuarios', 'chefeAtual', 'gabinetes', 'permissions', 'role'));
    }

    public function update(Request $request, Departamento $departamento)
    {
        $this->autorizarGestao($departamento);

        $user = Auth::user();

        // O gestor restrito não pode empurrar o departamento para fora do seu
        // gabinete — isso retirar-lho-ia do alcance e entregá-lo-ia a outro.
        if (! $this->gestorSemRestricao($user)
            && (int) $request->input('gabinete_id') !== (int) $this->gabineteDoGestor($user)->id) {
            abort(403, 'Você não pode mover o departamento para outro gabinete.');
        }

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255', Rule::unique('departamentos', 'nome')->ignore($departamento->id)],
            'sigla' => ['nullable', 'string', 'max:10'],
            'chefe_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'gabinete_id' => ['required', 'integer', 'exists:gabinetes,id'],
            'is_area_expediente' => ['nullable', 'boolean'],
        ]);
        $data['is_area_expediente'] = $request->boolean('is_area_expediente');

        $departamento->update($data);

        // Atualizar Role associada (Nome e Permissões)
        // Optimization: Don't query Role by name if name hasn't changed, or use id if possible.
        // Assuming role name matches department name logic.
        $roleName = $departamento->nome;
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

        if (! $role) {
            // Fallback: Create if not exists
            $role = Role::create(['name' => $roleName, 'guard_name' => 'web']);
        }

        // Sincronizar Permissões se enviadas
        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);

            // PROPAGAR: Atualizar todos os usuários deste departamento para terem esta Role também
            // Isso garante que eles herdem as permissões do departamento
            $usersDoDepartamento = User::where('departamento_id', $departamento->id)->get();
            foreach ($usersDoDepartamento as $u) {
                // Se o usuário tem papel 'user', atribuímos também o papel do departamento
                if ($u->hasRole('user')) {
                    $u->assignRole($role);
                }
            }
        }

        // Atribuir chefe de departamento, caso informado
        // Optimization: Check if chef has actually changed before running DB updates
        if ($request->filled('chefe_user_id')) {
            $novoChefeId = (int) $request->chefe_user_id;

            $chefeRole = Role::where('name', 'chefe-departamento')->where('guard_name', 'web')->first();
            $userRole = Role::where('name', 'user')->where('guard_name', 'web')->first();

            if ($chefeRole && $userRole) {
                // Find current chef(s) of this department
                $chefesAtuais = User::where('departamento_id', $departamento->id)
                    ->where('role_id', $chefeRole->id)
                    ->get();

                foreach ($chefesAtuais as $chefeAtual) {
                    if ($chefeAtual->id !== $novoChefeId) {
                        // Demote old chef
                        $chefeAtual->update(['role_id' => $userRole->id]);
                    }
                }

                // Promote new chef if not already chef
                $novoChefe = User::find($novoChefeId);
                if ($novoChefe && (int) $novoChefe->departamento_id === $departamento->id) {
                    if ($novoChefe->role_id !== $chefeRole->id) {
                        $novoChefe->update(['role_id' => $chefeRole->id]);
                    }
                }
            }
        } else {
            // Se nenhum chefe foi selecionado, remover qualquer chefe atual
            // Only update if they are currently chefs
            $chefeRole = Role::where('name', 'chefe-departamento')->where('guard_name', 'web')->first();
            $userRole = Role::where('name', 'user')->where('guard_name', 'web')->first();

            if ($chefeRole && $userRole) {
                User::where('departamento_id', $departamento->id)
                    ->where('role_id', $chefeRole->id)
                    ->update(['role_id' => $userRole->id]);
            }
        }

        return redirect()->route('departamentos.index')->with('success', 'Departamento atualizado com sucesso. Chefe de departamento ajustado, se aplicável.');
    }

    public function destroy(Departamento $departamento)
    {
        $this->autorizarGestao($departamento);

        if ($departamento->usuarios()->exists()) {
            return redirect()->route('departamentos.index')->with('error', 'Não é possível excluir: existem usuários associados.');
        }

        $departamento->delete();

        return redirect()->route('departamentos.index')->with('success', 'Departamento excluído com sucesso.');
    }
}
