@extends('layouts.guest')

@section('title', 'Acesso ao Sistema')

@section('styles')
    <style>
        .login-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 1rem;
        }

        /* Cartão principal */
        .login-card {
            background: #ffffff;
            border: 1px solid var(--border-soft);
            border-radius: var(--radius-xl) !important;
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }

        /* Painel de marca (esquerda) — gradiente azul suave */
        .brand-panel {
            background: linear-gradient(160deg, #eff6ff 0%, #e0e7ff 100%);
            border-right: 1px solid var(--border-soft);
            position: relative;
            overflow: hidden;
        }

        .brand-panel::after {
            content: '';
            position: absolute;
            width: 360px;
            height: 360px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.10) 0%, transparent 70%);
            bottom: -30%;
            right: -25%;
            pointer-events: none;
        }

        .brand-logo {
            max-height: 130px;
            width: auto;
            filter: drop-shadow(0 10px 24px rgba(37, 99, 235, 0.18));
        }

        .brand-pill {
            display: inline-block;
            background: rgba(37, 99, 235, 0.08);
            border: 1px solid rgba(37, 99, 235, 0.18);
            color: var(--primary-accent);
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 0.3rem 0.9rem;
            border-radius: 999px;
        }

        .brand-title {
            color: #1e293b;
            font-weight: 800;
            font-size: 1.35rem;
            line-height: 1.25;
        }

        .brand-sub {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        /* Formulário (direita) */
        .form-panel { background: #ffffff; }

        .form-floating > .form-control {
            background-color: #f8fafc;
            border: 1px solid var(--border-soft);
            color: var(--text-main);
            border-radius: var(--radius-md);
        }

        .form-floating > .form-control:focus {
            background-color: #ffffff;
            border-color: var(--primary-accent);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        }

        .form-floating > label { color: var(--text-muted); }

        .login-submit {
            background: var(--primary-accent);
            border: none;
            border-radius: var(--radius-md);
            padding: 0.85rem;
            color: #fff;
            font-weight: 600;
            letter-spacing: 0.02em;
            transition: background-color .2s ease, transform .2s ease, box-shadow .2s ease;
            box-shadow: 0 8px 18px -8px rgba(37, 99, 235, 0.5);
        }

        .login-submit:hover:not(:disabled) {
            background: var(--primary-accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 12px 24px -8px rgba(37, 99, 235, 0.55);
        }

        .login-submit:disabled { opacity: 0.75; cursor: progress; }

        .forgot-link {
            color: var(--primary-accent);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .forgot-link:hover { text-decoration: underline; }

        .form-check-input:checked {
            background-color: var(--primary-accent);
            border-color: var(--primary-accent);
        }

        @media (max-width: 575.98px) {
            .form-panel { padding: 2rem 1.5rem !important; }
        }
    </style>
@endsection

@section('content')
    <div class="login-wrapper">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10 col-xl-9">
                    <div class="card login-card border-0">
                        <div class="row g-0">
                            <!-- Painel de marca institucional -->
                            <div class="col-md-5 brand-panel d-none d-md-flex flex-column align-items-center justify-content-center text-center p-5">
                                <div class="position-relative" style="z-index: 1;">
                                    <img src="{{ $dadosInstituicao->logo_url }}" alt="Insígnia institucional"
                                        class="img-fluid mb-4 brand-logo">
                                    <div class="brand-pill mb-3">{{ $dadosInstituicao->cabecalho_linha1 ?? 'República de Angola' }}</div>
                                    <h2 class="brand-title mb-2">{{ $dadosInstituicao->nome_oficial ?? 'Governo Provincial do Namibe' }}</h2>
                                    <p class="brand-sub mb-0">
                                        {{ $dadosInstituicao->cabecalho_linha2 ?? 'Sistema de Gestão Administrativa e Documental' }}
                                    </p>
                                </div>
                            </div>

                            <!-- Painel do formulário -->
                            <div class="col-md-7 form-panel p-5 d-flex flex-column justify-content-center">
                                <!-- Marca compacta para telas pequenas -->
                                <div class="text-center d-md-none mb-4">
                                    <img src="{{ $dadosInstituicao->logo_url }}" alt="Insígnia institucional"
                                        style="max-height: 72px;" class="mb-2">
                                    <div class="fw-bold" style="color:#1e293b;">{{ $dadosInstituicao->sigla ?? 'GPN' }}</div>
                                </div>

                                <div class="mb-4">
                                    <h1 class="h4 fw-bold mb-1" style="color:#1e293b;">Acesso ao Sistema</h1>
                                    <p class="text-muted small mb-0">Insira as suas credenciais para continuar</p>
                                </div>

                                @if ($errors->any())
                                    <div class="alert alert-danger border-0 rounded-3 py-2 px-3 small mb-3" role="alert" aria-live="assertive">
                                        <i class="fas fa-circle-exclamation me-2"></i>
                                        Não foi possível autenticar. Verifique os dados e tente novamente.
                                    </div>
                                @endif

                                <form method="POST" action="{{ route('login') }}" novalidate id="loginForm">
                                    @csrf

                                    <div class="form-floating mb-3">
                                        <input id="email" type="email"
                                            class="form-control @error('email') is-invalid @enderror" name="email"
                                            value="{{ old('email') }}" required autocomplete="email" autofocus
                                            placeholder="nome@exemplo.com">
                                        <label for="email"><i class="fas fa-envelope me-2 text-muted"></i>Endereço de E-mail</label>
                                        @error('email')
                                            <div class="invalid-feedback small" aria-live="polite">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="form-floating mb-4">
                                        <input id="password" type="password"
                                            class="form-control @error('password') is-invalid @enderror" name="password"
                                            required autocomplete="current-password" placeholder="Senha">
                                        <label for="password"><i class="fas fa-lock me-2 text-muted"></i>Senha</label>
                                        @error('password')
                                            <div class="invalid-feedback small" aria-live="polite">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="remember" id="remember"
                                                {{ old('remember') ? 'checked' : '' }}>
                                            <label class="form-check-label text-muted small" for="remember">Lembrar de mim</label>
                                        </div>
                                        @if (Route::has('password.request'))
                                            <a class="forgot-link" href="{{ route('password.request') }}">Esqueceu a senha?</a>
                                        @endif
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" class="btn login-submit" id="loginSubmit">
                                            <span class="submit-label">Entrar <i class="fas fa-arrow-right-to-bracket ms-2"></i></span>
                                            <span class="submit-loading d-none">
                                                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                                A entrar...
                                            </span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.getElementById('loginForm')?.addEventListener('submit', function () {
            const btn = document.getElementById('loginSubmit');
            if (!btn) return;
            btn.disabled = true;
            btn.querySelector('.submit-label')?.classList.add('d-none');
            btn.querySelector('.submit-loading')?.classList.remove('d-none');
        });
    </script>
@endsection
