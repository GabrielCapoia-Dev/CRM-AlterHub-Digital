@php
    use Filament\Facades\Filament;
@endphp

<x-filament-panels::page.simple>
    <style>
        :root {
            --green: #2D6A35;
            --green-mid: #4A9455;
            --green-light: #8CC63F;
            --teal: #0D9488;
            --bg: #F6F4F0;
            --surface: #FFFFFF;
            --text: #1A1A18;
            --muted: rgba(26, 26, 24, 0.48);
            --shadow-md: 0 8px 32px rgba(26, 26, 24, 0.10);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* ── GLOBAL OVERRIDES ── */
        body {
            background: linear-gradient(160deg, #F9F8F6 0%, #F6F4F0 45%, #F0EEEB 100%) !important;
            color: var(--text) !important;
            font-family: 'Outfit', sans-serif !important;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(45, 106, 53, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(45, 106, 53, 0.03) 1px, transparent 1px);
            background-size: 72px 72px;
            z-index: -1;
            pointer-events: none;
        }

        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 60% at 20% 30%, rgba(45, 106, 53, 0.12) 0%, transparent 60%),
                radial-gradient(ellipse 55% 50% at 80% 70%, rgba(13, 148, 136, 0.08) 0%, transparent 60%),
                radial-gradient(ellipse 80% 80% at 50% 50%, rgba(13, 148, 136, 0.06) 0%, transparent 70%);
            z-index: -2;
            pointer-events: none;
        }

        /* ── CIRCLES ── */
        .login-circles {
            position: fixed;
            inset: 0;
            z-index: -1;
            pointer-events: none;
            overflow: hidden;
        }

        .circle {
            position: absolute;
            border-radius: 50%;
            border: 1px solid rgba(45, 106, 53, 0.05);
        }

        .c1 {
            width: 600px;
            height: 600px;
            top: -200px;
            right: -160px;
        }

        .c2 {
            width: 420px;
            height: 420px;
            bottom: -180px;
            left: -140px;
            border-color: rgba(13, 148, 136, 0.05);
        }

        .c3 {
            width: 700px;
            height: 700px;
            bottom: -350px;
            left: 50%;
            transform: translateX(-50%);
            border-color: rgba(45, 106, 53, 0.04);
        }

        /* ── PARTICLES ── */
        #particle-canvas {
            position: fixed;
            inset: 0;
            z-index: -1;
            pointer-events: none;
        }

        /* ── PAGE WRAPPER ── */
        .fi-simple-page {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px !important;
        }

        .fi-simple-page-content {
            width: 100%;
            max-width: 420px;
        }

        /* ── CARD ── */
        .login-card {
            background: rgba(255, 255, 255, 0.72);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(45, 106, 53, 0.1);
            border-radius: 24px;
            padding: 48px 40px 44px;
            box-shadow: var(--shadow-md);
            animation: cardSlideUp 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94) both;
        }

        @keyframes cardSlideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ── TITLE & SUBTITLE ── */
        .fi-simple-page .fi-page-heading {
            display: none !important;
        }

        .login-header {
            text-align: center;
            margin-bottom: 36px;
            animation: fadeDown 0.8s ease 0.2s both;
        }

        .brand {
            display: flex;
            align-items: baseline;
            justify-content: center;
            gap: 8px;
            margin-bottom: 12px;
        }

        .brand-uni {
            font-family: 'Cormorant Garamond', serif;
            font-weight: 600;
            font-size: 32px;
            color: var(--text);
            letter-spacing: 0.08em;
            line-height: 1;
        }

        .brand-uni span {
            color: var(--green);
        }

        .brand-gestao {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 12px;
            color: var(--green);
            letter-spacing: 0.2em;
            text-transform: uppercase;
        }

        .divider {
            width: 48px;
            height: 1.5px;
            background: linear-gradient(90deg, transparent, var(--green), transparent);
            margin: 16px auto;
            border-radius: 1px;
        }

        .subtitle {
            font-size: 13px;
            font-weight: 500;
            color: var(--muted);
            letter-spacing: 0.06em;
        }

        /* ── FORM WRAPPER ── */
        .fi-sc-form {
            animation: none !important;
        }

        .fi-form-content {
            padding: 0 !important;
        }

        /* ── FORM GROUPS ── */
        .fi-fo-field {
            margin-bottom: 24px;
            animation: fadeUp 0.8s ease 0.3s both;
        }

        .fi-fo-field:nth-child(2) {
            animation-delay: 0.35s;
        }

        .fi-fo-field:nth-child(3) {
            animation-delay: 0.4s;
        }

        .fi-fo-field:nth-child(4) {
            animation-delay: 0.45s;
        }

        .fi-fo-field-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text) !important;
            margin-bottom: 8px !important;
            transition: color 0.3s;
        }

        .fi-fo-field:focus-within .fi-fo-field-label {
            color: var(--green) !important;
        }

        .fi-input {
            width: 100%;
            padding: 14px 16px !important;
            background: rgba(255, 255, 255, 0.6) !important;
            border: 1.5px solid rgba(45, 106, 53, 0.12) !important;
            border-radius: 10px !important;
            font-family: 'Outfit', sans-serif;
            font-size: 14px !important;
            color: var(--text) !important;
            transition: all 0.3s;
            outline: none !important;
        }

        .fi-input::placeholder {
            color: rgba(26, 26, 24, 0.3) !important;
        }

        .fi-input:hover {
            border-color: rgba(45, 106, 53, 0.2) !important;
            background: rgba(255, 255, 255, 0.8) !important;
        }

        .fi-input:focus {
            border-color: var(--green) !important;
            background: rgba(255, 255, 255, 0.95) !important;
            box-shadow: 0 0 0 3px rgba(45, 106, 53, 0.1) !important;
        }

        /* ── CHECKBOX ── */
        .fi-fo-field-wrp-checkbox {
            margin-bottom: 0 !important;
        }

        .fi-checkbox {
            accent-color: var(--green) !important;
        }

        /* ── REMEMBER & FORGOT ── */
        .login-form-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
            font-size: 12px;
            animation: fadeUp 0.8s ease 0.5s both;
            flex-wrap: wrap;
            gap: 12px;
        }

        .checkbox-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .checkbox-label {
            color: var(--muted);
            margin: 0;
            cursor: pointer;
            transition: color 0.3s;
            font-size: 12px;
        }

        .checkbox-wrap:hover .checkbox-label {
            color: var(--text);
        }

        .forgot-link {
            color: var(--green);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            font-size: 12px;
        }

        .forgot-link:hover {
            color: var(--green-mid);
            text-decoration: underline;
        }

        /* ── FILAMENT BUTTONS ── */
        .fi-btn {
            width: 100% !important;
            padding: 14px 24px !important;
            background: linear-gradient(135deg, var(--green), var(--green-mid)) !important;
            color: white !important;
            font-family: 'Outfit', sans-serif !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            letter-spacing: 0.1em !important;
            text-transform: uppercase !important;
            border: none !important;
            border-radius: 10px !important;
            cursor: pointer !important;
            transition: all 0.3s !important;
            position: relative !important;
            overflow: hidden !important;
            box-shadow: 0 4px 20px rgba(45, 106, 53, 0.25) !important;
            animation: fadeUp 0.8s ease 0.55s both !important;
            min-height: 48px !important;
        }

        .fi-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, var(--green-mid), var(--teal));
            opacity: 0;
            transition: opacity 0.3s;
            z-index: -1;
        }

        .fi-btn:hover::before {
            opacity: 1;
        }

        .fi-btn:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 12px 32px rgba(45, 106, 53, 0.3) !important;
        }

        .fi-btn:active {
            transform: translateY(0) !important;
        }

        .fi-btn span {
            position: relative;
            z-index: 1;
            display: inline !important;
            opacity: 1 !important;
            visibility: visible !important;
            color: inherit !important;
        }

        /* ── FORM ACTIONS ── */
        .fi-form-actions {
            display: flex;
            gap: 12px;
            padding: 0 !important;
            animation: fadeUp 0.8s ease 0.55s both;
        }

        .fi-form-actions .fi-btn {
            animation: none !important;
        }

        /* ── DIVIDER ── */
        .divider-or {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 32px 0;
            opacity: 0;
            animation: fadeUp 0.8s ease 0.6s both;
        }

        .divider-or::before,
        .divider-or::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(45, 106, 53, 0.1);
        }

        .divider-or span {
            font-size: 12px;
            color: var(--muted);
            font-weight: 500;
        }

        /* ── SIGNUP PROMPT ── */
        .signup-prompt {
            text-align: center;
            font-size: 13px;
            color: var(--muted);
            opacity: 0;
            animation: fadeUp 0.8s ease 0.65s both;
        }

        .signup-prompt a {
            color: var(--green);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .signup-prompt a:hover {
            color: var(--green-mid);
        }

        /* ── ANIMATIONS ── */
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 640px) {
            .login-card {
                padding: 36px 28px 32px;
                border-radius: 20px;
            }

            .brand-uni {
                font-size: 28px;
            }

            .login-header {
                margin-bottom: 28px;
            }

            .fi-input {
                padding: 12px 14px !important;
                font-size: 16px !important;
            }

            .fi-btn {
                padding: 12px 20px !important;
                font-size: 11px !important;
            }

            .login-form-footer {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }

            .forgot-link {
                align-self: flex-end;
            }
        }
    </style>

    <!-- BACKGROUND CIRCLES -->
    <div class="login-circles">
        <div class="circle c1"></div>
        <div class="circle c2"></div>
        <div class="circle c3"></div>
    </div>

    <!-- PARTICLES CANVAS -->
    <canvas id="particle-canvas"></canvas>

    <!-- LOGIN CARD -->
    <div class="login-card">
        <!-- HEADER -->
        <div class="login-header">
            <div class="brand">
                <div class="brand-uni">UNI<span>BIOTECH</span></div>
            </div>
            <div class="divider"></div>
            <div class="brand-gestao">Gestão Edu</div>
            <div style="font-size: 11px; color: var(--muted); margin-top: 6px; letter-spacing: 0.06em;">
                Portal de Educação
            </div>
        </div>

        <!-- FILAMENT FORM - RENDERIZADO AQUI -->
        <div class="form-wrapper">
            @livewire('filament-forms::form', [
                'formId' => $this->id,
                'model' => $this,
                'formClass' => 'space-y-6',
                'statePath' => 'data',
            ])
        </div>

        <!-- REMEMBER & FORGOT -->
        <div class="login-form-footer">
            <div class="checkbox-wrap">
                <input 
                    type="checkbox" 
                    id="remember" 
                    name="data[remember]"
                    wire:model="data.remember"
                    class="fi-checkbox"
                >
                <label for="remember" class="checkbox-label">Lembrar-me</label>
            </div>
            @if (filament()->hasPasswordReset())
                <a href="{{ filament()->getRequestPasswordResetUrl() }}" class="forgot-link">
                    Esqueceu a senha?
                </a>
            @endif
        </div>

        <!-- SIGNUP -->
        @if (filament()->hasRegistration())
            <div class="divider-or">
                <span>Novo por aqui?</span>
            </div>

            <div class="signup-prompt">
                {{ __('filament-panels::auth/pages/login.actions.register.before') }}
                <a href="{{ filament()->getRegistrationUrl() }}">
                    {{ __('filament-panels::auth/pages/login.actions.register.label') }}
                </a>
            </div>
        @endif
    </div>

    <script>
        // ─── PARTICLES ───
        const canvas = document.getElementById('particle-canvas');
        if (canvas) {
            const ctx = canvas.getContext('2d');
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;

            window.addEventListener('resize', () => {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
            });

            const particles = [];
            for (let i = 0; i < 50; i++) {
                particles.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    r: Math.random() * 1.5 + 0.3,
                    vx: (Math.random() - 0.5) * 0.15,
                    vy: (Math.random() - 0.5) * 0.15,
                    alpha: Math.random() * 0.12 + 0.03,
                    hue: Math.random() > 0.5 ? 130 : 165
                });
            }

            function drawParticles() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                particles.forEach(p => {
                    p.x += p.vx;
                    p.y += p.vy;
                    if (p.x < 0) p.x = canvas.width;
                    if (p.x > canvas.width) p.x = 0;
                    if (p.y < 0) p.y = canvas.height;
                    if (p.y > canvas.height) p.y = 0;
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                    ctx.fillStyle = `hsla(${p.hue}, 55%, 35%, ${p.alpha})`;
                    ctx.fill();
                });

                // Conexões
                for (let i = 0; i < particles.length; i++) {
                    for (let j = i + 1; j < particles.length; j++) {
                        const dx = particles[i].x - particles[j].x;
                        const dy = particles[i].y - particles[j].y;
                        const dist = Math.sqrt(dx * dx + dy * dy);
                        if (dist < 120) {
                            ctx.beginPath();
                            ctx.moveTo(particles[i].x, particles[i].y);
                            ctx.lineTo(particles[j].x, particles[j].y);
                            ctx.strokeStyle = `rgba(45,106,53,${0.03 * (1 - dist / 120)})`;
                            ctx.lineWidth = 0.5;
                            ctx.stroke();
                        }
                    }
                }

                requestAnimationFrame(drawParticles);
            }

            drawParticles();
        }
    </script>
</x-filament-panels::page.simple>