<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UNIBIOTECH — Visão Geral da Empresa</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --cream: #F5F0E8;
    --dark: #1A1A2E;
    --navy: #16213E;
    --blue: #0F3460;
    --accent: #2A7D4F;
    --accent2: #4DB87A;
    --warm: #E8DFD0;
    --muted: #8A8A9A;
    --white: #FFFFFF;
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  html { scroll-behavior: smooth; }

  body {
    background: var(--cream);
    color: var(--dark);
    font-family: 'DM Sans', sans-serif;
    font-weight: 300;
    overflow-x: hidden;
  }

  /* ---- HERO ---- */
  .hero {
    min-height: 100vh;
    background: var(--navy);
    display: grid;
    grid-template-columns: 1fr 1fr;
    position: relative;
    overflow: hidden;
  }

  .hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 80% 80% at 70% 50%, rgba(42,125,79,0.18) 0%, transparent 70%);
    pointer-events: none;
  }

  .hero-left {
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 80px 60px 80px 80px;
    position: relative;
    z-index: 2;
  }

  .hero-tag {
    font-family: 'Syne', sans-serif;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.25em;
    text-transform: uppercase;
    color: var(--accent2);
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .hero-tag::before {
    content: '';
    display: block;
    width: 32px;
    height: 1px;
    background: var(--accent2);
  }

  .hero h1 {
    font-family: 'DM Serif Display', serif;
    font-size: clamp(42px, 5vw, 72px);
    line-height: 1.05;
    color: var(--white);
    margin-bottom: 24px;
  }
  .hero h1 em {
    font-style: italic;
    color: var(--accent2);
  }

  .hero-desc {
    font-size: 16px;
    line-height: 1.7;
    color: rgba(255,255,255,0.6);
    max-width: 420px;
    margin-bottom: 40px;
  }

  .hero-cta {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    background: var(--accent);
    color: white;
    font-family: 'Syne', sans-serif;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    padding: 16px 28px;
    border-radius: 4px;
    text-decoration: none;
    width: fit-content;
    transition: background 0.3s, transform 0.2s;
  }
  .hero-cta:hover { background: var(--accent2); transform: translateY(-2px); }
  .hero-cta svg { transition: transform 0.2s; }
  .hero-cta:hover svg { transform: translateX(4px); }

  .hero-right {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .hero-visual {
    position: relative;
    width: 380px;
    height: 380px;
  }

  .hero-circle {
    position: absolute;
    border-radius: 50%;
    border: 1px solid rgba(77,184,122,0.2);
    animation: pulse-ring 4s ease-in-out infinite;
  }
  .hero-circle:nth-child(1) { inset: 0; animation-delay: 0s; }
  .hero-circle:nth-child(2) { inset: -30px; opacity: 0.6; animation-delay: 0.8s; }
  .hero-circle:nth-child(3) { inset: -60px; opacity: 0.3; animation-delay: 1.6s; }

  @keyframes pulse-ring {
    0%, 100% { transform: scale(1); opacity: inherit; }
    50% { transform: scale(1.04); }
  }

  .hero-icon-core {
    position: absolute;
    inset: 40px;
    background: rgba(42,125,79,0.12);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(77,184,122,0.3);
  }

  .hero-icon-core svg {
    width: 100px;
    height: 100px;
    opacity: 0.8;
  }

  /* Floating stats around circle */
  .hero-stat {
    position: absolute;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    padding: 14px 18px;
    backdrop-filter: blur(10px);
  }
  .hero-stat:nth-child(5) { top: 20px; right: -20px; }
  .hero-stat:nth-child(6) { bottom: 60px; left: -40px; }
  .hero-stat:nth-child(7) { bottom: 10px; right: 10px; }

  .hero-stat-num {
    font-family: 'DM Serif Display', serif;
    font-size: 28px;
    color: var(--accent2);
    line-height: 1;
  }
  .hero-stat-label {
    font-size: 11px;
    color: rgba(255,255,255,0.5);
    font-weight: 500;
    margin-top: 4px;
    white-space: nowrap;
  }

  /* ---- SECTIONS COMMON ---- */
  section { position: relative; }

  .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 48px;
  }

  .section-label {
    font-family: 'Syne', sans-serif;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.3em;
    text-transform: uppercase;
    color: var(--accent);
    margin-bottom: 16px;
  }

  .section-title {
    font-family: 'DM Serif Display', serif;
    font-size: clamp(32px, 4vw, 52px);
    line-height: 1.1;
    margin-bottom: 20px;
  }

  /* ---- SOBRE ---- */
  .sobre {
    padding: 120px 0;
    background: var(--white);
  }

  .sobre-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 80px;
    align-items: center;
  }

  .sobre-text p {
    font-size: 17px;
    line-height: 1.8;
    color: #555;
    margin-bottom: 20px;
  }

  .sobre-highlight {
    border-left: 3px solid var(--accent);
    padding-left: 24px;
    margin: 32px 0;
  }
  .sobre-highlight p {
    font-family: 'DM Serif Display', serif;
    font-size: 22px;
    line-height: 1.5;
    color: var(--dark);
    font-style: italic;
  }

  .sobre-visual {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
  }

  .sobre-card {
    background: var(--cream);
    border-radius: 12px;
    padding: 28px 24px;
    transition: transform 0.3s;
  }
  .sobre-card:hover { transform: translateY(-4px); }
  .sobre-card:nth-child(2) { background: var(--navy); color: white; margin-top: 32px; }
  .sobre-card:nth-child(3) { background: var(--accent); color: white; margin-top: -16px; }
  .sobre-card:nth-child(4) { background: var(--warm); margin-top: 16px; }

  .sobre-card-num {
    font-family: 'DM Serif Display', serif;
    font-size: 42px;
    line-height: 1;
    margin-bottom: 8px;
  }
  .sobre-card:nth-child(2) .sobre-card-num { color: var(--accent2); }
  .sobre-card:nth-child(3) .sobre-card-num { color: rgba(255,255,255,0.9); }

  .sobre-card-label {
    font-size: 13px;
    font-weight: 500;
    line-height: 1.4;
  }
  .sobre-card:nth-child(2) .sobre-card-label { color: rgba(255,255,255,0.7); }
  .sobre-card:nth-child(3) .sobre-card-label { color: rgba(255,255,255,0.85); }

  /* ---- PRODUTOS ---- */
  .produtos {
    padding: 120px 0;
    background: var(--cream);
  }

  .produtos-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 64px;
  }

  .produtos-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
  }

  .produto-card {
    background: white;
    border-radius: 16px;
    padding: 36px 32px;
    border: 1px solid rgba(0,0,0,0.06);
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
  }

  .produto-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--accent);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s;
  }
  .produto-card:hover { transform: translateY(-6px); box-shadow: 0 20px 60px rgba(0,0,0,0.1); }
  .produto-card:hover::before { transform: scaleX(1); }

  .produto-icon {
    width: 52px;
    height: 52px;
    background: rgba(42,125,79,0.1);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
    font-size: 24px;
  }

  .produto-card h3 {
    font-family: 'Syne', sans-serif;
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 12px;
    color: var(--dark);
  }

  .produto-card p {
    font-size: 14px;
    line-height: 1.7;
    color: #777;
  }

  .produto-tag {
    display: inline-block;
    background: rgba(42,125,79,0.1);
    color: var(--accent);
    font-size: 11px;
    font-weight: 600;
    font-family: 'Syne', sans-serif;
    letter-spacing: 0.08em;
    padding: 4px 10px;
    border-radius: 20px;
    margin-top: 16px;
  }

  /* ---- DIFERENCIAIS ---- */
  .diferenciais {
    padding: 120px 0;
    background: var(--navy);
    color: white;
    overflow: hidden;
  }

  .diferenciais .section-label { color: var(--accent2); }
  .diferenciais .section-title { color: white; }

  .diferenciais-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 2px;
    margin-top: 64px;
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 16px;
    overflow: hidden;
  }

  .diferencial-item {
    padding: 48px 40px;
    border: 1px solid rgba(255,255,255,0.06);
    position: relative;
    transition: background 0.3s;
  }
  .diferencial-item:hover { background: rgba(42,125,79,0.1); }

  .diferencial-num {
    font-family: 'DM Serif Display', serif;
    font-size: 56px;
    color: rgba(77,184,122,0.15);
    line-height: 1;
    position: absolute;
    top: 24px;
    right: 32px;
  }

  .diferencial-icon {
    font-size: 32px;
    margin-bottom: 16px;
  }

  .diferencial-item h3 {
    font-family: 'Syne', sans-serif;
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 12px;
    color: var(--accent2);
  }

  .diferencial-item p {
    font-size: 15px;
    line-height: 1.7;
    color: rgba(255,255,255,0.6);
  }

  /* ---- APLICAÇÕES ---- */
  .aplicacoes {
    padding: 120px 0;
    background: var(--white);
  }

  .aplicacoes-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-top: 56px;
  }

  .aplicacao-item {
    text-align: center;
    padding: 32px 20px;
    border-radius: 12px;
    background: var(--cream);
    transition: all 0.3s;
    cursor: default;
  }
  .aplicacao-item:hover {
    background: var(--navy);
    color: white;
    transform: scale(1.04);
  }

  .aplicacao-item:hover .aplic-label { color: rgba(255,255,255,0.7); }

  .aplic-icon { font-size: 36px; margin-bottom: 16px; }
  .aplic-name {
    font-family: 'Syne', sans-serif;
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 6px;
  }
  .aplic-label {
    font-size: 12px;
    color: var(--muted);
  }

  /* ---- CONTATO ---- */
  .contato {
    padding: 100px 0;
    background: var(--cream);
  }

  .contato-inner {
    background: var(--navy);
    border-radius: 24px;
    padding: 72px 80px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 80px;
    align-items: center;
    position: relative;
    overflow: hidden;
  }

  .contato-inner::before {
    content: '';
    position: absolute;
    width: 500px; height: 500px;
    background: radial-gradient(circle, rgba(42,125,79,0.2) 0%, transparent 70%);
    right: -100px; top: -100px;
    pointer-events: none;
  }

  .contato-inner .section-label { color: var(--accent2); }
  .contato-inner .section-title { color: white; }

  .contato-desc {
    color: rgba(255,255,255,0.6);
    font-size: 16px;
    line-height: 1.7;
    margin-bottom: 32px;
  }

  .contato-info { display: flex; flex-direction: column; gap: 20px; }

  .contato-item {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    color: rgba(255,255,255,0.8);
  }
  .contato-item-icon {
    width: 40px; height: 40px;
    background: rgba(42,125,79,0.2);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
  }
  .contato-item-text { font-size: 14px; line-height: 1.6; }
  .contato-item-title {
    font-family: 'Syne', sans-serif;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--accent2);
    margin-bottom: 4px;
  }

  .contato-btns { display: flex; flex-direction: column; gap: 12px; margin-top: 32px; }

  .btn-wpp {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #25D366;
    color: white;
    padding: 15px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-family: 'Syne', sans-serif;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    transition: filter 0.2s, transform 0.2s;
  }
  .btn-wpp:hover { filter: brightness(1.1); transform: translateY(-2px); }

  .btn-site {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    color: white;
    padding: 15px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-family: 'Syne', sans-serif;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    transition: background 0.2s, transform 0.2s;
  }
  .btn-site:hover { background: rgba(255,255,255,0.15); transform: translateY(-2px); }

  /* ---- FOOTER ---- */
  footer {
    background: var(--dark);
    padding: 32px 48px;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .footer-brand {
    font-family: 'DM Serif Display', serif;
    font-size: 20px;
    color: white;
  }
  .footer-brand span { color: var(--accent2); }

  .footer-copy {
    font-size: 13px;
    color: rgba(255,255,255,0.4);
  }

  /* ---- ANIMATIONS ---- */
  .fade-up {
    opacity: 0;
    transform: translateY(30px);
    transition: opacity 0.7s ease, transform 0.7s ease;
  }
  .fade-up.visible {
    opacity: 1;
    transform: translateY(0);
  }

  /* ---- RESPONSIVE ---- */
  @media (max-width: 900px) {
    .hero { grid-template-columns: 1fr; }
    .hero-right { display: none; }
    .hero-left { padding: 80px 32px; }
    .sobre-grid, .contato-inner { grid-template-columns: 1fr; gap: 40px; }
    .produtos-grid { grid-template-columns: 1fr 1fr; }
    .diferenciais-grid { grid-template-columns: 1fr; }
    .aplicacoes-grid { grid-template-columns: 1fr 1fr; }
    .contato-inner { padding: 48px 32px; }
    .container { padding: 0 24px; }
    .produtos-header { flex-direction: column; align-items: flex-start; gap: 24px; }
  }

  @media (max-width: 600px) {
    .produtos-grid, .aplicacoes-grid { grid-template-columns: 1fr; }
    .sobre-visual { grid-template-columns: 1fr 1fr; }
    footer { flex-direction: column; gap: 12px; text-align: center; }
  }
</style>
</head>
<body>

<!-- HERO -->
<section class="hero">
  <div class="hero-left">
    <span class="hero-tag">Biotecnologia para o Setor Lácteo</span>
    <h1>A ciência que<br>transforma o <em>leite</em><br>em excelência.</h1>
    <p class="hero-desc">
      A Unibiotech é especializada no fornecimento de ingredientes e insumos de alta qualidade para a indústria láctea brasileira e internacional — com suporte técnico, tecnologia e agilidade do campo à mesa.
    </p>
    <a href="https://unibiotechbrasil.com.br/produtos/" class="hero-cta" target="_blank">
      Ver Produtos
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
        <path d="M3 8h10M9 4l4 4-4 4" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </a>
  </div>
  <div class="hero-right">
    <div class="hero-visual">
      <div class="hero-circle"></div>
      <div class="hero-circle"></div>
      <div class="hero-circle"></div>
      <div class="hero-icon-core">
        <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="50" cy="40" r="18" stroke="#4DB87A" stroke-width="2"/>
          <path d="M32 40 C32 60 38 72 50 76 C62 72 68 60 68 40" stroke="#4DB87A" stroke-width="2"/>
          <path d="M20 76 h60" stroke="#4DB87A" stroke-width="2" stroke-linecap="round"/>
          <circle cx="50" cy="40" r="6" fill="#4DB87A" opacity="0.4"/>
          <path d="M50 22 v-10 M50 68 v8" stroke="#4DB87A" stroke-width="1.5" stroke-dasharray="3 3"/>
          <path d="M36 28 L28 20 M64 28 L72 20" stroke="#4DB87A" stroke-width="1.5"/>
        </svg>
      </div>
      <div class="hero-stat">
        <div class="hero-stat-num">24h</div>
        <div class="hero-stat-label">Disponibilidade</div>
      </div>
      <div class="hero-stat">
        <div class="hero-stat-num">BR</div>
        <div class="hero-stat-label">Alcance Nacional</div>
      </div>
      <div class="hero-stat">
        <div class="hero-stat-num">100%</div>
        <div class="hero-stat-label">Qualidade Técnica</div>
      </div>
    </div>
  </div>
</section>

<!-- SOBRE -->
<section class="sobre">
  <div class="container">
    <div class="sobre-grid fade-up">
      <div class="sobre-text">
        <span class="section-label">Quem somos</span>
        <h2 class="section-title">Promovendo segurança e qualidade com o melhor custo-benefício</h2>
        <p>A <strong>Unibiotech — Biotech Brasil Fermentos e Coagulantes</strong> é uma empresa brasileira dedicada ao desenvolvimento e fornecimento de insumos de alta performance para a indústria de laticínios.</p>
        <div class="sobre-highlight">
          <p>"Renovando produtos e criando soluções para o mercado Nacional e Internacional de queijos e lácteos."</p>
        </div>
        <p>Localizada no Paraná, com ampla rede de distribuição, a empresa combina infraestrutura de fábrica e laboratório próprios com atendimento técnico especializado, entregando não apenas produtos, mas resultados reais no processo produtivo dos seus clientes.</p>
      </div>
      <div class="sobre-visual">
        <div class="sobre-card">
          <div class="sobre-card-num">PR</div>
          <div class="sobre-card-label">Sede em Alto Piquiri, Paraná</div>
        </div>
        <div class="sobre-card">
          <div class="sobre-card-num">+10</div>
          <div class="sobre-card-label">Estados atendidos com centros de distribuição</div>
        </div>
        <div class="sobre-card">
          <div class="sobre-card-num">🧪</div>
          <div class="sobre-card-label">Laboratório e fábrica próprios</div>
        </div>
        <div class="sobre-card">
          <div class="sobre-card-num">24h</div>
          <div class="sobre-card-label">Suporte e atendimento em todo o Brasil</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- PRODUTOS -->
<section class="produtos">
  <div class="container">
    <div class="produtos-header fade-up">
      <div>
        <span class="section-label">Portfólio</span>
        <h2 class="section-title">Nossos Produtos</h2>
      </div>
      <a href="https://unibiotechbrasil.com.br/produtos/" target="_blank" style="font-family:'Syne',sans-serif;font-size:13px;font-weight:700;color:var(--accent);text-decoration:none;letter-spacing:0.1em;text-transform:uppercase;white-space:nowrap">Ver todos →</a>
    </div>
    <div class="produtos-grid">
      <div class="produto-card fade-up">
        <div class="produto-icon">🧫</div>
        <h3>Quimosina & Coagulantes</h3>
        <p>Enzimas de alta pureza para a coagulação do leite na fabricação de queijos finos, artesanais e industriais. Garantem textura, rendimento e padronização do processo.</p>
        <span class="produto-tag">Enzimas</span>
      </div>
      <div class="produto-card fade-up">
        <div class="produto-icon">🧬</div>
        <h3>Fermentos Lácteos</h3>
        <p>Culturas starter e adjuntas para queijos maturados, frescos, mofados e funcionais. Controlam acidez, sabor e aroma com precisão microbiológica.</p>
        <span class="produto-tag">Culturas</span>
      </div>
      <div class="produto-card fade-up">
        <div class="produto-icon">🔬</div>
        <h3>Teste de Antibióticos</h3>
        <p>Kits de detecção rápida e confiável de resíduos de antibióticos no leite, garantindo conformidade com legislação vigente e segurança alimentar.</p>
        <span class="produto-tag">Diagnóstico</span>
      </div>
      <div class="produto-card fade-up">
        <div class="produto-icon">🧂</div>
        <h3>Insumos Alimentícios</h3>
        <p>Linha completa de ingredientes e aditivos para aprimorar o processo produtivo de lácteos, incluindo conservantes, corantes naturais e estabilizantes.</p>
        <span class="produto-tag">Insumos</span>
      </div>
      <div class="produto-card fade-up">
        <div class="produto-icon">🧀</div>
        <h3>Linha Artesanal</h3>
        <p>Produtos desenvolvidos para pequenos e médios produtores artesanais, com foco em queijos tradicionais, minas, coalho, canastra e outros queijos regionais.</p>
        <span class="produto-tag">Artesanal</span>
      </div>
      <div class="produto-card fade-up">
        <div class="produto-icon">🏭</div>
        <h3>Linha Industrial</h3>
        <p>Soluções escaláveis para laticínios industriais: Mussarela, Provolone, Prato, Requeijão de Corte, Ricota e toda a cadeia de produção de queijos processados.</p>
        <span class="produto-tag">Industrial</span>
      </div>
    </div>
  </div>
</section>

<!-- DIFERENCIAIS -->
<section class="diferenciais">
  <div class="container">
    <div class="fade-up">
      <span class="section-label">Por que a Unibiotech</span>
      <h2 class="section-title">Conceitos que fazem a diferença</h2>
    </div>
    <div class="diferenciais-grid fade-up">
      <div class="diferencial-item">
        <div class="diferencial-num">01</div>
        <div class="diferencial-icon">🤝</div>
        <h3>Múltiplas Parcerias</h3>
        <p>Centros de distribuição em diversos estados brasileiros. Transporte próprio e terceirizado para garantir agilidade nas entregas em todo o território nacional.</p>
      </div>
      <div class="diferencial-item">
        <div class="diferencial-num">02</div>
        <div class="diferencial-icon">🏗️</div>
        <h3>Fábrica e Laboratório Próprios</h3>
        <p>Infraestrutura completa com tecnologia de ponta, segurança alimentar e controle de qualidade em todas as etapas de produção e análise dos produtos.</p>
      </div>
      <div class="diferencial-item">
        <div class="diferencial-num">03</div>
        <div class="diferencial-icon">🧑‍🔬</div>
        <h3>Suporte Técnico Especializado</h3>
        <p>Equipe de especialistas disponível para identificar e resolver as necessidades da sua empresa, com acompanhamento técnico em campo quando necessário.</p>
      </div>
      <div class="diferencial-item">
        <div class="diferencial-num">04</div>
        <div class="diferencial-icon">🕐</div>
        <h3>24 Horas Conectada</h3>
        <p>Atendimento disponível a qualquer hora do dia, em qualquer lugar do Brasil. Relações comerciais claras, transparentes e baseadas em confiança.</p>
      </div>
    </div>
  </div>
</section>

<!-- APLICAÇÕES -->
<section class="aplicacoes">
  <div class="container">
    <div class="fade-up" style="text-align:center; max-width: 600px; margin: 0 auto 0;">
      <span class="section-label">Aplicações</span>
      <h2 class="section-title">Para cada tipo de queijo e lácteo</h2>
    </div>
    <div class="aplicacoes-grid fade-up">
      <div class="aplicacao-item">
        <div class="aplic-icon">🧀</div>
        <div class="aplic-name">Mussarela</div>
        <div class="aplic-label">Queijo Filado</div>
      </div>
      <div class="aplicacao-item">
        <div class="aplic-icon">🫙</div>
        <div class="aplic-name">Requeijão</div>
        <div class="aplic-label">Corte & Cremoso</div>
      </div>
      <div class="aplicacao-item">
        <div class="aplic-icon">🍃</div>
        <div class="aplic-name">Queijos Mofados</div>
        <div class="aplic-label">Gorgonzola, Brie</div>
      </div>
      <div class="aplicacao-item">
        <div class="aplic-icon">🌾</div>
        <div class="aplic-name">Artesanais</div>
        <div class="aplic-label">Canastra, Minas</div>
      </div>
      <div class="aplicacao-item">
        <div class="aplic-icon">🥛</div>
        <div class="aplic-name">Provolone</div>
        <div class="aplic-label">Defumado & Fresco</div>
      </div>
      <div class="aplicacao-item">
        <div class="aplic-icon">🍽️</div>
        <div class="aplic-name">Queijo Prato</div>
        <div class="aplic-label">Maturado</div>
      </div>
      <div class="aplicacao-item">
        <div class="aplic-icon">❄️</div>
        <div class="aplic-name">Queijos Frescos</div>
        <div class="aplic-label">Ricota, Cottage</div>
      </div>
      <div class="aplicacao-item">
        <div class="aplic-icon">⚗️</div>
        <div class="aplic-name">Outros Lácteos</div>
        <div class="aplic-label">Iogurtes & Bebidas</div>
      </div>
    </div>
  </div>
</section>

<!-- CONTATO -->
<section class="contato">
  <div class="container">
    <div class="contato-inner fade-up">
      <div>
        <span class="section-label">Fale conosco</span>
        <h2 class="section-title" style="font-size:clamp(28px,3vw,42px)">Pronto para transformar a qualidade dos seus lácteos?</h2>
        <p class="contato-desc">Nossa equipe especializada está preparada para identificar e atender as necessidades da sua empresa. Entre em contato agora.</p>
        <div class="contato-btns">
          <a href="https://api.whatsapp.com/send?phone=5544984556886" class="btn-wpp" target="_blank">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.123.554 4.118 1.528 5.849L.057 23.5l5.83-1.528A11.945 11.945 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.813 9.813 0 01-5.013-1.376l-.36-.213-3.46.908.923-3.374-.234-.374A9.817 9.817 0 012.182 12c0-5.42 4.398-9.818 9.818-9.818 5.42 0 9.818 4.398 9.818 9.818 0 5.42-4.398 9.818-9.818 9.818z"/></svg>
            Chamar no WhatsApp
          </a>
          <a href="https://unibiotechbrasil.com.br" class="btn-site" target="_blank">
            🌐 Acessar o Site Oficial
          </a>
        </div>
      </div>
      <div class="contato-info">
        <div class="contato-item">
          <div class="contato-item-icon">📍</div>
          <div class="contato-item-text">
            <div class="contato-item-title">Endereço</div>
            Rodovia PR 681, S/N KM 1,7 — Zona Rural<br>Alto Piquiri - PR, 87580-000
          </div>
        </div>
        <div class="contato-item">
          <div class="contato-item-icon">📞</div>
          <div class="contato-item-text">
            <div class="contato-item-title">Telefone</div>
            (44) 3656-2670
          </div>
        </div>
        <div class="contato-item">
          <div class="contato-item-icon">✉️</div>
          <div class="contato-item-text">
            <div class="contato-item-title">E-mail</div>
            contato@unibiotechbrasil.com.br
          </div>
        </div>
        <div class="contato-item">
          <div class="contato-item-icon">📱</div>
          <div class="contato-item-text">
            <div class="contato-item-title">WhatsApp</div>
            (44) 9 8455-6886
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="footer-brand">UNI<span>BIOTECH</span></div>
  <div class="footer-copy">© 2025 Unibiotech — Biotech Brasil Fermentos e Coagulantes. Todos os direitos reservados.</div>
</footer>

<script>
  // Scroll-triggered fade-up animations
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry, i) => {
      if (entry.isIntersecting) {
        setTimeout(() => {
          entry.target.classList.add('visible');
        }, 100);
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));
</script>
</body>
</html>