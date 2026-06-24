<?php

namespace App\Http\Controllers;

use App\Models\DocumentoTarefa;
use App\Services\DocumentoPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TarefaController extends Controller
{
    protected $permissionService;

    public function __construct(DocumentoPermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $deps = $this->permissionService->getUserDepartments($user);

        $query = DocumentoTarefa::with(['documento', 'assignedBy', 'assignedToUser', 'assignedToDepartamento'])
            ->where(function ($q) use ($user, $deps) {
                $q->where('assigned_to_user_id', $user->id);

                if (count($deps)) {
                    $q->orWhereIn('assigned_to_departamento_id', $deps);
                }
            });

        // Filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('titulo', 'like', "%{$search}%")
                    ->orWhere('descricao', 'like', "%{$search}%")
                    ->orWhereHas('documento', function ($dq) use ($search) {
                        $dq->where('assunto', 'like', "%{$search}%")
                            ->orWhere('numero_sequencial', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('status')) {
            $status = $request->input('status');
            if ($status === 'pendente') {
                $query->where('status', 'pendente');
            } elseif ($status === 'concluido') {
                $query->where('status', 'concluida');
            }
        } else {
            // Default: Show pending first, then by date
            $query->orderByRaw("CASE WHEN status = 'pendente' THEN 1 ELSE 2 END");
        }

        // Dynamic Sorting
        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');

        // Allow list of sortable columns
        if (in_array($sort, ['prazo_at', 'created_at', 'titulo'])) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy('prazo_at', 'asc')->orderBy('created_at', 'desc');
        }

        // If Kanban view, we might need more items or grouped items, but for now standard pagination
        // If 'view' param is 'kanban', maybe increase pagination limit?
        $limit = $request->input('view') === 'kanban' ? 50 : 15;

        $tarefas = $query->paginate($limit)->appends($request->query());

        return view('tarefas.index', compact('tarefas'));
    }
}
