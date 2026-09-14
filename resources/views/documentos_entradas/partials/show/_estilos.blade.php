    <style>
        /* Document Details Modern Polish */
        .doc-header-action-btn {
            font-size: 0.82rem;
            font-weight: 600;
            letter-spacing: 0.2px;
            padding: 0.45rem 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 0.375rem;
            text-transform: uppercase;
        }
        .doc-metadata-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 0.85rem;
        }
        .doc-meta-item {
            background-color: #f8fafc;
            border: 1px solid #edf2f7;
            border-radius: 0.5rem;
            padding: 0.75rem 0.9rem;
            transition: all 0.2s ease;
        }
        .doc-meta-item:hover {
            background-color: #f1f5f9;
            border-color: #e2e8f0;
        }
        .doc-meta-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.4px;
            margin-bottom: 0.25rem;
            display: block;
        }
        .doc-meta-value {
            font-size: 0.88rem;
            font-weight: 600;
            color: #1e293b;
            word-break: break-word;
        }

        /* Stepper Modern Ribbon */
        @keyframes pulse-step {
            0% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.4); }
            70% { box-shadow: 0 0 0 6px rgba(37, 99, 235, 0); }
            100% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0); }
        }

        /* Tabs & Activity Styling */

        /* Unified Timeline Activity Feed */

        /* Approval Mini Cards in Sidebar */
        .approval-mini-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.85rem;
            transition: border-color 0.2s ease;
        }
        .approval-mini-card.is-aprovado {
            border-left: 3.5px solid #10b981;
        }
        .approval-mini-card.is-pendente {
            border-left: 3.5px solid #f59e0b;
        }
        .approval-mini-card.is-rejeitado {
            border-left: 3.5px solid #ef4444;
        }

        @media (max-width: 768px) {
        }

        /* ---------- Cabeçalho compacto do documento ---------- */
        .doc-topbar {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.65rem 0.9rem;
            margin: -1.5rem -1.5rem 1rem;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
        }
        .doc-topbar-numero { font-size: 1.05rem; font-weight: 700; color: #0f172a; }
        .doc-topbar-badge { font-size: 0.68rem; padding: 0.25rem 0.6rem; }
        .doc-topbar-assunto {
            font-size: 0.85rem;
            color: #334155;
            margin-top: 0.15rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 62ch;
        }
        .min-w-0 { min-width: 0; }

        /* ---------- Faixa do pipeline ---------- */
        .doc-pipeline {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.85rem 1rem;
            margin-bottom: 1rem;
        }
        .doc-pipeline-titulo { font-size: 0.8rem; font-weight: 700; color: #0f172a; }
        .doc-pipeline-faixa { display: flex; align-items: flex-start; gap: 0; }
        .doc-pipeline-marco {
            position: relative;
            flex: 1 1 0;
            text-align: center;
            font-size: 0.66rem;
            color: #94a3b8;
            padding-top: 1.9rem;
            min-width: 0;
        }
        .doc-pipeline-marco::before {
            content: '';
            position: absolute;
            top: 0.72rem;
            left: -50%;
            width: 100%;
            height: 2px;
            background: #e2e8f0;
        }
        .doc-pipeline-marco:first-child::before { display: none; }
        .doc-pipeline-bola {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f1f5f9;
            color: #94a3b8;
            border: 2px solid #e2e8f0;
            font-size: 0.6rem;
        }
        .doc-pipeline-rotulo {
            display: block;
            padding: 0 0.2rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .doc-pipeline-marco.is-completed { color: #15803d; }
        .doc-pipeline-marco.is-completed .doc-pipeline-bola { background: #dcfce7; color: #15803d; border-color: #86efac; }
        .doc-pipeline-marco.is-completed::before { background: #86efac; }
        .doc-pipeline-marco.is-active { color: #1d4ed8; font-weight: 700; }
        .doc-pipeline-marco.is-active .doc-pipeline-bola { background: #dbeafe; color: #1d4ed8; border-color: #93c5fd; }
        .doc-pipeline-marco.is-rejected { color: #b91c1c; }
        .doc-pipeline-marco.is-rejected .doc-pipeline-bola { background: #fee2e2; color: #b91c1c; border-color: #fca5a5; }

        /* ---------- Visualizador do documento ---------- */
        .doc-viewer-frame {
            height: 70vh;
            min-height: 420px;
            border: 0;
            overflow: auto;
        }
        .doc-viewer-card.is-expandido .doc-viewer-frame { height: calc(100vh - 8rem); }

        /* ---------- Percurso: chips ---------- */
        .percurso-chip {
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #475569;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            padding: 0.3rem 0.8rem;
        }
        .percurso-chip:hover { background: #f8fafc; color: #0f172a; }
        .percurso-chip.active {
            background: #1d4ed8;
            border-color: #1d4ed8;
            color: #fff;
        }
        .percurso-chip.active .badge { background: rgba(255, 255, 255, 0.25) !important; color: #fff !important; }

        /* ---------- Percurso: linha do tempo densa ---------- */
        .percurso-evento { border-bottom: 1px solid #f1f5f9; }
        .percurso-evento:last-child { border-bottom: 0; }
        .percurso-evento[hidden] { display: none; }
        .percurso-evento-cabeca {
            display: grid;
            grid-template-columns: 2rem 1fr auto auto 1rem;
            align-items: center;
            gap: 0.75rem;
            width: 100%;
            padding: 0.55rem 1rem;
            background: none;
            border: 0;
            text-align: left;
            color: inherit;
        }
        .percurso-evento-cabeca:hover { background: #f8fafc; }
        .percurso-evento-cabeca:focus-visible { outline: 2px solid #1d4ed8; outline-offset: -2px; }
        .percurso-marcador {
            width: 1.6rem;
            height: 1.6rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.62rem;
            color: #fff;
        }
        .percurso-titulo {
            font-size: 0.85rem;
            font-weight: 600;
            color: #0f172a;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .percurso-meta {
            display: block;
            font-size: 0.72rem;
            font-weight: 400;
            color: #64748b;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .percurso-selo { font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.3px; padding: 0.2rem 0.55rem; }
        .percurso-data { font-size: 0.74rem; color: #64748b; white-space: nowrap; }
        .percurso-seta { font-size: 0.7rem; color: #94a3b8; transition: transform 0.15s ease; }
        .percurso-evento-cabeca[aria-expanded="true"] .percurso-seta { transform: rotate(180deg); }
        .percurso-evento-detalhe { padding: 0 1rem 0.9rem 3.75rem; }
        .percurso-descricao {
            font-size: 0.82rem;
            color: #475569;
            white-space: pre-line;
            line-height: 1.5;
            margin-bottom: 0.5rem;
        }
        .percurso-detalhe-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.74rem;
            color: #64748b;
            border-top: 1px solid #f1f5f9;
            padding-top: 0.5rem;
        }

        @media (max-width: 767.98px) {
            .doc-topbar { position: static; margin: -1rem -1rem 1rem; flex-wrap: wrap; }
            .doc-topbar-assunto { white-space: normal; max-width: none; }
            .doc-pipeline-rotulo { font-size: 0.6rem; }
            .percurso-evento-cabeca { grid-template-columns: 1.8rem 1fr 1rem; row-gap: 0.25rem; }
            .percurso-selo, .percurso-data { grid-column: 2; justify-self: start; }
            .percurso-evento-detalhe { padding-left: 1rem; }
            .doc-viewer-frame { height: 55vh; min-height: 320px; }
        }
    </style>
