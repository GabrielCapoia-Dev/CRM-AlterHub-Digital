<x-filament-panels::page>

    <div class="ub-root">

        {{-- HERO --}}
        <div class="ub-hero">
            <div class="ub-hero-grid"></div>
            <div class="ub-hero-glow"></div>

            <div class="ub-hero-inner">
                <div class="ub-eyebrow">
                    <span class="ub-pulse"></span>
                    Sistema Ativo
                </div>

                <h1 class="ub-title">
                    Bem-vindo à<br>
                    <span class="ub-title-accent">UniBiotech</span>
                </h1>

                <p class="ub-subtitle">
                    Plataforma de gestão interna. Acesse os módulos disponíveis abaixo.
                </p>
            </div>

            <div class="ub-hero-logo">
                <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="32" cy="32" r="30" stroke="rgba(255,255,255,0.08)" stroke-width="2"/>
                    <path d="M20 44V24c0-2.2 1.8-4 4-4h16c2.2 0 4 1.8 4 4v20" stroke="rgba(255,255,255,0.6)" stroke-width="2" stroke-linecap="round"/>
                    <path d="M16 44h32" stroke="rgba(255,255,255,0.6)" stroke-width="2" stroke-linecap="round"/>
                    <path d="M26 20v-4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v4" stroke="rgba(255,255,255,0.4)" stroke-width="1.5" stroke-linecap="round"/>
                    <circle cx="32" cy="32" r="5" stroke="#60a5fa" stroke-width="1.5"/>
                    <path d="M32 27v-3M32 41v-3M27 32h-3M41 32h-3" stroke="#60a5fa" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </div>
        </div>

        {{-- CARDS --}}
        <div class="ub-section">
            <p class="ub-section-label">Acesso Rápido</p>

            <div class="ub-grid">

                <a href="{{ route('filament.painel.resources.usuarios.index') }}" class="ub-card ub-card--blue">
                    <div class="ub-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </div>
                    <div class="ub-card-body">
                        <h3>Usuários</h3>
                        <p>Controle de acesso e permissões</p>
                    </div>
                    <span class="ub-card-arrow">→</span>
                </a>

                <a href="{{ route('filament.painel.resources.niveis-de-acesso.index') }}" class="ub-card ub-card--purple">
                    <div class="ub-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                        </svg>
                    </div>
                    <div class="ub-card-body">
                        <h3>Níveis de Acesso</h3>
                        <p>Roles e permissões do sistema</p>
                    </div>
                    <span class="ub-card-arrow">→</span>
                </a>

            </div>
        </div>

    </div>

    <style>
        .ub-root *, .ub-root *::before, .ub-root *::after { box-sizing: border-box; }

        /* ── HERO ── */
        .ub-hero {
            position: relative;
            overflow: hidden;
            border-radius: 1.25rem;
            padding: 3.5rem 2.5rem 3rem;
            margin-bottom: 2rem;
            background: linear-gradient(135deg, #0a1628 0%, #0d2347 50%, #091a38 100%);
            border: 1px solid rgba(255,255,255,0.07);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2rem;
        }

        .ub-hero-grid {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
            background-size: 40px 40px;
            mask-image: radial-gradient(ellipse at center, black 40%, transparent 80%);
        }

        .ub-hero-glow {
            position: absolute; top: -80px; right: -60px;
            width: 400px; height: 400px; border-radius: 50%;
            background: radial-gradient(circle, rgba(14,99,196,0.2) 0%, transparent 70%);
            pointer-events: none;
        }

        .ub-hero-inner { position: relative; z-index: 1; max-width: 580px; }

        .ub-hero-logo {
            position: relative; z-index: 1;
            flex-shrink: 0; width: 96px; height: 96px;
            opacity: 0.7;
        }

        .ub-eyebrow {
            display: inline-flex; align-items: center; gap: 0.5rem;
            color: #86efac;
            font-family: 'Courier New', monospace;
            font-size: 0.7rem; font-weight: 600;
            letter-spacing: 0.14em; text-transform: uppercase;
            margin-bottom: 1.25rem;
        }

        .ub-pulse {
            width: 7px; height: 7px; border-radius: 50%;
            background: #22c55e; flex-shrink: 0;
            animation: ub-pulse 2s ease-in-out infinite;
        }

        @keyframes ub-pulse {
            0%,100% { box-shadow: 0 0 0 0 rgba(34,197,94,0.5); }
            50%      { box-shadow: 0 0 0 6px rgba(34,197,94,0); }
        }

        .ub-title {
            font-size: clamp(1.9rem, 4.5vw, 2.8rem);
            font-weight: 700; color: #f0f6ff;
            line-height: 1.15; margin: 0 0 1rem;
            letter-spacing: -0.02em;
            font-family: system-ui, sans-serif;
        }

        .ub-title-accent {
            background: linear-gradient(90deg, #60a5fa, #93c5fd, #bfdbfe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .ub-subtitle {
            font-size: 0.95rem; color: rgba(255,255,255,0.5);
            line-height: 1.65; margin: 0;
            font-family: system-ui, sans-serif;
        }

        /* ── SECTION ── */
        .ub-section { animation: ub-fadeup 0.45s ease-out 0.1s both; }

        @keyframes ub-fadeup {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .ub-section-label {
            font-family: 'Courier New', monospace;
            font-size: 0.68rem; font-weight: 700;
            letter-spacing: 0.15em; text-transform: uppercase;
            color: #9ca3af; margin: 0 0 1rem 0.25rem;
        }

        .ub-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 0.875rem;
        }

        /* ── CARD ── */
        .ub-card {
            display: flex; align-items: center; gap: 1rem;
            padding: 1.1rem 1.25rem;
            border-radius: 0.875rem; text-decoration: none;
            border: 1.5px solid transparent;
            background: white;
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
            box-shadow: 1px 2px 6px rgba(0,0,0,0.07);
        }

        .dark .ub-card { background: rgb(17 24 39); box-shadow: 1px 2px 6px rgba(0,0,0,0.3); }

        .ub-card:hover { transform: translateY(-3px); box-shadow: 2px 6px 18px rgba(0,0,0,0.1); border-color: var(--c); }

        .ub-card-icon {
            flex-shrink: 0; width: 42px; height: 42px;
            border-radius: 0.625rem;
            display: flex; align-items: center; justify-content: center;
            background: var(--ic); color: var(--c);
        }

        .ub-card-icon svg { width: 20px; height: 20px; }

        .ub-card-body { flex: 1; min-width: 0; }

        .ub-card-body h3 {
            font-family: system-ui, sans-serif;
            font-size: 0.925rem; font-weight: 600;
            color: #111827; margin: 0 0 0.2rem; line-height: 1.3;
        }

        .dark .ub-card-body h3 { color: #f3f4f6; }

        .ub-card-body p {
            font-family: system-ui, sans-serif;
            font-size: 0.78rem; color: #6b7280;
            margin: 0; line-height: 1.4;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .dark .ub-card-body p { color: #9ca3af; }

        .ub-card-arrow {
            font-size: 1rem; flex-shrink: 0;
            color: var(--c);
            opacity: 0; transform: translateX(-4px);
            transition: opacity 0.18s ease, transform 0.18s ease;
        }

        .ub-card:hover .ub-card-arrow { opacity: 1; transform: translateX(0); }

        .ub-card--blue   { --c: #2563eb; --ic: #dbeafe; }
        .ub-card--purple { --c: #7c3aed; --ic: #ede9fe; }
        .ub-card--teal   { --c: #0d9488; --ic: #ccfbf1; }
        .ub-card--green  { --c: #16a34a; --ic: #dcfce7; }
        .ub-card--amber  { --c: #d97706; --ic: #fef3c7; }
        .ub-card--rose   { --c: #e11d48; --ic: #ffe4e6; }

        .dark .ub-card--blue   { --ic: rgba(37,99,235,0.18); }
        .dark .ub-card--purple { --ic: rgba(124,58,237,0.18); }
        .dark .ub-card--teal   { --ic: rgba(13,148,136,0.18); }
        .dark .ub-card--green  { --ic: rgba(22,163,74,0.18); }
        .dark .ub-card--amber  { --ic: rgba(217,119,6,0.18); }
        .dark .ub-card--rose   { --ic: rgba(225,29,72,0.18); }

        @media (max-width: 640px) {
            .ub-hero { padding: 2.5rem 1.5rem 2rem; }
            .ub-hero-logo { display: none; }
            .ub-grid { grid-template-columns: 1fr; }
        }
    </style>

</x-filament-panels::page>