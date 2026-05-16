<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meanly — Sovereign B2B Platform</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
 
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
 
        :root {
            --brand-primary: #f53003;
            --brand-bg: #050505;
            --brand-card: #0a0a0a;
            --brand-text: #ffffff;
            --brand-subtext: #888888;
            --brand-border: #1a1a1a;
        }
 
        html { scroll-behavior: smooth; }
 
        body {
            font-family: 'Instrument Sans', sans-serif;
            background: var(--brand-bg);
            color: var(--brand-text);
            line-height: 1.5;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }
 
        /* ── NAV ── */
        nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            padding: 1.5rem 2rem;
            display: flex; align-items: center; justify-content: center;
            background: rgba(5, 5, 5, 0.8);
            backdrop-filter: blur(20px);
        }
        .nav-container {
            width: 100%;
            max-width: 1200px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .logo { font-size: 1.2rem; font-weight: 800; letter-spacing: -0.03em; color: var(--brand-text); text-decoration: none; display: flex; align-items: center; gap: 0.5rem; }
        .logo-mark { width: 10px; height: 10px; background: var(--brand-primary); border-radius: 2px; }
        
        .nav-links { display: flex; gap: 2rem; align-items: center; }
        .nav-links a { color: var(--brand-subtext); text-decoration: none; font-size: 13px; font-weight: 500; transition: color .2s; }
        .nav-links a:hover { color: var(--brand-text); }
        
        .nav-actions { display: flex; gap: 1rem; align-items: center; }
        .btn-nav-login { color: var(--brand-text); text-decoration: none; font-size: 13px; font-weight: 500; }
        .btn-nav-cta {
            background: var(--brand-text); color: #000 !important; padding: 0.5rem 1rem;
            border-radius: 100px; font-weight: 600; font-size: 13px;
            text-decoration: none; transition: opacity .2s;
        }
        .btn-nav-cta:hover { opacity: 0.9; }
 
        /* ── HERO ── */
        .hero {
            padding: 12rem 1.5rem 6rem;
            display: flex; flex-direction: column; align-items: flex-start;
            max-width: 1200px; margin: 0 auto;
        }
 
        .hero h1 {
            font-size: clamp(2.5rem, 6vw, 4.5rem);
            font-weight: 700;
            letter-spacing: -0.04em;
            line-height: 1.1;
            max-width: 800px;
            margin-bottom: 2.5rem;
        }
        
        .hero-actions { display: flex; gap: 1rem; margin-bottom: 5rem; }
        
        .btn-pill-primary {
            background: var(--brand-text); color: #000;
            padding: 0.8rem 1.8rem; border-radius: 100px;
            font-weight: 600; font-size: 15px; text-decoration: none;
            display: flex; align-items: center; gap: 0.5rem;
            transition: opacity .2s;
        }
        .btn-pill-primary:hover { opacity: 0.9; }
        
        .btn-pill-secondary {
            background: rgba(255,255,255,0.05); color: var(--brand-text);
            padding: 0.8rem 1.8rem; border-radius: 100px; border: 1px solid var(--brand-border);
            font-weight: 600; font-size: 15px; text-decoration: none;
            transition: background .2s;
        }
        .btn-pill-secondary:hover { background: rgba(255,255,255,0.1); }
 
        /* ── APP PREVIEW ── */
        .app-preview {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            background: #111;
            border: 1px solid var(--brand-border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 40px 100px rgba(0,0,0,0.8);
            aspect-ratio: 16/9;
            position: relative;
        }
        .app-header {
            background: #1a1a1a;
            padding: 0.75rem 1rem;
            display: flex; gap: 0.5rem;
            border-bottom: 1px solid var(--brand-border);
        }
        .dot { width: 8px; height: 8px; border-radius: 50%; background: #333; }
        
        .app-content {
            padding: 2rem;
            display: grid;
            grid-template-columns: 240px 1fr;
            gap: 2rem;
            height: 100%;
        }
        .sidebar { border-right: 1px solid var(--brand-border); padding-right: 2rem; }
        .sidebar-item { height: 8px; background: #222; border-radius: 4px; margin-bottom: 1.5rem; width: 100%; }
        .sidebar-item.short { width: 60%; }
 
        .main-view { display: flex; flex-direction: column; gap: 1.5rem; }
        .card-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
        .preview-card { background: #161615; border: 1px solid var(--brand-border); border-radius: 8px; height: 100px; }
        .preview-chart { flex: 1; background: #161615; border: 1px solid var(--brand-border); border-radius: 8px; }
 
        /* ── SECTIONS ── */
        section { padding: 8rem 1.5rem; max-width: 1200px; margin: 0 auto; width: 100%; }
        .section-title { font-size: clamp(1.5rem, 3vw, 2rem); font-weight: 700; margin-bottom: 4rem; letter-spacing: -0.02em; }
        
        .features-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 4rem;
        }
        .feature-card h3 { font-size: 15px; font-weight: 600; margin-bottom: 1rem; color: var(--brand-text); }
        .feature-card p { font-size: 14px; color: var(--brand-subtext); line-height: 1.6; }
 
        footer {
            padding: 6rem 2rem;
            max-width: 1200px; margin: 0 auto;
            border-top: 1px solid var(--brand-border);
            display: flex; justify-content: space-between;
            color: var(--brand-subtext);
            font-size: 13px;
        }
        .footer-links { display: flex; gap: 2rem; }
        .footer-links a { color: var(--brand-subtext); text-decoration: none; }
 
        @media (max-width: 768px) {
            .nav-links { display: none; }
            .hero { padding-top: 8rem; }
            .app-preview { display: none; }
        }
    </style>
</head>
<body>
 
<nav>
    <div class="nav-container">
        <a href="/" class="logo">
            <div class="logo-mark"></div>
            MEANLY
        </a>
        <div class="nav-links">
            <a href="#product">Product</a>
            <a href="#enterprise">Enterprise</a>
            <a href="#pricing">Pricing</a>
            <a href="#resources">Resources</a>
        </div>
        <div class="nav-actions">
            <a href="/login" class="btn-nav-login">Sign in</a>
            <a href="/register" class="btn-nav-cta">Get Started</a>
        </div>
    </div>
</nav>
 
<section class="hero">
    <h1>Built to make you extraordinarily productive, Meanly is the best way to sell digital goods.</h1>
    
    <div class="hero-actions">
        <a href="/register" class="btn-pill-primary">Get Started →</a>
        <a href="/demo" class="btn-pill-secondary">Request a demo</a>
    </div>
 
    <div class="app-preview">
        <div class="app-header">
            <div class="dot"></div>
            <div class="dot"></div>
            <div class="dot"></div>
        </div>
        <div class="app-content">
            <div class="sidebar">
                <div class="sidebar-item"></div>
                <div class="sidebar-item short"></div>
                <div class="sidebar-item"></div>
                <div class="sidebar-item short"></div>
            </div>
            <div class="main-view">
                <div class="card-row">
                    <div class="preview-card"></div>
                    <div class="preview-card"></div>
                    <div class="preview-card"></div>
                </div>
                <div class="preview-chart"></div>
            </div>
        </div>
    </div>
</section>
 
<section id="features">
    <h2 class="section-title">Institutional infrastructure for high-frequency digital commerce.</h2>
    <div class="features-grid">
        <div class="feature-card">
            <h3>Sovereign Ledger</h3>
            <p>Every transaction, order, and intent is cryptographically anchored in your private ledger. Immutable and verifiable.</p>
        </div>
        <div class="feature-card">
            <h3>Unlimited Scaling</h3>
            <p>Connect to any marketplace via native SDKs. Handle thousands of requests per second with millisecond latency.</p>
        </div>
        <div class="feature-card">
            <h3>Secure Custody</h3>
            <p>Enterprise-grade security for your digital assets. Automated delivery via encrypted channels with zero-trust architecture.</p>
        </div>
    </div>
</section>
 
<footer>
    <div>&copy; {{ date('Y') }} Meanly Systems. Anchored in Sovereignty.</div>
    <div class="footer-links">
        <a href="#">Privacy</a>
        <a href="#">Terms</a>
        <a href="#">Twitter</a>
    </div>
</footer>
 
</body>
</html>
