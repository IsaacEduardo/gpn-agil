@extends('layouts.app')

@section('title', 'Calendário de Reservas')

@section('styles')
    <style>
        /* Custom styling for FullCalendar */
        .fc {
            --fc-border-color: #f1f5f9;
            --fc-button-bg-color: #f8fafc;
            --fc-button-border-color: #cbd5e1;
            --fc-button-text-color: #334155;
            --fc-button-hover-bg-color: #e2e8f0;
            --fc-button-hover-border-color: #94a3b8;
            --fc-button-active-bg-color: #cbd5e1;
            --fc-button-active-border-color: #64748b;
            --fc-today-bg-color: rgba(59, 130, 246, 0.04);
            font-family: var(--font-body);
        }

        .fc-header-toolbar {
            margin-bottom: 1.5rem !important;
            padding: 0.75rem 1rem;
            background: #f8fafc;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
        }

        .fc-toolbar-title {
            font-family: var(--font-heading) !important;
            font-weight: 700 !important;
            font-size: 1.25rem !important;
            color: var(--primary-bg) !important;
        }

        .fc-button {
            font-weight: 600 !important;
            font-size: 0.85rem !important;
            padding: 0.5rem 1rem !important;
            border-radius: 0.5rem !important;
            transition: all 0.2s !important;
            box-shadow: none !important;
        }

        .fc-button-primary:not(:disabled).fc-button-active, 
        .fc-button-primary:not(:disabled):active {
            background-color: var(--primary-accent) !important;
            border-color: var(--primary-accent) !important;
            color: #fff !important;
        }

        .fc-daygrid-day-number {
            font-weight: 600;
            font-size: 0.875rem;
            color: #475569;
            padding: 8px !important;
            text-decoration: none !important;
        }

        .fc-col-header-cell-cushion {
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            padding: 10px 0 !important;
            text-decoration: none !important;
        }

        /* Glassmorphism for Room Types */
        .fc-event.event-room-salao_nobre {
            background: rgba(99, 102, 241, 0.1) !important;
            border-left: 4px solid #6366f1 !important;
            border-top: none !important;
            border-right: none !important;
            border-bottom: none !important;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            border-radius: 6px;
            box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.05);
            transition: all 0.2s ease-in-out;
            cursor: pointer;
            margin: 2px 4px !important;
        }

        .fc-event.event-room-anfiteatro {
            background: rgba(25, 135, 84, 0.1) !important;
            border-left: 4px solid #198754 !important;
            border-top: none !important;
            border-right: none !important;
            border-bottom: none !important;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            border-radius: 6px;
            box-shadow: 0 4px 6px -1px rgba(25, 135, 84, 0.05);
            transition: all 0.2s ease-in-out;
            cursor: pointer;
            margin: 2px 4px !important;
        }

        .fc-event:hover {
            transform: translateY(-1px) scale(1.01);
            box-shadow: 0 8px 12px -3px rgba(0, 0, 0, 0.08), 0 3px 6px -2px rgba(0, 0, 0, 0.04) !important;
            filter: brightness(0.97);
        }

        /* List view compatibility and style */
        .fc-list-event {
            cursor: pointer;
        }
        
        .fc-list-event-title a {
            color: inherit !important;
            text-decoration: none !important;
            font-weight: 600;
        }

        .fc-daygrid-day {
            transition: background-color 0.2s;
            cursor: pointer;
        }
        
        .fc-daygrid-day:hover {
            background-color: #f8fafc;
        }

        /* Status Dot Styling */
        .status-indicator-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }

        /* Legend Custom Component */
        .legend-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            font-weight: 500;
            color: #475569;
        }

        .legend-color-box {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }

        /* Filters styling */
        .filters-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: var(--shadow-card);
        }
    </style>
@endsection

