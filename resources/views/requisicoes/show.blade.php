@extends('layouts.app')

@section('content')
    <div class="container">
        @php
            $tipo = $requisicao->tipo;
            $tipoValue = $tipo instanceof \App\Enums\TipoRequisicao ? $tipo->value : $tipo;

            $status = $requisicao->status;
            $statusValue = $status instanceof \App\Enums\StatusRequisicao ? $status->value : $status;
        @endphp
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Detalhes da Requisição #{{ $requisicao->codigo_sequencial }}</h5>
                <div>
                    <a href="{{ route('requisicoes.index') }}" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                    @if ($tipoValue == 'oficina')
                        <a href="{{ route('requisicoes.oficina.pdf', $requisicao->id) }}" target="_blank"
                            class="btn btn-sm btn-danger ms-2" title="Baixar Ofício (PDF)">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                    @endif
                    @if ($tipoValue == 'servico')
                        <a href="{{ route('requisicoes.servico.pdf', $requisicao->id) }}" target="_blank"
                            class="btn btn-sm btn-danger ms-2" title="Baixar Ofício (PDF)">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                    @endif
                    @if ($tipoValue == 'produto')
                        <a href="{{ route('requisicoes.produtos.pdf', $requisicao->id) }}" target="_blank"
                            class="btn btn-sm btn-danger ms-2" title="Baixar Ofício (PDF)">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                    @endif
                    @if (
                        $statusValue != 'aprovada' &&
                            $statusValue != 'aprovado' &&
                            $statusValue != 'assinada' &&
                            $statusValue != 'assinado')
                        @if ($tipoValue == 'produto')
                            <a href="{{ route('requisicoes.produtos.edit', $requisicao->id) }}"
                                class="btn btn-sm btn-primary">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                        @elseif($tipoValue == 'oficina')
                            <a href="{{ route('requisicoes.oficina.edit', $requisicao->id) }}"
                                class="btn btn-sm btn-primary">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                        @elseif($tipoValue == 'servico')
                            <a href="{{ route('requisicoes.servico.edit', $requisicao->id) }}"
                                class="btn btn-sm btn-primary">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                        @else
                            <a href="{{ route('requisicoes.edit', $requisicao->id) }}" class="btn btn-sm btn-primary">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                        @endif
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted">Informações Gerais</h6>
                        <table class="table table-sm">
                            <tr>
                                <th width="30%">Código:</th>
                                <td>{{ $requisicao->codigo_sequencial }}</td>
                            </tr>
                            <tr>
                                <th>Tipo:</th>
                                <td>
                                    @if ($tipoValue == 'produto')
                                        <span class="badge bg-primary">Produtos</span>
                                    @elseif($tipoValue == 'oficina')
                                        <span class="badge bg-warning">Oficina</span>
                                    @elseif($tipoValue == 'servico')
                                        <span class="badge bg-info">Serviço</span>
                                    @elseif($tipoValue == 'passagem')
                                        <span class="badge bg-secondary">Passagem</span>
                                    @else
                                        <span class="badge bg-secondary">{{ ucfirst($tipoValue) }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Data:</th>
                                <td>{{ \Carbon\Carbon::parse($requisicao->created_at)->format('d/m/Y H:i') }}</td>
                            </tr>
                            <tr>
                                <th>Solicitante:</th>
                                <td>{{ $requisicao->solicitante }}</td>
                            </tr>
                            <tr>
                                <th>Empresa Destinatária:</th>
                                <td>{{ optional($requisicao->empresa)->nome ?? $requisicao->empresa_destinataria }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Status</h6>
                        <table class="table table-sm">
                            <tr>
                                <th width="30%">Status Atual:</th>
                                <td>
                                    @if ($status instanceof \App\Enums\StatusRequisicao)
                                        <span class="badge bg-{{ $status->color() }}">{{ $status->label() }}</span>
                                    @else
                                        @if ($statusValue == 'pendente')
                                            <span class="badge bg-warning">Pendente</span>
                                        @elseif($statusValue == 'aprovada' || $statusValue == 'aprovado')
                                            <span class="badge bg-success">Aprovada</span>
                                        @elseif($statusValue == 'assinada' || $statusValue == 'assinado')
                                            <span class="badge bg-primary">Assinada</span>
                                        @elseif($statusValue == 'rejeitada' || $statusValue == 'rejeitado')
                                            <span class="badge bg-danger">Rejeitada</span>
                                        @elseif($statusValue == 'em_andamento')
                                            <span class="badge bg-info">Em Andamento</span>
                                        @elseif($statusValue == 'concluido' || $statusValue == 'finalizado' || $statusValue == 'finalizada')
                                            <span class="badge bg-primary">Concluído</span>
                                        @else
                                            <span class="badge bg-secondary">{{ ucfirst($statusValue) }}</span>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Última Atualização:</th>
                                <td>{{ \Carbon\Carbon::parse($requisicao->updated_at)->format('d/m/Y H:i') }}</td>
                            </tr>
                            <tr>
                                <th>Observações:</th>
                                <td>{{ $requisicao->observacoes ?? 'Nenhuma observação' }}</td>
                            </tr>
                            @if ($requisicao->status == 'rejeitada' && $requisicao->motivo_rejeicao)
                                <tr>
                                    <th class="text-danger">Motivo da Rejeição:</th>
                                    <td class="text-danger">{{ $requisicao->motivo_rejeicao }}</td>
                                </tr>
                            @endif
                            @if ($requisicao->assinado_em)
                                <tr>
                                    <td colspan="2" class="p-0 pt-2">
                                        <div class="alert alert-success mb-0 py-2">
                                            <i class="fas fa-certificate"></i> <strong>Assinado Digitalmente</strong><br>
                                            <small>
                                                Por: {{ $requisicao->assinadoPor->name ?? 'N/A' }}<br>
                                                Em: {{ $requisicao->assinado_em->format('d/m/Y H:i') }}
                                            </small>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        </table>
                    </div>
                </div>

                <!-- Detalhes específicos baseados no tipo de requisição -->
                @if ($tipoValue == 'produto')
                    <div class="mt-4">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-box me-2"></i>Produtos Solicitados</h5>
                            </div>
                            <div class="card-body">
                                <div>
                                    <ul class="mb-0">
                                        @forelse($requisicao->produtos as $produto)
                                            @php
                                                $nome =
                                                    $produto->nome_produto ??
                                                    ($produto->nome ?? ($produto->descricao ?? '-'));
                                                $qtd = $produto->quantidade ?? null;
                                                $un = $produto->unidade_medida ?? ($produto->unidade ?? null);
                                            @endphp
                                            <li>
                                                <strong>{{ $nome }}</strong>
                                                @if ($qtd && $un)
                                                    ({{ $qtd }} {{ $un }})
                                                @elseif($qtd)
                                                    ({{ $qtd }})
                                                @elseif($un)
                                                    ({{ $un }})
                                                @endif
                                            </li>
                                        @empty
                                            <li class="text-muted">Nenhum produto encontrado</li>
                                        @endforelse
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                @elseif($tipoValue == 'oficina')
                    <div class="mt-4">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Detalhes do Serviço de Oficina</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <table class="table table-sm table-bordered">
                                            <tr>
                                                <th width="30%" class="table-light">Viatura:</th>
                                                <td>
                                                    @if (isset($requisicao->oficina) && isset($requisicao->oficina->viatura))
                                                        <strong>{{ $requisicao->oficina->viatura->prefixo }} -
                                                            {{ $requisicao->oficina->viatura->placa }}</strong>
                                                    @else
                                                        Não informada
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="table-light">Tipo de Manutenção:</th>
                                                <td>
                                                    @php $ts = optional($requisicao->oficina)->tipo_servico; @endphp
                                                    @if ($ts === 'Preventivo')
                                                        Preventiva
                                                    @elseif($ts)
                                                        Corretiva
                                                    @else
                                                        Não informada
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="table-light">Descrição:</th>
                                                <td>{{ $requisicao->oficina ? $requisicao->oficina->descricao_problema : 'Não informada' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="table-light">Prioridade:</th>
                                                <td>
                                                    @php $u = optional($requisicao->oficina)->urgencia; @endphp
                                                    @if ($u === 'baixa')
                                                        <span class="badge bg-success">Baixa</span>
                                                    @elseif($u === 'media')
                                                        <span class="badge bg-primary">Média</span>
                                                    @elseif($u === 'alta')
                                                        <span class="badge bg-warning text-dark">Alta</span>
                                                    @elseif($u === 'critica')
                                                        <span class="badge bg-danger">Urgente</span>
                                                    @else
                                                        <span class="badge bg-secondary">Não informada</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @elseif($tipoValue == 'servico')
                    <div class="mt-4">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-concierge-bell me-2"></i>Serviço Solicitado</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <table class="table table-sm table-bordered">
                                            <tr>
                                                <th width="30%" class="table-light">Tipo de Serviço:</th>
                                                <td><strong>{{ optional($requisicao->servicos)->tipo_servico ?? 'Não informado' }}</strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="table-light">Descrição:</th>
                                                <td>{{ optional($requisicao->servicos)->descricao ?? 'Não informada' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="table-light">Local:</th>
                                                <td>{{ optional($requisicao->servicos)->local ?? 'Não informado' }}</td>
                                            </tr>
                                            <tr>
                                                <th class="table-light">Data Desejada:</th>
                                                <td>
                                                    @if (optional($requisicao->servicos)->data_hora_desejada)
                                                        {{ \Carbon\Carbon::parse($requisicao->servicos->data_hora_desejada)->format('d/m/Y H:i') }}
                                                    @else
                                                        Não informada
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="table-light">Prioridade:</th>
                                                <td>
                                                    <?php $prioridade = optional($requisicao->servicos)->prioridade; ?>
                                                    @if ($prioridade === 'Baixa')
                                                        <span class="badge bg-success">Baixa</span>
                                                    @elseif($prioridade === 'Média')
                                                        <span class="badge bg-primary">Média</span>
                                                    @elseif($prioridade === 'Alta')
                                                        <span class="badge bg-warning text-dark">Alta</span>
                                                    @elseif($prioridade === 'Urgente')
                                                        <span class="badge bg-danger">Urgente</span>
                                                    @else
                                                        <span class="badge bg-secondary">Não informada</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @elseif($tipoValue == 'passagem')
                    <div class="mt-4">
                        <div class="card">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0"><i class="fas fa-ticket-alt me-2"></i>Detalhes da Passagem</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-bordered">
                                    <tr>
                                        <th class="table-light" width="30%">Beneficiário:</th>
                                        <td>{{ optional($requisicao->passagem)->beneficiario_nome }}</td>
                                    </tr>
                                    <tr>
                                        <th class="table-light">Destino:</th>
                                        <td>{{ optional($requisicao->passagem)->destino }}</td>
                                    </tr>
                                    <tr>
                                        <th class="table-light">Tipo:</th>
                                        <td>{{ optional($requisicao->passagem)->ida_volta ? 'Ida e volta' : 'Ida' }}</td>
                                    </tr>
                                    <tr>
                                        <th class="table-light">Partida:</th>
                                        <td>{{ optional(optional($requisicao->passagem)->data_partida)->format('d/m/Y') }}
                                        </td>
                                    </tr>
                                    @if (optional($requisicao->passagem)->ida_volta)
                                        <tr>
                                            <th class="table-light">Regresso:</th>
                                            <td>{{ optional(optional($requisicao->passagem)->data_regresso)->format('d/m/Y') }}
                                            </td>
                                        </tr>
                                    @endif
                                </table>
                            </div>
                        </div>
                    </div>
                @endif




                <!-- Botões de Ação -->
                <div class="mt-4">
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Ações</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    @php
                                        $actor = Auth::user();
                                        $actorDeps =
                                            $actor && method_exists($actor, 'departamentos') && $actor->departamentos
                                                ? $actor->departamentos->pluck('id')->all()
                                                : [];
                                        if (!count($actorDeps) && $actor && $actor->departamento_id) {
                                            $actorDeps = [$actor->departamento_id];
                                        }
                                        $owner = $requisicao->usuario;
                                        $ownerDeps =
                                            $owner && method_exists($owner, 'departamentos') && $owner->departamentos
                                                ? $owner->departamentos->pluck('id')->all()
                                                : [];
                                        if ($owner && !count($ownerDeps) && $owner->departamento_id) {
                                            $ownerDeps = [$owner->departamento_id];
                                        }
                                        $inScope = count(array_intersect($actorDeps, $ownerDeps)) > 0;
                                        if ($actor && $actor->role && $actor->role->name === 'admin') {
                                            $inScope = true;
                                        }
                                        $isChefe =
                                            $actor && $actor->role && $actor->role->name === 'chefe-departamento';
                                        $canApproveOrReject =
                                            ($actor &&
                                                method_exists($actor, 'hasPermission') &&
                                                $actor->hasPermission('aprovar_requisicoes')) ||
                                            ($isChefe && $inScope);
                                    @endphp
                                    @if ($statusValue == 'pendente' && $canApproveOrReject)
                                        <form action="{{ route('requisicoes.aprovar', $requisicao->id) }}" method="POST"
                                            class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-check"></i> Aprovar
                                            </button>
                                        </form>

                                        @inject('signatureService', 'App\Services\SignatureService')
                                        @if (!$requisicao->assinado_em && $signatureService->canSign($requisicao, auth()->user()))
                                            <button type="button" class="btn btn-dark ms-2" data-bs-toggle="modal"
                                                data-bs-target="#signModal">
                                                <i class="fas fa-file-signature"></i> Assinar Digitalmente
                                            </button>
                                        @endif

                                        <button type="button" class="btn btn-danger ms-2" data-bs-toggle="modal"
                                            data-bs-target="#rejeitarModal">
                                            <i class="fas fa-times"></i> Rejeitar
                                        </button>
                                    @endif

                                    <a href="{{ route('termos.index') }}" class="btn btn-primary">
                                        <i class="fas fa-file-contract"></i> Gerenciar Termos de Entrega
                                    </a>
                                </div>

                                <form action="{{ route('requisicoes.destroy', $requisicao) }}" method="POST"
                                    class="d-inline"
                                    onsubmit="return confirm('Tem certeza que deseja excluir esta requisição?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger">
                                        <i class="fas fa-trash"></i> Excluir
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de Rejeição -->
        <div class="modal fade" id="rejeitarModal" tabindex="-1" aria-labelledby="rejeitarModalLabel"
            aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('requisicoes.rejeitar', $requisicao->id) }}" method="POST">
                        @csrf
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="rejeitarModalLabel">Rejeitar Requisição</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Por favor, informe o motivo da rejeição:</p>
                            <div class="mb-3">
                                <label for="motivo_rejeicao" class="form-label">Motivo</label>
                                <textarea class="form-control" id="motivo_rejeicao" name="motivo_rejeicao" rows="4" required
                                    placeholder="Descreva o motivo da rejeição..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-danger">Confirmar Rejeição</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal de Assinatura -->
        <div class="modal fade" id="signModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('requisicoes.sign', $requisicao->id) }}" method="POST">
                        @csrf
                        <div class="modal-header bg-dark text-white">
                            <h5 class="modal-title"><i class="fas fa-file-signature me-2"></i>Assinar Digitalmente</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Ao assinar digitalmente, você aprova esta requisição e garante sua autenticidade.</p>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Confirme sua senha</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Senha do certificado digital</label>
                                <input type="password" name="certificate_password" class="form-control"
                                    autocomplete="off" placeholder="Preencha apenas se o seu certificado tiver senha">
                                <small class="text-muted">Não é armazenada — solicitada apenas no momento de assinar.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-dark">Assinar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endsection
