    <script>
        // ---- Percurso: filtros em chips, expansão de eventos, visualizador ----
        document.addEventListener('DOMContentLoaded', function() {
            const percurso = document.getElementById('percursoDocumento');
            if (percurso) {
                const eventos = percurso.querySelectorAll('.percurso-evento');
                const tabelas = percurso.querySelectorAll('.percurso-tabela');
                const semResultados = percurso.querySelector('.percurso-sem-resultados');

                // Os tipos de evento que cada chip mostra. 'tudo' mostra todos.
                const tiposPorFiltro = {
                    tudo: null,
                    tramitacao: ['encaminhamento_envio', 'encaminhamento_recebido', 'despacho', 'visto_departamento', 'visto_gabinete', 'registo', 'arquivamento'],
                    tarefas: ['tarefa_criada', 'tarefa_concluida'],
                    externo: ['encaminhamento_externo'],
                    vinculos: ['vinculo'],
                    auditoria: [],
                };

                percurso.querySelectorAll('.percurso-chip').forEach(function(chip) {
                    chip.addEventListener('click', function() {
                        const filtro = chip.dataset.filtro;

                        percurso.querySelectorAll('.percurso-chip').forEach(function(c) {
                            const activo = c === chip;
                            c.classList.toggle('active', activo);
                            c.setAttribute('aria-pressed', activo ? 'true' : 'false');
                        });

                        const tipos = tiposPorFiltro[filtro];
                        let visiveis = 0;
                        eventos.forEach(function(ev) {
                            const mostrar = tipos === null || tipos.indexOf(ev.dataset.tipo) !== -1;
                            ev.hidden = !mostrar;
                            if (mostrar) visiveis++;
                        });

                        // A tabela detalhada do filtro abre junto; 'tudo' fecha todas.
                        tabelas.forEach(function(t) {
                            t.hidden = filtro === 'tudo' || t.dataset.tabela !== filtro;
                        });

                        if (semResultados) {
                            semResultados.hidden = visiveis > 0 || filtro === 'auditoria';
                        }
                    });
                });

                percurso.querySelectorAll('.percurso-fechar-tabela').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        btn.closest('.percurso-tabela').hidden = true;
                    });
                });

                // Expandir/colapsar a descrição de um evento.
                percurso.querySelectorAll('.percurso-evento-cabeca').forEach(function(cab) {
                    cab.addEventListener('click', function() {
                        const aberto = cab.getAttribute('aria-expanded') === 'true';
                        cab.setAttribute('aria-expanded', aberto ? 'false' : 'true');
                        const detalhe = document.getElementById(cab.getAttribute('aria-controls'));
                        if (detalhe) detalhe.hidden = aberto;
                    });
                });
            }

            document.querySelectorAll('.doc-viewer-zoom').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const cartao = btn.closest('.doc-viewer-card');
                    const expandido = cartao.classList.toggle('is-expandido');
                    btn.querySelector('i').className = expandido
                        ? 'fas fa-down-left-and-up-right-to-center'
                        : 'fas fa-up-right-and-down-left-from-center';
                });
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Modal de encaminhamento interno
            const form = document.getElementById('form-encaminhar');
            const btn = document.getElementById('btnEncaminhar');
            const modalEl = document.getElementById('confirmEncaminharModal');
            if (form && btn && modalEl) {
                btn.addEventListener('click', function() {
                    // Lê o combobox pesquisável: input hidden (id) + campo de texto (nome legível).
                    const destinoHidden = form.querySelector('input[name="destino_departamento_id"]');
                    const destinoLabel = form.querySelector('.dep-combobox-input');
                    const destinoText = destinoHidden && destinoHidden.value
                        ? (destinoLabel && destinoLabel.value ? destinoLabel.value : destinoHidden.value)
                        : '—';
                    const obsField = form.querySelector('[name="observacao"]');
                    const observacao = (obsField && obsField.value) ? obsField.value : '—';
                    modalEl.querySelector('[data-field="destino"]').textContent = destinoText;
                    modalEl.querySelector('[data-field="observacao"]').textContent = observacao;
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                });
                modalEl.querySelector('[data-action="confirm"]').addEventListener('click', function() {
                    form.submit();
                });
            }

            // Novo: Modal de saída para outro gabinete
            const formSaida = document.getElementById('form-saida-gabinete');
            const btnSaida = document.getElementById('btnSaidaGabinete');
            const modalSaidaEl = document.getElementById('confirmSaidaGabineteModal');
            if (formSaida && btnSaida && modalSaidaEl) {
                btnSaida.addEventListener('click', function() {
                    const destSel = formSaida.querySelector('select[name="destino_gabinete_id"]');
                    const destText = destSel && destSel.value ? destSel.options[destSel.selectedIndex].text : '—';
                    const dataSaida = formSaida.querySelector('input[name="saida_gabinete_data"]').value || '—';
                    const oficio = formSaida.querySelector('input[name="encaminhamento_oficio_numero"]').value || '—';
                    
                    modalSaidaEl.querySelector('[data-field="destino_gabinete"]').textContent = destText;
                    modalSaidaEl.querySelector('[data-field="data_saida"]').textContent = dataSaida.split('-').reverse().join('/');
                    modalSaidaEl.querySelector('[data-field="oficio_numero"]').textContent = oficio;
                    const modal = new bootstrap.Modal(modalSaidaEl);
                    modal.show();
                });
                modalSaidaEl.querySelector('[data-action="confirm-saida"]').addEventListener('click', function() {
                    formSaida.submit();
                });
            }

            const btnVistoDep = document.getElementById('btnVistoDepartamentoAprovar');
            const vistoDepForm = document.getElementById('visto-dep-aprovar-form');
            const modalVistoDepEl = document.getElementById('confirmVistoDepAprovarModal');
            if (btnVistoDep && vistoDepForm && modalVistoDepEl) {
                btnVistoDep.addEventListener('click', function() {
                    const modal = new bootstrap.Modal(modalVistoDepEl);
                    modal.show();
                });
                modalVistoDepEl.querySelector('[data-action="confirm-visto-dep"]').addEventListener('click', function() {
                    vistoDepForm.submit();
                });
            }

            const btnVistoGab = document.getElementById('btnVistoGabineteAprovar');
            const vistoGabForm = document.getElementById('visto-gab-aprovar-form');
            const modalVistoGabEl = document.getElementById('confirmVistoGabAprovarModal');
            if (btnVistoGab && vistoGabForm && modalVistoGabEl) {
                btnVistoGab.addEventListener('click', function() {
                    const modal = new bootstrap.Modal(modalVistoGabEl);
                    modal.show();
                });
                modalVistoGabEl.querySelector('[data-action="confirm-visto-gab"]').addEventListener('click', function() {
                    vistoGabForm.submit();
                });
            }

            const modalRecEl = document.getElementById('confirmReceberEncaminhamentoModal');
            document.querySelectorAll('[data-action="open-receber"]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const formId = btn.getAttribute('data-form-id');
                    modalRecEl.setAttribute('data-form-id', formId);
                    modalRecEl.querySelector('[data-field="rec-origem"]').textContent = btn.getAttribute('data-origem') || '—';
                    modalRecEl.querySelector('[data-field="rec-destino"]').textContent = btn.getAttribute('data-destino') || '—';
                    modalRecEl.querySelector('[data-field="rec-data"]').textContent = btn.getAttribute('data-data') || '—';
                    const modal = new bootstrap.Modal(modalRecEl);
                    modal.show();
                });
            });
            if (modalRecEl) {
                modalRecEl.querySelector('[data-action="confirm-receber"]').addEventListener('click', function() {
                    const formId = modalRecEl.getAttribute('data-form-id');
                    const form = formId ? document.getElementById(formId) : null;
                    if (form) {
                        form.submit();
                    }
                });
            }
            const tarefaForm = document.getElementById('form-designar-tarefa');
            if (tarefaForm) {
                const tipoUsuario = tarefaForm.querySelector('#tipoUsuario');
                const tipoDepartamento = tarefaForm.querySelector('#tipoDepartamento');
                const destinoUsuario = tarefaForm.querySelector('[data-field="destino-usuario"]');
                const destinoDepartamento = tarefaForm.querySelector('[data-field="destino-departamento"]');
                const depSelect = destinoDepartamento ? destinoDepartamento.querySelector('select') : null;
                const userSelect = destinoUsuario ? destinoUsuario.querySelector('select') : null;
                const updateVisibility = function() {
                    const isDep = tipoDepartamento && tipoDepartamento.checked;
                    if (destinoUsuario) destinoUsuario.classList.toggle('d-none', isDep);
                    if (destinoDepartamento) destinoDepartamento.classList.toggle('d-none', !isDep);
                    
                    if (isDep) {
                        if (depSelect) {
                            depSelect.setAttribute('name', 'destino_id');
                            depSelect.required = true;
                        }
                        if (userSelect) {
                            userSelect.removeAttribute('name');
                            userSelect.required = false;
                        }
                    } else {
                        if (userSelect) {
                            userSelect.setAttribute('name', 'destino_ids[]');
                            userSelect.required = true;
                        }
                        if (depSelect) {
                            depSelect.removeAttribute('name');
                            depSelect.required = false;
                        }
                    }
                };
                if (tipoUsuario) tipoUsuario.addEventListener('change', updateVisibility);
                if (tipoDepartamento) tipoDepartamento.addEventListener('change', updateVisibility);
                updateVisibility();
                
                // New: Loading state for submit button
                tarefaForm.addEventListener('submit', function() {
                    const btn = document.getElementById('btnSubmitTarefa');
                    if (btn) {
                        btn.disabled = true;
                        btn.querySelector('.spinner-border').classList.remove('d-none');
                        btn.querySelector('.fa-plus').classList.add('d-none');
                    }
                });
            }
            // Modal Detalhes da Tarefa (clique no título)
            document.addEventListener('click', function(e) {
                const link = e.target.closest('.btn-ver-tarefa-show');
                if (!link) return;
                e.preventDefault();

                const d = link.dataset;
                document.getElementById('modalShowTarefaTitulo').textContent = d.titulo;

                // Description
                const descEl = document.getElementById('modalShowTarefaDescricao');
                descEl.textContent = d.descricao || 'Sem descrição detalhada.';
                if (!d.descricao) descEl.classList.add('text-muted', 'fst-italic');
                else descEl.classList.remove('text-muted', 'fst-italic');

                // Status badge
                const statusEl = document.getElementById('modalShowTarefaStatus');
                const statusMap = {
                    pendente: { bg: 'bg-warning', label: 'Pendente' },
                    concluida: { bg: 'bg-success', label: 'Concluída' },
                    concluido: { bg: 'bg-success', label: 'Concluído' },
                    cancelada: { bg: 'bg-danger', label: 'Cancelada' },
                };
                const st = statusMap[d.status] || { bg: 'bg-secondary', label: d.status };
                statusEl.className = 'badge rounded-pill ' + st.bg;
                statusEl.textContent = st.label;

                // Prazo
                const prazoEl = document.getElementById('modalShowTarefaPrazo');
                prazoEl.innerHTML = '<i class="far fa-calendar-alt me-1"></i> Prazo: ' + (d.prazo || '—');

                // Solicitante
                document.getElementById('modalShowTarefaSolicitante').textContent = d.solicitante;

                // Destino
                const destinoEl = document.getElementById('modalShowTarefaDestino');
                const iconClass = d.destinoTipo === 'user' ? 'fa-user' : (d.destinoTipo === 'dep' ? 'fa-building' : 'fa-minus');
                destinoEl.innerHTML = '<i class="fas ' + iconClass + ' text-secondary me-1"></i> ' + d.destino;

                // Criado em
                document.getElementById('modalShowTarefaCriado').textContent = d.criado;

                // Open modal
                const modal = new bootstrap.Modal(document.getElementById('modalDetalheTarefaShow'));
                modal.show();
            });

            // Clicar em qualquer parte da linha da tarefa (exceto controlos de ação) também abre o modal
            document.addEventListener('click', function(e) {
                if (e.target.closest('.btn-ver-tarefa-show')) return; // título já é tratado acima
                if (e.target.closest('a, button, select, input, label, form, [data-bs-toggle]')) return; // ignora ações
                const row = e.target.closest('tr.tarefa-row');
                if (!row) return;
                const link = row.querySelector('.btn-ver-tarefa-show');
                if (link) link.click();
            });

            // Modal de visualizar OCR do Anexo
            let currentOcrAnexoId = null;

            function carregarDadosOcr(anexoId) {
                const contentContainer = document.getElementById('modalOcrContentContainer');
                const preEl = document.getElementById('modalOcrAnexoConteudo');
                const loadingEl = document.getElementById('modalOcrLoading');
                const btnCopiar = document.getElementById('btnCopiarOcr');
                const badgeEl = document.getElementById('modalOcrStatusBadge');
                const metodoEl = document.getElementById('modalOcrMetodoInfo');
                const wordCountEl = document.getElementById('modalOcrWordCount');
                const errorAlert = document.getElementById('modalOcrErrorAlert');
                const errorMsg = document.getElementById('modalOcrErrorMsg');

                contentContainer.classList.add('d-none');
                loadingEl.classList.remove('d-none');
                errorAlert.classList.add('d-none');

                fetch(`/documentos-entradas/{{ $doc->id }}/anexos/${anexoId}/ocr`)
                    .then(response => response.json())
                    .then(data => {
                        loadingEl.classList.add('d-none');
                        contentContainer.classList.remove('d-none');

                        // Status Badge
                        if (data.ocr_status_badge) {
                            badgeEl.className = `badge ${data.ocr_status_badge.class || 'bg-secondary'}`;
                            badgeEl.innerHTML = `<i class="${data.ocr_status_badge.icon || 'fas fa-info-circle'} me-1"></i> ${data.ocr_status_badge.label || data.ocr_status}`;
                        } else {
                            badgeEl.className = 'badge bg-secondary';
                            badgeEl.textContent = data.ocr_status || 'Pendente';
                        }

                        // Metodo Info & Word Count
                        let metodoTexto = '';
                        if (data.ocr_metodo === 'PDF_NATIVO') {
                            metodoTexto = 'Extração Direta (PDF Pesquisável)';
                        } else if (data.ocr_metodo === 'TESSERACT_OCR') {
                            metodoTexto = 'Tesseract OCR (PDF Escaneado)';
                        } else if (data.ocr_metodo === 'IMAGEM_OCR') {
                            metodoTexto = 'Tesseract OCR (Imagem)';
                        } else if (data.ocr_metodo) {
                            metodoTexto = data.ocr_metodo;
                        }
                        metodoEl.textContent = metodoTexto;
                        wordCountEl.textContent = (data.ocr_palavras_count || 0) + ' palavras' + (data.ocr_processado_em ? ` • ${data.ocr_processado_em}` : '');

                        // Error handling
                        if (data.ocr_status === 'FALHA' && data.ocr_erro) {
                            errorAlert.classList.remove('d-none');
                            errorMsg.textContent = data.ocr_erro;
                        }

                        if (data.texto_extraido && data.texto_extraido.trim() !== '') {
                            preEl.textContent = data.texto_extraido;
                            btnCopiar.classList.remove('d-none');
                        } else {
                            if (data.ocr_status === 'PROCESSANDO') {
                                preEl.innerHTML = '<span class="text-warning"><i class="fas fa-spinner fa-spin me-1"></i> O processo de OCR está em execução em segundo plano. Por favor, aguarde alguns instantes.</span>';
                            } else if (data.ocr_status === 'FALHA') {
                                preEl.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i> Falha ao extrair texto deste anexo.</span>';
                            } else {
                                preEl.innerHTML = '<span class="text-muted italic"><i class="fas fa-info-circle me-1"></i> Não foi possível extrair nenhum texto deste anexo ou o OCR ainda não foi iniciado.</span>';
                            }
                            btnCopiar.classList.add('d-none');
                        }
                    })
                    .catch(err => {
                        loadingEl.classList.add('d-none');
                        contentContainer.classList.remove('d-none');
                        preEl.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i> Erro ao carregar os dados de OCR.</span>';
                        btnCopiar.classList.add('d-none');
                    });
            }

            document.querySelectorAll('.btn-ver-ocr').forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    currentOcrAnexoId = this.getAttribute('data-anexo-id');
                    const nome = this.getAttribute('data-nome');

                    document.getElementById('modalOcrAnexoNome').textContent = nome;

                    const modal = new bootstrap.Modal(document.getElementById('modalVerOcrAnexo'));
                    modal.show();

                    carregarDadosOcr(currentOcrAnexoId);
                });
            });

            // Botão Reprocessar OCR
            const btnReprocessar = document.getElementById('btnReprocessarOcr');
            if (btnReprocessar) {
                btnReprocessar.addEventListener('click', function() {
                    if (!currentOcrAnexoId) return;

                    const originalHTML = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> A reprocessar...';
                    this.disabled = true;

                    fetch(`/documentos-entradas/{{ $doc->id }}/anexos/${currentOcrAnexoId}/reprocessar-ocr`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}'
                        }
                    })
                    .then(response => response.json())
                    .then(res => {
                        this.innerHTML = '<i class="fas fa-check me-1"></i> Job Enfileirado!';
                        setTimeout(() => {
                            this.innerHTML = originalHTML;
                            this.disabled = false;
                            carregarDadosOcr(currentOcrAnexoId);
                        }, 1200);
                    })
                    .catch(err => {
                        if (window.Toast) {
                            window.Toast.error('Erro no OCR', 'Não foi possível solicitar o reprocessamento do anexo.');
                        }
                        this.innerHTML = originalHTML;
                        this.disabled = false;
                    });
                });
            }

            // Copiar texto OCR
            const btnCopiar = document.getElementById('btnCopiarOcr');
            if (btnCopiar) {
                btnCopiar.addEventListener('click', function() {
                    const text = document.getElementById('modalOcrAnexoConteudo').textContent;
                    navigator.clipboard.writeText(text).then(() => {
                        const originalHTML = this.innerHTML;
                        this.innerHTML = '<i class="fas fa-check text-success"></i> Copiado!';
                        this.classList.remove('btn-outline-secondary');
                        this.classList.add('btn-outline-success');
                        setTimeout(() => {
                            this.innerHTML = originalHTML;
                            this.classList.remove('btn-outline-success');
                            this.classList.add('btn-outline-secondary');
                        }, 2000);
                    });
                });
            }
        });
    </script>
