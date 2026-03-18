<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UNIBIOTECH — Excelência em Biotecnologia Láctea</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #F6F4F0;
            --bg2: #EDEAE4;
            --surface: #FFFFFF;
            --glass: rgba(255, 255, 255, 0.65);
            --glass-border: rgba(45, 106, 53, 0.13);
            --green: #2D6A35;
            --green-mid: #4A9455;
            --green-light: #8CC63F;
            --green-glow: rgba(45, 106, 53, 0.08);
            --teal: #0D9488;
            --text: #1A1A18;
            --muted: rgba(26, 26, 24, 0.48);
            --white: #FFFFFF;
            --ink: #111110;
            --shadow-sm: 0 2px 8px rgba(26, 26, 24, 0.06);
            --shadow-md: 0 8px 32px rgba(26, 26, 24, 0.10);
            --shadow-lg: 0 24px 64px rgba(26, 26, 24, 0.13);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
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
        }

        /* ─── NOISE OVERLAY ─── */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 1;
            opacity: 0.25;
        }

        /* ─── CANVAS PARTICLES ─── */
        #particle-canvas {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
        }

        /* ─── LAYOUT ─── */
        .page {
            position: relative;
            z-index: 2;
        }

        /* ─── NAV ─── */
        nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            padding: 20px 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            background: rgba(246, 244, 240, 0.72);
            border-bottom: 1px solid rgba(45, 106, 53, 0.08);
            transition: padding 0.3s, background 0.3s, box-shadow 0.3s;
        }

        nav.scrolled {
            background: rgba(246, 244, 240, 0.94);
            box-shadow: 0 1px 24px rgba(26, 26, 24, 0.07);
            padding: 14px 60px;
        }

        .nav-brand {
            font-family: 'Cormorant Garamond', serif;
            font-size: 22px;
            font-weight: 600;
            letter-spacing: 0.15em;
            color: var(--ink);
        }

        .nav-brand span {
            color: var(--green);
        }

        .nav-links {
            display: flex;
            gap: 36px;
            list-style: none;
        }

        .nav-links a {
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--muted);
            text-decoration: none;
            transition: color 0.3s;
            position: relative;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 1.5px;
            background: var(--green);
            transition: width 0.3s;
        }

        .nav-links a:hover {
            color: var(--green);
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        /* ─── HERO ─── */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            padding: 120px 60px 80px;
            background: var(--bg);
        }

        .hero-bg-gradient {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 60% 50% at 20% 50%, rgba(45, 106, 53, 0.07) 0%, transparent 60%),
                radial-gradient(ellipse 40% 60% at 80% 30%, rgba(13, 148, 136, 0.05) 0%, transparent 55%),
                radial-gradient(ellipse 80% 40% at 50% 100%, rgba(140, 198, 63, 0.04) 0%, transparent 50%);
            pointer-events: none;
        }

        .hero-grid-overlay {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(45, 106, 53, 0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(45, 106, 53, 0.04) 1px, transparent 1px);
            background-size: 60px 60px;
            mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 0%, transparent 70%);
            -webkit-mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 0%, transparent 70%);
            pointer-events: none;
        }

        .hero-content {
            text-align: center;
            max-width: 900px;
            position: relative;
            z-index: 2;
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 8px 20px;
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 100px;
            backdrop-filter: blur(12px);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.25em;
            text-transform: uppercase;
            color: var(--green);
            margin-bottom: 36px;
            animation: fadeDown 0.8s ease both;
            box-shadow: var(--shadow-sm);
        }

        .hero-eyebrow-dot {
            width: 6px;
            height: 6px;
            background: var(--green);
            border-radius: 50%;
            animation: blink 2s infinite;
        }

        @keyframes blink {

            0%,
            100% {
                opacity: 1
            }

            50% {
                opacity: 0.2
            }
        }

        .hero-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(52px, 8vw, 110px);
            font-weight: 300;
            line-height: 0.95;
            letter-spacing: -0.02em;
            color: var(--ink);
            margin-bottom: 32px;
            animation: fadeUp 1s 0.2s ease both;
        }

        .hero-title em {
            font-style: italic;
            background: linear-gradient(135deg, var(--green), var(--teal));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-title .line-indent {
            display: block;
            margin-left: 80px;
        }

        .hero-sub {
            font-size: 16px;
            line-height: 1.8;
            color: var(--muted);
            max-width: 580px;
            margin: 0 auto 48px;
            animation: fadeUp 1s 0.4s ease both;
        }

        .hero-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            animation: fadeUp 1s 0.6s ease both;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 32px;
            background: linear-gradient(135deg, var(--green), var(--green-mid));
            color: #fff;
            font-family: 'Outfit', sans-serif;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(45, 106, 53, 0.25);
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, var(--green-mid), var(--teal));
            opacity: 0;
            transition: opacity 0.3s;
        }

        .btn-primary:hover::before {
            opacity: 1;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 40px rgba(45, 106, 53, 0.28);
        }

        .btn-primary span,
        .btn-primary svg {
            position: relative;
            z-index: 1;
        }

        .btn-ghost {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 32px;
            background: var(--glass);
            border: 1px solid rgba(26, 26, 24, 0.12);
            color: var(--text);
            font-size: 13px;
            font-weight: 500;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s;
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
        }

        .btn-ghost:hover {
            border-color: var(--green);
            color: var(--green);
            background: rgba(45, 106, 53, 0.06);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        /* scroll indicator */
        .hero-scroll {
            position: absolute;
            bottom: 40px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            font-size: 10px;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            animation: fadeIn 1s 1s ease both;
        }

        .scroll-line {
            width: 1px;
            height: 50px;
            background: linear-gradient(to bottom, var(--green), transparent);
            animation: scrollPulse 2s ease-in-out infinite;
        }

        @keyframes scrollPulse {

            0%,
            100% {
                opacity: 0.4;
                transform: scaleY(1)
            }

            50% {
                opacity: 1;
                transform: scaleY(1.2)
            }
        }

        /* ─── STATS BAND ─── */
        .stats-band {
            padding: 0 60px;
        }

        .stats-inner {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            background: var(--white);
            border: 1px solid rgba(45, 106, 53, 0.1);
            border-radius: 20px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .stat-item {
            padding: 40px 36px;
            border-right: 1px solid rgba(45, 106, 53, 0.08);
            position: relative;
            transition: background 0.4s;
        }

        .stat-item:last-child {
            border-right: none;
        }

        .stat-item:hover {
            background: rgba(45, 106, 53, 0.03);
        }

        .stat-num {
            font-family: 'Cormorant Garamond', serif;
            font-size: 52px;
            font-weight: 300;
            color: var(--ink);
            line-height: 1;
            margin-bottom: 8px;
        }

        .stat-num span {
            color: var(--green);
        }

        .stat-label {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.4;
        }

        .stat-icon {
            position: absolute;
            top: 28px;
            right: 28px;
            color: rgba(45, 106, 53, 0.15);
        }

        /* ─── ABOUT ─── */
        .about {
            padding: 140px 60px;
            position: relative;
        }

        .about::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 2px;
            height: 60%;
            background: linear-gradient(to bottom, transparent, var(--green-mid), transparent);
        }

        .section-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.28em;
            text-transform: uppercase;
            color: var(--green);
            margin-bottom: 20px;
        }

        .section-tag::before {
            content: '';
            width: 24px;
            height: 1.5px;
            background: var(--green);
        }

        .about-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 100px;
            align-items: center;
            max-width: 1240px;
            margin: 0 auto;
        }

        .about-headline {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(36px, 4vw, 58px);
            font-weight: 300;
            line-height: 1.15;
            color: var(--ink);
            margin-bottom: 28px;
        }

        .about-headline em {
            font-style: italic;
            color: var(--green);
        }

        .about-body {
            font-size: 16px;
            line-height: 1.85;
            color: var(--muted);
            margin-bottom: 20px;
        }

        .about-quote {
            margin: 36px 0;
            padding: 24px 28px;
            border-left: 2px solid var(--green);
            background: rgba(45, 106, 53, 0.04);
            border-radius: 0 10px 10px 0;
        }

        .about-quote p {
            font-family: 'Cormorant Garamond', serif;
            font-size: 22px;
            font-style: italic;
            color: var(--ink);
            line-height: 1.5;
        }

        .about-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .about-card {
            padding: 28px 24px;
            background: var(--white);
            border: 1px solid rgba(45, 106, 53, 0.08);
            border-radius: 14px;
            position: relative;
            overflow: hidden;
            transition: all 0.4s;
            box-shadow: var(--shadow-sm);
        }

        .about-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 50% 0%, rgba(45, 106, 53, 0.05) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.4s;
        }

        .about-card:hover {
            transform: translateY(-6px);
            border-color: rgba(45, 106, 53, 0.2);
            box-shadow: var(--shadow-lg);
        }

        .about-card:hover::before {
            opacity: 1;
        }

        .about-card-num {
            font-family: 'Cormorant Garamond', serif;
            font-size: 46px;
            font-weight: 300;
            color: var(--green);
            line-height: 1;
            margin-bottom: 8px;
        }

        .about-card-text {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.5;
        }

        .about-card-icon {
            position: absolute;
            top: 20px;
            right: 20px;
            color: rgba(45, 106, 53, 0.12);
        }

        /* ─── PRODUCTS ─── */
        .products {
            padding: 140px 60px;
            background: var(--white);
            position: relative;
        }

        .products::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 60% 50% at 80% 20%, rgba(45, 106, 53, 0.04) 0%, transparent 60%),
                radial-gradient(ellipse 40% 40% at 10% 80%, rgba(140, 198, 63, 0.04) 0%, transparent 60%);
            pointer-events: none;
        }

        .products-header {
            text-align: center;
            max-width: 600px;
            margin: 0 auto 80px;
        }

        .products-headline {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(40px, 5vw, 68px);
            font-weight: 300;
            color: var(--ink);
            line-height: 1.1;
        }

        .products-headline em {
            font-style: italic;
            color: var(--green);
        }

        .products-sub {
            font-size: 15px;
            color: var(--muted);
            margin-top: 16px;
            line-height: 1.7;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .product-card {
            position: relative;
            padding: 40px 32px;
            background: var(--bg);
            border: 1px solid rgba(45, 106, 53, 0.08);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.5s cubic-bezier(.25, .46, .45, .94);
            box-shadow: var(--shadow-sm);
        }

        .product-card-glow {
            position: absolute;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(45, 106, 53, 0.08) 0%, transparent 70%);
            top: -60px;
            right: -60px;
            transition: all 0.5s;
            opacity: 0;
        }

        .product-card:hover .product-card-glow {
            opacity: 1;
            transform: scale(1.5);
        }

        .product-card:hover {
            border-color: rgba(45, 106, 53, 0.2);
            transform: translateY(-8px) scale(1.01);
            box-shadow: var(--shadow-lg);
            background: var(--white);
        }

        .product-num {
            font-family: 'Cormorant Garamond', serif;
            font-size: 72px;
            font-weight: 300;
            color: rgba(45, 106, 53, 0.05);
            position: absolute;
            top: 16px;
            right: 20px;
            line-height: 1;
            transition: color 0.4s;
        }

        .product-card:hover .product-num {
            color: rgba(45, 106, 53, 0.1);
        }

        .product-icon-wrap {
            width: 56px;
            height: 56px;
            background: rgba(45, 106, 53, 0.07);
            border: 1px solid rgba(45, 106, 53, 0.15);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            transition: all 0.4s;
            color: var(--green);
        }

        .product-card:hover .product-icon-wrap {
            background: var(--green);
            border-color: var(--green);
            color: white;
            box-shadow: 0 8px 24px rgba(45, 106, 53, 0.25);
            transform: scale(1.08) rotate(-4deg);
        }

        .product-name {
            font-size: 17px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 12px;
        }

        .product-desc {
            font-size: 14px;
            line-height: 1.75;
            color: var(--muted);
            margin-bottom: 20px;
        }

        .product-tag {
            display: inline-block;
            padding: 5px 12px;
            background: rgba(45, 106, 53, 0.07);
            border: 1px solid rgba(45, 106, 53, 0.15);
            border-radius: 100px;
            font-size: 11px;
            font-weight: 600;
            color: var(--green);
            letter-spacing: 0.08em;
            transition: all 0.3s;
        }

        .product-card:hover .product-tag {
            background: var(--green);
            border-color: var(--green);
            color: white;
        }

        /* ─── DIFFERENTIALS ─── */
        .differentials {
            padding: 140px 60px;
            background: var(--bg);
        }

        .diff-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1px;
            border: 1px solid rgba(45, 106, 53, 0.1);
            border-radius: 24px;
            overflow: hidden;
            max-width: 1200px;
            margin: 64px auto 0;
            background: rgba(45, 106, 53, 0.08);
            box-shadow: var(--shadow-md);
        }

        .diff-item {
            padding: 56px 48px;
            background: var(--white);
            position: relative;
            overflow: hidden;
            transition: background 0.4s;
        }

        .diff-item::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 0% 100%, rgba(45, 106, 53, 0.04) 0%, transparent 60%);
            opacity: 0;
            transition: opacity 0.4s;
        }

        .diff-item:hover {
            background: rgba(245, 250, 245, 1);
        }

        .diff-item:hover::after {
            opacity: 1;
        }

        .diff-counter {
            font-family: 'Cormorant Garamond', serif;
            font-size: 80px;
            font-weight: 300;
            color: rgba(45, 106, 53, 0.05);
            position: absolute;
            top: 24px;
            right: 32px;
            line-height: 1;
        }

        .diff-icon {
            color: var(--green);
            margin-bottom: 20px;
        }

        .diff-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 14px;
        }

        .diff-desc {
            font-size: 15px;
            line-height: 1.75;
            color: var(--muted);
        }

        /* ─── APPLICATIONS ─── */
        .applications {
            padding: 140px 60px;
            text-align: center;
            background: var(--white);
            position: relative;
        }

        .applications::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(45, 106, 53, 0.04) 0%, transparent 70%);
            pointer-events: none;
        }

        .app-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            max-width: 1000px;
            margin: 64px auto 0;
        }

        .app-item {
            padding: 32px 20px;
            background: var(--bg);
            border: 1px solid rgba(45, 106, 53, 0.08);
            border-radius: 16px;
            transition: all 0.4s;
            cursor: default;
            box-shadow: var(--shadow-sm);
        }

        .app-item:hover {
            border-color: var(--green);
            background: var(--white);
            transform: translateY(-6px) scale(1.03);
            box-shadow: var(--shadow-lg);
        }

        .app-icon-wrap {
            width: 48px;
            height: 48px;
            margin: 0 auto 16px;
            background: rgba(45, 106, 53, 0.07);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--green);
            transition: all 0.4s;
        }

        .app-item:hover .app-icon-wrap {
            background: var(--green);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 8px 20px rgba(45, 106, 53, 0.25);
        }

        .app-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 4px;
        }

        .app-sub {
            font-size: 12px;
            color: var(--muted);
        }

        /* ─── CONTACT ─── */
        .contact {
            padding: 100px 60px 140px;
            background: var(--bg);
        }

        .contact-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px;
            background: rgba(45, 106, 53, 0.1);
            border-radius: 28px;
            overflow: hidden;
            border: 1px solid rgba(45, 106, 53, 0.1);
            box-shadow: var(--shadow-lg);
        }

        .contact-left {
            padding: 72px 64px;
            background: var(--white);
            position: relative;
            overflow: hidden;
        }

        .contact-left::before {
            content: '';
            position: absolute;
            top: -100px;
            left: -100px;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(45, 106, 53, 0.06) 0%, transparent 70%);
            pointer-events: none;
        }

        .contact-headline {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(36px, 4vw, 56px);
            font-weight: 300;
            color: var(--ink);
            line-height: 1.15;
            margin-bottom: 20px;
        }

        .contact-headline em {
            font-style: italic;
            color: var(--green);
        }

        .contact-desc {
            font-size: 15px;
            color: var(--muted);
            line-height: 1.8;
            margin-bottom: 40px;
        }

        .contact-btns {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .btn-wpp {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 18px 28px;
            background: var(--green);
            color: white;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 0.06em;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(45, 106, 53, 0.25);
        }

        .btn-wpp::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, var(--green), var(--green-light));
            opacity: 0;
            transition: opacity 0.3s;
        }

        .btn-wpp:hover::before {
            opacity: 1;
        }

        .btn-wpp:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(45, 106, 53, 0.3);
        }

        .btn-wpp span,
        .btn-wpp svg {
            position: relative;
            z-index: 1;
        }

        .btn-outline {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 17px 28px;
            background: transparent;
            border: 1px solid rgba(26, 26, 24, 0.12);
            color: var(--text);
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-outline:hover {
            border-color: var(--green);
            color: var(--green);
            background: rgba(45, 106, 53, 0.04);
            transform: translateY(-3px);
        }

        .contact-right {
            padding: 72px 64px;
            background: var(--bg2);
            display: flex;
            flex-direction: column;
            gap: 32px;
        }

        .contact-info-item {
            display: flex;
            gap: 18px;
            align-items: flex-start;
        }

        .contact-info-icon {
            width: 44px;
            height: 44px;
            background: rgba(45, 106, 53, 0.08);
            border: 1px solid rgba(45, 106, 53, 0.15);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--green);
            flex-shrink: 0;
            transition: all 0.3s;
        }

        .contact-info-item:hover .contact-info-icon {
            background: var(--green);
            border-color: var(--green);
            color: white;
            box-shadow: 0 6px 20px rgba(45, 106, 53, 0.25);
        }

        .contact-info-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--green);
            margin-bottom: 6px;
        }

        .contact-info-value {
            font-size: 15px;
            color: var(--text);
            line-height: 1.6;
        }

        /* ─── FOOTER ─── */
        footer {
            padding: 32px 60px;
            background: var(--ink);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .footer-logo {
            font-family: 'Cormorant Garamond', serif;
            font-size: 20px;
            font-weight: 600;
            letter-spacing: 0.15em;
            color: rgba(255, 255, 255, 0.9);
        }

        .footer-logo span {
            color: var(--green-light);
        }

        .footer-copy {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.35);
        }

        .footer-social {
            display: flex;
            gap: 16px;
        }

        .footer-social a {
            width: 38px;
            height: 38px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.4);
            transition: all 0.3s;
            text-decoration: none;
        }

        .footer-social a:hover {
            border-color: var(--green-light);
            color: var(--green-light);
            background: rgba(140, 198, 63, 0.1);
        }

        /* ─── ANIMATIONS ─── */
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(30px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        @keyframes fadeDown {
            from {
                opacity: 0;
                transform: translateY(-20px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0
            }

            to {
                opacity: 1
            }
        }

        .reveal {
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.8s cubic-bezier(.25, .46, .45, .94), transform 0.8s cubic-bezier(.25, .46, .45, .94);
        }

        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .reveal-delay-1 {
            transition-delay: 0.1s;
        }

        .reveal-delay-2 {
            transition-delay: 0.2s;
        }

        .reveal-delay-3 {
            transition-delay: 0.3s;
        }

        .reveal-delay-4 {
            transition-delay: 0.4s;
        }

        .reveal-delay-5 {
            transition-delay: 0.5s;
        }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 1024px) {

            nav,
            nav.scrolled {
                padding: 16px 32px;
            }

            .hero {
                padding: 120px 32px 80px;
            }

            .stats-band {
                padding: 0 32px;
            }

            .stats-inner {
                grid-template-columns: 1fr 1fr;
            }

            .about-grid {
                grid-template-columns: 1fr;
                gap: 60px;
            }

            .products-grid {
                grid-template-columns: 1fr 1fr;
            }

            .diff-grid {
                grid-template-columns: 1fr;
            }

            .app-grid {
                grid-template-columns: 1fr 1fr;
            }

            .contact-inner {
                grid-template-columns: 1fr;
            }

            .about,
            .products,
            .differentials,
            .applications,
            .contact {
                padding-left: 32px;
                padding-right: 32px;
            }
        }

        @media (max-width: 600px) {
            .nav-links {
                display: none;
            }

            .products-grid {
                grid-template-columns: 1fr;
            }

            .hero-title {
                font-size: 48px;
            }

            .hero-title .line-indent {
                margin-left: 24px;
            }

            .hero-actions {
                flex-direction: column;
            }

            footer {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }
        }

        .nav-btn-panel {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            background: transparent;
            border: 1.5px solid rgba(45, 106, 53, 0.25);
            color: var(--green);
            font-family: 'Outfit', sans-serif;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .nav-btn-panel:hover {
            background: var(--green);
            border-color: var(--green);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(45, 106, 53, 0.2);
        }
    </style>
</head>

<body>

    <!-- Particles Canvas -->
    <canvas id="particle-canvas"></canvas>

    <div class="page">

        <!-- NAV -->
        <nav id="main-nav">
            <div class="nav-brand">UNI<span>BIOTECH</span></div>
            <ul class="nav-links">
                <li><a href="#sobre">Sobre</a></li>
                <li><a href="#produtos">Produtos</a></li>
                <li><a href="#diferenciais">Diferenciais</a></li>
                <li><a href="#contato">Contato</a></li>
            </ul>
            <a href="/admin/login" class="nav-btn-panel">
                <!-- heroicon: lock-closed -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="14" height="14">
                    <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 00-5.25 5.25v3a3 3 0 00-3 3v6.75a3 3 0 003 3h10.5a3 3 0 003-3v-6.75a3 3 0 00-3-3v-3c0-2.9-2.35-5.25-5.25-5.25zm3.75 8.25v-3a3.75 3.75 0 10-7.5 0v3h7.5z" clip-rule="evenodd" />
                </svg>
                Acessar Painel
            </a>
        </nav>

        <!-- HERO -->
        <section class="hero">
            <div class="hero-bg-gradient"></div>
            <div class="hero-grid-overlay"></div>
            <div class="hero-content">
                <div class="hero-eyebrow">
                    <div class="hero-eyebrow-dot"></div>
                    Biotecnologia para Lácteos · Alto Piquiri, PR
                </div>
                <h1 class="hero-title">
                    A ciência que<br>
                    transforma <em>leite</em>
                    <span class="line-indent">em excelência</span>
                </h1>
                <p class="hero-sub">
                    Quimosinas, coagulantes, fermentos e insumos de alta performance para a indústria láctea nacional e internacional — com suporte técnico especializado e entrega ágil.
                </p>
                <div class="hero-actions">
                    <a href="https://unibiotechbrasil.com.br/produtos/" class="btn-primary" target="_blank">
                        <span>Ver Produtos</span>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </a>
                    <a href="#contato" class="btn-ghost">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                            <path d="M2.25 6.338c0-1.162.98-2.113 2.144-2.087l3.5.084A1.5 1.5 0 019.26 5.49l.77 3.042a1.5 1.5 0 01-.714 1.686l-1.478.837a11.24 11.24 0 005.106 5.107l.837-1.478a1.5 1.5 0 011.685-.714l3.042.77a1.5 1.5 0 011.155 1.366l.084 3.5c.026 1.164-.925 2.145-2.087 2.145H18C9.716 21 2.25 13.534 2.25 5.25v-.912z" />
                        </svg>
                        Fale Conosco
                    </a>
                </div>
            </div>
            <div class="hero-scroll">
                <div class="scroll-line"></div>
                <span>Scroll</span>
            </div>
        </section>

        <!-- STATS -->
        <div class="stats-band reveal">
            <div class="stats-inner">
                <div class="stat-item">
                    <div class="stat-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
                            <path fill-rule="evenodd" d="M3 2.25a.75.75 0 000 1.5v16.5h-.75a.75.75 0 000 1.5H15v-18a.75.75 0 000-1.5H3zM6.75 19.5v-2.25a.75.75 0 01.75-.75h3a.75.75 0 01.75.75v2.25a.75.75 0 01-.75.75h-3a.75.75 0 01-.75-.75zM6 6.75A.75.75 0 016.75 6h.75a.75.75 0 010 1.5h-.75A.75.75 0 016 6.75zM6.75 9h.75a.75.75 0 010 1.5h-.75a.75.75 0 010-1.5zM6 12.75a.75.75 0 01.75-.75h.75a.75.75 0 010 1.5h-.75a.75.75 0 01-.75-.75zM10.5 6a.75.75 0 000 1.5h.75a.75.75 0 000-1.5h-.75zm-.75 3.75A.75.75 0 0110.5 9h.75a.75.75 0 010 1.5h-.75a.75.75 0 01-.75-.75zM10.5 12a.75.75 0 000 1.5h.75a.75.75 0 000-1.5h-.75zM16.5 6.75v15h5.25a.75.75 0 000-1.5H21v-12a.75.75 0 000-1.5h-4.5zm1.5 4.5a.75.75 0 01.75-.75h.008a.75.75 0 01.75.75v.008a.75.75 0 01-.75.75h-.008a.75.75 0 01-.75-.75v-.008zm.75 2.25a.75.75 0 000 1.5h.008a.75.75 0 000-1.5h-.008zM18 17.25a.75.75 0 01.75-.75h.008a.75.75 0 01.75.75v.008a.75.75 0 01-.75.75h-.008a.75.75 0 01-.75-.75v-.008z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="stat-num">1<span> fábrica</span></div>
                    <div class="stat-label">Própria com laboratório de P&D integrado</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
                            <path fill-rule="evenodd" d="M11.54 22.351l.07.04.028.016a.76.76 0 00.723 0l.028-.015.071-.041a16.975 16.975 0 001.144-.742 19.58 19.58 0 002.683-2.282c1.944-2.083 3.918-5.099 3.918-9.573A8.25 8.25 0 002.25 12c0 4.474 1.974 7.49 3.918 9.573a19.58 19.58 0 002.683 2.282 16.975 16.975 0 001.144.742zM12 13.5a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="stat-num">+10<span> estados</span></div>
                    <div class="stat-label">Centros de distribuição em todo o Brasil</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
                            <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zM12.75 6a.75.75 0 00-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 000-1.5h-3.75V6z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="stat-num">24<span>h</span></div>
                    <div class="stat-label">Disponibilidade de atendimento contínuo</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
                            <path fill-rule="evenodd" d="M10.5 3.798v5.02a3 3 0 01-.879 2.121l-2.377 2.377a9.845 9.845 0 015.091 1.013 8.315 8.315 0 005.713.636l.285-.071-3.954-3.955a3 3 0 01-.879-2.121v-5.02a23.614 23.614 0 00-3 0zm4.5.138a.75.75 0 00.093-1.495A24.837 24.837 0 0012 2.25a25.048 25.048 0 00-3.093.191A.75.75 0 009 3.936v4.882a1.5 1.5 0 01-.44 1.06l-6.293 6.294c-1.62 1.621-.903 4.475 1.471 4.88 2.686.46 5.447.698 8.262.698 2.816 0 5.576-.239 8.262-.697 2.373-.406 3.092-3.26 1.47-4.881L15.44 9.879A1.5 1.5 0 0115 8.818V3.936z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="stat-num">∞<span> tipos</span></div>
                    <div class="stat-label">Variedades de queijos e lácteos atendidos</div>
                </div>
            </div>
        </div>

        <!-- ABOUT -->
        <section class="about" id="sobre">
            <div class="about-grid">
                <div class="reveal">
                    <span class="section-tag">Quem somos</span>
                    <h2 class="about-headline">Renovando produtos<br>e <em>criando soluções</em><br>para o setor lácteo</h2>
                    <p class="about-body">A <strong>Unibiotech</strong> é uma empresa brasileira especializada no fornecimento de insumos de biotecnologia para a produção de queijos e lácteos — atendendo desde o pequeno produtor artesanal até grandes indústrias.</p>
                    <div class="about-quote">
                        <p>"Acompanhamento técnico adequado, atendimento comercial eficiente, produto de qualidade eficaz."</p>
                    </div>
                    <p class="about-body">Com fábrica e laboratório próprios em Alto Piquiri–PR, combinamos tecnologia de ponta com profundo conhecimento do mercado nacional e internacional.</p>
                </div>
                <div class="about-cards reveal reveal-delay-2">
                    <div class="about-card">
                        <div class="about-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="28" height="28">
                                <path d="M5.223 2.25c-.497 0-.974.198-1.325.55l-1.3 1.298A3.75 3.75 0 007.5 9.75c.627.47 1.406.75 2.25.75.844 0 1.624-.28 2.25-.75.626.47 1.406.75 2.25.75.844 0 1.623-.28 2.25-.75a3.75 3.75 0 004.902-5.652l-1.3-1.299a1.875 1.875 0 00-1.325-.549H5.223z" />
                                <path fill-rule="evenodd" d="M3 20.25v-8.755c1.42.674 3.08.673 4.5 0A5.234 5.234 0 009.75 12c.804 0 1.568-.182 2.25-.506a5.234 5.234 0 002.25.506c.804 0 1.567-.182 2.25-.506 1.42.674 3.08.675 4.5.001v8.755h.75a.75.75 0 010 1.5H2.25a.75.75 0 010-1.5H3zm3-6a.75.75 0 01.75-.75h3a.75.75 0 01.75.75v3a.75.75 0 01-.75.75h-3a.75.75 0 01-.75-.75v-3zm8.25-.75a.75.75 0 00-.75.75v5.25c0 .414.336.75.75.75h3a.75.75 0 00.75-.75v-5.25a.75.75 0 00-.75-.75h-3z" clip-rule="evenodd" />
                            </svg></div>
                        <div class="about-card-num">PR</div>
                        <div class="about-card-text">Sede em Alto Piquiri, Paraná</div>
                    </div>
                    <div class="about-card">
                        <div class="about-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="28" height="28">
                                <path d="M3.375 4.5C2.339 4.5 1.5 5.34 1.5 6.375V13.5h12V6.375c0-1.036-.84-1.875-1.875-1.875h-8.25zM13.5 15h-12v2.625c0 1.035.84 1.875 1.875 1.875H3.75a3 3 0 106 0h2.25a.75.75 0 00.75-.75V15z" />
                                <path d="M8.25 19.5a1.5 1.5 0 10-3 0 1.5 1.5 0 003 0zM15.75 6.75a.75.75 0 00-.75.75v11.25c0 .087.015.17.042.248a3 3 0 015.958.464c.853-.175 1.522-.935 1.464-1.883a18.845 18.845 0 00-3.464-9.579zM19.5 19.5a1.5 1.5 0 10-3 0 1.5 1.5 0 003 0z" />
                            </svg></div>
                        <div class="about-card-num">+10</div>
                        <div class="about-card-text">Estados com distribuição própria</div>
                    </div>
                    <div class="about-card">
                        <div class="about-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="28" height="28">
                                <path fill-rule="evenodd" d="M10.5 3.798v5.02a3 3 0 01-.879 2.121l-2.377 2.377a9.845 9.845 0 015.091 1.013 8.315 8.315 0 005.713.636l.285-.071-3.954-3.955a3 3 0 01-.879-2.121v-5.02a23.614 23.614 0 00-3 0zm4.5.138a.75.75 0 00.093-1.495A24.837 24.837 0 0012 2.25a25.048 25.048 0 00-3.093.191A.75.75 0 009 3.936v4.882a1.5 1.5 0 01-.44 1.06l-6.293 6.294c-1.62 1.621-.903 4.475 1.471 4.88 2.686.46 5.447.698 8.262.698 2.816 0 5.576-.239 8.262-.697 2.373-.406 3.092-3.26 1.47-4.881L15.44 9.879A1.5 1.5 0 0115 8.818V3.936z" clip-rule="evenodd" />
                            </svg></div>
                        <div class="about-card-num">Lab</div>
                        <div class="about-card-text">Fábrica e laboratório próprios</div>
                    </div>
                    <div class="about-card">
                        <div class="about-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="28" height="28">
                                <path d="M4.5 6.375a4.125 4.125 0 118.25 0 4.125 4.125 0 01-8.25 0zM14.25 8.625a3.375 3.375 0 116.75 0 3.375 3.375 0 01-6.75 0zM1.5 19.125a7.125 7.125 0 0114.25 0v.003l-.001.119a.75.75 0 01-.363.63 13.067 13.067 0 01-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 01-.364-.63l-.001-.122zM17.25 19.128l-.001.144a2.25 2.25 0 01-.233.96 10.088 10.088 0 005.06-1.01.75.75 0 00.42-.643 4.875 4.875 0 00-6.957-4.611 8.586 8.586 0 011.71 5.157v.003z" />
                            </svg></div>
                        <div class="about-card-num">B2B</div>
                        <div class="about-card-text">Artesanais e industriais em todo o Brasil</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- PRODUCTS -->
        <section class="products" id="produtos">
            <div class="products-header reveal">
                <span class="section-tag" style="justify-content:center">Portfólio</span>
                <h2 class="products-headline">Nossos <em>Produtos</em></h2>
                <p class="products-sub">Linha completa de enzimas, culturas e insumos para cada etapa da produção láctea</p>
            </div>
            <div class="products-grid">
                <div class="product-card reveal reveal-delay-1">
                    <div class="product-card-glow"></div>
                    <div class="product-num">01</div>
                    <div class="product-icon-wrap"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="26" height="26">
                            <path fill-rule="evenodd" d="M10.5 3.798v5.02a3 3 0 01-.879 2.121l-2.377 2.377a9.845 9.845 0 015.091 1.013 8.315 8.315 0 005.713.636l.285-.071-3.954-3.955a3 3 0 01-.879-2.121v-5.02a23.614 23.614 0 00-3 0zm4.5.138a.75.75 0 00.093-1.495A24.837 24.837 0 0012 2.25a25.048 25.048 0 00-3.093.191A.75.75 0 009 3.936v4.882a1.5 1.5 0 01-.44 1.06l-6.293 6.294c-1.62 1.621-.903 4.475 1.471 4.88 2.686.46 5.447.698 8.262.698 2.816 0 5.576-.239 8.262-.697 2.373-.406 3.092-3.26 1.47-4.881L15.44 9.879A1.5 1.5 0 0115 8.818V3.936z" clip-rule="evenodd" />
                        </svg></div>
                    <div class="product-name">Quimosina & Coagulantes</div>
                    <div class="product-desc">Enzimas de alta pureza para coagulação precisa do leite. Garantem textura superior, maior rendimento e padronização total do processo produtivo.</div>
                    <span class="product-tag">Enzimas</span>
                </div>
                <div class="product-card reveal reveal-delay-2">
                    <div class="product-card-glow"></div>
                    <div class="product-num">02</div>
                    <div class="product-icon-wrap"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="26" height="26">
                            <path fill-rule="evenodd" d="M9 4.5a.75.75 0 01.721.544l.813 2.846a3.75 3.75 0 002.576 2.576l2.846.813a.75.75 0 010 1.442l-2.846.813a3.75 3.75 0 00-2.576 2.576l-.813 2.846a.75.75 0 01-1.442 0l-.813-2.846a3.75 3.75 0 00-2.576-2.576l-2.846-.813a.75.75 0 010-1.442l2.846-.813A3.75 3.75 0 007.466 7.89l.813-2.846A.75.75 0 019 4.5zM18 1.5a.75.75 0 01.728.568l.258 1.036c.236.94.97 1.674 1.91 1.91l1.036.258a.75.75 0 010 1.456l-1.036.258c-.94.236-1.674.97-1.91 1.91l-.258 1.036a.75.75 0 01-1.456 0l-.258-1.036a2.625 2.625 0 00-1.91-1.91l-1.036-.258a.75.75 0 010-1.456l1.036-.258a2.625 2.625 0 001.91-1.91l.258-1.036A.75.75 0 0118 1.5z" clip-rule="evenodd" />
                        </svg></div>
                    <div class="product-name">Fermentos Lácteos</div>
                    <div class="product-desc">Culturas starter e adjuntas para queijos maturados, frescos e mofados. Controlam acidez, sabor e aroma com precisão microbiológica.</div>
                    <span class="product-tag">Culturas</span>
                </div>
                <div class="product-card reveal reveal-delay-3">
                    <div class="product-card-glow"></div>
                    <div class="product-num">03</div>
                    <div class="product-icon-wrap"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="26" height="26">
                            <path fill-rule="evenodd" d="M10.5 3.75a6.75 6.75 0 100 13.5 6.75 6.75 0 000-13.5zM2.25 10.5a8.25 8.25 0 1114.59 5.28l4.69 4.69a.75.75 0 11-1.06 1.06l-4.69-4.69A8.25 8.25 0 012.25 10.5z" clip-rule="evenodd" />
                        </svg></div>
                    <div class="product-name">Teste de Antibióticos</div>
                    <div class="product-desc">Kits rápidos e confiáveis para detecção de resíduos de antibióticos no leite — conformidade com legislação vigente e segurança alimentar total.</div>
                    <span class="product-tag">Diagnóstico</span>
                </div>
                <div class="product-card reveal reveal-delay-1">
                    <div class="product-card-glow"></div>
                    <div class="product-num">04</div>
                    <div class="product-icon-wrap"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="26" height="26">
                            <path fill-rule="evenodd" d="M9 4.5a.75.75 0 01.721.544l.813 2.846a3.75 3.75 0 002.576 2.576l2.846.813a.75.75 0 010 1.442l-2.846.813a3.75 3.75 0 00-2.576 2.576l-.813 2.846a.75.75 0 01-1.442 0l-.813-2.846a3.75 3.75 0 00-2.576-2.576l-2.846-.813a.75.75 0 010-1.442l2.846-.813A3.75 3.75 0 007.466 7.89l.813-2.846A.75.75 0 019 4.5z" clip-rule="evenodd" />
                        </svg></div>
                    <div class="product-name">Insumos Alimentícios</div>
                    <div class="product-desc">Ingredientes e aditivos selecionados para aprimorar o processo produtivo: conservantes, corantes naturais, estabilizantes e muito mais.</div>
                    <span class="product-tag">Insumos</span>
                </div>
                <div class="product-card reveal reveal-delay-2">
                    <div class="product-card-glow"></div>
                    <div class="product-num">05</div>
                    <div class="product-icon-wrap"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="26" height="26">
                            <path d="M19.006 3.705a.75.75 0 00-.512-1.41L6 6.838V3a.75.75 0 00-.75-.75h-1.5A.75.75 0 003 3v4.93l-1.006.365a.75.75 0 00.512 1.41l16.5-6z" />
                            <path fill-rule="evenodd" d="M3.019 11.115L18 5.667V9.09l4.006 1.456a.75.75 0 11-.512 1.41l-.494-.18v8.475h.75a.75.75 0 010 1.5H2.25a.75.75 0 010-1.5H3v-9.129l.019-.006zM18 20.25v-9.565l1.5.545v9.02H18zm-9-6a.75.75 0 00-.75.75v4.5c0 .414.336.75.75.75h3a.75.75 0 00.75-.75V15a.75.75 0 00-.75-.75H9z" clip-rule="evenodd" />
                        </svg></div>
                    <div class="product-name">Linha Artesanal</div>
                    <div class="product-desc">Para pequenos e médios produtores. Queijos Canastra, Minas, Coalho e toda a tradição queijeira brasileira com suporte técnico dedicado.</div>
                    <span class="product-tag">Artesanal</span>
                </div>
                <div class="product-card reveal reveal-delay-3">
                    <div class="product-card-glow"></div>
                    <div class="product-num">06</div>
                    <div class="product-icon-wrap"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="26" height="26">
                            <path fill-rule="evenodd" d="M11.078 2.25c-.917 0-1.699.663-1.85 1.567L9.05 4.889c-.02.12-.115.26-.297.348a7.493 7.493 0 00-.986.57c-.166.115-.334.126-.45.083L6.3 5.508a1.875 1.875 0 00-2.282.819l-.922 1.597a1.875 1.875 0 00.432 2.385l.84.692c.095.078.17.229.154.43a7.598 7.598 0 000 1.139c.015.2-.059.352-.153.43l-.841.692a1.875 1.875 0 00-.432 2.385l.922 1.597a1.875 1.875 0 002.282.818l1.019-.382c.115-.043.283-.031.45.082.312.214.641.405.985.57.182.088.277.228.297.35l.178 1.071c.151.904.933 1.567 1.85 1.567h1.844c.916 0 1.699-.663 1.85-1.567l.178-1.072c.02-.12.114-.26.297-.349.344-.165.673-.356.985-.57.167-.114.335-.125.45-.082l1.02.382a1.875 1.875 0 002.28-.819l.923-1.597a1.875 1.875 0 00-.432-2.385l-.84-.692c-.095-.078-.17-.229-.154-.43a7.614 7.614 0 000-1.139c-.016-.2.059-.352.153-.43l.84-.692c.708-.582.891-1.59.433-2.385l-.922-1.597a1.875 1.875 0 00-2.282-.818l-1.02.382c-.114.043-.282.031-.449-.083a7.49 7.49 0 00-.985-.57c-.183-.087-.277-.227-.297-.348l-.179-1.072a1.875 1.875 0 00-1.85-1.567h-1.843zM12 15.75a3.75 3.75 0 100-7.5 3.75 3.75 0 000 7.5z" clip-rule="evenodd" />
                        </svg></div>
                    <div class="product-name">Linha Industrial</div>
                    <div class="product-desc">Soluções escaláveis para grandes laticínios: Mussarela, Provolone, Prato, Requeijão de Corte — performance em escala.</div>
                    <span class="product-tag">Industrial</span>
                </div>
            </div>
        </section>

        <!-- DIFFERENTIALS -->
        <section class="differentials" id="diferenciais">
            <div style="max-width:1200px;margin:0 auto">
                <div class="reveal" style="text-align:center;max-width:600px;margin:0 auto">
                    <span class="section-tag" style="justify-content:center">Por que a Unibiotech</span>
                    <h2 style="font-family:'Cormorant Garamond',serif;font-size:clamp(40px,5vw,64px);font-weight:300;color:var(--ink);line-height:1.1">Conceitos que fazem a <em style="font-style:italic;color:var(--green)">diferença</em></h2>
                </div>
                <div class="diff-grid reveal reveal-delay-2">
                    <div class="diff-item">
                        <div class="diff-counter">01</div>
                        <div class="diff-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="36" height="36">
                                <path d="M10.5 1.5a.75.75 0 00-1.5 0V4.5a.75.75 0 001.5 0V1.5zM5.636 4.136a.75.75 0 011.06 0l1.592 1.591a.75.75 0 01-1.061 1.06l-1.591-1.59a.75.75 0 010-1.061zm12.728 0a.75.75 0 010 1.06l-1.591 1.592a.75.75 0 01-1.06-1.061l1.59-1.591a.75.75 0 011.061 0zm-6.816 4.496a.75.75 0 01.82.311l5.228 7.917a.75.75 0 01-.777 1.148l-2.097-.43 1.045 3.9a.75.75 0 01-1.45.388l-1.044-3.899-1.601 1.42a.75.75 0 01-1.247-.606l.569-9.47a.75.75 0 01.554-.678zM3 10.5a.75.75 0 01.75-.75H6a.75.75 0 010 1.5H3.75A.75.75 0 013 10.5zm14.25 0a.75.75 0 01.75-.75h2.25a.75.75 0 010 1.5H18a.75.75 0 01-.75-.75zm-8.962 3.712a.75.75 0 010 1.061l-1.591 1.591a.75.75 0 11-1.061-1.06l1.591-1.592a.75.75 0 011.061 0z" />
                            </svg></div>
                        <div class="diff-title">Múltiplas Parcerias</div>
                        <div class="diff-desc">Centros de distribuição em diversos estados brasileiros. Transporte próprio e terceirizado garantem agilidade em todo o território nacional.</div>
                    </div>
                    <div class="diff-item">
                        <div class="diff-counter">02</div>
                        <div class="diff-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="36" height="36">
                                <path fill-rule="evenodd" d="M4.5 2.25a.75.75 0 000 1.5v16.5h-.75a.75.75 0 000 1.5H20.25a.75.75 0 000-1.5h-.75V3.75a.75.75 0 000-1.5h-15zM9 6a.75.75 0 000 1.5h1.5a.75.75 0 000-1.5H9zm-.75 3.75A.75.75 0 019 9h1.5a.75.75 0 010 1.5H9a.75.75 0 01-.75-.75zM9 12a.75.75 0 000 1.5h1.5a.75.75 0 000-1.5H9zm3.75-5.25A.75.75 0 0113.5 6H15a.75.75 0 010 1.5h-1.5a.75.75 0 01-.75-.75zM13.5 9a.75.75 0 000 1.5H15A.75.75 0 0015 9h-1.5zm-.75 3.75a.75.75 0 01.75-.75H15a.75.75 0 010 1.5h-1.5a.75.75 0 01-.75-.75zM9 19.5v-2.25a.75.75 0 01.75-.75h4.5a.75.75 0 01.75.75v2.25a.75.75 0 01-.75.75h-4.5A.75.75 0 019 19.5z" clip-rule="evenodd" />
                            </svg></div>
                        <div class="diff-title">Fábrica e Laboratório Próprios</div>
                        <div class="diff-desc">Infraestrutura completa com tecnologia de ponta, segurança alimentar e controle de qualidade rigoroso em todas as etapas.</div>
                    </div>
                    <div class="diff-item">
                        <div class="diff-counter">03</div>
                        <div class="diff-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="36" height="36">
                                <path d="M11.7 2.805a.75.75 0 01.6 0A60.65 60.65 0 0122.83 8.72a.75.75 0 01-.231 1.337 49.949 49.949 0 00-9.902 3.912l-.003.002-.34.18a.75.75 0 01-.707 0A50.009 50.009 0 007.5 12.174v-.224c0-.131.067-.248.172-.311a54.614 54.614 0 014.653-2.52.75.75 0 00-.65-1.352 56.129 56.129 0 00-4.78 2.589 1.858 1.858 0 00-.859 1.228 49.803 49.803 0 00-4.634-1.527.75.75 0 01-.231-1.337A60.653 60.653 0 0111.7 2.805z" />
                                <path d="M13.06 15.473a48.45 48.45 0 017.666-3.282c.134 1.414.22 2.843.255 4.285a.75.75 0 01-.46.71 47.878 47.878 0 00-8.105 4.342.75.75 0 01-.832 0 47.877 47.877 0 00-8.104-4.342.75.75 0 01-.461-.71c.035-1.442.121-2.87.255-4.286A48.4 48.4 0 016 13.18v1.27a1.5 1.5 0 00-.14 2.508c-.09.38-.222.753-.397 1.11.452.213.901.434 1.346.661a6.729 6.729 0 00.551-1.608 1.5 1.5 0 00.14-2.67v-.645a48.549 48.549 0 013.44 1.668 2.25 2.25 0 002.12 0z" />
                            </svg></div>
                        <div class="diff-title">Suporte Técnico Especializado</div>
                        <div class="diff-desc">Equipe de especialistas para identificar e resolver as necessidades da sua empresa — com acompanhamento técnico em campo e consultoria personalizada.</div>
                    </div>
                    <div class="diff-item">
                        <div class="diff-counter">04</div>
                        <div class="diff-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="36" height="36">
                                <path fill-rule="evenodd" d="M5.636 4.575a.75.75 0 010 1.06 9 9 0 000 12.729.75.75 0 01-1.06 1.06c-4.101-4.1-4.101-10.748 0-14.849a.75.75 0 011.06 0zm12.728 0a.75.75 0 011.06 0c4.101 4.1 4.101 10.749 0 14.85a.75.75 0 11-1.06-1.061 9 9 0 000-12.728.75.75 0 010-1.061zm-9.193 2.122a.75.75 0 010 1.06 6 6 0 000 8.486.75.75 0 11-1.061 1.06 7.5 7.5 0 010-10.606.75.75 0 011.06 0zm5.656 0a.75.75 0 011.061 0 7.5 7.5 0 010 10.607.75.75 0 01-1.06-1.061 6 6 0 000-8.486.75.75 0 010-1.06zM12 13.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3z" clip-rule="evenodd" />
                            </svg></div>
                        <div class="diff-title">24 Horas Conectada</div>
                        <div class="diff-desc">Disponibilidade total a qualquer hora, em qualquer lugar do Brasil. Relações comerciais transparentes construídas sobre confiança e resultados.</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- APPLICATIONS -->
        <section class="applications" id="aplicacoes">
            <div style="max-width:1000px;margin:0 auto">
                <div class="reveal">
                    <span class="section-tag" style="justify-content:center">Aplicações</span>
                    <h2 style="font-family:'Cormorant Garamond',serif;font-size:clamp(40px,5vw,64px);font-weight:300;color:var(--ink);line-height:1.1">Para cada tipo de <em style="font-style:italic;color:var(--green)">queijo</em> e lácteo</h2>
                </div>
                <div class="app-grid reveal reveal-delay-2">
                    <div class="app-item">
                        <div class="app-icon-wrap"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22">
                                <path fill-rule="evenodd" d="M2.25 4.125c0-1.036.84-1.875 1.875-1.875h5.25c1.036 0 1.875.84 1.875 1.875V17.25a4.5 4.5 0 11-9 0V4.125zm4.5 14.25a1.125 1.125 0 100-2.25 1.125 1.125 0 000 2.25z" clip-rule="evenodd" />
                                <path d="M10.719 21.75h9.156c1.036 0 1.875-.84 1.875-1.875v-5.25c0-1.036-.84-1.875-1.875-1.875h-.14l-8.742 8.743c-.09.089-.18.175-.274.257zM12.738 17.625l6.474-6.474a1.875 1.875 0 000-2.651L15.5 4.787a1.875 1.875 0 00-2.651 0l-.1.099V17.25c0 .126-.003.251-.01.375z" />
                            </svg></div>
                        <div class="app-name">Mussarela</div>
                        <div class="app-sub">Queijo Filado</div>
                    </div>
                    <div class="app-item">
                        <div class="app-icon-wrap"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22">
                                <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z" clip-rule="evenodd" />
                            </svg></div>
                        <div class="app-name">Requeijão</div>
                        <div class="app-sub">Corte & Cremoso</div>
                    </div>
                    <div class="app-item">
                        <div class="app-icon-wrap"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22">
                                <path fill-rule="evenodd" d="M12.963 2.286a.75.75 0 00-1.071-.136 9.742 9.742 0 00-3.539 6.177A7.547 7.547 0 016.648 6.61a.75.75 0 00-1.152.082A9 9 0 1015.68 4.534a7.46 7.46 0 01-2.717-2.248zM15.75 14.25a3.75 3.75 0 11-7.313-1.172c.628.465 1.35.81 2.133 1a5.99 5.99 0 011.925-3.545 3.75 3.75 0 013.255 3.717z" clip-rule="evenodd" />
                            </svg></div>
                        <div class="app-name">Mofados</div>
                        <div class="app-sub">Gorgonzola, Brie</div>
                    </div>
                    <div class="app-item">
                        <div class="app-icon-wrap"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22">
                                <path d="M19.006 3.705a.75.75 0 00-.512-1.41L6 6.838V3a.75.75 0 00-.75-.75h-1.5A.75.75 0 003 3v4.93l-1.006.365a.75.75 0 00.512 1.41l16.5-6z" />
                                <path fill-rule="evenodd" d="M3.019 11.115L18 5.667V9.09l4.006 1.456a.75.75 0 11-.512 1.41l-.494-.18v8.475h.75a.75.75 0 010 1.5H2.25a.75.75 0 010-1.5H3v-9.129l.019-.006zM18 20.25v-9.565l1.5.545v9.02H18zm-9-6a.75.75 0 00-.75.75v4.5c0 .414.336.75.75.75h3a.75.75 0 00.75-.75V15a.75.75 0 00-.75-.75H9z" clip-rule="evenodd" />
                            </svg></div>
                        <div class="app-name">Artesanais</div>
                        <div class="app-sub">Canastra, Minas</div>
                    </div>
                    <div class="app-item">
                        <div class="app-icon-wrap"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22">
                                <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z" />
                            </svg></div>
                        <div class="app-name">Provolone</div>
                        <div class="app-sub">Defumado & Fresco</div>
                    </div>
                    <div class="app-item">
                        <div class="app-icon-wrap"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22">
                                <path fill-rule="evenodd" d="M1.5 7.125c0-1.036.84-1.875 1.875-1.875h6c1.036 0 1.875.84 1.875 1.875v3.75c0 1.036-.84 1.875-1.875 1.875h-6A1.875 1.875 0 011.5 10.875v-3.75zm12 1.5c0-1.036.84-1.875 1.875-1.875h5.25c1.036 0 1.875.84 1.875 1.875v8.25c0 1.035-.84 1.875-1.875 1.875h-5.25a1.875 1.875 0 01-1.875-1.875v-8.25zM3 16.125c0-1.036.84-1.875 1.875-1.875h5.25c1.036 0 1.875.84 1.875 1.875v2.25c0 1.035-.84 1.875-1.875 1.875h-5.25A1.875 1.875 0 013 18.375v-2.25z" clip-rule="evenodd" />
                            </svg></div>
                        <div class="app-name">Prato</div>
                        <div class="app-sub">Maturado Clássico</div>
                    </div>
                    <div class="app-item">
                        <div class="app-icon-wrap"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22">
                                <path fill-rule="evenodd" d="M1.5 7.125c0-1.036.84-1.875 1.875-1.875h6c1.036 0 1.875.84 1.875 1.875v3.75c0 1.036-.84 1.875-1.875 1.875h-6A1.875 1.875 0 011.5 10.875v-3.75zm12 1.5c0-1.036.84-1.875 1.875-1.875h5.25c1.036 0 1.875.84 1.875 1.875v8.25c0 1.035-.84 1.875-1.875 1.875h-5.25a1.875 1.875 0 01-1.875-1.875v-8.25zM3 16.125c0-1.036.84-1.875 1.875-1.875h5.25c1.036 0 1.875.84 1.875 1.875v2.25c0 1.035-.84 1.875-1.875 1.875h-5.25A1.875 1.875 0 013 18.375v-2.25z" clip-rule="evenodd" />
                            </svg></div>
                        <div class="app-name">Frescos</div>
                        <div class="app-sub">Ricota, Cottage</div>
                    </div>
                    <div class="app-item">
                        <div class="app-icon-wrap"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22">
                                <path fill-rule="evenodd" d="M10.5 3.798v5.02a3 3 0 01-.879 2.121l-2.377 2.377a9.845 9.845 0 015.091 1.013 8.315 8.315 0 005.713.636l.285-.071-3.954-3.955a3 3 0 01-.879-2.121v-5.02a23.614 23.614 0 00-3 0zm4.5.138a.75.75 0 00.093-1.495A24.837 24.837 0 0012 2.25a25.048 25.048 0 00-3.093.191A.75.75 0 009 3.936v4.882a1.5 1.5 0 01-.44 1.06l-6.293 6.294c-1.62 1.621-.903 4.475 1.471 4.88 2.686.46 5.447.698 8.262.698 2.816 0 5.576-.239 8.262-.697 2.373-.406 3.092-3.26 1.47-4.881L15.44 9.879A1.5 1.5 0 0115 8.818V3.936z" clip-rule="evenodd" />
                            </svg></div>
                        <div class="app-name">Outros Lácteos</div>
                        <div class="app-sub">Iogurtes & Bebidas</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CONTACT -->
        <section class="contact" id="contato">
            <div class="contact-inner reveal">
                <div class="contact-left">
                    <span class="section-tag">Fale conosco</span>
                    <h2 class="contact-headline">Pronto para transformar<br>seus <em>lácteos?</em></h2>
                    <p class="contact-desc">Nossa equipe especializada está preparada para identificar e atender as necessidades da sua empresa. Entre em contato agora mesmo.</p>
                    <div class="contact-btns">
                        <a href="https://api.whatsapp.com/send?phone=5544984556886" class="btn-wpp" target="_blank">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" />
                                <path d="M12 0C5.373 0 0 5.373 0 12c0 2.123.554 4.118 1.528 5.849L.057 23.5l5.83-1.528A11.945 11.945 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.813 9.813 0 01-5.013-1.376l-.36-.213-3.46.908.923-3.374-.234-.374A9.817 9.817 0 012.182 12c0-5.42 4.398-9.818 9.818-9.818 5.42 0 9.818 4.398 9.818 9.818 0 5.42-4.398 9.818-9.818 9.818z" />
                            </svg>
                            <span>Chamar no WhatsApp</span>
                        </a>
                        <a href="https://unibiotechbrasil.com.br" class="btn-outline" target="_blank">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                                <path d="M21.721 12.752a9.711 9.711 0 00-.945-5.003 12.754 12.754 0 01-4.339 2.708 18.991 18.991 0 01-.214 4.772 17.165 17.165 0 005.498-2.477zM14.634 15.55a17.324 17.324 0 00.332-4.647c-.952.227-1.945.347-2.966.347-1.021 0-2.014-.12-2.966-.347a17.515 17.515 0 00.332 4.647 17.385 17.385 0 005.268 0zM9.772 17.119a18.963 18.963 0 004.456 0A17.182 17.182 0 0112 21.724a17.18 17.18 0 01-2.228-4.605zM7.777 15.23a18.87 18.87 0 01-.214-4.774 12.753 12.753 0 01-4.34-2.708 9.711 9.711 0 00-.944 5.004 17.165 17.165 0 005.498 2.477zM21.356 14.752a9.765 9.765 0 01-7.478 6.817 18.64 18.64 0 001.988-4.718 18.627 18.627 0 005.49-2.098zM2.644 14.752c1.682.971 3.53 1.688 5.49 2.099a18.64 18.64 0 001.988 4.718 9.765 9.765 0 01-7.478-6.816zM13.878 2.43a9.755 9.755 0 016.116 3.986 11.267 11.267 0 01-3.746 2.504 18.63 18.63 0 00-2.37-6.49zM12 2.276a17.152 17.152 0 012.805 7.121c-.897.23-1.837.353-2.805.353-.968 0-1.908-.122-2.805-.353A17.151 17.151 0 0112 2.276zM10.122 2.43a18.629 18.629 0 00-2.37 6.49 11.266 11.266 0 01-3.746-2.504 9.754 9.754 0 016.116-3.985z" />
                            </svg>
                            Acessar o Site Oficial
                        </a>
                    </div>
                </div>
                <div class="contact-right">
                    <div class="contact-info-item">
                        <div class="contact-info-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                                <path fill-rule="evenodd" d="M11.54 22.351l.07.04.028.016a.76.76 0 00.723 0l.028-.015.071-.041a16.975 16.975 0 001.144-.742 19.58 19.58 0 002.683-2.282c1.944-2.083 3.918-5.099 3.918-9.573A8.25 8.25 0 002.25 12c0 4.474 1.974 7.49 3.918 9.573a19.58 19.58 0 002.683 2.282 16.975 16.975 0 001.144.742zM12 13.5a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                            </svg></div>
                        <div>
                            <div class="contact-info-label">Endereço</div>
                            <div class="contact-info-value">Rodovia PR 681, S/N KM 1,7 — Zona Rural<br>Alto Piquiri - PR, 87580-000</div>
                        </div>
                    </div>
                    <div class="contact-info-item">
                        <div class="contact-info-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                                <path fill-rule="evenodd" d="M1.5 4.5a3 3 0 013-3h1.372c.86 0 1.61.586 1.819 1.42l1.105 4.423a1.875 1.875 0 01-.694 1.955l-1.293.97c-.135.101-.164.249-.126.352a11.285 11.285 0 006.697 6.697c.103.038.25.009.352-.126l.97-1.293a1.875 1.875 0 011.955-.694l4.423 1.105c.834.209 1.42.959 1.42 1.82V19.5a3 3 0 01-3 3h-2.25C8.552 22.5 1.5 15.448 1.5 6.75V4.5z" clip-rule="evenodd" />
                            </svg></div>
                        <div>
                            <div class="contact-info-label">Telefone</div>
                            <div class="contact-info-value">(44) 3656-2670<br>(44) 9 8455-6886</div>
                        </div>
                    </div>
                    <div class="contact-info-item">
                        <div class="contact-info-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                                <path d="M1.5 8.67v8.58a3 3 0 003 3h15a3 3 0 003-3V8.67l-8.928 5.493a3 3 0 01-3.144 0L1.5 8.67z" />
                                <path d="M22.5 6.908V6.75a3 3 0 00-3-3h-15a3 3 0 00-3 3v.158l9.714 5.978a1.5 1.5 0 001.572 0L22.5 6.908z" />
                            </svg></div>
                        <div>
                            <div class="contact-info-label">E-mail</div>
                            <div class="contact-info-value">contato@unibiotechbrasil.com.br</div>
                        </div>
                    </div>
                    <div class="contact-info-item">
                        <div class="contact-info-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                                <path fill-rule="evenodd" d="M9.315 7.584C12.195 3.883 16.695 1.5 21.75 1.5a.75.75 0 01.75.75c0 5.056-2.383 9.555-6.084 12.436A6.75 6.75 0 019.75 22.5a.75.75 0 01-.75-.75v-4.131A15.838 15.838 0 016.382 15H2.25a.75.75 0 01-.75-.75 6.75 6.75 0 017.815-6.666zM15 6.75a2.25 2.25 0 100 4.5 2.25 2.25 0 000-4.5z" clip-rule="evenodd" />
                            </svg></div>
                        <div>
                            <div class="contact-info-label">Redes Sociais</div>
                            <div class="contact-info-value">Instagram · Facebook · @unibiotechbrasil</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FOOTER -->
        <footer>
            <div class="footer-logo">UNI<span>BIOTECH</span></div>
            <div class="footer-copy">© 2025 Biotech Brasil Fermentos e Coagulantes. Todos os direitos reservados.</div>
            <div class="footer-social">
                <a href="https://instagram.com/unibiotechbrasil" target="_blank" title="Instagram">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z" />
                    </svg>
                </a>
                <a href="https://www.facebook.com/Unibiotech-Biotech-Brasil-Fermentos-e-Coagulantes-Ltda-1136756963014126/" target="_blank" title="Facebook">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                    </svg>
                </a>
            </div>
        </footer>

    </div>

    <script>
        // ─── PARTICLES (light-adapted) ───
        const canvas = document.getElementById('particle-canvas');
        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        window.addEventListener('resize', () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        });

        const particles = [];
        for (let i = 0; i < 70; i++) {
            particles.push({
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height,
                r: Math.random() * 1.8 + 0.4,
                vx: (Math.random() - 0.5) * 0.25,
                vy: (Math.random() - 0.5) * 0.25,
                alpha: Math.random() * 0.2 + 0.06,
                hue: Math.random() > 0.6 ? 130 : 150
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

            for (let i = 0; i < particles.length; i++) {
                for (let j = i + 1; j < particles.length; j++) {
                    const dx = particles[i].x - particles[j].x;
                    const dy = particles[i].y - particles[j].y;
                    const dist = Math.sqrt(dx * dx + dy * dy);
                    if (dist < 110) {
                        ctx.beginPath();
                        ctx.moveTo(particles[i].x, particles[i].y);
                        ctx.lineTo(particles[j].x, particles[j].y);
                        ctx.strokeStyle = `rgba(45,106,53,${0.05 * (1 - dist / 110)})`;
                        ctx.lineWidth = 0.6;
                        ctx.stroke();
                    }
                }
            }
            requestAnimationFrame(drawParticles);
        }
        drawParticles();

        // ─── SCROLL REVEAL ───
        const revealEls = document.querySelectorAll('.reveal');
        const revealObserver = new IntersectionObserver(entries => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    e.target.classList.add('visible');
                    revealObserver.unobserve(e.target);
                }
            });
        }, {
            threshold: 0.1
        });
        revealEls.forEach(el => revealObserver.observe(el));

        // ─── NAV SCROLL ───
        const nav = document.getElementById('main-nav');
        window.addEventListener('scroll', () => {
            nav.classList.toggle('scrolled', window.scrollY > 50);
        });
    </script>
</body>

</html>