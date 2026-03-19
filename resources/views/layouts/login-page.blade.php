<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Gestão Edu · Unibiotech</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --green: #2D6A35;
            --green-mid: #4A9455;
            --green-light: #8CC63F;
            --green-glow: rgba(45, 106, 53, 0.08);
            --teal: #0D9488;
            --bg: #F6F4F0;
            --bg2: #EDEAE4;
            --surface: #FFFFFF;
            --text: #1A1A18;
            --muted: rgba(26, 26, 24, 0.48);
            --shadow-sm: 0 2px 8px rgba(26, 26, 24, 0.06);
            --shadow-md: 0 8px 32px rgba(26, 26, 24, 0.10);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            font-weight: 300;
            overflow-x: hidden;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ── PARTICLE CANVAS ── */
        #particle-canvas {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
        }

        /* ── GRADIENT BG ── */
        .bg-base {
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 60% at 20% 30%, rgba(45, 106, 53, 0.12) 0%, transparent 60%),
                radial-gradient(ellipse 55% 50% at 80% 70%, rgba(58, 142, 230, 0.08) 0%, transparent 60%),
                radial-gradient(ellipse 80% 80% at 50% 50%, rgba(13, 148, 136, 0.06) 0%, transparent 70%),
                linear-gradient(160deg, #F9F8F6 0%, #F6F4F0 45%, #F0EEEB 100%);
            z-index: -2;
            pointer-events: none;
        }

        /* ── SUBTLE GRID ── */
        .bg-grid {
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(45, 106, 53, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(45, 106, 53, 0.03) 1px, transparent 1px);
            background-size: 72px 72px;
            z-index: -1;
            pointer-events: none;
        }

        /* ── CIRCLE DECORATIONS ── */
        .bg-circles {
            position: fixed;
            inset: 0;
            overflow: hidden;
            z-index: -1;
            pointer-events: none;
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

        /* ── CONTAINER ── */
        .container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 420px;
            padding: 24px;
        }

        /* ── CARD ── */
        .card {
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

        /* ── HEADER ── */
        .header {
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

        /* ── FORM ── */
        .form-group {
            margin-bottom: 24px;
            animation: fadeUp 0.8s ease 0.3s both;
        }

        .form-group:nth-child(2) {
            animation-delay: 0.35s;
        }

        .form-group:nth-child(3) {
            animation-delay: 0.4s;
        }

        .form-group:nth-child(4) {
            animation-delay: 0.45s;
        }

        label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text);
            margin-bottom: 8px;
            transition: color 0.3s;
        }

        .form-group:focus-within label {
            color: var(--green);
        }

        input {
            width: 100%;
            padding: 14px 16px;
            background: rgba(255, 255, 255, 0.6);
            border: 1.5px solid rgba(45, 106, 53, 0.12);
            border-radius: 10px;
            font-family: 'Outfit', sans-serif;
            font-size: 14px;
            color: var(--text);
            transition: all 0.3s;
            outline: none;
        }

        input::placeholder {
            color: rgba(26, 26, 24, 0.3);
        }

        input:hover {
            border-color: rgba(45, 106, 53, 0.2);
            background: rgba(255, 255, 255, 0.8);
        }

        input:focus {
            border-color: var(--green);
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 0 0 3px rgba(45, 106, 53, 0.1);
        }

        /* ── REMEMBER & FORGOT ── */
        .form-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
            font-size: 12px;
            animation: fadeUp 0.8s ease 0.5s both;
        }

        .checkbox-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--green);
        }

        .checkbox-label {
            color: var(--muted);
            margin: 0;
            cursor: pointer;
            transition: color 0.3s;
        }

        .checkbox-wrap:hover .checkbox-label {
            color: var(--text);
        }

        .forgot-link {
            color: var(--green);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }

        .forgot-link:hover {
            color: var(--green-mid);
            text-decoration: underline;
        }

        /* ── SUBMIT BUTTON ── */
        .btn-submit {
            width: 100%;
            padding: 14px 24px;
            background: linear-gradient(135deg, var(--green), var(--green-mid));
            color: white;
            font-family: 'Outfit', sans-serif;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(45, 106, 53, 0.25);
            animation: fadeUp 0.8s ease 0.55s both;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, var(--green-mid), var(--teal));
            opacity: 0;
            transition: opacity 0.3s;
        }

        .btn-submit:hover::before {
            opacity: 1;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(45, 106, 53, 0.3);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-submit span {
            position: relative;
            z-index: 1;
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

        /* ── SIGNUP LINK ── */
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
            .container {
                max-width: 100%;
            }

            .card {
                padding: 36px 28px 32px;
                border-radius: 20px;
            }

            .brand-uni {
                font-size: 28px;
            }

            .header {
                margin-bottom: 28px;
            }

            input {
                padding: 12px 14px;
                font-size: 16px; /* Evita zoom no iOS */
            }

            .btn-submit {
                padding: 12px 20px;
                font-size: 11px;
            }

            .form-footer {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }

            .forgot-link {
                align-self: flex-end;
            }
        }
    </style>
</head>

<body>
    <!-- BACKGROUND ELEMENTS -->
    <div class="bg-base"></div>
    <div class="bg-grid"></div>

    <div class="bg-circles">
        <div class="circle c1"></div>
        <div class="circle c2"></div>
        <div class="circle c3"></div>
    </div>

    <!-- PARTICLES -->
    <canvas id="particle-canvas"></canvas>

    <!-- MAIN CONTAINER -->
    <div class="container">
        <div class="card">
            <!-- HEADER -->
            <div class="header">
                <div class="brand">
                    <div class="brand-uni">UNI<span>BIOTECH</span></div>
                </div>
                <div class="divider"></div>
                <div class="brand-gestao">Gestão Edu</div>
                <div style="font-size: 11px; color: var(--muted); margin-top: 6px; letter-spacing: 0.06em;">Portal de Educação</div>
            </div>

            <!-- FORM -->
            <form id="login-form">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="seu.email@exemplo.com"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password">Senha</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="••••••••"
                        required
                    >
                </div>

                <div class="form-footer">
                    <div class="checkbox-wrap">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember" class="checkbox-label">Lembrar-me</label>
                    </div>
                    <a href="#" class="forgot-link">Esqueceu a senha?</a>
                </div>

                <button type="submit" class="btn-submit">
                    <span>Entrar</span>
                </button>
            </form>

            <!-- SIGNUP -->
            <div class="divider-or">
                <span>Novo por aqui?</span>
            </div>

            <div class="signup-prompt">
                Solicite acesso através do <a href="#">portal da instituição</a>
            </div>
        </div>
    </div>

    <script>
        // ─── PARTICLES ───
        const canvas = document.getElementById('particle-canvas');
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

        // ─── FORM SUBMIT ───
        document.getElementById('login-form').addEventListener('submit', e => {
            e.preventDefault();
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            console.log('Login attempt:', { email, password });
            alert(`Bem-vindo! (Demo)\n\nEmail: ${email}`);
        });
    </script>
</body>

</html>