@section('content')
    <div class="card border-0 shadow-sm mb-3 filters-card">
        <div class="card-body p-3">
            <div class="row align-items-end g-3">
                <div class="col-md-3">
                    <label for="filter-espaco" class="form-label fw-semibold text-secondary small">Filtrar por Espaço</label>
                    <select class="form-select" id="filter-espaco">
                        <option value="">Todos os espaços</option>
                        <option value="salao_nobre">Salão Nobre</option>
                        <option value="anfiteatro">Anfiteatro</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filter-status" class="form-label fw-semibold text-secondary small">Filtrar por Status</label>
                    <select class="form-select" id="filter-status">
                        <option value="">Todos os status</option>
                        <option value="pendente">Pendente</option>
                        <option value="aprovada">Aprovada</option>
                        <option value="rejeitada">Rejeitada</option>
                        <option value="cancelada">Cancelada</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-2">
                        <a href="{{ route('reservas.index') }}" class="btn btn-outline-primary w-50">
                            <i class="fas fa-list me-1"></i> Lista
                        </a>
                        <a href="{{ route('reservas.create') }}" class="btn btn-success w-50">
                            <i class="fas fa-plus me-1"></i> Nova Reserva
                        </a>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-secondary w-100" id="refresh-calendar-btn">
                        <i class="fas fa-sync-alt me-1"></i> Atualizar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <!-- Container do FullCalendar -->
            <div id="calendar"></div>

            <!-- Legenda explicativa -->
            <div class="legend-card mt-4 p-3">
                <div class="row g-3">
                    <div class="col-md-6 border-end-md">
                        <div class="d-flex flex-wrap gap-3 align-items-center">
                            <span class="text-muted fw-bold text-uppercase small" style="letter-spacing: 0.5px;">Status:</span>
                            <div class="legend-item">
                                <span class="status-indicator-dot" style="background: #F0AD4E;"></span>
                                <span>Pendente</span>
                            </div>
                            <div class="legend-item">
                                <span class="status-indicator-dot" style="background: #198754;"></span>
                                <span>Aprovada</span>
                            </div>
                            <div class="legend-item">
                                <span class="status-indicator-dot" style="background: #D9534F;"></span>
                                <span>Rejeitada</span>
                            </div>
                            <div class="legend-item">
                                <span class="status-indicator-dot" style="background: #64748b;"></span>
                                <span>Cancelada</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 ps-md-4">
                        <div class="d-flex flex-wrap gap-3 align-items-center">
                            <span class="text-muted fw-bold text-uppercase small" style="letter-spacing: 0.5px;">Espaços:</span>
                            <div class="legend-item">
                                <span class="legend-color-box" style="background: rgba(99, 102, 241, 0.15); border-left: 3px solid #6366f1;"></span>
                                <span>Salão Nobre</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color-box" style="background: rgba(25, 135, 84, 0.15); border-left: 3px solid #198754;"></span>
                                <span>Anfiteatro</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para detalhes da reserva -->
    <div class="modal fade" id="reservationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fw-bold text-primary"><i class="fas fa-calendar-check me-2"></i>Detalhes da Reserva</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" id="reservation-details">
                    <!-- Conteúdo dinâmico -->
                </div>
                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Fechar</button>
                    <a href="#" class="btn btn-primary" id="view-reservation-btn">
                        Ver Detalhes Completos <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <!-- Script do FullCalendar v6 via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            const filterEspaco = document.getElementById('filter-espaco');
            const filterStatus = document.getElementById('filter-status');
            const refreshBtn = document.getElementById('refresh-calendar-btn');

            // Inicializar FullCalendar
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'pt-br',
                editable: false,
                selectable: true,
                dayMaxEvents: 3, // Limita quantidade visível por célula
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
                },
                buttonText: {
                    today: 'Hoje',
                    month: 'Mês',
                    week: 'Semana',
                    day: 'Dia',
                    list: 'Agenda'
                },
                // Feed de dados via AJAX
                events: function(info, successCallback, failureCallback) {
                    const espaco = filterEspaco.value;
                    const status = filterStatus.value;
                    
                    let url = new URL("{{ route('reservas.calendar') }}", window.location.origin);
                    url.searchParams.set('json', '1');
                    url.searchParams.set('start', info.startStr.split('T')[0]);
                    url.searchParams.set('end', info.endStr.split('T')[0]);
                    
                    if (espaco) url.searchParams.set('tipo_espaco', espaco);
                    if (status) url.searchParams.set('status', status);
                    
                    fetch(url, { headers: { 'Accept': 'application/json' } })
                        .then(response => {
                            if (!response.ok) throw new Error('Falha ao carregar as reservas');
                            return response.json();
                        })
                        .then(data => {
                            const events = data.map(item => {
                                // Combinar data com as horas para marcar o evento no horário exato
                                const dateOnly = item.data_evento.includes('T') ? item.data_evento.split('T')[0] : item.data_evento;
                                const startStr = `${dateOnly}T${item.hora_inicio}`;
                                const endStr = `${dateOnly}T${item.hora_fim}`;
                                const className = item.tipo_espaco === 'salao_nobre' ? 'event-room-salao_nobre' : 'event-room-anfiteatro';
                                
                                return {
                                    id: item.id,
                                    title: item.evento_titulo,
                                    start: startStr,
                                    end: endStr,
                                    classNames: [className],
                                    extendedProps: {
                                        codigo_reserva: item.codigo_reserva,
                                        tipo_espaco: item.tipo_espaco,
                                        status: item.status,
                                        data_evento: item.data_evento,
                                        hora_inicio: item.hora_inicio,
                                        hora_fim: item.hora_fim,
                                        evento_titulo: item.evento_titulo,
                                        evento_descricao: item.evento_descricao,
                                        numero_participantes: item.numero_participantes,
                                        solicitante_nome: item.solicitante_nome,
                                        solicitante_email: item.solicitante_email,
                                        solicitante_telefone: item.solicitante_telefone
                                    }
                                };
                            });
                            successCallback(events);
                        })
                        .catch(error => {
                            console.error('Erro ao buscar eventos do calendário:', error);
                            failureCallback(error);
                        });
                },
                // Customização visual de cada bloco de evento
                eventContent: function(arg) {
                    const event = arg.event;
                    const props = event.extendedProps;
                    
                    let statusColor = '#94a3b8';
                    if (props.status === 'pendente') statusColor = '#F0AD4E';
                    else if (props.status === 'aprovada') statusColor = '#198754';
                    else if (props.status === 'rejeitada') statusColor = '#D9534F';
                    else if (props.status === 'cancelada') statusColor = '#64748b';
                    
                    const isSalao = props.tipo_espaco === 'salao_nobre';
                    const isMonthOrList = arg.view.type === 'dayGridMonth' || arg.view.type === 'listMonth';
                    const timeHtml = arg.timeText ? `<span class="event-time-badge font-monospace me-1 fw-semibold text-secondary" style="font-size: 0.725rem;">${arg.timeText}</span>` : '';
                    
                    if (isMonthOrList) {
                        return {
                            html: `
                                <div class="d-flex align-items-center w-100 px-1 py-0.5" style="gap: 5px; overflow: hidden;">
                                    <span class="status-indicator-dot" style="background-color: ${statusColor};" title="Status: ${props.status}"></span>
                                    <div class="text-truncate flex-grow-1" style="font-weight: 500; font-size: 0.8rem; line-height: 1.2;">
                                        ${timeHtml}
                                        <span style="color: ${isSalao ? '#1e1b4b' : '#064e3b'};">${event.title}</span>
                                    </div>
                                </div>
                            `
                        };
                    } else {
                        // Exibição expandida nas visualizações de Semana ou Dia
                        return {
                            html: `
                                <div class="p-2 w-100 d-flex flex-column justify-content-between h-100" style="overflow: hidden; gap: 4px;">
                                    <div class="d-flex align-items-center justify-content-between">
                                        ${timeHtml}
                                        <span class="badge text-uppercase font-monospace" style="font-size: 0.65rem; background-color: ${statusColor}22; color: ${statusColor}; border: 1px solid ${statusColor}44; padding: 2px 6px;">
                                            ${props.status}
                                        </span>
                                    </div>
                                    <div class="text-truncate fw-bold" style="font-size: 0.875rem; color: ${isSalao ? '#1e1b4b' : '#064e3b'};">
                                        ${event.title}
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between" style="font-size: 0.75rem; color: #64748b;">
                                        <span class="text-truncate"><i class="far fa-user me-1"></i>${props.solicitante_nome}</span>
                                        <span class="badge" style="background-color: ${isSalao ? '#6366f1' : '#198754'}15; color: ${isSalao ? '#6366f1' : '#198754'}; font-weight: 500;">
                                            ${isSalao ? 'Salão' : 'Anfi'}
                                        </span>
                                    </div>
                                </div>
                            `
                        };
                    }
                },
                // Ao clicar em um evento, exibe modal de detalhes
                eventClick: function(info) {
                    const reservation = Object.assign({ id: info.event.id }, info.event.extendedProps);
                    showReservationDetails(reservation);
                },
                // Ao clicar em um dia livre, redireciona preenchendo a data
                dateClick: function(info) {
                    window.location.href = `/reservas/create?date=${info.dateStr}`;
                }
            });

            calendar.render();

            // Atualizar calendário ao mudar filtros
            filterEspaco.addEventListener('change', () => calendar.refetchEvents());
            filterStatus.addEventListener('change', () => calendar.refetchEvents());
            refreshBtn.addEventListener('click', () => calendar.refetchEvents());

            // Função para renderizar os detalhes no Modal
            function showReservationDetails(reservation) {
                const modal = new bootstrap.Modal(document.getElementById('reservationModal'));
                const detailsContainer = document.getElementById('reservation-details');
                const viewBtn = document.getElementById('view-reservation-btn');

                let statusBadge = '';
                switch (reservation.status) {
                    case 'pendente':
                        statusBadge = '<span class="badge bg-warning text-dark px-2 py-1">Pendente</span>';
                        break;
                    case 'aprovada':
                        statusBadge = '<span class="badge bg-success px-2 py-1">Aprovada</span>';
                        break;
                    case 'rejeitada':
                        statusBadge = '<span class="badge bg-danger px-2 py-1">Rejeitada</span>';
                        break;
                    case 'cancelada':
                        statusBadge = '<span class="badge bg-secondary px-2 py-1">Cancelada</span>';
                        break;
                }

                const tipoEspaco = reservation.tipo_espaco === 'salao_nobre' ? 'Salão Nobre' : 'Anfiteatro';
                const formattedDate = new Date(reservation.data_evento + 'T00:00:00').toLocaleDateString('pt-BR');

                detailsContainer.innerHTML = `
                    <div class="row g-4">
                        <div class="col-md-6 border-end-md">
                            <h6 class="text-primary fw-bold mb-3"><i class="fas fa-info-circle me-2"></i>Informações Gerais</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="fw-semibold text-secondary w-35">Código:</td>
                                    <td class="fw-bold">${reservation.codigo_reserva}</td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold text-secondary">Status:</td>
                                    <td>${statusBadge}</td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold text-secondary">Espaço:</td>
                                    <td><span class="badge bg-light text-dark border">${tipoEspaco}</span></td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold text-secondary">Data:</td>
                                    <td class="fw-bold">${formattedDate}</td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold text-secondary">Horário:</td>
                                    <td>${reservation.hora_inicio} às ${reservation.hora_fim}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6 ps-md-4">
                            <h6 class="text-primary fw-bold mb-3"><i class="fas fa-user me-2"></i>Dados do Solicitante</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="fw-semibold text-secondary w-35">Nome:</td>
                                    <td>${reservation.solicitante_nome}</td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold text-secondary">E-mail:</td>
                                    <td>${reservation.solicitante_email}</td>
                                </tr>
                                ${reservation.solicitante_telefone ? `
                                <tr>
                                    <td class="fw-semibold text-secondary">Telefone:</td>
                                    <td>${reservation.solicitante_telefone}</td>
                                </tr>` : ''}
                            </table>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="bg-light rounded p-3">
                                <h6 class="text-primary fw-bold mb-2"><i class="fas fa-heading me-2"></i>Evento: ${reservation.evento_titulo}</h6>
                                ${reservation.numero_participantes ? `<p class="mb-2"><strong>Número de Participantes:</strong> ${reservation.numero_participantes}</p>` : ''}
                                ${reservation.evento_descricao ? `
                                    <div class="mt-2">
                                        <strong>Descrição:</strong>
                                        <p class="text-muted mb-0 small mt-1" style="white-space: pre-line;">${reservation.evento_descricao}</p>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;

                viewBtn.href = `/reservas/${reservation.id}`;
                modal.show();
            }
        });
    </script>
@endsection