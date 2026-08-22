<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Crie uma nova senha — Unibiotech</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
</head>
<body class="required-auth">
    <main class="required-auth__layout">
        <section class="required-auth__visual" aria-hidden="true">
            <div class="required-auth__story">
                <div class="auth-story__brand">
                    <img class="auth-story__logo" src="{{ asset('images/unibiotech-logo.svg') }}" alt="">
                    <span class="auth-story__portal">Portal corporativo</span>
                </div>

                <div class="required-auth__visual-copy">
                    <span class="auth-story__eyebrow">Portal Unibiotech</span>
                    <h1>Segurança para seguir criando soluções.</h1>
                    <p>Defina sua senha pessoal e mantenha protegidos os dados da operação.</p>
                </div>

                <div class="auth-story__features">
                    <span>Qualidade</span>
                    <span>Segurança</span>
                    <span>Confiança</span>
                </div>
            </div>
        </section>

        <section class="required-auth__panel">
            <div class="required-auth__card">
                <img class="required-auth__mobile-logo" src="{{ asset('images/unibiotech-logo.svg') }}" alt="Unibiotech">
                <div class="required-auth__accent" aria-hidden="true"></div>
                <span class="required-auth__eyebrow">Primeiro acesso</span>
                <h2>Crie uma nova senha</h2>
                <p class="required-auth__intro">Olá, {{ $user->name }}. Informe a senha temporária recebida e defina sua senha pessoal.</p>

                <form method="POST" action="{{ route('password.change-required.update') }}">
                    @csrf

                    <label for="temporary_password">Senha temporária</label>
                    <input id="temporary_password" name="temporary_password" type="password" required autofocus autocomplete="current-password">

                    @error('temporary_password')
                        <div class="required-auth__error" role="alert">{{ $message }}</div>
                    @enderror

                    <label for="password">Nova senha</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password">

                    <label for="password_confirmation">Confirme a nova senha</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">

                    @error('password')
                        <div class="required-auth__error" role="alert">{{ $message }}</div>
                    @enderror

                    <p class="required-auth__hint">Use entre {{ max(8, (int) config('crm.access.user_password_min_length', 8)) }} e 30 caracteres, com maiúscula, minúscula, número e símbolo. Não reutilize a senha temporária.</p>

                    <button class="required-auth__submit" type="submit">Salvar nova senha e continuar</button>
                </form>

                <form class="required-auth__logout" method="POST" action="{{ route('filament.painel.auth.logout') }}">
                    @csrf
                    <button type="submit">Sair e trocar depois</button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
