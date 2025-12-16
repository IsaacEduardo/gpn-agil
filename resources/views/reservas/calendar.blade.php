@extends('layouts.app')

@section('title', 'Calendário de Reservas')

@section('styles')
    <style>
        .calendar-container {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .calendar-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 1.5rem;
            border-radius: 0.5rem 0.5rem 0 0;
        }

        .calendar-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .calendar-nav button {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            transition: all 0.3s ease;
        }

        .calendar-nav button:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #dee2e6;
            border: 1px solid #dee2e6;
        }

        .calendar-day-header {
            background: #f8f9fa;
            padding: 0.75rem;
            text-align: center;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }

        .calendar-day {
            background: white;
            min-height: 120px;
            padding: 0.5rem;
            position: relative;
            transition: background-color 0.2s ease;
        }

        .calendar-day:hover {
            background: #f8f9fa;
        }

        .calendar-day.other-month {
            background: #f8f9fa;
            color: #6c757d;
        }

        .calendar-day.today {
            background: #e3f2fd;
            border: 2px solid #2196f3;
        }

        .day-number {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: #495057;
        }

        .calendar-day.other-month .day-number {
            color: #adb5bd;
        }

        .reservation-item {
            background: #007bff;
            color: white;
            padding: 0.25rem 0.5rem;
            margin-bottom: 0.25rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .reservation-item:hover {
            background: #0056b3;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .reservation-item.status-pendente {
            background: var(--bs-warning);
            color: #000;
        }

        .reservation-item.status-aprovada {
            background: var(--bs-success);
            color: #fff;
        }

        .reservation-item.status-rejeitada {
            background: var(--bs-danger);
            color: #fff;
        }

        .reservation-item.status-cancelada {
            background: var(--bs-secondary);
            color: #fff;
        }

        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 0.25rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 0.25rem;
        }

        .filters {
            background: white;
            padding: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1rem;
        }

        .reservation-tooltip {
            position: absolute;
            background: #333;
            color: white;
            padding: 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            z-index: 1000;
            max-width: 200px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .events-container {
            margin-top: 0.25rem;
        }
        .reservation-item.hidden {
            display: none;
        }
        .event-count-badge {
            float: right;
            font-size: 0.65rem;
        }
        .view-more-link {
            display: inline-block;
            margin-top: 0.25rem;
            font-size: 0.7rem;
            color: var(--bs-primary);
            cursor: pointer;
        }

        /* Skeleton loading */
        .day-skeleton {
            position: absolute;
            left: 0.5rem;
            right: 0.5rem;
            top: 1.5rem;
            bottom: 0.5rem;
        }
        .skeleton-line {
            height: 8px;
            border-radius: 4px;
            background: linear-gradient(90deg, #eee 25%, #ddd 37%, #eee 63%);
            background-size: 400% 100%;
            animation: shimmer 1.2s ease-in-out infinite;
            margin-bottom: 6px;
        }
        @keyframes shimmer {
            0% { background-position: 100% 0; }
            100% { background-position: 0 0; }
        }

        @media (max-width: 768px) {
            .calendar-day {
                min-height: 80px;
                padding: 0.25rem;
            }

            .reservation-item {
                font-size: 0.65rem;
                padding: 0.125rem 0.25rem;
            }

            .calendar-nav {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
@endsection

@section('content')
    <div class="filters">
        <div class="row align-items-end">
            <div class="col-md-3">
                <label for="filter-espaco" class="form-label">Filtrar por Espaço</label>
                <select class="form-select" id="filter-espaco">
                    <option value="">Todos os espaços</option>
                    <option value="salao_nobre">Salão Nobre</option>
                    <option value="anfiteatro">Anfiteatro</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="filter-status" class="form-label">Filtrar por Status</label>
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
                    <a href="{{ route('reservas.index') }}" class="btn btn-outline-primary">
                        <i class="fas fa-list me-1"></i> Lista
                    </a>
                    <a href="{{ route('reservas.create') }}" class="btn btn-success">
                        <i class="fas fa-plus me-1"></i> Nova Reserva
                    </a>
                </div>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-outline-secondary w-100" onclick="window.location.reload()">
                    <i class="fas fa-sync-alt me-1"></i> Atualizar
                </button>
            </div>
        </div>
    </div>

    <div class="calendar-container">
        <div class="calendar-header">
            <div class="calendar-nav">
                <button type="button" id="prev-month">
                    <i class="fas fa-chevron-left me-1"></i> Anterior
                </button>
                <h4 id="current-month" class="mb-0"></h4>
                <button type="button" id="next-month">
                    Próximo <i class="fas fa-chevron-right ms-1"></i>
                </button>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-calendar-alt me-2"></i>
                    <span>Calendário de Reservas de Espaços</span>
                </div>
                <div>
                    <button type="button" class="btn btn-light btn-sm" id="today-btn">
                        <i class="fas fa-calendar-day me-1"></i> Hoje
                    </button>
                </div>
            </div>
        </div>

        <div class="calendar-grid" id="calendar-grid">
            <!-- Cabeçalhos dos dias da semana -->
            <div class="calendar-day-header">Dom</div>
            <div class="calendar-day-header">Seg</div>
            <div class="calendar-day-header">Ter</div>
            <div class="calendar-day-header">Qua</div>
            <div class="calendar-day-header">Qui</div>
            <div class="calendar-day-header">Sex</div>
            <div class="calendar-day-header">Sáb</div>

            <!-- Os dias serão inseridos aqui via JavaScript -->
        </div>

        <div class="legend">
            <div class="legend-item">
                <div class="legend-color" style="background: var(--bs-warning);"></div>
                <span>Pendente</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: var(--bs-success);"></div>
                <span>Aprovada</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: var(--bs-danger);"></div>
                <span>Rejeitada</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: var(--bs-secondary);"></div>
                <span>Cancelada</span>
            </div>
        </div>
    </div>

    <!-- Modal para detalhes da reserva -->
    <div class="modal fade" id="reservationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalhes da Reserva</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="reservation-details">
                    <!-- Conteúdo será carregado via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <a href="#" class="btn btn-primary" id="view-reservation-btn">Ver Detalhes</a>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let currentDate = new Date();
            let reservations = [];
            let isLoading = false;
            const weekCache = new Map(); // cache por semana "YYYY-MM-DD_YYYY-MM-DD"

            const monthNames = [
                'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
            ];

            // Elementos DOM
            const currentMonthElement = document.getElementById('current-month');
            const calendarGrid = document.getElementById('calendar-grid');
            const prevMonthBtn = document.getElementById('prev-month');
            const nextMonthBtn = document.getElementById('next-month');
            const todayBtn = document.getElementById('today-btn');
            const filterEspaco = document.getElementById('filter-espaco');
            const filterStatus = document.getElementById('filter-status');

            // Event listeners
            prevMonthBtn.addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() - 1);
                clearWeekCache();
                loadReservations();
            });

            nextMonthBtn.addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() + 1);
                clearWeekCache();
                loadReservations();
            });

            todayBtn.addEventListener('click', () => {
                currentDate = new Date();
                clearWeekCache();
                loadReservations();
            });

            filterEspaco.addEventListener('change', () => { clearWeekCache(); renderCalendar(); loadReservations(); });
            filterStatus.addEventListener('change', () => { clearWeekCache(); renderCalendar(); loadReservations(); });

            // Função para carregar reservas
            function setLoading(state) {
                isLoading = state;
                currentMonthElement.style.opacity = state ? '0.6' : '1';
            }

            function loadReservations() {
                // Carga incremental por semana
                const year = currentDate.getFullYear();
                const month = currentDate.getMonth();

                // Renderizar o esqueleto do mês primeiro
                renderCalendar();

                const firstOfMonth = new Date(year, month, 1);
                const firstCellDate = new Date(firstOfMonth);
                firstCellDate.setDate(firstOfMonth.getDate() - firstOfMonth.getDay()); // inicia no domingo

                const weeks = [];
                for (let i = 0; i < 6; i++) {
                    const start = new Date(firstCellDate);
                    start.setDate(firstCellDate.getDate() + i * 7);
                    const end = new Date(start);
                    end.setDate(start.getDate() + 6);
                    weeks.push({ start, end });
                }

                setLoading(true);
                let pending = weeks.length;

                weeks.forEach(({ start, end }) => {
                    const key = `${formatDate(start)}_${formatDate(end)}`;
                    // Marcar semana como em carregamento
                    markWeekLoading(start, end);
                    if (weekCache.has(key)) {
                        // já em cache: popular imediatamente
                        populateWeek(start, end, weekCache.get(key));
                        if (--pending === 0) setLoading(false);
                        return;
                    }

                    const espaco = encodeURIComponent(filterEspaco.value || '');
                    const status = encodeURIComponent(filterStatus.value || '');
                    const url = `{{ route('reservas.calendar') }}?start=${formatDate(start)}&end=${formatDate(end)}${espaco ? `&tipo_espaco=${espaco}` : ''}${status ? `&status=${status}` : ''}`;
                    fetch(url, { headers: { 'Accept': 'application/json' } })
                        .then(response => {
                            if (!response.ok) throw new Error('Falha ao obter dados da semana');
                            return response.json();
                        })
                        .then(data => {
                            const list = Array.isArray(data) ? data : [];
                            weekCache.set(key, list);
                            // Atualiza também agregador mensal se necessário
                            reservations = reservations.concat(list);
                            populateWeek(start, end, list);
                        })
                        .catch(error => {
                            console.error('Erro ao carregar semana:', error);
                        })
                        .finally(() => {
                            if (--pending === 0) setLoading(false);
                        });
                });
            }

            // Função para renderizar o calendário
            function renderCalendar() {
                const year = currentDate.getFullYear();
                const month = currentDate.getMonth();

                // Atualizar título
                currentMonthElement.textContent = `${monthNames[month]} ${year}`;

                // Limpar dias existentes (manter cabeçalhos)
                const existingDays = calendarGrid.querySelectorAll('.calendar-day');
                existingDays.forEach(day => day.remove());

                // Primeiro dia do mês e último dia do mês
                const firstDay = new Date(year, month, 1);
                const lastDay = new Date(year, month + 1, 0);
                const daysInMonth = lastDay.getDate();
                const startingDayOfWeek = firstDay.getDay();

                // Dias do mês anterior
                const prevMonth = new Date(year, month - 1, 0);
                const daysInPrevMonth = prevMonth.getDate();

                // Renderizar dias do mês anterior
                for (let i = startingDayOfWeek - 1; i >= 0; i--) {
                    const dayNumber = daysInPrevMonth - i;
                    const dayElement = createDayElement(dayNumber, true, new Date(year, month - 1, dayNumber));
                    calendarGrid.appendChild(dayElement);
                }

                // Renderizar dias do mês atual
                for (let day = 1; day <= daysInMonth; day++) {
                    const dayDate = new Date(year, month, day);
                    const dayElement = createDayElement(day, false, dayDate);
                    calendarGrid.appendChild(dayElement);
                }

                // Renderizar dias do próximo mês para completar a grade
                const totalCells = calendarGrid.children.length - 7; // Subtrair cabeçalhos
                const remainingCells = 42 - totalCells; // 6 semanas * 7 dias

                for (let day = 1; day <= remainingCells; day++) {
                    const dayElement = createDayElement(day, true, new Date(year, month + 1, day));
                    calendarGrid.appendChild(dayElement);
                }
            }

            // Função para criar elemento do dia
            function createDayElement(dayNumber, isOtherMonth, date) {
                const dayElement = document.createElement('div');
                dayElement.className = 'calendar-day';
                // Marca data para popular posteriormente
                const dateString = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
                dayElement.dataset.date = dateString;

                if (isOtherMonth) {
                    dayElement.classList.add('other-month');
                }

                // Verificar se é hoje
                const today = new Date();
                if (date.getFullYear() === today.getFullYear() &&
                    date.getMonth() === today.getMonth() &&
                    date.getDate() === today.getDate()) {
                    dayElement.classList.add('today');
                }

                // Número do dia
                const dayNumberElement = document.createElement('div');
                dayNumberElement.className = 'day-number';
                dayNumberElement.textContent = dayNumber;
                // Badge de contagem
                const countBadge = document.createElement('span');
                countBadge.className = 'badge bg-primary event-count-badge';
                countBadge.textContent = '0';
                countBadge.style.display = 'none';
                dayNumberElement.appendChild(countBadge);
                dayElement.appendChild(dayNumberElement);

                // Container para eventos
                const eventsContainer = document.createElement('div');
                eventsContainer.className = 'events-container';
                dayElement.appendChild(eventsContainer);

                // As reservas serão adicionadas após a carga semanal (populateWeek)

                return dayElement;
            }

            // Função para obter reservas de uma data específica
            function getReservationsForDate(date) {
                const dateString = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
                const espacoFilter = filterEspaco.value;
                const statusFilter = filterStatus.value;

                return reservations.filter(reservation => {
                    const reservationDate = reservation.data_evento;
                    let matches = reservationDate === dateString;

                    if (matches && espacoFilter) {
                        matches = reservation.tipo_espaco === espacoFilter;
                    }

                    if (matches && statusFilter) {
                        matches = reservation.status === statusFilter;
                    }

                    return matches;
                });
            }

            // Função para criar elemento de reserva
            function createReservationElement(reservation) {
                const element = document.createElement('div');
                element.className = `reservation-item status-${reservation.status}`;
                element.textContent = `${reservation.hora_inicio} - ${reservation.evento_titulo}`;
                element.title = `${reservation.evento_titulo} (${reservation.solicitante_nome})`;

                // Adicionar evento de clique
                element.addEventListener('click', () => {
                    showReservationDetails(reservation);
                });

                // Tooltip rico
                attachTooltip(element, reservation);

                return element;
            }

            function attachTooltip(target, reservation) {
                let tooltipEl = null;
                let showTimer = null;
                let hideTimer = null;
                const statusClass =
                    reservation.status === 'pendente' ? 'bg-warning' :
                    reservation.status === 'aprovada' ? 'bg-success' :
                    reservation.status === 'rejeitada' ? 'bg-danger' : 'bg-secondary';
                const tipoEspaco = reservation.tipo_espaco === 'salao_nobre' ? 'Salão Nobre' : 'Anfiteatro';

                function positionTooltip(e) {
                    if (!tooltipEl) return;
                    const offset = 12;
                    tooltipEl.style.left = (e.pageX + offset) + 'px';
                    tooltipEl.style.top = (e.pageY + offset) + 'px';
                }

                target.addEventListener('mouseenter', (e) => {
                    clearTimeout(hideTimer);
                    showTimer = setTimeout(() => {
                        if (tooltipEl) return; // já visível
                        tooltipEl = document.createElement('div');
                        tooltipEl.className = 'reservation-tooltip';
                        tooltipEl.innerHTML = `
                            <div class="fw-semibold">${reservation.evento_titulo}</div>
                            <div class="small">${reservation.hora_inicio} – ${reservation.hora_fim}</div>
                            <div class="small">${reservation.solicitante_nome}</div>
                            <div class="mt-1"><span class="badge ${statusClass}">${capitalize(reservation.status)}</span> • ${tipoEspaco}</div>
                        `;
                        document.body.appendChild(tooltipEl);
                        positionTooltip(e);
                    }, 180); // pequeno delay para evitar flicker
                });
                target.addEventListener('mousemove', positionTooltip);
                target.addEventListener('mouseleave', () => {
                    clearTimeout(showTimer);
                    hideTimer = setTimeout(() => {
                        if (tooltipEl) {
                            tooltipEl.remove();
                            tooltipEl = null;
                        }
                    }, 60);
                });
            }

            function capitalize(s) { return (s || '').charAt(0).toUpperCase() + (s || '').slice(1); }

            function formatDate(d) {
                return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
            }

            // Normaliza diferentes formatos de data para YYYY-MM-DD
            function normalizeDateString(input) {
                if (!input) return '';
                if (typeof input === 'string') {
                    // ISO: 2025-11-04T00:00:00.000000Z -> 2025-11-04
                    const tIdx = input.indexOf('T');
                    if (tIdx > 0) return input.substring(0, tIdx);
                    // BR: 04/11/2025 -> 2025-11-04
                    if (input.includes('/')) {
                        const [dd, mm, yyyy] = input.split('/');
                        return `${yyyy}-${mm.padStart(2, '0')}-${dd.padStart(2, '0')}`;
                    }
                    // Já no formato YYYY-MM-DD
                    return input;
                }
                // Date object
                return formatDate(input);
            }

            function clearWeekCache() { weekCache.clear(); reservations = []; }

            function populateWeek(start, end, list) {
                // Aplica filtros novamente por segurança
                const espacoFilter = filterEspaco.value;
                const statusFilter = filterStatus.value;
                const filtered = list.filter(r => {
                    let ok = true;
                    if (espacoFilter) ok = ok && r.tipo_espaco === espacoFilter;
                    if (statusFilter) ok = ok && r.status === statusFilter;
                    return ok;
                });

                filtered.forEach(reservation => {
                    const dateKey = normalizeDateString(reservation.data_evento);
                    const selector = `.calendar-day:not(.other-month)[data-date="${dateKey}"]`;
                    const dayEl = calendarGrid.querySelector(selector);
                    if (dayEl) {
                        const container = dayEl.querySelector('.events-container') || dayEl;
                        container.appendChild(createReservationElement(reservation));
                    }
                });

                // Atualizar contagens e overflow por dia no intervalo
                let d = new Date(start);
                while (d <= end) {
                    const dateStr = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
                    const dayEl = calendarGrid.querySelector(`.calendar-day:not(.other-month)[data-date="${dateStr}"]`);
                    if (dayEl) updateDayCountAndOverflow(dayEl);
                    d.setDate(d.getDate() + 1);
                }

                // Remover skeleton da semana
                clearWeekLoading(start, end);
            }

            const MAX_VISIBLE_EVENTS = 3;
            function updateDayCountAndOverflow(dayEl) {
                const container = dayEl.querySelector('.events-container') || dayEl;
                const items = Array.from(container.querySelectorAll('.reservation-item'));
                const count = items.length;
                const badge = dayEl.querySelector('.event-count-badge');
                if (badge) {
                    badge.textContent = String(count);
                    badge.style.display = count > 0 ? 'inline-block' : 'none';
                }

                // Gerenciar overflow com "ver mais"
                let toggle = dayEl.querySelector('.view-more-link');
                // Limpa estado anterior
                items.forEach((item, idx) => {
                    if (idx >= MAX_VISIBLE_EVENTS) item.classList.add('hidden');
                    else item.classList.remove('hidden');
                });
                if (count > MAX_VISIBLE_EVENTS) {
                    if (!toggle) {
                        toggle = document.createElement('a');
                        toggle.href = 'javascript:void(0)';
                        toggle.className = 'view-more-link';
                        container.appendChild(toggle);
                    }
                    const extra = count - MAX_VISIBLE_EVENTS;
                    toggle.textContent = `ver mais (${extra})`;
                    toggle.onclick = () => {
                        const expanded = toggle.dataset.expanded === 'true';
                        if (!expanded) {
                            items.forEach(i => i.classList.remove('hidden'));
                            toggle.textContent = 'ver menos';
                            toggle.dataset.expanded = 'true';
                        } else {
                            items.forEach((item, idx) => {
                                if (idx >= MAX_VISIBLE_EVENTS) item.classList.add('hidden');
                            });
                            toggle.textContent = `ver mais (${extra})`;
                            toggle.dataset.expanded = 'false';
                        }
                    };
                } else if (toggle) {
                    toggle.remove();
                }
            }

            function markWeekLoading(start, end) {
                let d = new Date(start);
                while (d <= end) {
                    const dateStr = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
                    const dayEl = calendarGrid.querySelector(`.calendar-day:not(.other-month)[data-date="${dateStr}"]`);
                    if (dayEl && !dayEl.querySelector('.day-skeleton')) {
                        const sk = document.createElement('div');
                        sk.className = 'day-skeleton';
                        sk.innerHTML = '<div class="skeleton-line"></div><div class="skeleton-line"></div><div class="skeleton-line"></div>';
                        dayEl.appendChild(sk);
                    }
                    d.setDate(d.getDate() + 1);
                }
            }
            function clearWeekLoading(start, end) {
                let d = new Date(start);
                while (d <= end) {
                    const dateStr = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
                    const dayEl = calendarGrid.querySelector(`.calendar-day:not(.other-month)[data-date="${dateStr}"]`);
                    if (dayEl) {
                        const sk = dayEl.querySelector('.day-skeleton');
                        if (sk) sk.remove();
                    }
                    d.setDate(d.getDate() + 1);
                }
            }

            // Função para mostrar detalhes da reserva
            function showReservationDetails(reservation) {
                const modal = new bootstrap.Modal(document.getElementById('reservationModal'));
                const detailsContainer = document.getElementById('reservation-details');
                const viewBtn = document.getElementById('view-reservation-btn');

                // Definir status badge
                let statusBadge = '';
                switch (reservation.status) {
                    case 'pendente':
                        statusBadge = '<span class="badge bg-warning">Pendente</span>';
                        break;
                    case 'aprovada':
                        statusBadge = '<span class="badge bg-success">Aprovada</span>';
                        break;
                    case 'rejeitada':
                        statusBadge = '<span class="badge bg-danger">Rejeitada</span>';
                        break;
                    case 'cancelada':
                        statusBadge = '<span class="badge bg-secondary">Cancelada</span>';
                        break;
                }

                // Definir tipo de espaço
                const tipoEspaco = reservation.tipo_espaco === 'salao_nobre' ? 'Salão Nobre' : 'Anfiteatro';

                detailsContainer.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-primary">Informações Gerais</h6>
                    <p><strong>Código:</strong> ${reservation.codigo_reserva}</p>
                    <p><strong>Status:</strong> ${statusBadge}</p>
                    <p><strong>Espaço:</strong> ${tipoEspaco}</p>
                    <p><strong>Data:</strong> ${new Date(reservation.data_evento + 'T00:00:00').toLocaleDateString('pt-BR')}</p>
                    <p><strong>Horário:</strong> ${reservation.hora_inicio} às ${reservation.hora_fim}</p>
                </div>
                <div class="col-md-6">
                    <h6 class="text-primary">Solicitante</h6>
                    <p><strong>Nome:</strong> ${reservation.solicitante_nome}</p>
                    <p><strong>E-mail:</strong> ${reservation.solicitante_email}</p>
                    ${reservation.solicitante_telefone ? `<p><strong>Telefone:</strong> ${reservation.solicitante_telefone}</p>` : ''}
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12">
                    <h6 class="text-primary">Evento</h6>
                    <p><strong>Título:</strong> ${reservation.evento_titulo}</p>
                    ${reservation.numero_participantes ? `<p><strong>Participantes:</strong> ${reservation.numero_participantes}</p>` : ''}
                    ${reservation.evento_descricao ? `<p><strong>Descrição:</strong> ${reservation.evento_descricao}</p>` : ''}
                </div>
            </div>
        `;

                viewBtn.href = `/reservas/${reservation.id}`;
                modal.show();
            }

            // Inicializar calendário
            loadReservations();
        });
    </script>
@endsection