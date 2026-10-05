@extends('layouts.app')

@section('title', 'Numeração')

@section('breadcrumbs')
    <div class="container py-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-decoration-none text-muted">Início</a></li>
                <li class="breadcrumb-item active text-primary fw-bold" aria-current="page">Numeração</li>
            </ol>
        </nav>
    </div>
@endsection

@section('content')
    <div class="container pb-5">

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1">Numeração de documentos</h2>
                <p class="text-muted mb-0">
                    Continue a numeração feita fora do sistema: indique o último número emitido em papel em cada série.
                    A 1 de Janeiro (hora de Luanda) cada série recomeça em 1.
                </p>
            </div>
            <ul class="nav nav-pills">
                @foreach ($anos as $a)
                    <li class="nav-item">
                        <a class="nav-link {{ $a === $ano ? 'active' : '' }}" href="{{ route('admin.numeracao.index', ['ano' => $a]) }}">{{ $a }}</a>
                    </li>
                @endforeach
            </ul>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->all() as $erro)
                    <div>{{ $erro }}</div>
                @endforeach
            </div>
        @endif

        {{-- Livros do gabinete: numerados pelo sistema só a partir de uma data --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold">Início da numeração dos livros do gabinete</h5>
                <small class="text-muted">
                    Ofício, Ordem de Serviço, Nota e Informação/Parecer. Antes desta data saem com o número em branco
                    (ex.: <code>NOTA _____/SEC.GOV.PROV.HLA.AEX/{{ $anos[0] }}</code>), para preencher à mão a partir do livro em papel.
                </small>
            </div>
            <div class="card-body">
                <p class="mb-3">
                    @if ($inicioGabinete && now('Africa/Luanda')->lt($inicioGabinete))
                        <span class="badge bg-warning text-dark">Numeração em papel</span>
                        O sistema numera estes documentos a partir de <strong>{{ $inicioGabinete->format('d/m/Y') }}</strong>.
                    @else
                        <span class="badge bg-success">Numeração automática</span>
                        {{ $inicioGabinete ? 'Activa desde '.$inicioGabinete->format('d/m/Y').'.' : 'O sistema numera estes documentos.' }}
                    @endif
                </p>
                <form method="POST" action="{{ route('admin.numeracao.inicio-gabinete') }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-3">
                        <label class="form-label small mb-0" for="numeracao_gabinete_desde">Numerar a partir de</label>
                        <input type="date" name="numeracao_gabinete_desde" id="numeracao_gabinete_desde" class="form-control form-control-sm"
                               value="{{ old('numeracao_gabinete_desde', $inicioGabinete?->toDateString()) }}">
                        <div class="form-text">Vazio = numerar desde já.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-0" for="motivo_inicio">Motivo</label>
                        <input type="text" name="motivo" id="motivo_inicio" class="form-control form-control-sm" minlength="5" maxlength="500" required
                               placeholder="Ex.: Numeração no sistema a partir de 1 de Janeiro">
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-sm btn-primary" type="submit">Gravar data</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Séries existentes --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold">Séries de {{ $ano }}</h5>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="small text-uppercase text-muted">
                            <th class="ps-4">Série</th>
                            <th>Último número</th>
                            <th>Maior emitido no sistema</th>
                            <th>Próximo documento</th>
                            <th class="text-end pe-4">Acções</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($series as $s)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold">{{ $s->rotulo }}</div>
                                    <code class="small text-muted">{{ $s->chave }}</code>
                                </td>
                                <td>{{ $s->ultimo_numero }}</td>
                                <td>{{ $s->maior_emitido }}</td>
                                <td><code>{{ $s->proxima }}</code></td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-outline-primary" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#editar-{{ $s->id }}">
                                        <i class="fas fa-pen me-1"></i> Definir último número
                                    </button>
                                </td>
                            </tr>
                            <tr class="collapse" id="editar-{{ $s->id }}">
                                <td colspan="5" class="bg-light px-4">
                                    <form method="POST" action="{{ route('admin.numeracao.definir') }}" class="row g-2 align-items-end form-numeracao">
                                        @csrf
                                        <input type="hidden" name="ano" value="{{ $ano }}">
                                        <input type="hidden" name="chave" value="{{ $s->chave }}">
                                        <div class="col-md-3">
                                            <label class="form-label small mb-0">Último número emitido</label>
                                            <input type="number" name="ultimo_numero" class="form-control form-control-sm campo-numero"
                                                   min="{{ $s->maior_emitido }}" value="{{ $s->ultimo_numero }}" required>
                                            <div class="form-text">Mínimo: {{ $s->maior_emitido }} (já emitido no sistema).</div>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label small mb-0">Motivo</label>
                                            <input type="text" name="motivo" class="form-control form-control-sm" minlength="5" maxlength="500" required
                                                   placeholder="Ex.: Último ofício em papel: nº 584, de 30/06">
                                        </div>
                                        <div class="col-md-4 d-flex align-items-center gap-2">
                                            <button class="btn btn-sm btn-primary" type="submit">Gravar</button>
                                            <span class="small text-muted previa-proxima"></span>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    Ainda não há séries para {{ $ano }}. Defina-as abaixo antes do primeiro documento.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Definir uma série (mesmo antes de existir qualquer documento) --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold">Definir série</h5>
                <small class="text-muted">A série é a mesma que os documentos vão usar (ex.: um ofício de um departamento da Secretaria Geral usa o livro único de ofícios).</small>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.numeracao.definir') }}" class="row g-3 align-items-end form-numeracao" id="form-nova-serie">
                    @csrf
                    <input type="hidden" name="ano" value="{{ $ano }}">
                    <div class="col-md-3">
                        <label class="form-label small mb-0" for="tipo">Série</label>
                        <select name="tipo" id="tipo" class="form-select form-select-sm">
                            <option value="internos" @selected(old('tipo', 'internos') === 'internos')>Documentos internos</option>
                            <option value="entradas" @selected(old('tipo') === 'entradas')>Livro de Documentos de Entrada</option>
                        </select>
                    </div>
                    <div class="col-md-4 so-internos">
                        <label class="form-label small mb-0" for="emissor">Emissor</label>
                        <select name="emissor" id="emissor" class="form-select form-select-sm">
                            <option value="">Selecione…</option>
                            @foreach ($gabinetes as $g)
                                <optgroup label="{{ $g->nome }}">
                                    <option value="gab:{{ $g->id }}" @selected(old('emissor') === "gab:{$g->id}")>{{ $g->nome }} (o próprio gabinete)</option>
                                    @foreach ($g->departamentos as $d)
                                        <option value="dep:{{ $d->id }}" @selected(old('emissor') === "dep:{$d->id}")>{{ $d->sigla ? $d->sigla.' — ' : '' }}{{ $d->nome }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 so-internos">
                        <label class="form-label small mb-0" for="documento_especie_id">Espécie</label>
                        <select name="documento_especie_id" id="documento_especie_id" class="form-select form-select-sm">
                            <option value="">Selecione…</option>
                            @foreach ($especies as $e)
                                <option value="{{ $e->id }}" @selected((string) old('documento_especie_id') === (string) $e->id)>{{ $e->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0" for="ultimo_numero_novo">Último número</label>
                        <input type="number" name="ultimo_numero" id="ultimo_numero_novo" min="0" class="form-control form-control-sm campo-numero"
                               value="{{ old('ultimo_numero', 0) }}" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small mb-0" for="motivo_novo">Motivo</label>
                        <input type="text" name="motivo" id="motivo_novo" class="form-control form-control-sm" minlength="5" maxlength="500" required
                               value="{{ old('motivo') }}" placeholder="Ex.: Último ofício em papel: nº 584, de 30/06">
                    </div>
                    <div class="col-md-4 d-flex align-items-center gap-2">
                        <button class="btn btn-sm btn-primary" type="submit">Definir</button>
                        <span class="small text-muted previa-proxima"></span>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // "O próximo documento será …" calculado pelo servidor (mesmo formato da numeração real).
        document.addEventListener('DOMContentLoaded', () => {
            const url = @json(route('admin.numeracao.previa'));

            document.querySelectorAll('.form-numeracao').forEach((form) => {
                const alvo = form.querySelector('.previa-proxima');
                let timer = null;

                const actualizar = () => {
                    const dados = new URLSearchParams(new FormData(form));
                    dados.delete('_token');
                    dados.delete('motivo');
                    fetch(url + '?' + dados.toString(), { headers: { Accept: 'application/json' } })
                        .then((r) => (r.ok ? r.json() : null))
                        .then((j) => {
                            alvo.textContent = j ? `Próximo: ${j.proxima}` + (j.minimo ? ` (mínimo ${j.minimo})` : '') : '';
                        })
                        .catch(() => { alvo.textContent = ''; });
                };

                form.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(actualizar, 300); });
                form.addEventListener('change', actualizar);
            });

            // Emissor e espécie só se aplicam aos documentos internos.
            const tipo = document.getElementById('tipo');
            const alternar = () => document.querySelectorAll('.so-internos').forEach((el) => {
                el.classList.toggle('d-none', tipo.value !== 'internos');
            });
            tipo.addEventListener('change', alternar);
            alternar();
        });
    </script>
@endsection
