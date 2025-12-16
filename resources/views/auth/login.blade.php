@extends('layouts.app')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center align-items-center" style="min-height: 80vh;">
            <div class="col-lg-10 col-xl-9">
                <div class="card shadow-lg border-0 overflow-hidden rounded-4">
                    <div class="row g-0">
                        <div
                            class="col-md-5 bg-primary text-white d-flex align-items-center justify-content-center p-5 position-relative overflow-hidden">
                            <div class="position-absolute top-0 start-0 w-100 h-100" 
                                style="background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%); pointer-events: none;"></div>
                            <div class="text-center position-relative z-1">
                                <img src="{{ asset('images/insignia.png') }}" alt="Insígnia institucional"
                                    class="img-fluid mb-4 drop-shadow" style="max-height: 120px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2));">
                                <h4 class="fw-bold mb-2 tracking-tight">GPN-AGIL</h4>
                                <p class="mb-0 text-white-50 small text-uppercase letter-spacing-2">Gestão de Logística e Patrimônio</p>
                            </div>
                        </div>
                        <div class="col-md-7 p-5 bg-white">
                            <div class="d-flex align-items-center justify-content-between mb-4">
                                <h4 class="fw-bold text-dark mb-0">Bem-vindo</h4>
                                <span class="text-muted small">Faça login para continuar</span>
                            </div>
                            
                            <form method="POST" action="{{ route('login') }}" novalidate>
                                @csrf

                                <div class="form-floating mb-3">
                                    <input id="email" type="email"
                                        class="form-control @error('email') is-invalid @enderror" name="email"
                                        value="{{ old('email') }}" required autocomplete="email" autofocus placeholder="name@example.com">
                                    <label for="email">Endereço de E-mail</label>
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-floating mb-4">
                                    <input id="password" type="password"
                                        class="form-control @error('password') is-invalid @enderror" name="password"
                                        required autocomplete="current-password" placeholder="Senha">
                                    <label for="password">Senha</label>
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="remember" id="remember"
                                            {{ old('remember') ? 'checked' : '' }}>
                                        <label class="form-check-label text-muted small" for="remember">Lembrar de mim</label>
                                    </div>
                                    @if (Route::has('password.request'))
                                        <a class="text-decoration-none small fw-medium" href="{{ route('password.request') }}">
                                            Esqueceu a senha?
                                        </a>
                                    @endif
                                </div>

                                <div class="d-grid gap-3">
                                    <button type="submit" class="btn btn-primary py-3 fw-semibold text-uppercase letter-spacing-1">
                                        Acessar Sistema
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-4 text-muted small">
                    &copy; {{ date('Y') }} GPN-AGIL. Todos os direitos reservados.
                </div>
            </div>
        </div>
    </div>
@endsection
