@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            @if (session('status'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('status') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Informações do Perfil</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        @method('PATCH')

                        <div class="mb-3">
                            <label for="name" class="form-label">Nome</label>
                            <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name" autofocus>
                            @error('name')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail</label>
                            <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email', $user->email) }}" required autocomplete="email">
                            @error('email')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                Atualizar Perfil
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Atualizar Senha</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.password.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="current_password" class="form-label">Senha Atual</label>
                            <input id="current_password" type="password" class="form-control @error('current_password') is-invalid @enderror" name="current_password" required autocomplete="current-password">
                            @error('current_password')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Nova Senha</label>
                            <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="new-password">
                            @error('password')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password-confirm" class="form-label">Confirmar Nova Senha</label>
                            <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password">
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                Atualizar Senha
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Assinatura Digital (Certificado PKCS#12)</h5>
                </div>
                <div class="card-body">
                    @if ($user->certificate)
                        <div class="alert alert-info border-0 shadow-sm d-flex align-items-center mb-4">
                            <div class="me-3 fs-2">
                                🔑
                            </div>
                            <div>
                                <h6 class="alert-heading fw-bold mb-1">Certificado Digital Ativo</h6>
                                <p class="mb-1 text-secondary small">
                                    <strong>Emissor:</strong> {{ $user->certificate->issuer }}
                                </p>
                                <p class="mb-1 text-secondary small">
                                    <strong>Validade:</strong> 
                                    De {{ $user->certificate->valid_from->format('d/m/Y') }} 
                                    até {{ $user->certificate->valid_to->format('d/m/Y') }}
                                </p>
                                <p class="mb-0 small">
                                    <strong>Status:</strong> 
                                    @if ($user->certificate->isValid())
                                        <span class="badge bg-success">Válido</span>
                                    @else
                                        <span class="badge bg-danger">Expirado</span>
                                    @endif
                                </p>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('profile.certificate.destroy') }}" onsubmit="return confirm('Deseja realmente revogar e remover seu certificado digital? Você precisará fazer upload de um novo arquivo para assinar novos documentos.');">
                            @csrf
                            @method('DELETE')
                            <div class="d-grid">
                                <button type="submit" class="btn btn-outline-danger">
                                    Revogar e Remover Certificado
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="alert alert-warning border-0 shadow-sm mb-4 small">
                            ⚠️ Você ainda não tem um certificado digital cadastrado. Documentos assinados por você utilizarão um hash simples gerado pelo sistema. Faça o upload do seu certificado PKCS#12 (.p12 / .pfx) para habilitar assinaturas de nível governamental.
                        </div>

                        <form method="POST" action="{{ route('profile.certificate.upload') }}" enctype="multipart/form-data">
                            @csrf

                            <div class="mb-3">
                                <label for="certificate_file" class="form-label">Arquivo do Certificado (.p12 / .pfx)</label>
                                <input id="certificate_file" type="file" class="form-control @error('certificate_file') is-invalid @enderror" name="certificate_file" accept=".p12,.pfx" required>
                                @error('certificate_file')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="certificate_password" class="form-label">Senha do Certificado (PIN)</label>
                                <input id="certificate_password" type="password" class="form-control @error('certificate_password') is-invalid @enderror" name="certificate_password" placeholder="Senha do arquivo de chaves">
                                <div class="form-text text-muted small">Sua senha será guardada de forma totalmente segura e encriptada.</div>
                                @error('certificate_password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    Cadastrar Certificado Digital
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection