@extends('layouts.app')

@section('content')
<div class="container py-4">
    {{-- Header Section --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold text-primary mb-1 d-flex align-items-center">
                <span class="p-2 bg-danger-subtle text-danger rounded-3 me-3 d-inline-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                    <i class="fas fa-calendar-alt fs-5"></i>
                </span>
                <span>Tabela de Temporalidade Documental</span>
            </h4>
            <p class="text-muted small mb-0 ms-0 ms-md-5">
                Configure os tempos de guarda e ações de expiração das espécies documentais oficiais
            </p>
        </div>
        <div>
            <a href="{{ route('edms.index') }}" class="btn btn-outline-secondary px-3 rounded-pill fw-semibold shadow-sm">
                <i class="fas fa-arrow-left me-1"></i> Voltar ao Arquivo
            </a>
        </div>
    </div>

    {{-- Rules Editor Card --}}
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <h6 class="fw-bold text-dark mb-4">
                <i class="fas fa-edit text-primary me-2"></i>Editar Prazos e Destinação Final
            </h6>

            <form action="{{ route('edms.retention-store') }}" method="POST">
                @csrf
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-4">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4" style="width: 30%;">Espécie Documental</th>
                                <th style="width: 20%;">Prazo de Guarda (Anos)</th>
                                <th style="width: 25%;">Destinação Final</th>
                                <th class="pe-4" style="width: 25%;">Observações / Justificação</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($especies as $index => $especie)
                                @php
                                    $schedule = $especie->retentionSchedule;
                                @endphp
                                <tr>
                                    <td class="ps-4 fw-semibold text-dark">
                                        {{ $especie->nome }}
                                        <input type="hidden" name="rules[{{ $index }}][documento_especie_id]" value="{{ $especie->id }}">
                                    </td>
                                    <td>
                                        <div class="input-group" style="max-width: 140px;">
                                            <input type="number" name="rules[{{ $index }}][temporalidade_anos]" 
                                                class="form-control bg-light border-0 py-2 text-center" 
                                                value="{{ $schedule ? $schedule->temporalidade_anos : 5 }}" 
                                                min="0" required>
                                            <span class="input-group-text bg-transparent border-0 text-muted small">Anos</span>
                                        </div>
                                    </td>
                                    <td>
                                        <select name="rules[{{ $index }}][acao_final]" class="form-select bg-light border-0 py-2" required>
                                            <option value="arquivar" {{ ($schedule && $schedule->acao_final == 'arquivar') ? 'selected' : '' }}>
                                                Arquivar Permanentemente
                                            </option>
                                            <option value="eliminar" {{ ($schedule && $schedule->acao_final == 'eliminar') ? 'selected' : '' }}>
                                                Eliminar Ficheiro
                                            </option>
                                        </select>
                                    </td>
                                    <td class="pe-4">
                                        <input type="text" name="rules[{{ $index }}][observacoes]" 
                                            class="form-control bg-light border-0 py-2" 
                                            value="{{ $schedule ? $schedule->observacoes : '' }}" 
                                            placeholder="Ex: Legislação nº X / Despacho Provincial Y">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end gap-2 p-3 border-top">
                    <a href="{{ route('edms.index') }}" class="btn btn-light rounded-pill px-4">Cancelar</a>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm fw-semibold">
                        <i class="fas fa-save me-1"></i> Gravar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
