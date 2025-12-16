<div class="card quick-actions mb-4">
    <div class="card-header bg-light">
        <div class="section-title"><i class="fas fa-bolt me-2"></i>Ações rápidas</div>
    </div>
    <div class="card-body d-flex flex-wrap gap-2 align-items-center">
        <a href="{{ route('requisicoes.produtos.create.novo') }}" class="btn btn-primary" data-bs-toggle="tooltip" title="Criar uma nova requisição de produtos">
            <i class="fas fa-box-open"></i>
            <span class="ms-2">Nova requisição de produtos</span>
        </a>
        <a href="{{ route('requisicoes.oficina.create.novo') }}" class="btn btn-primary" data-bs-toggle="tooltip" title="Criar uma nova requisição de oficina">
            <i class="fas fa-tools"></i>
            <span class="ms-2">Nova requisição de oficina</span>
        </a>
        <a href="{{ route('requisicoes.servico.create.novo') }}" class="btn btn-primary" data-bs-toggle="tooltip" title="Criar uma nova requisição de serviços">
            <i class="fas fa-concierge-bell"></i>
            <span class="ms-2">Nova requisição de serviços</span>
        </a>
        <a href="{{ route('requisicoes.passagem.create.novo') }}" class="btn btn-primary" data-bs-toggle="tooltip" title="Criar uma nova requisição de bilhete de passagem">
            <i class="fas fa-ticket-alt"></i>
            <span class="ms-2">Nova requisição de passagem</span>
        </a>
        <a href="{{ route('reservas.create') }}" class="btn btn-info text-white" data-bs-toggle="tooltip" title="Agendar uma nova reserva de espaço">
            <i class="fas fa-calendar-plus"></i>
            <span class="ms-2">Nova reserva de espaço</span>
        </a>
        <a href="{{ route('viaturas.index') }}" class="btn btn-outline-success">
            <i class="fas fa-car"></i>
            <span class="ms-2">Ver viaturas</span>
        </a>
        <a href="{{ route('requisicoes.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-file-alt"></i>
            <span class="ms-2">Ver requisições</span>
        </a>
        <a href="{{ route('reservas.calendar') }}" class="btn btn-outline-info">
            <i class="fas fa-calendar"></i>
            <span class="ms-2">Calendário de reservas</span>
        </a>
    </div>
</div>
