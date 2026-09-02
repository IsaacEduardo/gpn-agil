{{-- Componente Enterprise de Toast Notifications (Sonner / Radix UI inspired) --}}
<div id="toast-portal" class="toast-portal position-fixed top-0 end-0 p-3" style="z-index: 9999; pointer-events: none;" aria-live="polite" aria-atomic="true">
    <div id="toast-stack" class="d-flex flex-column gap-2" style="max-width: 420px; width: 100vw; pointer-events: auto;"></div>
</div>

<style>
    .toast-portal {
        max-width: 100%;
    }
    .gov-toast {
        background: #ffffff;
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 0.875rem;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
        padding: 0.875rem 1rem;
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        opacity: 0;
        transform: translateX(100%) scale(0.95);
        animation: toastSlideIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    .gov-toast.toast-hiding {
        animation: toastSlideOut 0.25s ease-in forwards;
    }
    @keyframes toastSlideIn {
        from {
            opacity: 0;
            transform: translateX(100%) scale(0.92);
        }
        to {
            opacity: 1;
            transform: translateX(0) scale(1);
        }
    }
    @keyframes toastSlideOut {
        from {
            opacity: 1;
            transform: translateX(0) scale(1);
            max-height: 200px;
            margin-bottom: 0.5rem;
        }
        to {
            opacity: 0;
            transform: translateX(110%) scale(0.92);
            max-height: 0;
            margin-bottom: 0;
            padding-top: 0;
            padding-bottom: 0;
            border-width: 0;
        }
    }
    .gov-toast__icon-wrapper {
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 0.625rem;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1rem;
    }
    .gov-toast__content {
        flex-grow: 1;
        min-width: 0;
        padding-top: 0.125rem;
    }
    .gov-toast__title {
        font-size: 0.875rem;
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 0.2rem;
        line-height: 1.3;
    }
    .gov-toast__message {
        font-size: 0.8125rem;
        color: #475569;
        line-height: 1.4;
        word-break: break-word;
    }
    .gov-toast__action-btn {
        margin-top: 0.5rem;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.25rem 0.625rem;
        border-radius: 0.375rem;
        background: #f1f5f9;
        color: #1e293b;
        text-decoration: none;
        border: 1px solid #e2e8f0;
        transition: all 0.15s ease;
    }
    .gov-toast__action-btn:hover {
        background: #e2e8f0;
        color: #0f172a;
    }
    .gov-toast__close {
        background: transparent;
        border: none;
        color: #94a3b8;
        padding: 0.25rem;
        border-radius: 0.375rem;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.15s ease;
        margin-left: auto;
    }
    .gov-toast__close:hover {
        color: #334155;
        background: #f1f5f9;
    }
    .gov-toast__progress {
        position: absolute;
        bottom: 0;
        left: 0;
        height: 3px;
        width: 100%;
        background: rgba(0, 0, 0, 0.04);
    }
    .gov-toast__progress-bar {
        height: 100%;
        width: 100%;
        transform-origin: left;
        animation: toastProgress linear forwards;
    }
    @keyframes toastProgress {
        from { transform: scaleX(1); }
        to { transform: scaleX(0); }
    }

    /* Semantic variants */
    .gov-toast--success .gov-toast__icon-wrapper {
        background: #ecfdf5;
        color: #059669;
        border: 1px solid #d1fae5;
    }
    .gov-toast--success .gov-toast__progress-bar {
        background: #059669;
    }

    .gov-toast--error .gov-toast__icon-wrapper {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fee2e2;
    }
    .gov-toast--error .gov-toast__progress-bar {
        background: #dc2626;
    }

    .gov-toast--warning .gov-toast__icon-wrapper {
        background: #fffbeb;
        color: #d97706;
        border: 1px solid #fef3c7;
    }
    .gov-toast--warning .gov-toast__progress-bar {
        background: #d97706;
    }

    .gov-toast--info .gov-toast__icon-wrapper {
        background: #eff6ff;
        color: #2563eb;
        border: 1px solid #dbeafe;
    }
    .gov-toast--info .gov-toast__progress-bar {
        background: #2563eb;
    }
</style>

<script>
    (function() {
        const MAX_TOASTS = 3;
        const DEFAULT_DURATION = 4500;

        const ICONS = {
            success: 'fas fa-check',
            error: 'fas fa-times',
            warning: 'fas fa-exclamation-triangle',
            info: 'fas fa-info'
        };

        const TITLES = {
            success: 'Sucesso',
            error: 'Atenção / Erro',
            warning: 'Aviso',
            info: 'Informação'
        };

        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        window.Toast = {
            show: function(options) {
                const stack = document.getElementById('toast-stack');
                if (!stack) return null;

                const type = ['success', 'error', 'warning', 'info'].includes(options.type) ? options.type : 'info';
                const title = options.title || TITLES[type];
                const message = options.message || '';
                const duration = options.duration !== undefined ? options.duration : (type === 'error' ? 6500 : DEFAULT_DURATION);
                const action = options.action || null; // { text: 'Ver', url: '#', onClick: fn }

                // Gerenciar limite de stacking (remove os mais antigos se exceder MAX_TOASTS)
                const currentToasts = stack.querySelectorAll('.gov-toast:not(.toast-hiding)');
                if (currentToasts.length >= MAX_TOASTS) {
                    for (let i = 0; i <= currentToasts.length - MAX_TOASTS; i++) {
                        if (currentToasts[i]) {
                            window.Toast.dismiss(currentToasts[i]);
                        }
                    }
                }

                const toastEl = document.createElement('div');
                toastEl.className = `gov-toast gov-toast--${type}`;
                toastEl.setAttribute('role', 'alert');

                let actionHtml = '';
                if (action && action.text) {
                    const href = action.url ? `href="${escapeHtml(action.url)}"` : 'href="javascript:void(0)"';
                    actionHtml = `<a ${href} class="gov-toast__action-btn">${escapeHtml(action.text)} <i class="fas fa-arrow-right ms-1" style="font-size: 0.65rem;"></i></a>`;
                }

                let progressHtml = '';
                if (duration > 0) {
                    progressHtml = `
                        <div class="gov-toast__progress">
                            <div class="gov-toast__progress-bar" style="animation-duration: ${duration}ms;"></div>
                        </div>
                    `;
                }

                toastEl.innerHTML = `
                    <div class="gov-toast__icon-wrapper">
                        <i class="${ICONS[type]}"></i>
                    </div>
                    <div class="gov-toast__content">
                        ${title ? `<div class="gov-toast__title">${escapeHtml(title)}</div>` : ''}
                        <div class="gov-toast__message">${message}</div>
                        ${actionHtml}
                    </div>
                    <button type="button" class="gov-toast__close" aria-label="Fechar notificação">
                        <i class="fas fa-times" style="font-size: 0.75rem;"></i>
                    </button>
                    ${progressHtml}
                `;

                // Configurar clique de ação personalizada
                if (action && typeof action.onClick === 'function') {
                    const btn = toastEl.querySelector('.gov-toast__action-btn');
                    if (btn) {
                        btn.addEventListener('click', function(e) {
                            action.onClick(e);
                            window.Toast.dismiss(toastEl);
                        });
                    }
                }

                // Botão de fechar
                const closeBtn = toastEl.querySelector('.gov-toast__close');
                if (closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        window.Toast.dismiss(toastEl);
                    });
                }

                // Auto dismiss timer
                let timeoutId = null;
                if (duration > 0) {
                    timeoutId = setTimeout(() => {
                        window.Toast.dismiss(toastEl);
                    }, duration);

                    // Pausar no hover
                    toastEl.addEventListener('mouseenter', () => {
                        clearTimeout(timeoutId);
                        const pBar = toastEl.querySelector('.gov-toast__progress-bar');
                        if (pBar) pBar.style.animationPlayState = 'paused';
                    });

                    toastEl.addEventListener('mouseleave', () => {
                        const pBar = toastEl.querySelector('.gov-toast__progress-bar');
                        if (pBar) pBar.style.animationPlayState = 'running';
                        timeoutId = setTimeout(() => {
                            window.Toast.dismiss(toastEl);
                        }, 2000);
                    });
                }

                stack.appendChild(toastEl);
                return toastEl;
            },

            success: function(titleOrMessage, messageOrOptions, options) {
                return this._normalizeArgs('success', titleOrMessage, messageOrOptions, options);
            },

            error: function(titleOrMessage, messageOrOptions, options) {
                return this._normalizeArgs('error', titleOrMessage, messageOrOptions, options);
            },

            warning: function(titleOrMessage, messageOrOptions, options) {
                return this._normalizeArgs('warning', titleOrMessage, messageOrOptions, options);
            },

            info: function(titleOrMessage, messageOrOptions, options) {
                return this._normalizeArgs('info', titleOrMessage, messageOrOptions, options);
            },

            _normalizeArgs: function(type, arg1, arg2, arg3) {
                let title = null;
                let message = '';
                let opts = {};

                if (typeof arg1 === 'string' && typeof arg2 === 'string') {
                    title = arg1;
                    message = arg2;
                    opts = arg3 || {};
                } else if (typeof arg1 === 'string' && typeof arg2 === 'object') {
                    title = null;
                    message = arg1;
                    opts = arg2 || {};
                } else if (typeof arg1 === 'string') {
                    message = arg1;
                    opts = {};
                }

                return this.show({
                    type: type,
                    title: title,
                    message: message,
                    ...opts
                });
            },

            dismiss: function(toastEl) {
                if (!toastEl || toastEl.classList.contains('toast-hiding')) return;
                toastEl.classList.add('toast-hiding');
                setTimeout(() => {
                    if (toastEl.parentNode) {
                        toastEl.parentNode.removeChild(toastEl);
                    }
                }, 250);
            },

            dismissAll: function() {
                const stack = document.getElementById('toast-stack');
                if (!stack) return;
                const toasts = stack.querySelectorAll('.gov-toast');
                toasts.forEach(t => this.dismiss(t));
            }
        };

        // Retrocompatibilidade total com window.showToast
        window.showToast = function(message, type = 'success', duration = 4500) {
            return window.Toast[type] ? window.Toast[type](message, { duration }) : window.Toast.info(message, { duration });
        };

        // Disparo automático de Flash Messages do Laravel no carregamento
        document.addEventListener('DOMContentLoaded', function() {
            @if (session('success'))
                window.Toast.success(@json(session('success')));
            @endif

            @if (session('error'))
                window.Toast.error(@json(session('error')));
            @endif

            @if (session('warning'))
                window.Toast.warning(@json(session('warning')));
            @endif

            @if (session('info'))
                window.Toast.info(@json(session('info')));
            @endif

            @if (isset($errors) && $errors->any())
                @php
                    $errorList = implode('<br>', $errors->all());
                @endphp
                window.Toast.error('Erro de Validação', {!! json_encode($errorList) !!}, { duration: 7000 });
            @endif
        });
    })();
</script>
