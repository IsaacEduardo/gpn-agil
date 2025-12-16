@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header bg-warning text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Nova Requisição de Oficina</h5>
                            <a href="{{ route('requisicoes.index') }}" class="btn btn-sm btn-light">
                                <i class="fas fa-arrow-left me-1"></i> Voltar
                            </a>
                        </div>
                    </div>

                    <div class="card-body">
                        <form action="{{ route('requisicoes.oficinas.store', $requisicao->id) }}" method="POST">
                            @csrf

                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Informações da Requisição #{{ $requisicao->codigo_sequencial }}</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Data:</strong> {{ $requisicao->data_requisicao->format('d/m/Y') }}
                                            </p>
                                            <p><strong>Solicitante:</strong> {{ $requisicao->usuario?->name ?? 'N/A' }}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Status:</strong> {{ $requisicao->status instanceof \App\Enums\StatusRequisicao ? $requisicao->status->label() : ucfirst($requisicao->status) }}</p>
                                            <p><strong>Tipo:</strong> Oficina</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @include('requisicoes.oficina.form', [
                                'requisicao' => $requisicao,
                                'viaturas' => $viaturas,
                            ])

                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Salvar Informações
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
