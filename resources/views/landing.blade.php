<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('landing.hero.title', ['highlight' => '']) }} {{ __('landing.hero.highlight') }} — Meanly</title>
    <meta name="description" content="Meanly — {{ __('landing.hero.badge') }}. {{ __('landing.hero.desc') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --amber: #f59e0b;
            --amber-light: #fcd34d;
            --amber-dark: #d97706;
            --bg: #080b10;
            --bg-card: #0f1420;
            --bg-card2: #141926;
            --border: rgba(255,255,255,0.07);
            --text: #f1f5f9;
            --muted: #64748b;
            --muted2: #94a3b8;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* ── NAV ── */
        nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            padding: 1rem 2rem;
            display: flex; align-items: center; justify-content: space-between;
            background: rgba(8, 11, 16, 0.8);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
        }
        .logo { font-size: 1.4rem; font-weight: 900; letter-spacing: -0.5px; color: var(--amber); text-decoration: none; }
        .logo span { color: var(--text); }
        .nav-links { display: flex; gap: 2rem; align-items: center; }
        .nav-links a { color: var(--muted2); text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: color .2s; }
        .nav-links a:hover { color: var(--text); }
        
        .lang-switch { display: flex; gap: 0.75rem; align-items: center; margin-right: 1rem; border-right: 1px solid var(--border); padding-right: 1rem; }
        .lang-link { 
            color: var(--muted); text-decoration: none; font-size: 0.75rem; font-weight: 700; 
            text-transform: uppercase; transition: color .2s; 
        }
        .lang-link.active { color: var(--amber); }
        .lang-link:hover { color: var(--text); }

        .nav-cta {
            background: var(--amber); color: #000 !important; padding: .5rem 1.25rem;
            border-radius: 8px; font-weight: 700; font-size: .875rem;
            text-decoration: none; transition: background .2s, transform .1s;
        }
        .nav-cta:hover { background: var(--amber-light); transform: translateY(-1px); color: #000 !important; }

        /* ── HERO ── */
        .hero {
            min-height: 100vh;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            text-align: center;
            padding: 8rem 1.5rem 5rem;
            position: relative; overflow: hidden;
        }
        .hero-glow {
            position: absolute; border-radius: 50%; filter: blur(120px); pointer-events: none;
        }
        .hero-glow-1 { width: 600px; height: 600px; background: rgba(245,158,11,0.12); top: -100px; left: 50%; transform: translateX(-50%); }
        .hero-glow-2 { width: 400px; height: 400px; background: rgba(99,102,241,0.08); bottom: 0; right: -100px; }

        .hero-badge {
            display: inline-flex; align-items: center; gap: .5rem;
            background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.3);
            color: var(--amber); padding: .4rem 1rem; border-radius: 999px;
            font-size: .8rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase;
            margin-bottom: 1.5rem;
        }
        .hero h1 {
            font-size: clamp(2.5rem, 7vw, 5rem);
            font-weight: 900;
            letter-spacing: -2px;
            line-height: 1.05;
            max-width: 900px;
            margin-bottom: 1.5rem;
        }
        .hero h1 em { font-style: normal; color: var(--amber); }
        .hero p {
            font-size: clamp(1rem, 2vw, 1.2rem);
            color: var(--muted2);
            max-width: 560px;
            margin-bottom: 2.5rem;
        }
        .hero-actions { display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center; }
        .btn-primary {
            background: var(--amber); color: #000;
            padding: .85rem 2rem; border-radius: 10px;
            font-weight: 700; font-size: 1rem; text-decoration: none;
            transition: all .2s; box-shadow: 0 0 30px rgba(245,158,11,0.3);
        }
        .btn-primary:hover { background: var(--amber-light); transform: translateY(-2px); box-shadow: 0 0 40px rgba(245,158,11,0.5); }
        .btn-secondary {
            background: transparent; color: var(--text);
            padding: .85rem 2rem; border-radius: 10px; border: 1px solid var(--border);
            font-weight: 600; font-size: 1rem; text-decoration: none;
            transition: all .2s;
        }
        .btn-secondary:hover { border-color: rgba(255,255,255,0.2); background: rgba(255,255,255,0.04); }

        .hero-stats {
            display: flex; gap: 3rem; margin-top: 4rem; flex-wrap: wrap; justify-content: center;
        }
        .stat { text-align: center; }
        .stat-num { font-size: 2rem; font-weight: 900; color: var(--amber); }
        .stat-label { font-size: .8rem; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; }

        /* ── SECTION ── */
        section { padding: 5rem 1.5rem; }
        .container { max-width: 1100px; margin: 0 auto; }
        .section-label {
            font-size: .75rem; font-weight: 700; letter-spacing: .12em;
            text-transform: uppercase; color: var(--amber); margin-bottom: 1rem;
        }
        .section-title { font-size: clamp(1.8rem, 4vw, 2.8rem); font-weight: 800; letter-spacing: -1px; margin-bottom: 1rem; }
        .section-desc { color: var(--muted2); font-size: 1.05rem; max-width: 540px; }

        /* ── HOW IT WORKS ── */
        .how-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem; margin-top: 3rem;
        }
        .how-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 16px; padding: 2rem;
            transition: border-color .2s, transform .2s;
        }
        .how-card:hover { border-color: rgba(245,158,11,0.3); transform: translateY(-4px); }
        .how-num {
            width: 40px; height: 40px; border-radius: 10px;
            background: rgba(245,158,11,0.15); color: var(--amber);
            font-weight: 900; font-size: 1.1rem;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 1.25rem;
        }
        .how-card h3 { font-size: 1rem; font-weight: 700; margin-bottom: .5rem; }
        .how-card p { font-size: .875rem; color: var(--muted2); }

        /* ── FEATURES ── */
        .features-section { background: var(--bg-card2); }
        .features-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem; margin-top: 3rem;
        }
        .feature-card {
            background: var(--bg); border: 1px solid var(--border);
            border-radius: 16px; padding: 1.75rem;
            transition: border-color .2s;
        }
        .feature-card:hover { border-color: rgba(245,158,11,0.25); }
        .feature-icon {
            font-size: 2rem; margin-bottom: 1rem;
            width: 52px; height: 52px;
            background: rgba(245,158,11,0.08); border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
        }
        .feature-card h3 { font-size: 1.05rem; font-weight: 700; margin-bottom: .5rem; }
        .feature-card p { font-size: .875rem; color: var(--muted2); }

        /* ── CHANNELS ── */
        .channels-grid {
            display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 2.5rem;
        }
        .channel-pill {
            display: flex; align-items: center; gap: .6rem;
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 10px; padding: .65rem 1.25rem;
            font-size: .9rem; font-weight: 600;
            transition: border-color .2s;
        }
        .channel-pill:hover { border-color: rgba(245,158,11,0.3); }

        /* ── SECURITY ── */
        .security-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 3rem; align-items: center;
        }
        .security-item { display: flex; gap: 1rem; margin-bottom: 1.5rem; }
        .security-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--amber); margin-top: 6px; flex-shrink: 0; }
        .security-item h4 { font-size: .95rem; font-weight: 700; margin-bottom: .25rem; }
        .security-item p { font-size: .85rem; color: var(--muted2); }
        .security-visual {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 20px; padding: 2rem;
            font-family: 'Courier New', monospace; font-size: .8rem;
            color: #4ade80; line-height: 1.8;
        }
        .code-line { display: flex; gap: .5rem; }
        .code-key { color: var(--muted); }
        .code-val-green { color: #4ade80; }
        .code-val-amber { color: var(--amber); }
        .code-val-blue { color: #60a5fa; }

        /* ── CTA ── */
        .cta-section {
            background: linear-gradient(135deg, rgba(245,158,11,0.1), rgba(99,102,241,0.08));
            border: 1px solid rgba(245,158,11,0.2); border-radius: 24px;
            padding: 4rem; text-align: center; margin: 0 1.5rem 5rem;
        }
        .cta-section h2 { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 900; letter-spacing: -1px; margin-bottom: 1rem; }
        .cta-section p { color: var(--muted2); font-size: 1.05rem; margin-bottom: 2rem; }

        /* ── FOOTER ── */
        footer {
            border-top: 1px solid var(--border);
            padding: 2rem 1.5rem;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 1rem;
            color: var(--muted);
            font-size: .85rem;
        }
        .footer-links { display: flex; gap: 1.5rem; }
        .footer-links a { color: var(--muted); text-decoration: none; transition: color .2s; }
        .footer-links a:hover { color: var(--text); }

        /* ── HERO REGISTRATION FORM ── */
        .hero-form-container {
            background: rgba(15, 20, 32, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.08);
            padding: 1.5rem;
            border-radius: 20px;
            margin-bottom: 2.5rem;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3), 0 0 20px rgba(245,158,11,0.05);
        }
        .hero-form {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .form-group-country {
            flex: 0 0 140px;
        }
        .form-group-inn {
            flex: 1 1 200px;
        }
        .hero-form select, .hero-form input {
            width: 100%;
            height: 52px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            color: var(--text);
            font-family: inherit;
            font-size: 0.95rem;
            padding: 0 1rem;
            transition: all 0.2s ease;
            outline: none;
        }
        .hero-form select:focus, .hero-form input:focus {
            border-color: var(--amber);
            background: rgba(255,255,255,0.06);
            box-shadow: 0 0 0 3px rgba(245,158,11,0.15);
        }
        .hero-form select option {
            background: #0f1420;
            color: #fff;
        }
        .hero-btn-submit {
            background: var(--amber);
            color: #000;
            border: none;
            border-radius: 12px;
            padding: 0 1.5rem;
            height: 52px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .hero-btn-submit:hover {
            background: var(--amber-light);
            transform: translateY(-1px);
            box-shadow: 0 4px 20px rgba(245,158,11,0.4);
        }
        .form-subtext {
            margin-top: 0.75rem;
            font-size: 0.75rem;
            color: var(--muted);
            text-align: left;
            padding-left: 0.5rem;
        }

        @media (max-width: 768px) {
            .hero-form { flex-direction: column; }
            .form-group-country { flex: 1 1 auto; }
            .hero-btn-submit { width: 100%; }
            .nav-links { display: none; }
            .security-grid { grid-template-columns: 1fr; }
            .security-visual { display: none; }
            .cta-section { padding: 2.5rem 1.5rem; }
            .hero-stats { gap: 2rem; }
        }
    </style>
</head>
<body>

<!-- NAV -->
<nav>
    <a href="/" class="logo">Mean<span>ly</span></a>
    <div class="nav-links">
        <a href="#how">{{ __('landing.nav.how') }}</a>
        <a href="#features">{{ __('landing.nav.features') }}</a>
        <a href="#security">{{ __('landing.nav.security') }}</a>
        
        <div class="lang-switch" style="font-size: 1.2rem; gap: 0.5rem;">
            <a href="{{ route('lang.switch', 'ru') }}" class="lang-link {{ app()->getLocale() == 'ru' ? 'active' : '' }}" title="Русский">🇷🇺</a>
            <a href="{{ route('lang.switch', 'en') }}" class="lang-link {{ app()->getLocale() == 'en' ? 'active' : '' }}" title="English">🇬🇧</a>
            <a href="{{ route('lang.switch', 'tk') }}" class="lang-link {{ app()->getLocale() == 'tk' ? 'active' : '' }}" title="Türkmen">🇹🇲</a>
            <a href="{{ route('lang.switch', 'uz') }}" class="lang-link {{ app()->getLocale() == 'uz' ? 'active' : '' }}" title="Oʻzbek">🇺🇿</a>
            <a href="{{ route('lang.switch', 'ka') }}" class="lang-link {{ app()->getLocale() == 'ka' ? 'active' : '' }}" title="ქართული">🇬🇪</a>
            <a href="{{ route('lang.switch', 'hy') }}" class="lang-link {{ app()->getLocale() == 'hy' ? 'active' : '' }}" title="Հայերեն">🇦🇲</a>
            <a href="{{ route('lang.switch', 'kk') }}" class="lang-link {{ app()->getLocale() == 'kk' ? 'active' : '' }}" title="Қазақ">🇰🇿</a>
        </div>

        <a href="/partner" class="nav-cta">{{ __('landing.nav.login') }}</a>
    </div>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="hero-glow hero-glow-1"></div>
    <div class="hero-glow hero-glow-2"></div>

    <div class="hero-badge">
        {{ __('landing.hero.badge') }}
    </div>

    <h1>{!! __('landing.hero.title', ['highlight' => '<em>' . __('landing.hero.highlight') . '</em>']) !!}</h1>

    <p>{{ __('landing.hero.desc') }}</p>

    <div class="hero-actions" style="margin-top: 2.5rem;">
        <a href="/partner/register" class="btn-primary" style="font-size: 1.1rem; padding: 1rem 2.5rem;">{{ __('landing.hero.cta_primary') }} →</a>
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

<!-- HOW IT WORKS -->
<section id="how">
    <div class="container">
        <div class="section-label">{{ __('landing.how.label') }}</div>
        <h2 class="section-title">{{ __('landing.how.title') }}</h2>
        <p class="section-desc">{{ __('landing.how.desc') }}</p>

        <div class="how-grid">
            @foreach(__('landing.how.steps') as $index => $step)
            <div class="how-card">
                <div class="how-num">{{ $index + 1 }}</div>
                <h3>{{ $step['title'] }}</h3>
                <p>{{ $step['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

<!-- FEATURES -->
<section id="features" class="features-section">
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

<!-- CHANNELS -->
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

<!-- SECURITY -->
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
                <div class="code-line"><span class="code-key">email_field:</span> <span class="code-val-green">"email_bidx"</span></div>
                <div class="code-line">&nbsp;</div>
                <div class="code-line"><span class="code-key">// PII Storage</span></div>
                <div class="code-line"><span class="code-key">email:</span> <span class="code-val-blue">"vault:local:eyJ..."</span></div>
                <div class="code-line"><span class="code-key">phone:</span> <span class="code-val-blue">"vault:local:eyJ..."</span></div>
                <div class="code-line">&nbsp;</div>
                <div class="code-line"><span class="code-key">// Ledger Record</span></div>
                <div class="code-line"><span class="code-key">event:</span> <span class="code-val-amber">"ORDER_RECEIVE"</span></div>
                <div class="code-line"><span class="code-key">hash:</span> <span class="code-val-green">"3b1fdbe45a..."</span></div>
                <div class="code-line"><span class="code-key">status:</span> <span class="code-val-green">✓ VERIFIED</span></div>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<div class="container">
    <div class="cta-section">
        <h2>{!! __('landing.cta.title') !!}</h2>
        <p>{{ __('landing.cta.desc') }}</p>
        <a href="/partner" class="btn-primary">{{ __('landing.cta.button') }}</a>
    </div>
</div>

<!-- FOOTER -->
<footer>
    <div>
        <span style="color: var(--amber); font-weight: 900;">Meanly</span>
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
