{{--
    Controlador do visualizador do documento. Um só para todos os ficheiros:
    trocar de ficheiro cancela o carregamento anterior em vez de abrir outro
    visualizador. Recebe de _visualizador o prefixo dos IDs e a sequência.
--}}
@if ($temPdf)
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
@endif

<script>
    (function (config) {
        document.addEventListener('DOMContentLoaded', function () {
            const el = sufixo => document.getElementById(config.prefixo + '_' + sufixo);
            const ficheiros = config.ficheiros;

            const cartao = document.getElementById(config.prefixo);
            const frame = el('frame');
            const canvas = el('canvas');
            const imagem = el('imagem');
            const carregando = el('carregando');
            const erro = el('erro');
            const aviso = el('aviso');

            if (!cartao || !frame || !canvas || !imagem || !carregando || !erro || !ficheiros.length) return;

            const ctx = canvas.getContext('2d');
            const ESCALA_BASE = 1.25;
            const ESCALA_MIN = 0.5;
            const ESCALA_MAX = 3.0;
            const pdfDisponivel = typeof pdfjsLib !== 'undefined';

            if (pdfDisponivel) {
                pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
            }

            let atual = -1;
            // Cada troca de ficheiro incrementa o pedido; uma resposta que chegue
            // depois de outra troca já não é a do ficheiro pedido e é descartada.
            let pedido = 0;
            let renderizacao = 0;
            let loadingTask = null;
            let pdfDoc = null;
            let renderTask = null;
            let pagina = 1;
            let escala = ESCALA_BASE;
            // Ligado ao entrar ou sair do ecrã inteiro; desligado quando o
            // utilizador mexe no zoom, para não contrariar o que escolheu.
            let ajustarLargura = false;
            // Ficheiros que já falharam nesta cadeia: a passagem automática ao
            // seguinte nunca volta a eles, por isso termina sempre.
            const falhados = new Set();

            function mostrar(estado) {
                carregando.classList.toggle('d-none', estado !== 'carregando');
                canvas.classList.toggle('d-none', estado !== 'pdf');
                imagem.classList.toggle('d-none', estado !== 'imagem');
                erro.classList.toggle('d-none', estado !== 'erro');
            }

            function mostrarAviso(texto) {
                if (!aviso) return;
                aviso.textContent = texto;
                aviso.classList.toggle('d-none', !texto);
            }

            function libertarPdf() {
                if (renderTask) {
                    renderTask.cancel();
                    renderTask = null;
                }
                // destroy() da loadingTask aborta um download a meio e destrói
                // também o documento já aberto — PDFDocumentProxy.destroy()
                // delega nela.
                if (loadingTask) {
                    loadingTask.destroy();
                    loadingTask = null;
                }
                pdfDoc = null;
            }

            function marcarLista() {
                document.querySelectorAll('[data-doc-viewer-item]').forEach(function (item) {
                    const ativo = Number(item.dataset.docViewerItem) === atual;
                    item.classList.toggle('is-visualizado', ativo);
                    if (ativo) {
                        item.setAttribute('aria-current', 'true');
                    } else {
                        item.removeAttribute('aria-current');
                    }
                });
            }

            function atualizarCabecalho(ficheiro) {
                const nome = el('nome');
                if (nome) {
                    nome.textContent = ficheiro.nome;
                    nome.title = ficheiro.nome;
                }
                const abrir = el('abrir');
                if (abrir) abrir.href = ficheiro.url;
                const erroLink = el('erroLink');
                if (erroLink) erroLink.href = ficheiro.url;

                el('controlosPdf')?.classList.toggle('d-none', ficheiro.tipo !== 'pdf');

                const posicao = el('fichPosicao');
                if (posicao) posicao.textContent = (atual + 1) + ' de ' + ficheiros.length;
                const anterior = el('fichAnterior');
                if (anterior) anterior.disabled = atual <= 0;
                const seguinte = el('fichSeguinte');
                if (seguinte) seguinte.disabled = atual >= ficheiros.length - 1;

                imagem.alt = ficheiro.nome;
            }

            function proximoPorTentar(desde) {
                for (let passo = 1; passo < ficheiros.length; passo++) {
                    const indice = (desde + passo) % ficheiros.length;
                    if (!falhados.has(indice)) return indice;
                }
                return null;
            }

            function falhou(indice) {
                falhados.add(indice);
                const seguinte = proximoPorTentar(indice);

                if (seguinte === null) {
                    mostrarAviso('');
                    mostrar('erro');
                    return;
                }

                mostrarAviso('Não foi possível abrir «' + ficheiros[indice].nome + '». A mostrar o ficheiro seguinte.');
                carregar(seguinte, { automatico: true });
            }

            function renderizar(num, inicial) {
                if (!pdfDoc) return;

                const meuPedido = pedido;
                const minhaRenderizacao = ++renderizacao;
                const doc = pdfDoc;
                el('pagina').textContent = num;

                doc.getPage(num).then(function (page) {
                    // Só a última página pedida chega ao canvas.
                    if (meuPedido !== pedido || minhaRenderizacao !== renderizacao || doc !== pdfDoc) return;

                    if (renderTask) {
                        renderTask.cancel();
                        renderTask = null;
                    }

                    // Por página: as páginas de um PDF podem ter larguras diferentes.
                    if (ajustarLargura) {
                        escala = escalaParaLargura(page);
                        atualizarIndicadorZoom();
                    }

                    const viewport = page.getViewport({ scale: escala });
                    // Resolução HD para ecrãs com alta densidade de píxeis.
                    const outputScale = window.devicePixelRatio || 1;

                    canvas.width = Math.floor(viewport.width * outputScale);
                    canvas.height = Math.floor(viewport.height * outputScale);
                    canvas.style.width = Math.floor(viewport.width) + 'px';
                    canvas.style.height = Math.floor(viewport.height) + 'px';

                    const tarefa = page.render({
                        canvasContext: ctx,
                        transform: outputScale !== 1 ? [outputScale, 0, 0, outputScale, 0, 0] : null,
                        viewport: viewport,
                    });
                    renderTask = tarefa;

                    tarefa.promise.then(function () {
                        if (renderTask === tarefa) renderTask = null;
                        if (meuPedido === pedido) mostrar('pdf');
                    }).catch(function (motivo) {
                        if (renderTask === tarefa) renderTask = null;
                        // Cancelada por uma troca de página, zoom ou ficheiro: não é erro.
                        if (motivo && motivo.name === 'RenderingCancelledException') return;
                        if (meuPedido !== pedido) return;
                        console.error('Erro ao renderizar página:', motivo);
                        if (inicial) falhou(atual);
                    });
                }).catch(function (motivo) {
                    if (meuPedido !== pedido) return;
                    console.error('Erro ao obter a página:', motivo);
                    if (inicial) falhou(atual);
                });
            }

            function carregar(indice, opcoes) {
                const ficheiro = ficheiros[indice];
                if (!ficheiro) return;

                // Um clique do utilizador volta a tentar o ficheiro, mesmo que já
                // tenha falhado; só a passagem automática respeita os falhados.
                if (!(opcoes && opcoes.automatico)) {
                    falhados.delete(indice);
                    mostrarAviso('');
                }

                const meuPedido = ++pedido;
                libertarPdf();
                atual = indice;
                pagina = 1;
                atualizarCabecalho(ficheiro);
                marcarLista();
                mostrar('carregando');

                if (ficheiro.tipo === 'imagem') {
                    imagem.onload = function () { if (meuPedido === pedido) mostrar('imagem'); };
                    imagem.onerror = function () { if (meuPedido === pedido) falhou(indice); };
                    imagem.src = ficheiro.url;
                    return;
                }

                imagem.onload = imagem.onerror = null;
                imagem.removeAttribute('src');

                if (!pdfDisponivel) {
                    falhou(indice);
                    return;
                }

                const tarefa = pdfjsLib.getDocument(ficheiro.url);
                loadingTask = tarefa;

                tarefa.promise.then(function (doc) {
                    if (meuPedido !== pedido) return;
                    pdfDoc = doc;
                    el('paginas').textContent = doc.numPages;
                    renderizar(pagina, true);
                }).catch(function (motivo) {
                    // Destruída por uma troca de ficheiro: não é falha deste.
                    if (meuPedido !== pedido) return;
                    console.error('Erro no carregamento do PDF.js:', motivo);
                    falhou(indice);
                });
            }

            function escalaParaLargura(page) {
                const estilo = getComputedStyle(frame);
                const util = frame.clientWidth - parseFloat(estilo.paddingLeft) - parseFloat(estilo.paddingRight);
                const natural = page.getViewport({ scale: 1 }).width;
                if (!(util > 0) || !natural) return escala;
                return Math.min(ESCALA_MAX, Math.max(ESCALA_MIN, util / natural));
            }

            function atualizarIndicadorZoom() {
                const nivel = el('zoomNivel');
                if (nivel) nivel.textContent = Math.round((escala / ESCALA_BASE) * 100) + '%';
            }

            // Zoom escolhido pelo utilizador: sai do ajuste à largura.
            function mudarZoom(novaEscala) {
                ajustarLargura = false;
                escala = Math.min(ESCALA_MAX, Math.max(ESCALA_MIN, novaEscala));
                atualizarIndicadorZoom();
                if (pdfDoc) renderizar(pagina, false);
            }

            el('paginaAnterior')?.addEventListener('click', function () {
                if (!pdfDoc || pagina <= 1) return;
                pagina--;
                renderizar(pagina, false);
            });

            el('paginaSeguinte')?.addEventListener('click', function () {
                if (!pdfDoc || pagina >= pdfDoc.numPages) return;
                pagina++;
                renderizar(pagina, false);
            });

            el('zoomIn')?.addEventListener('click', function () {
                if (escala >= ESCALA_MAX) return;
                mudarZoom(escala + 0.25);
            });

            el('zoomOut')?.addEventListener('click', function () {
                if (escala <= ESCALA_MIN) return;
                mudarZoom(escala - 0.25);
            });

            el('zoomReset')?.addEventListener('click', function () {
                mudarZoom(ESCALA_BASE);
            });

            // ---------- Ecrã inteiro ----------
            const botaoExpandir = el('expandir');
            let estavaExpandido = false;

            function cartaoExpandido() {
                return document.fullscreenElement === cartao || cartao.classList.contains('is-ecra-inteiro');
            }

            // Único sítio onde o estado se aplica. Corre a partir do evento
            // `fullscreenchange`, e não do clique: sair por Esc, F11 ou pelo
            // próprio browser também passa por aqui.
            function aplicarEstadoExpandido() {
                const expandido = cartaoExpandido();
                if (expandido === estavaExpandido) return;
                estavaExpandido = expandido;

                if (botaoExpandir) {
                    botaoExpandir.setAttribute('aria-pressed', expandido ? 'true' : 'false');
                    botaoExpandir.setAttribute('aria-label', expandido ? 'Sair do ecrã inteiro' : 'Ver em ecrã inteiro');
                    botaoExpandir.title = expandido ? 'Sair do ecrã inteiro (Esc)' : 'Ver em ecrã inteiro';
                    botaoExpandir.querySelector('i').className = expandido
                        ? 'fas fa-down-left-and-up-right-to-center'
                        : 'fas fa-up-right-and-down-left-from-center';
                    const texto = botaoExpandir.querySelector('span');
                    if (texto) texto.textContent = expandido ? 'Reduzir' : 'Expandir';
                }

                // Mede depois de o browser aplicar o novo tamanho.
                ajustarLargura = true;
                requestAnimationFrame(function () {
                    if (pdfDoc) renderizar(pagina, false);
                });
            }

            function entrarPorCss() {
                cartao.classList.add('is-ecra-inteiro');
                document.body.classList.add('doc-viewer-ecra-inteiro');
                aplicarEstadoExpandido();
            }

            function sairPorCss() {
                cartao.classList.remove('is-ecra-inteiro');
                document.body.classList.remove('doc-viewer-ecra-inteiro');
                aplicarEstadoExpandido();
            }

            botaoExpandir?.addEventListener('click', function () {
                if (document.fullscreenElement === cartao) {
                    document.exitFullscreen().catch(function () {});
                    return;
                }
                if (cartao.classList.contains('is-ecra-inteiro')) {
                    sairPorCss();
                    return;
                }
                // Tem de ser chamado aqui, no clique: o browser exige um gesto
                // do utilizador. Sem a API (Safari em iPhone) ou recusado, cobre
                // a página por CSS.
                if (document.fullscreenEnabled && cartao.requestFullscreen) {
                    cartao.requestFullscreen().catch(entrarPorCss);
                } else {
                    entrarPorCss();
                }
            });

            document.addEventListener('fullscreenchange', aplicarEstadoExpandido);

            // Em ecrã inteiro verdadeiro o browser trata do Esc; no modo por CSS
            // é preciso fazê-lo aqui.
            document.addEventListener('keydown', function (evento) {
                if (evento.key === 'Escape' && cartao.classList.contains('is-ecra-inteiro')) {
                    sairPorCss();
                }
            });

            let esperaRedimensionar = null;
            window.addEventListener('resize', function () {
                if (!ajustarLargura) return;
                clearTimeout(esperaRedimensionar);
                esperaRedimensionar = setTimeout(function () {
                    if (ajustarLargura && pdfDoc) renderizar(pagina, false);
                }, 150);
            });

            el('fichAnterior')?.addEventListener('click', function () {
                if (atual > 0) carregar(atual - 1);
            });

            el('fichSeguinte')?.addEventListener('click', function () {
                if (atual < ficheiros.length - 1) carregar(atual + 1);
            });

            document.querySelectorAll('[data-doc-viewer-ver]').forEach(function (botao) {
                botao.addEventListener('click', function () {
                    carregar(Number(botao.dataset.docViewerVer));
                    cartao.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });

            carregar(0);
        });
    })({
        prefixo: @json($visor),
        ficheiros: @json($ficheirosVisualizaveis),
    });
</script>
