@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Gestão de Usuários</h3>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Novo Usuário</a>
    </div>

    <form method="GET" class="card mb-3">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-4">
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Pesquisar por nome ou email">
                </div>
                <div class="col-md-3">
                    <select name="departamento_id" class="form-select">
                        <option value="">Todos os Departamentos</option>
                        @foreach($departamentos as $dep)
                            <option value="{{ $dep->id }}" {{ request('departamento_id') == $dep->id ? 'selected' : '' }}>{{ $dep->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="gabinete_id" class="form-select">
                        <option value="">Todos os Gabinetes</option>
                        @foreach($gabinetes as $gab)
                            <option value="{{ $gab->id }}" {{ request('gabinete_id') == $gab->id ? 'selected' : '' }}>{{ $gab->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="role_id" class="form-select">
                        <option value="">Todos os Papéis</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" {{ request('role_id') == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="per_page" class="form-select">
                        @php($pp = (int) request('per_page', $users->perPage()))
                        @foreach([10,15,25,50] as $opt)
                            <option value="{{ $opt }}" {{ $pp == $opt ? 'selected' : '' }}>Por página: {{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" type="submit">Filtrar</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0" id="usersTable">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Papel</th>
                        <th>Departamentos</th>
                        <th>Gabinete</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody id="usersTbody">
                    @forelse($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ optional($user->role)->name ?? '—' }}</td>
                            <td>
                                @php($deps = $user->departamentos ?? collect())
                                @if($deps->count() > 0)
                                    {{ $deps->pluck('nome')->implode(', ') }}
                                @else
                                    {{ optional($user->departamento)->nome ?? '—' }}
                                @endif
                            </td>
                            <td>
                                @php(
                                    $depList = ($user->departamentos && $user->departamentos->count() > 0)
                                        ? $user->departamentos
                                        : collect([$user->departamento])->filter()
                                )
                                @php($gabs = $depList->map(fn($d) => optional($d->gabinete)->nome)->filter()->unique()->values())
                                @if($gabs->count() > 0)
                                    {{ $gabs->implode(', ') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary">Editar</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted">Nenhum usuário encontrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <div class="d-flex align-items-center justify-content-between">
                <div id="usersStatus" class="small text-muted"></div>
                <div id="usersPagination" class="btn-group"></div>
            </div>
            @php($from = ($users->currentPage() - 1) * $users->perPage() + 1)
            @php($to = min($users->currentPage() * $users->perPage(), $users->total()))
            <noscript>
                <div class="d-flex align-items-center justify-content-between mt-2">
                    <div class="small text-muted">Mostrando {{ $from }}–{{ $to }} de {{ $users->total() }}</div>
                    {{ $users->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            </noscript>
        </div>
    </div>
</div>
@endsection
<script>
    (function(){
        const form = document.querySelector('form');
        const tbody = document.getElementById('usersTbody');
        const status = document.getElementById('usersStatus');
        const pagination = document.getElementById('usersPagination');
        let page = 1; let timer = null;

        function qs(){
            const params = new URLSearchParams(new FormData(form));
            params.set('page', page);
            return params.toString();
        }
        function setLoading(on){ status.textContent = on ? 'Carregando…' : ''; }
        function render(items){
            tbody.innerHTML = '';
            if(!items || items.length === 0){
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Nenhum usuário encontrado.</td></tr>';
                return;
            }
            items.forEach(function(u){
                const deps = (u.departamentos||[]).join(', ');
                const gabs = (u.gabinetes||[]).join(', ');
                const role = u.role || '—';
                const html = `<tr>
                    <td>${u.name}</td>
                    <td>${u.email}</td>
                    <td>${role}</td>
                    <td>${deps || '—'}</td>
                    <td>${gabs || '—'}</td>
                    <td class="text-end"><a href="${makeEditUrl(u.id)}" class="btn btn-sm btn-outline-secondary">Editar</a></td>
                </tr>`;
                tbody.insertAdjacentHTML('beforeend', html);
            });
        }
        function makeEditUrl(id){
            const tpl = "{{ route('admin.users.edit', ':id') }}";
            return tpl.replace(':id', id);
        }
        function renderPagination(p){
            pagination.innerHTML = '';
            if(!p || p.last_page <= 1){ return; }
            const prev = document.createElement('button');
            prev.className = 'btn btn-outline-secondary'; prev.textContent = 'Anterior'; prev.disabled = p.current_page <= 1;
            prev.onclick = function(){ if(p.current_page > 1){ page = p.current_page - 1; fetchUsers(); } };
            const next = document.createElement('button');
            next.className = 'btn btn-outline-secondary'; next.textContent = 'Próxima'; next.disabled = p.current_page >= p.last_page;
            next.onclick = function(){ if(p.current_page < p.last_page){ page = p.current_page + 1; fetchUsers(); } };
            pagination.appendChild(prev);
            const info = document.createElement('span'); info.className = 'btn disabled'; info.textContent = `Página ${p.current_page} de ${p.last_page}`;
            pagination.appendChild(info);
            pagination.appendChild(next);
            status.textContent = `Total: ${p.total} · Por página: ${p.per_page}`;
        }
        async function fetchUsers(){
            setLoading(true);
            try{
                const res = await fetch(`{{ route('admin.users.index') }}?${qs()}`, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                const json = await res.json();
                render(json.items || []);
                renderPagination(json.pagination || null);
            }catch(e){ /* ignore */ }
            setLoading(false);
        }
        function debounceFetch(){ clearTimeout(timer); timer = setTimeout(function(){ page = 1; fetchUsers(); }, 300); }

        form.addEventListener('submit', function(e){ e.preventDefault(); page = 1; fetchUsers(); });
        form.querySelectorAll('input[name="q"]').forEach(function(inp){ inp.addEventListener('input', debounceFetch); });
        form.querySelectorAll('select').forEach(function(sel){ sel.addEventListener('change', function(){ page = 1; fetchUsers(); }); });

        // inicializar com os dados da página renderizada
        renderPagination({ current_page: {{ (int)($users->currentPage() ?? 1) }}, last_page: {{ (int)($users->lastPage() ?? 1) }}, total: {{ (int)($users->total() ?? 0) }}, per_page: {{ (int)($users->perPage() ?? 15) }} });
    })();
</script>
