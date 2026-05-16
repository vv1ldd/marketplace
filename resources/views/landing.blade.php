<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('landing.hero.title', ['highlight' => '']) }} {{ __('landing.hero.highlight') }} — Meanly</title>
    <meta name="description" content="Meanly — {{ __('landing.hero.badge') }}. {{ __('landing.hero.desc') }}">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
 
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
 
        :root {
            --brand-primary: #f53003;
            --brand-bg: #0a0a0a;
            --brand-card: #161615;
            --brand-text: #EDEDEC;
            --brand-subtext: #A1A09A;
            --brand-border: #3E3E3A;
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
            padding: 1.5rem 3rem;
            display: flex; align-items: center; justify-content: space-between;
            background: rgba(10, 10, 10, 0.8);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--brand-border);
        }
        .logo { font-size: 1.5rem; font-weight: 800; letter-spacing: -0.03em; color: var(--brand-text); text-decoration: none; display: flex; align-items: center; gap: 0.5rem; }
        .logo-mark { width: 12px; height: 12px; background: var(--brand-primary); border-radius: 2px; }
        
        .nav-links { display: flex; gap: 2.5rem; align-items: center; }
        .nav-links a { color: var(--brand-subtext); text-decoration: none; font-size: 14px; font-weight: 500; transition: color .2s; }
        .nav-links a:hover { color: var(--brand-text); }
        
        .lang-switch { display: flex; gap: 0.5rem; align-items: center; margin-right: 1rem; border-right: 1px solid var(--brand-border); padding-right: 1rem; }
        .lang-link { color: var(--brand-subtext); text-decoration: none; font-size: 1.1rem; transition: transform .2s; }
        .lang-link.active { filter: grayscale(0); transform: scale(1.1); }
        .lang-link:not(.active) { filter: grayscale(1) opacity(0.5); }
 
        .nav-cta {
            background: var(--brand-primary); color: #fff !important; padding: .6rem 1.25rem;
            border-radius: 6px; font-weight: 600; font-size: 14px;
            text-decoration: none; transition: filter .2s, transform .1s;
        }
        .nav-cta:hover { filter: brightness(1.1); transform: translateY(-1px); }
 
        /* ── HERO ── */
        .hero {
            min-height: 100vh;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            text-align: center;
            padding: 8rem 1.5rem 5rem;
            position: relative;
        }
 
        .hero-badge {
            display: inline-block;
            background: rgba(245, 48, 3, 0.05);
            border: 1px solid var(--brand-border);
            color: var(--brand-subtext);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 2.5rem;
        }
 
        .hero h1 {
            font-size: clamp(2.5rem, 8vw, 5.5rem);
            font-weight: 800;
            letter-spacing: -0.04em;
            line-height: 0.95;
            max-width: 1000px;
            margin-bottom: 2rem;
        }
        .hero h1 em { font-style: normal; color: var(--brand-primary); }
        
        .hero p {
            font-size: clamp(1.1rem, 2vw, 1.4rem);
            color: var(--brand-subtext);
            max-width: 600px;
            margin-bottom: 3.5rem;
        }
        
        .hero-actions { display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center; }
        
        .btn-primary {
            background: var(--brand-primary); color: #fff;
            padding: 1rem 2rem; border-radius: 8px;
            font-weight: 700; font-size: 16px; text-decoration: none;
            transition: all .2s;
        }
        .btn-primary:hover { filter: brightness(1.1); transform: translateY(-2px); }
        
        .btn-secondary {
            background: transparent; color: var(--brand-text);
            padding: 1rem 2rem; border-radius: 8px; border: 1px solid var(--brand-border);
            font-weight: 700; font-size: 16px; text-decoration: none;
            transition: all .2s;
        }
        .btn-secondary:hover { background: rgba(255,255,255,0.05); }
 
        .hero-stats {
            display: flex; gap: 4rem; margin-top: 5rem; flex-wrap: wrap; justify-content: center;
        }
        .stat { text-align: center; }
        .stat-num { font-size: 2.2rem; font-weight: 800; color: var(--brand-primary); letter-spacing: -0.02em; }
        .stat-label { font-size: 11px; color: var(--brand-subtext); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; }
 
        /* ── SECTIONS ── */
        section { padding: 8rem 1.5rem; }
        .container { max-width: 1100px; margin: 0 auto; }
        
        .section-label {
            font-size: 11px; font-weight: 700; letter-spacing: .05em;
            text-transform: uppercase; color: var(--brand-primary); margin-bottom: 1.5rem;
        }
        .section-title { font-size: clamp(2rem, 4vw, 3rem); font-weight: 800; letter-spacing: -0.03em; margin-bottom: 1.5rem; }
        .section-desc { color: var(--brand-subtext); font-size: 1.1rem; max-width: 600px; }
 
        /* ── HOW IT WORKS ── */
        .how-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 2rem; margin-top: 4rem;
        }
        .how-card {
            background: var(--brand-card); border: 1px solid var(--brand-border);
            border-radius: 12px; padding: 2.5rem;
            transition: border-color .2s, transform .2s;
        }
        .how-card:hover { border-color: var(--brand-primary); }
        .how-num {
            font-size: 11px; font-weight: 800; color: var(--brand-primary);
            margin-bottom: 1.5rem; display: block;
        }
        .how-card h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 0.75rem; }
        .how-card p { font-size: 14px; color: var(--brand-subtext); line-height: 1.6; }
 
        /* ── FEATURES ── */
        .features-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2rem; margin-top: 4rem;
        }
        .feature-card {
            background: var(--brand-bg); border: 1px solid var(--brand-border);
            border-radius: 12px; padding: 2.5rem;
            transition: border-color .2s;
        }
        .feature-card:hover { border-color: var(--brand-primary); }
        .feature-icon {
            font-size: 1.5rem; margin-bottom: 1.5rem; color: var(--brand-primary); font-weight: 800;
        }
        .feature-card h3 { font-size: 1.2rem; font-weight: 700; margin-bottom: 0.75rem; }
        .feature-card p { font-size: 14px; color: var(--brand-subtext); line-height: 1.6; }
 
        /* ── CHANNELS ── */
        .channels-grid {
            display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 3rem;
        }
        .channel-pill {
            display: flex; align-items: center; gap: .75rem;
            background: var(--brand-card); border: 1px solid var(--brand-border);
            border-radius: 8px; padding: .75rem 1.5rem;
            font-size: 14px; font-weight: 600;
            transition: border-color .2s;
        }
        .channel-pill:hover { border-color: var(--brand-primary); }
 
        /* ── SECURITY ── */
        .security-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; margin-top: 4rem; align-items: center;
        }
        .security-item { display: flex; gap: 1.25rem; margin-bottom: 2rem; }
        .security-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--brand-primary); margin-top: 8px; flex-shrink: 0; }
        .security-item h4 { font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; }
        .security-item p { font-size: 14px; color: var(--brand-subtext); line-height: 1.6; }
        .security-visual {
            background: #000; border: 1px solid var(--brand-border);
            border-radius: 16px; padding: 2.5rem;
            font-family: 'Courier New', monospace; font-size: 13px;
            color: #4ade80; line-height: 1.8;
            box-shadow: 0 40px 80px rgba(0,0,0,0.5);
        }
        .code-line { display: flex; gap: .75rem; }
        .code-key { color: #A1A09A; }
        .code-val-amber { color: var(--brand-primary); }
        .code-val-blue { color: #60a5fa; }
 
        /* ── CTA ── */
        .cta-section {
            background: var(--brand-card);
            border: 1px solid var(--brand-border); border-radius: 20px;
            padding: 5rem 3rem; text-align: center; margin: 0 1.5rem 8rem;
        }
        .cta-section h2 { font-size: clamp(2rem, 5vw, 3.5rem); font-weight: 800; letter-spacing: -0.03em; margin-bottom: 1.5rem; }
        .cta-section p { color: var(--brand-subtext); font-size: 1.2rem; margin-bottom: 3rem; max-width: 700px; margin-left: auto; margin-right: auto; }
 
        /* ── FOOTER ── */
        footer {
            border-top: 1px solid var(--brand-border);
            padding: 4rem 3rem;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 2rem;
            color: var(--brand-subtext);
            font-size: 13px;
            max-width: 1200px; margin: 0 auto; width: 100%;
        }
        .footer-links { display: flex; gap: 2.5rem; }
        .footer-links a { color: var(--brand-subtext); text-decoration: none; font-weight: 500; transition: color .2s; }
        .footer-links a:hover { color: var(--brand-text); }
 
        @media (max-width: 768px) {
            nav { padding: 1.5rem; }
            .nav-links { display: none; }
            .security-grid { grid-template-columns: 1fr; }
            .security-visual { display: none; }
            .cta-section { padding: 3.5rem 1.5rem; }
            section { padding: 5rem 1.5rem; }
        }
    </style>
</head>
<body>
 
<nav>
    <a href="/" class="logo">
        <div class="logo-mark"></div>
        MEANLY
    </a>
    <div class="nav-links">
        <a href="#how">{{ __('landing.nav.how') }}</a>
        <a href="#features">{{ __('landing.nav.features') }}</a>
        <a href="#security">{{ __('landing.nav.security') }}</a>
        
        <div class="lang-switch">
            <a href="{{ route('lang.switch', 'ru') }}" class="lang-link {{ app()->getLocale() == 'ru' ? 'active' : '' }}" title="Русский">🇷🇺</a>
            <a href="{{ route('lang.switch', 'en') }}" class="lang-link {{ app()->getLocale() == 'en' ? 'active' : '' }}" title="English">🇬🇧</a>
            <a href="{{ route('lang.switch', 'tk') }}" class="lang-link {{ app()->getLocale() == 'tk' ? 'active' : '' }}" title="Türkmen">🇹🇲</a>
            <a href="{{ route('lang.switch', 'uz') }}" class="lang-link {{ app()->getLocale() == 'uz' ? 'active' : '' }}" title="Oʻzbek">🇺🇿</a>
            <a href="{{ route('lang.switch', 'ka') }}" class="lang-link {{ app()->getLocale() == 'ka' ? 'active' : '' }}" title="ქართული">🇬🇪</a>
            <a href="{{ route('lang.switch', 'hy') }}" class="lang-link {{ app()->getLocale() == 'hy' ? 'active' : '' }}" title="Հայերեն">🇦🇲</a>
            <a href="{{ route('lang.switch', 'kk') }}" class="lang-link {{ app()->getLocale() == 'kk' ? 'active' : '' }}" title="Қазақ">🇰🇿</a>
        </div>
 
        <a href="/partner" class="nav-cta">{{ __('landing.nav.login') }} →</a>
    </div>
</nav>
 
<section class="hero">
    <div class="hero-badge">
        {{ __('landing.hero.badge') }}
    </div>
 
    <h1>{!! __('landing.hero.title', ['highlight' => '<em>' . __('landing.hero.highlight') . '</em>']) !!}</h1>
 
    <p>{{ __('landing.hero.desc') }}</p>
 
    <div class="hero-actions">
        <a href="/partner/register" class="btn-primary">{{ __('landing.hero.cta_primary') }}</a>
        <a href="#security" class="btn-secondary">Technical Specs</a>
    </div>
 
    <div class="hero-stats">
        <div class="stat">
            <div class="stat-num">∞</div>
            <div class="stat-label">{{ __('landing.hero.stat_channels') }}</div>
        </div>
        <div class="stat">
            <div class="stat-num">AES-256</div>
            <div class="stat-label">{{ __('landing.hero.stat_crypto') }}</div>
        </div>
        <div class="stat">
            <div class="stat-num">API</div>
            <div class="stat-label">{{ __('landing.hero.stat_api') }}</div>
        </div>
        <div class="stat">
            <div class="stat-num">Real-time</div>
            <div class="stat-label">{{ __('landing.hero.stat_realtime') }}</div>
        </div>
    </div>
</section>
 
<section id="how">
    <div class="container">
        <div class="section-label">{{ __('landing.how.label') }}</div>
        <h2 class="section-title">{{ __('landing.how.title') }}</h2>
        <p class="section-desc">{{ __('landing.how.desc') }}</p>
 
        <div class="how-grid">
            @foreach(__('landing.how.steps') as $index => $step)
            <div class="how-card">
                <span class="how-num">STEP {{ $index + 1 }}</span>
                <h3>{{ $step['title'] }}</h3>
                <p>{{ $step['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>
 
<section id="features" style="background: var(--brand-card)">
    <div class="container">
        <div class="section-label">{{ __('landing.features.label') }}</div>
        <h2 class="section-title">{{ __('landing.features.title') }}</h2>
        <p class="section-desc">{{ __('landing.features.desc') }}</p>
 
        <div class="features-grid">
            @foreach(__('landing.features.items') as $item)
            <div class="feature-card">
                <div class="feature-icon">{{ $item['icon'] }}</div>
                <h3>{{ $item['title'] }}</h3>
                <p>{{ $item['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>
 
<section>
    <div class="container">
        <div class="section-label">{{ __('landing.channels.label') }}</div>
        <h2 class="section-title">{{ __('landing.channels.title') }}</h2>
        <div class="channels-grid">
            <div class="channel-pill">🟡 Яндекс.Маркет</div>
            <div class="channel-pill">🛒 WooCommerce</div>
            <div class="channel-pill">✈️ Telegram</div>
            <div class="channel-pill">🏪 Оффлайн-точки</div>
            <div class="channel-pill">🔗 REST API</div>
            <div class="channel-pill">🔔 Вебхуки</div>
        </div>
    </div>
</section>
 
<section id="security">
    <div class="container">
        <div class="section-label">{{ __('landing.security.label') }}</div>
        <h2 class="section-title">{{ __('landing.security.title') }}</h2>
        <div class="security-grid">
            <div>
                @foreach(__('landing.security.items') as $item)
                <div class="security-item">
                    <div class="security-dot"></div>
                    <div>
                        <h4>{{ $item['title'] }}</h4>
                        <p>{{ $item['desc'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="security-visual">
                <div class="code-line"><span class="code-key">// Sovereign Auth Layer</span></div>
                <div class="code-line"><span class="code-key">driver:</span> <span class="code-val-amber">"vault"</span></div>
                <div class="code-line"><span class="code-key">email_field:</span> <span class="code-val-amber">"email_bidx"</span></div>
                <div class="code-line">&nbsp;</div>
                <div class="code-line"><span class="code-key">// PII Storage</span></div>
                <div class="code-line"><span class="code-key">email:</span> <span class="code-val-blue">"vault:local:eyJ..."</span></div>
                <div class="code-line"><span class="code-key">phone:</span> <span class="code-val-blue">"vault:local:eyJ..."</span></div>
                <div class="code-line">&nbsp;</div>
                <div class="code-line"><span class="code-key">// Ledger Record</span></div>
                <div class="code-line"><span class="code-key">event:</span> <span class="code-val-amber">"ORDER_RECEIVE"</span></div>
                <div class="code-line"><span class="code-key">hash:</span> <span class="code-val-blue">"3b1fdbe45a..."</span></div>
                <div class="code-line"><span class="code-key">status:</span> ✓ VERIFIED</div>
            </div>
        </div>
    </div>
</section>
 
<div class="container">
    <div class="cta-section">
        <h2>{!! __('landing.cta.title') !!}</h2>
        <p>{{ __('landing.cta.desc') }}</p>
        <a href="/partner" class="btn-primary">{{ __('landing.cta.button') }} →</a>
    </div>
</div>
 
<footer>
    <div>
        <span style="color: var(--brand-primary); font-weight: 800; letter-spacing: -0.02em;">MEANLY</span>
        &nbsp;·&nbsp; © {{ date('Y') }} {{ __('landing.footer.rights') }}
    </div>
    <div class="footer-links">
        <a href="/partner">{{ __('landing.footer.partner') }}</a>
        <a href="/admin">{{ __('landing.footer.admin') }}</a>
        <a href="/redeem">{{ __('landing.footer.redeem') }}</a>
    </div>
</footer>
 
</body>
</html>
