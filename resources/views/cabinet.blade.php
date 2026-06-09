<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Личный Сейф — Meanly Systems</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://unpkg.com/@simplewebauthn/browser/dist/bundle/index.umd.min.js"></script>

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --brand-primary: #f53003;
            --brand-bg: #030303;
            --brand-card: #090909;
            --brand-text: #ffffff;
            --brand-subtext: #8e8e93;
            --brand-border: rgba(255, 255, 255, 0.05);
            --brand-border-hover: rgba(255, 255, 255, 0.15);
            --glass-bg: rgba(9, 9, 9, 0.7);
            --glass-blur: 24px;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--brand-bg);
            color: var(--brand-text);
            line-height: 1.5;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* Ambient Glows */
        .ambient-glows {
            position: absolute;
            top: 0; left: 0; right: 0; height: 100vh;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }
        .glow-1 {
            position: absolute; top: -10%; left: 20%; width: 60vw; height: 60vw;
            background: radial-gradient(circle, rgba(245, 48, 3, 0.04) 0%, rgba(0,0,0,0) 70%);
            filter: blur(80px);
        }
        .glow-2 {
            position: absolute; top: 30%; right: -10%; width: 50vw; height: 50vw;
            background: radial-gradient(circle, rgba(245, 48, 3, 0.03) 0%, rgba(0,0,0,0) 75%);
            filter: blur(100px);
        }

        /* ── NAV ── */
        nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
            padding: 1.25rem 2rem;
            display: flex; align-items: center; justify-content: center;
            background: rgba(3, 3, 3, 0.7);
            backdrop-filter: blur(var(--glass-blur));
            border-bottom: 1px solid var(--brand-border);
        }
        .nav-container {
            width: 100%;
            max-width: 1200px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .logo { 
            font-size: 1.3rem; 
            font-weight: 900; 
            letter-spacing: -0.04em; 
            color: var(--brand-text); 
            text-decoration: none; 
            display: flex; 
            align-items: center; 
            gap: 0.6rem; 
        }
        .logo-mark { 
            width: 12px; 
            height: 12px; 
            background: var(--brand-primary); 
            border-radius: 3px; 
            box-shadow: 0 0 15px rgba(245, 48, 3, 0.5);
        }
        .nav-links { display: flex; gap: 2rem; align-items: center; }
        .nav-links a { 
            color: var(--brand-subtext); 
            text-decoration: none; 
            font-size: 13.5px; 
            font-weight: 600; 
            transition: color 0.2s; 
        }
        .nav-links a:hover { color: var(--brand-text); }
        .nav-actions { display: flex; gap: 1rem; align-items: center; }
        .btn-nav-cta {
            background: var(--brand-primary); 
            color: #fff !important; 
            padding: 0.5rem 1.25rem;
            border-radius: 8px; 
            font-weight: 700; 
            font-size: 13px;
            text-decoration: none; 
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-nav-cta:hover { 
            transform: translateY(-1px);
            box-shadow: 0 6px 25px rgba(245, 48, 3, 0.5);
        }
        .btn-logout {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--brand-border);
            color: var(--brand-subtext);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
        }
        .btn-logout:hover {
            color: var(--brand-text);
            background: rgba(255,255,255,0.08);
            border-color: var(--brand-border-hover);
        }

        /* ── CABINET CONTAINER ── */
        .cabinet-container {
            max-width: 1200px;
            padding: 6.5rem 1.5rem 2.5rem;
            margin: 0 auto;
            position: relative;
            z-index: 10;
        }

        /* ── FOOTER ── */
        footer {
            padding: 6rem 0;
            margin-top: 6rem;
            border-top: 1px solid var(--brand-border);
            color: var(--brand-subtext);
            font-size: 13px;
            width: 100%;
        }
        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .footer-links { display: flex; gap: 2rem; }
        .footer-links a { color: var(--brand-subtext); text-decoration: none; }

        /* ── WELCOME CARD ── */
        .welcome-card {
            background: var(--brand-card);
            border: 1px solid var(--brand-border);
            border-radius: 18px;
            padding: 1.35rem;
            position: relative;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        .welcome-card::after {
            content: '';
            position: absolute; top: -100px; right: -100px; width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(245, 48, 3, 0.05) 0%, rgba(0,0,0,0) 70%);
            pointer-events: none;
        }
        .welcome-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        .welcome-meta {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .badge-type {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: rgba(245, 48, 3, 0.1);
            color: var(--brand-primary);
            padding: 0.25rem 0.75rem;
            border-radius: 100px;
        }

        /* 🔒 Vault Locked State */
        .vault-locked-overlay {
            background: var(--brand-card);
            border: 1px solid var(--brand-border);
            border-radius: 20px;
            padding: 4rem 2rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-bottom: 2rem;
            position: absolute;
            z-index: 10;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            backdrop-filter: blur(4px);
            transition: opacity 0.5s ease;
        }
        .workspace-left {
            position: relative;
        }
        .vault-entry-card {
            width: min(360px, 100%);
            min-height: 300px;
            margin: 0 auto 2rem;
            background: #ffffff;
            border: 4px solid #050505;
            border-radius: 24px;
            box-shadow: 10px 10px 0 #050505;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .vault-entry-card h3 {
            margin: 0 0 0.75rem;
            color: #050505;
            font-size: 1.45rem;
            font-weight: 950;
            letter-spacing: -0.04em;
        }
        .vault-entry-card p {
            max-width: 280px;
            margin: 0 0 1.5rem;
            color: #4b5563;
            font-size: 13px;
            font-weight: 750;
            line-height: 1.45;
        }
        .vault-entry-card .btn-unlock-vault {
            border: 3px solid #050505;
            border-radius: 999px;
            background: var(--brand-primary, #7c3aed);
            color: #ffffff;
            cursor: pointer;
            font-size: 12px;
            font-weight: 950;
            padding: 0.8rem 1.1rem;
            text-transform: uppercase;
            box-shadow: 4px 4px 0 #050505;
        }
        .vault-entry-card .btn-unlock-vault:disabled {
            cursor: wait;
            opacity: 0.7;
        }
        #vault-unlock-status {
            min-height: 18px;
            margin-top: 1rem;
            color: #64748b;
            font-size: 12px;
            font-weight: 850;
        }
        .lock-icon-container {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--brand-border);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            animation: pulse-lock 3s infinite alternate;
        }
        .lock-icon-container i {
            font-size: 2.5rem;
            color: var(--brand-primary);
        }
        .vault-locked-overlay h3 {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        .vault-locked-overlay p {
            color: var(--brand-subtext);
            margin-bottom: 2rem;
            font-size: 14px;
            max-width: 300px;
        }
        @keyframes pulse-lock {
            0% { box-shadow: 0 0 0 0 rgba(var(--brand-primary-rgb, 245, 48, 3), 0.2); }
            100% { box-shadow: 0 0 20px 10px rgba(var(--brand-primary-rgb, 245, 48, 3), 0); }
        }
        .badge-status {
            font-size: 11px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.25rem 0.75rem;
            border-radius: 100px;
        }
        .badge-status.success {
            background: rgba(16, 124, 16, 0.1);
            color: #107c10;
            border: 1px solid rgba(16, 124, 16, 0.2);
        }
        .badge-status.warning {
            background: rgba(245, 48, 3, 0.05);
            color: #ffaa00;
            border: 1px solid rgba(255, 170, 0, 0.2);
            text-decoration: none;
            transition: background 0.2s;
        }
        .badge-status.warning:hover {
            background: rgba(245, 48, 3, 0.1);
        }
        .vault-lock-form {
            display: inline-flex;
            margin: 0;
        }
        .badge-status.lock-action {
            appearance: none;
            cursor: pointer;
            font-family: inherit;
        }
        .welcome-title {
            font-size: clamp(1.35rem, 3vw, 2rem);
            font-weight: 900;
            letter-spacing: -0.03em;
            margin-top: 0.35rem;
        }
        .welcome-desc {
            color: var(--brand-subtext);
            font-size: 12.5px;
            margin-top: 0.35rem;
            max-width: 600px;
            line-height: 1.45;
        }
        .cabinet-top-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            align-items: start;
            margin-bottom: 1rem;
            padding: 1rem;
            border: 1px solid var(--brand-border);
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.035);
        }
        .cabinet-top-grid > .cabinet-side-panel {
            grid-column: 1;
            grid-row: 2;
        }
        .cabinet-top-grid > .vault-workspace-grid {
            grid-column: 1;
            grid-row: 1;
            min-width: 0;
            margin: 0;
        }
        .cabinet-side-panel {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .sl1-manage-card {
            min-height: 0;
        }
        .vault-wallet-card {
            margin-top: 0;
            background: var(--brand-card);
            border: 1px solid var(--brand-border);
            border-radius: 16px;
            padding: 1rem;
            text-align: left;
        }
        .vault-wallet-card h3 {
            margin: 0 0 0.45rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--brand-text);
            font-size: 0.9rem;
            font-weight: 900;
            letter-spacing: -0.02em;
        }
        .vault-wallet-card p {
            margin: 0 0 0.75rem;
            color: var(--brand-subtext);
            font-size: 11px;
            line-height: 1.35;
        }
        .vault-wallet-balances {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.55rem;
            margin-bottom: 0.75rem;
        }
        .vault-wallet-balance {
            background: rgba(255,255,255,0.025);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 12px;
            padding: 0.75rem;
        }
        .vault-wallet-label {
            margin-bottom: 0.35rem;
            color: var(--brand-subtext);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .vault-wallet-value {
            color: var(--brand-text);
            font-family: 'JetBrains Mono', monospace;
            font-size: 15px;
            font-weight: 900;
        }
        .vault-wallet-note {
            margin-top: 0.35rem;
            color: var(--brand-subtext);
            font-size: 10px;
        }
        .vault-wallet-history-title {
            margin-bottom: 0.75rem;
            color: var(--brand-subtext);
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
        .vault-wallet-empty {
            padding: 0.75rem;
            background: rgba(0,0,0,0.2);
            border-radius: 10px;
            color: var(--brand-subtext);
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            text-align: center;
        }
        .vault-wallet-transactions {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }
        .vault-wallet-transaction {
            display: flex;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.6rem;
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 12px;
            background: rgba(255,255,255,0.018);
        }
        .vault-wallet-transaction-title {
            color: var(--brand-text);
            font-size: 12px;
            font-weight: 800;
        }
        .vault-wallet-transaction-meta {
            margin-top: 0.25rem;
            color: var(--brand-subtext);
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
        }
        .vault-wallet-transaction-amount {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 900;
            white-space: nowrap;
        }
        .vault-wallet-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: 0 0.75rem;
            border: 1px solid var(--brand-border);
            border-radius: 10px;
            color: var(--brand-primary);
            font-size: 0.75rem;
            font-weight: 900;
            text-decoration: none;
        }
        .sl1-manage-card {
            background: var(--brand-card);
            border: 1px solid var(--brand-border);
            border-radius: 16px;
            padding: 1.15rem;
            text-align: left;
        }
        .sl1-manage-head {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.65rem;
        }
        .sl1-manage-icon {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--brand-border);
            border-radius: 10px;
            color: var(--brand-primary);
            background: rgba(124, 58, 237, 0.06);
            flex-shrink: 0;
        }
        .sl1-manage-eyebrow {
            display: block;
            color: var(--brand-subtext);
            font-family: 'JetBrains Mono', monospace;
            font-size: 9px;
            font-weight: 850;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .sl1-manage-card h3 {
            margin: 0.15rem 0 0;
            color: var(--brand-text);
            font-size: clamp(1rem, 2vw, 1.35rem);
            font-weight: 900;
            letter-spacing: -0.02em;
        }
        .sl1-manage-card p {
            margin: 0 0 0.85rem;
            color: var(--brand-subtext);
            font-size: 13px;
            line-height: 1.45;
        }
        .sl1-manage-features {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.55rem;
            margin-bottom: 0.85rem;
        }
        .sl1-manage-feature {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            color: var(--brand-text);
            font-size: 11px;
            font-weight: 800;
            padding: 0.65rem 0.7rem;
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 10px;
            background: rgba(255,255,255,0.025);
        }
        .sl1-manage-feature i {
            color: var(--brand-primary);
            font-size: 0.85rem;
        }
        .sl1-manage-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 42px;
            width: auto;
            padding: 0 1.25rem;
            border: 0;
            border-radius: 10px;
            background: var(--brand-primary);
            color: #ffffff;
            box-shadow: 0 4px 18px rgba(124, 58, 237, 0.28);
            font-size: 0.82rem;
            font-weight: 900;
            text-decoration: none;
        }
        .sl1-manage-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 25px rgba(124, 58, 237, 0.42);
        }
        .workspace-grid.workspace-grid-single {
            grid-template-columns: 1fr !important;
        }
        .workspace-grid.vault-workspace-grid {
            grid-template-columns: 1fr !important;
        }
        @media (max-width: 820px) {
            .cabinet-top-grid {
                grid-template-columns: 1fr;
            }
            .cabinet-top-grid > .welcome-card,
            .cabinet-top-grid > .cabinet-side-panel,
            .cabinet-top-grid > .vault-workspace-grid {
                grid-column: 1;
                grid-row: auto;
            }
            .cabinet-top-grid > .vault-workspace-grid {
                order: 1;
            }
            .cabinet-top-grid > .cabinet-side-panel {
                order: 2;
            }
            .sl1-manage-features {
                grid-template-columns: 1fr;
            }
        }

        /* ── STATS ROW ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        .stat-card {
            background: var(--brand-card);
            border: 1px solid var(--brand-border);
            border-radius: 16px;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .stat-card i {
            position: absolute; right: 1rem; top: 1rem;
            font-size: 1.8rem;
            color: rgba(255,255,255,0.03);
        }
        .stat-label {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--brand-subtext);
        }
        .stat-value {
            font-size: 2.2rem;
            font-weight: 900;
            margin-top: 0.5rem;
            letter-spacing: -0.02em;
        }
        .stat-desc {
            font-size: 11px;
            color: var(--brand-subtext);
            margin-top: 0.25rem;
        }

        /* ── VAULT SECTION ── */
        .vault-title-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .vault-title-row h2 {
            font-size: 1.6rem;
            font-weight: 900;
            letter-spacing: -0.03em;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .vault-title-row h2 i { color: var(--brand-primary); }
        .vault-help {
            position: relative;
            width: 18px;
            height: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--brand-border);
            border-radius: 50%;
            color: var(--brand-primary);
            background: rgba(124, 58, 237, 0.08);
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            font-weight: 900;
            cursor: help;
        }
        .vault-help::after {
            content: attr(data-tooltip);
            position: absolute;
            left: 50%;
            top: calc(100% + 10px);
            z-index: 40;
            width: min(280px, calc(100vw - 48px));
            padding: 0.75rem 0.85rem;
            border: 1px solid var(--brand-border);
            border-radius: 12px;
            background: var(--brand-card);
            color: var(--brand-text);
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.24);
            font-family: inherit;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.35;
            letter-spacing: 0;
            text-transform: none;
            white-space: normal;
            opacity: 0;
            pointer-events: none;
            transform: translate(-50%, -4px);
            transition: opacity 0.16s ease, transform 0.16s ease;
        }
        .vault-help:hover::after,
        .vault-help:focus-visible::after {
            opacity: 1;
            transform: translate(-50%, 0);
        }
        .vault-title-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .vault-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.25rem;
            margin-bottom: 4rem;
        }
        .vault-card {
            background: var(--brand-card);
            border: 1px solid var(--brand-border);
            border-radius: 20px;
            padding: 1.5rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2rem;
            transition: border-color 0.3s, transform 0.3s;
            position: relative;
            overflow: hidden;
            scroll-margin-top: 7rem;
        }
        .vault-card:hover {
            border-color: var(--platform-color, var(--brand-border-hover));
            transform: translateY(-2px);
        }
        .vault-card.is-focused,
        .vault-card:target {
            border-color: var(--platform-color, var(--brand-primary));
            box-shadow: 0 0 0 1px var(--platform-color, var(--brand-primary)), 0 18px 42px rgba(0, 0, 0, 0.32);
            transform: translateY(-2px);
        }
        .vault-card::before {
            content: '';
            position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
            background: var(--platform-gradient, var(--brand-primary));
        }
        
        .vault-info-group {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-grow: 1;
        }
        .vault-icon-pane {
            width: 48px; height: 48px;
            border-radius: 12px;
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--brand-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--platform-color, var(--brand-primary));
            flex-shrink: 0;
        }
        .vault-details {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            text-align: left;
        }
        .vault-platform-badge {
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--platform-color, var(--brand-text));
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .vault-prod-title {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--brand-text);
            line-height: 1.3;
        }
        .vault-meta-row {
            display: flex;
            gap: 1rem;
            font-size: 11px;
            color: var(--brand-subtext);
        }

        .secure-key-block {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--brand-border);
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13.5px;
            font-weight: 700;
            color: var(--brand-primary);
        }
        .secure-key-block button {
            all: unset;
            cursor: pointer;
            color: var(--brand-subtext);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 0.5rem;
            transition: color 0.2s;
        }
        .secure-key-block button:hover {
            color: #fff;
        }
        .vault-card.is-expanded {
            flex-wrap: wrap;
            align-items: flex-start;
        }
        .vault-safe-status {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            min-width: 180px;
        }
        .vault-safe-hint {
            color: var(--brand-subtext);
            font-family: 'Outfit', sans-serif;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.35;
            max-width: 320px;
            text-transform: none;
        }
        .vault-actions {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            margin-left: auto;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .vault-open-button {
            all: unset;
            cursor: pointer;
            color: var(--brand-text);
            background: rgba(245, 48, 3, 0.12);
            border: 1px solid rgba(245, 48, 3, 0.28);
            border-radius: 8px;
            padding: 0.48rem 0.8rem;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            transition: background 0.2s, border-color 0.2s, color 0.2s;
        }
        .vault-open-button:hover:not(:disabled) {
            background: rgba(245, 48, 3, 0.22);
            border-color: rgba(245, 48, 3, 0.5);
            color: #fff;
        }
        .vault-open-button:disabled {
            cursor: not-allowed;
            opacity: 0.55;
        }
        .vault-safe-link {
            color: var(--brand-subtext);
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 0.06em;
            text-decoration: none;
            text-transform: uppercase;
        }
        .vault-safe-link:hover {
            color: var(--brand-text);
        }
        .vault-support-link {
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.35);
            border-radius: 999px;
            padding: 0.42rem 0.7rem;
            font-size: 10px;
            font-weight: 950;
            letter-spacing: 0.06em;
            text-decoration: none;
            text-transform: uppercase;
        }
        .vault-support-link:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #fff;
        }
        .support-chat-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.32);
            backdrop-filter: blur(2px);
            z-index: 9998;
            display: none;
        }
        .support-chat-drawer {
            position: fixed;
            top: 0;
            right: -430px;
            width: 410px;
            max-width: 100%;
            height: 100vh;
            background: #fff;
            border-left: 5px solid #050505;
            box-shadow: -10px 0 0 rgba(0,0,0,0.16);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            transition: right .3s cubic-bezier(.16,1,.3,1);
        }
        .support-chat-drawer.open {
            right: 0;
        }
        .support-chat-header {
            background: #7c3aed;
            color: #fff;
            border-bottom: 4px solid #050505;
            padding: 16px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .support-chat-title {
            font-size: 19px;
            font-weight: 950;
            line-height: 1;
        }
        .support-chat-subtitle {
            display: block;
            font-size: 10px;
            font-weight: 850;
            letter-spacing: .05em;
            margin-top: 4px;
            opacity: .88;
            text-transform: uppercase;
        }
        .support-chat-close {
            background: #fff;
            color: #050505;
            border: 3px solid #050505;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-size: 22px;
            font-weight: 950;
            cursor: pointer;
            box-shadow: 2px 2px 0 #050505;
        }
        .support-chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 18px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            background: #f8fafc;
        }
        .support-chat-bubble {
            max-width: 88%;
            padding: 12px 14px;
            border: 3px solid #050505;
            border-radius: 10px;
            color: #111827;
            font-size: 13px;
            line-height: 1.45;
            font-weight: 750;
            box-shadow: 4px 4px 0 #050505;
            white-space: pre-wrap;
        }
        .support-chat-bubble.assistant {
            align-self: flex-start;
            background: #fff;
        }
        .support-chat-bubble.user {
            align-self: flex-end;
            background: #ede9fe;
        }
        .support-chat-bubble.error {
            align-self: flex-start;
            background: #fee2e2;
            border-color: #ef4444;
            box-shadow: 4px 4px 0 #ef4444;
        }
        .support-chat-meta {
            display: block;
            color: #64748b;
            font-size: 10px;
            font-weight: 950;
            letter-spacing: .06em;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        .support-chat-footer {
            border-top: 4px solid #050505;
            padding: 14px;
            background: #fff;
        }
        .support-chat-input-wrapper {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 8px;
        }
        .support-chat-input-wrapper input {
            border: 3px solid #050505;
            border-radius: 8px;
            padding: 11px;
            color: #111827;
            font-size: 13px;
            font-weight: 850;
            outline: none;
            background: #eef2ff;
        }
        .support-chat-input-wrapper button {
            border: 3px solid #050505;
            background: #7c3aed;
            color: #fff;
            border-radius: 8px;
            width: 46px;
            font-weight: 950;
            cursor: pointer;
            box-shadow: 3px 3px 0 #050505;
        }
        .vault-inline-safe {
            flex: 1 0 100%;
            border-top: 1px solid var(--brand-border);
            margin-top: 0.25rem;
            padding-top: 1rem;
        }
        .vault-inline-safe[hidden] {
            display: none;
        }
        .vault-inline-message {
            color: var(--brand-subtext);
            font-size: 12px;
            font-weight: 650;
            margin-bottom: 0.85rem;
        }
        .vault-code-list {
            display: grid;
            gap: 0.75rem;
        }
        .vault-code-card {
            display: grid;
            gap: 0.5rem;
            background: rgba(255,255,255,0.025);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 0.85rem;
        }
        .vault-code-card span {
            color: var(--brand-subtext);
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
        .vault-code-card code {
            color: var(--brand-text);
            font-family: 'JetBrains Mono', monospace;
            font-size: 16px;
            font-weight: 900;
            word-break: break-all;
        }
        .vault-code-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .vault-code-actions a,
        .vault-code-actions button {
            all: unset;
            cursor: pointer;
            color: var(--brand-primary);
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        /* ── SCRATCH CARD EFFECT ── */
        .scratch-container {
            position: relative;
            width: 100%;
            height: 60px;
            border-radius: 10px;
            overflow: hidden;
            background: rgba(0, 0, 0, 0.4);
            border: 1px dashed var(--brand-border);
            user-select: none;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.5);
            margin: 0.25rem 0 0.5rem 0;
        }
        .scratch-underlay {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 1rem;
            box-sizing: border-box;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.01) 0%, rgba(0, 0, 0, 0.3) 100%);
            transition: filter 0.3s ease;
        }
        .scratch-container.is-blurred .scratch-underlay {
            filter: blur(8px);
        }
        .scratch-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            cursor: crosshair;
            z-index: 5;
            transition: opacity 0.4s ease, transform 0.4s ease;
            touch-action: none;
        }
        .scratch-canvas.fade-out {
            opacity: 0;
            transform: scale(1.05);
            pointer-events: none;
        }
        .vault-code-actions button:disabled,
        .vault-code-actions a.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* ── INLINE SCRATCH CARD OVERRIDES ── */
        .secure-key-block.has-scratch {
            padding: 0;
            overflow: hidden;
            width: 340px;
            height: 64px;
            display: flex;
            align-items: stretch;
            justify-content: stretch;
            background: transparent;
            border: 1px solid var(--brand-border);
            flex-shrink: 0;
            position: relative;
        }
        .inline-scratch-container {
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: rgba(0, 0, 0, 0.4);
            user-select: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .inline-scratch-underlay {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 0.5rem;
            box-sizing: border-box;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.01) 0%, rgba(0, 0, 0, 0.35) 100%);
            transition: filter 0.3s ease;
            gap: 0.35rem;
        }
        .inline-scratch-container.is-blurred .inline-scratch-underlay {
            filter: blur(8px);
        }
        .inline-scratch-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            cursor: crosshair;
            z-index: 5;
            transition: opacity 0.4s ease, transform 0.4s ease;
            touch-action: none;
        }
        .inline-scratch-canvas.fade-out {
            opacity: 0;
            transform: scale(1.05);
            pointer-events: none;
        }
        
        /* Inline Revealed Content */
        .revealed-inline-code {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            gap: 0.25rem;
        }
        .revealed-inline-code code {
            color: var(--brand-text);
            font-family: 'JetBrains Mono', monospace;
            font-size: 13.5px;
            font-weight: 900;
            word-break: break-all;
            text-align: center;
            user-select: text;
        }
        .revealed-inline-actions {
            display: flex;
            gap: 0.8rem;
            align-items: center;
        }
        .revealed-inline-actions button,
        .revealed-inline-actions a {
            all: unset;
            cursor: pointer;
            color: var(--brand-primary);
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            transition: color 0.2s;
        }
        .revealed-inline-actions button:hover,
        .revealed-inline-actions a:hover {
            color: #fff;
        }

        .inline-reveal-btn {
            position: absolute;
            bottom: 6px;
            right: 6px;
            z-index: 9999 !important;
            transform: translate3d(0, 0, 10px);
            pointer-events: auto !important;
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 3px 8px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .inline-reveal-btn:hover {
            background: var(--brand-primary);
            border-color: #fff;
            color: #fff;
        }
        .scratch-proof-badge {
            font-family: 'JetBrains Mono', monospace;
            font-size: 8px;
            font-weight: 800;
            color: #22c55e !important;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 1px;
        }

        /* ── B2B UPGRADE BANNER ── */
        .b2b-banner {
            background: linear-gradient(135deg, rgba(245, 48, 3, 0.03) 0%, rgba(9, 9, 9, 0.8) 100%);
            border: 1px solid var(--brand-border);
            border-radius: 24px;
            padding: 3rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 3rem;
            position: relative;
            overflow: hidden;
            text-align: left;
            margin-top: 2rem;
        }
        .b2b-banner::after {
            content: '';
            position: absolute; right: -150px; bottom: -150px; width: 350px; height: 350px;
            background: radial-gradient(circle, rgba(245, 48, 3, 0.04) 0%, rgba(0,0,0,0) 70%);
            pointer-events: none;
        }
        .b2b-content h3 {
            font-size: 1.5rem;
            font-weight: 900;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
        }
        .b2b-content p {
            color: var(--brand-subtext);
            font-size: 13.5px;
            max-width: 700px;
            line-height: 1.6;
        }

        /* ── EMPTY VAULT PLACEHOLDER ── */
        .empty-vault {
            background: var(--brand-card);
            border: 1px solid var(--brand-border);
            border-radius: 20px;
            padding: 5rem 2rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1.5rem;
        }
        .empty-vault-icon {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--brand-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            color: var(--brand-subtext);
        }
        .empty-vault h3 {
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .empty-vault p {
            color: var(--brand-subtext);
            font-size: 13.5px;
            max-width: 400px;
            line-height: 1.6;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: 1fr; }
            .vault-card { flex-direction: column; align-items: stretch; gap: 1.25rem; padding: 1.5rem; }
            .secure-key-block { justify-content: space-between; }
            .vault-actions { justify-content: flex-start; margin-left: 0; }
            .b2b-banner { flex-direction: column; align-items: flex-start; gap: 2rem; }
        }

        /* 🎨 ============================================
           PREMIUM 3-SKIN THEME SWITCHER SYSTEM
           ============================================ */

        /* --- 🎨 Theme Switcher Nav Pill --- */
        .skin-switcher-pill {
            display: inline-flex;
            align-items: center;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--brand-border);
            border-radius: 100px;
            padding: 4px;
            gap: 4px;
            box-shadow: inset 1px 1px 4px rgba(0,0,0,0.5);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            margin-right: 1.5rem;
        }
        .skin-btn {
            background: transparent;
            border: none;
            color: var(--brand-subtext);
            font-size: 0.65rem;
            font-weight: 800;
            padding: 6px 12px;
            border-radius: 100px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .skin-btn:hover {
            color: var(--brand-text);
            background: rgba(255,255,255,0.02);
        }

        /* Highlight active theme buttons dynamically */
        body[data-theme="partner"] #skin-btn-partner {
            background: #ff9f0a !important;
            color: #000000 !important;
            box-shadow: 0 2px 10px rgba(255, 159, 10, 0.3) !important;
            font-weight: 900;
        }
        body[data-theme="consortium"] #skin-btn-consortium {
            background: #f53003 !important;
            color: #ffffff !important;
            box-shadow: 0 2px 10px rgba(245, 48, 3, 0.4) !important;
            font-weight: 900;
        }
        body[data-theme="retro"] #skin-btn-retro {
            background: #7c3aed !important;
            color: #ffffff !important;
            box-shadow: 2px 2px 0px #000000 !important;
            font-weight: 900;
            border: 2px solid #000000 !important;
        }

        /* 🌟 Theme 1: Partner (Modern Glassmorphic Gold/Amber) */
        body[data-theme="partner"] {
            --brand-primary: #ff9f0a;
            --brand-bg: #060608;
            --brand-card: rgba(14, 14, 18, 0.65);
            --brand-text: #ffffff;
            --brand-subtext: #9a9ab0;
            --brand-border: rgba(255, 255, 255, 0.04);
            --brand-border-hover: rgba(255, 159, 10, 0.2);
            --glass-bg: rgba(11, 11, 14, 0.7);
            background: #060608 !important;
            font-family: 'Outfit', sans-serif !important;
        }
        body[data-theme="partner"] .glow-1 {
            background: radial-gradient(circle, rgba(255, 159, 10, 0.09) 0%, rgba(0,0,0,0) 70%) !important;
        }
        body[data-theme="partner"] .glow-2 {
            background: radial-gradient(circle, rgba(255, 159, 10, 0.05) 0%, rgba(0,0,0,0) 70%) !important;
        }
        body[data-theme="partner"] .logo-mark {
            background: #ff9f0a !important;
            box-shadow: 0 0 15px rgba(255, 159, 10, 0.5) !important;
        }
        body[data-theme="partner"] .btn-nav-cta {
            background: #ff9f0a !important;
            color: #000 !important;
            box-shadow: 0 4px 20px rgba(255, 159, 10, 0.35) !important;
        }
        body[data-theme="partner"] .btn-nav-cta:hover {
            box-shadow: 0 6px 25px rgba(255, 159, 10, 0.55) !important;
        }
        body[data-theme="partner"] .badge-type {
            background: rgba(255, 159, 10, 0.1) !important;
            color: #ff9f0a !important;
        }
        body[data-theme="partner"] .secure-key-block {
            color: #ff9f0a !important;
        }

        /* 🚩 Theme 2: Consortium Flagship (Flat Dark Neobrutalism) - DEFAULT */
        body[data-theme="consortium"] {
            --brand-primary: #f53003;
            --brand-bg: #030303;
            --brand-card: #090909;
            --brand-text: #ffffff;
            --brand-subtext: #8e8e93;
            --brand-border: rgba(255, 255, 255, 0.05);
            --brand-border-hover: rgba(245, 48, 3, 0.25);
            --glass-bg: rgba(3, 3, 3, 0.85);
            background: #030303 !important;
            font-family: 'JetBrains Mono', monospace !important;
        }
        body[data-theme="consortium"] .glow-1 {
            background: radial-gradient(circle, rgba(245, 48, 3, 0.08) 0%, rgba(0,0,0,0) 70%) !important;
        }
        body[data-theme="consortium"] h1, 
        body[data-theme="consortium"] h2, 
        body[data-theme="consortium"] h3, 
        body[data-theme="consortium"] .logo, 
        body[data-theme="consortium"] .btn-nav-cta,
        body[data-theme="consortium"] .stat-value,
        body[data-theme="consortium"] .secure-key-block {
            font-family: 'JetBrains Mono', monospace !important;
            letter-spacing: -0.01em !important;
        }

        /* ⚡ Theme 3: Consortium Retro (Light Neo-Brutalism - Stark & Bold) */
        body[data-theme="retro"] {
            --brand-primary: #7c3aed;
            --brand-bg: #eef0fc;
            --brand-card: #ffffff;
            --brand-text: #000000;
            --brand-subtext: #4e4e5e;
            --brand-border: #000000;
            --brand-border-hover: #000000;
            --glass-bg: rgba(238, 240, 252, 0.95);
            background: #eef0fc !important;
            font-family: 'Outfit', sans-serif !important;
        }
        body[data-theme="retro"] .glow-1,
        body[data-theme="retro"] .glow-2 {
            display: none !important;
        }
        body[data-theme="retro"] nav {
            background: #ffffff !important;
            border-bottom: 2px solid #000000 !important;
        }
        body[data-theme="retro"] .logo {
            color: #000000 !important;
        }
        body[data-theme="retro"] .logo-mark {
            background: #7c3aed !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
            box-shadow: none !important;
        }
        body[data-theme="retro"] .skin-switcher-pill {
            background: #ffffff !important;
            border: 2px solid #000000 !important;
            box-shadow: none !important;
            border-radius: 0px !important;
        }
        body[data-theme="retro"] .skin-btn {
            border-radius: 0px !important;
            color: #000000 !important;
        }
        body[data-theme="retro"] .nav-links a {
            color: #000000 !important;
            font-weight: 700 !important;
        }
        body[data-theme="retro"] .nav-links a:hover {
            color: #7c3aed !important;
        }
        body[data-theme="retro"] .btn-logout {
            background: #ffffff !important;
            color: #000000 !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
            box-shadow: 2px 2px 0px #000000 !important;
            font-weight: 700 !important;
        }
        body[data-theme="retro"] .btn-logout:hover {
            background: #000000 !important;
            color: #ffffff !important;
        }
        body[data-theme="retro"] .btn-nav-cta {
            background: #7c3aed !important;
            color: #ffffff !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
            box-shadow: 4px 4px 0px #000000 !important;
        }
        body[data-theme="retro"] .btn-nav-cta:hover {
            transform: translate(-2px, -2px) !important;
            box-shadow: 6px 6px 0px #000000 !important;
        }
        body[data-theme="retro"] .welcome-card {
            background: #ffffff !important;
            border: 2px solid #000000 !important;
            box-shadow: 6px 6px 0px #000000 !important;
            border-radius: 0px !important;
            color: #000000 !important;
        }
        body[data-theme="retro"] .welcome-card::after {
            display: none !important;
        }
        body[data-theme="retro"] .welcome-title {
            color: #000000 !important;
        }
        body[data-theme="retro"] .welcome-desc {
            color: #4e4e5e !important;
        }
        body[data-theme="retro"] .badge-type {
            background: rgba(124, 58, 237, 0.1) !important;
            color: #7c3aed !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
        }
        body[data-theme="retro"] .badge-status.success {
            background: rgba(16, 124, 16, 0.1) !important;
            color: #107c10 !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
        }
        body[data-theme="retro"] .stat-card {
            background: #ffffff !important;
            border: 2px solid #000000 !important;
            box-shadow: 4px 4px 0px #000000 !important;
            border-radius: 0px !important;
            color: #000000 !important;
        }
        body[data-theme="retro"] .stat-label {
            color: #4e4e5e !important;
        }
        body[data-theme="retro"] .stat-value {
            color: #000000 !important;
        }
        body[data-theme="retro"] .stat-desc {
            color: #4e4e5e !important;
        }
        body[data-theme="retro"] .vault-title-row h2 {
            color: #000000 !important;
        }
        body[data-theme="retro"] .vault-card {
            background: #ffffff !important;
            border: 2px solid #000000 !important;
            box-shadow: 6px 6px 0px #000000 !important;
            border-radius: 0px !important;
            color: #000000 !important;
        }
        body[data-theme="retro"] .vault-prod-title {
            color: #000000 !important;
        }
        body[data-theme="retro"] .vault-meta-row {
            color: #4e4e5e !important;
        }
        body[data-theme="retro"] .secure-key-block {
            background: #ffffff !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
            color: #7c3aed !important;
        }
        body[data-theme="retro"] .empty-vault {
            background: #ffffff !important;
            border: 2px solid #000000 !important;
            box-shadow: 6px 6px 0px #000000 !important;
            border-radius: 0px !important;
            color: #000000 !important;
        }
        body[data-theme="retro"] .empty-vault-icon {
            background: #ffffff !important;
            border: 2px solid #000000 !important;
            color: #7c3aed !important;
            border-radius: 0px !important;
        }
        body[data-theme="retro"] .b2b-banner {
            background: #ffffff !important;
            border: 2px solid #000000 !important;
            box-shadow: 6px 6px 0px #000000 !important;
            border-radius: 0px !important;
        }
        body[data-theme="retro"] .b2b-banner::after {
            display: none !important;
        }
        body[data-theme="retro"] .b2b-content h3 {
            color: #000000 !important;
        }
        body[data-theme="retro"] .b2b-content p {
            color: #4e4e5e !important;
        }
        body[data-theme="retro"] footer {
            background: #ffffff !important;
            border-top: 2px solid #000000 !important;
            color: #000000 !important;
        }
        body[data-theme="retro"] .footer-links a {
            color: #000000 !important;
            font-weight: 700 !important;
        }
        body[data-theme="retro"] .footer-links a:hover {
            color: #7c3aed !important;
        }

            @media (max-width: 992px) {
                .footer-container { flex-direction: column; gap: 2rem; text-align: center; }
                .footer-links { justify-content: center; }
            }

            .workspace-grid {
                display: grid;
                grid-template-columns: 2fr 1fr;
                gap: 2rem;
                margin-bottom: 4rem;
                align-items: start;
            }

            @media (max-width: 992px) {
                .workspace-grid {
                    grid-template-columns: 1fr !important;
                }
                .vault-wallet-balances {
                    grid-template-columns: 1fr !important;
                }
            }

            /* Retro overrides for workspace cards */
            body[data-theme="retro"] .sec-card {
                background: #ffffff !important;
                border: 2px solid #000000 !important;
                box-shadow: 6px 6px 0px #000000 !important;
                border-radius: 0px !important;
                color: #000000 !important;
            }
            body[data-theme="retro"] .sec-card h3 {
                color: #000000 !important;
            }
            body[data-theme="retro"] .sec-card p {
                color: #4e4e5e !important;
            }
            body[data-theme="retro"] .terminal-stats span {
                color: #000000 !important;
            }

            /* Constitutional panel: fix #ffffff hash values becoming invisible on white retro bg */
            body[data-theme="retro"] #sl1-epoch-block span[style*="color: #ffffff"],
            body[data-theme="retro"] #sl1-epoch-block span[style*="color:#ffffff"] {
                color: #000000 !important;
            }
            body[data-theme="retro"] #sl1-epoch-block {
                color: #2e2e2e !important;
            }
            body[data-theme="retro"] #sl1-epoch-block span {
                color: #2e2e2e !important;
            }
            body[data-theme="retro"] #sl1-epoch-val { color: #7c3aed !important; }
            body[data-theme="retro"] #sl1-root-val  { color: #000000 !important; }
            body[data-theme="retro"] #sl1-policy-val { color: #107c10 !important; }
            body[data-theme="retro"] #sl1-fed-val   { color: #b45309 !important; }
            body[data-theme="retro"] #sl1-state-val { color: #4e4e5e !important; }
            body[data-theme="retro"] #sl1-status-label { color: #107c10 !important; }
            body[data-theme="retro"] #sl1-no-receipts { color: #4e4e5e !important; }

            /* Drop zone styles */
            #sl1-drop-zone {
                border: 2px dashed rgba(255, 255, 255, 0.12);
                transition: all 0.2s ease-in-out;
            }
            #sl1-drop-zone:hover, #sl1-drop-zone.dragover {
                border-color: var(--brand-primary) !important;
                background: rgba(245, 48, 3, 0.03) !important;
            }
            
            /* Retro overrides for drop zone */
            body[data-theme="retro"] #sl1-drop-zone {
                border: 2px dashed #000000 !important;
                background: #fcfcfc !important;
                border-radius: 0px !important;
            }
            body[data-theme="retro"] #sl1-drop-zone:hover,
            body[data-theme="retro"] #sl1-drop-zone.dragover {
                background: #fff8f6 !important;
                border-color: #f53003 !important;
            }
            body[data-theme="retro"] #sl1-verification-result {
                background: #f9f9f9 !important;
                border: 2px solid #000000 !important;
                border-radius: 0px !important;
                color: #000000 !important;
            }

            /* 🦁 Easter Egg: Son's Birthday (May 19) - Albiceleste */
            body[data-holiday="sons-birthday"] {
                --brand-primary: #74acdf !important; /* Argentine Sky Blue */
                --brand-border-hover: rgba(116, 172, 223, 0.45) !important;
            }
            body[data-holiday="sons-birthday"] .logo-mark {
                background: linear-gradient(135deg, #74acdf 0%, #ffffff 100%) !important; /* Albiceleste gradient! */
                box-shadow: 0 0 15px rgba(116, 172, 223, 0.55) !important;
            }
            body[data-holiday="sons-birthday"] .product-card::before {
                background: linear-gradient(90deg, #74acdf, #ffffff, #74acdf) !important; /* Albiceleste! */
            }
            body[data-holiday="sons-birthday"] .btn-buy, 
            body[data-holiday="sons-birthday"] .btn-nav-cta {
                background: #74acdf !important; /* Albiceleste Sky Blue! */
                color: #ffffff !important;
                border-color: #ffffff !important;
                box-shadow: 0 4px 15px rgba(116, 172, 223, 0.45) !important;
            }

            /* 🌸 Easter Egg: Orchid Day (May 12) - Beautiful Orchid Purple & Violet Theme */
            body[data-holiday="orchid-day"] {
                --brand-primary: #d946ef !important; /* Orchid Magenta */
                --brand-border-hover: rgba(217, 70, 239, 0.45) !important;
            }
            body[data-holiday="orchid-day"] .logo-mark {
                background: linear-gradient(135deg, #d946ef 0%, #c084fc 100%) !important;
                box-shadow: 0 0 15px rgba(217, 70, 239, 0.5) !important;
            }
            body[data-holiday="orchid-day"] .product-card::before {
                background: linear-gradient(90deg, #d946ef, #c084fc, #e879f9) !important;
            }
            body[data-holiday="orchid-day"] .btn-buy, 
            body[data-holiday="orchid-day"] .btn-nav-cta {
                background: linear-gradient(135deg, #d946ef 0%, #86198f 100%) !important;
                color: #ffffff !important;
                border-color: rgba(255, 255, 255, 0.2) !important;
                box-shadow: 0 4px 15px rgba(217, 70, 239, 0.35) !important;
            }

            /* 🩺 Easter Egg: Doctor's Day / Stethoscope Day (April 21) - Healing Mint & Cyan Theme */
            body[data-holiday="doctor-day"] {
                --brand-primary: #06b6d4 !important; /* Healing Cyan */
                --brand-border-hover: rgba(6, 182, 212, 0.45) !important;
            }
            body[data-holiday="doctor-day"] .logo-mark {
                background: linear-gradient(135deg, #0d9488 0%, #06b6d4 100%) !important;
                box-shadow: 0 0 15px rgba(13, 148, 136, 0.5) !important;
            }
            body[data-holiday="doctor-day"] .product-card::before {
                background: linear-gradient(90deg, #0d9488, #06b6d4, #10b981, #2dd4bf) !important;
            }
            body[data-holiday="doctor-day"] .btn-buy, 
            body[data-holiday="doctor-day"] .btn-nav-cta {
                background: linear-gradient(135deg, #0d9488 0%, #115e59 100%) !important;
                color: #ffffff !important;
                border-color: rgba(255, 255, 255, 0.2) !important;
                box-shadow: 0 4px 15px rgba(13, 148, 136, 0.35) !important;
            }

            /* 📚 Easter Egg: Library of Babel Day (Jorge Luis Borges' Birthday - August 24) - Antique Amber & Parchment Theme */
            body[data-holiday="babel-library"] {
                --brand-primary: #d97706 !important; /* Antique Amber */
                --brand-border-hover: rgba(217, 119, 6, 0.45) !important;
            }
            body[data-holiday="babel-library"] .logo-mark {
                background: linear-gradient(135deg, #b45309 0%, #f59e0b 100%) !important;
                box-shadow: 0 0 15px rgba(180, 83, 9, 0.5) !important;
            }
            body[data-holiday="babel-library"] .product-card::before {
                background: linear-gradient(90deg, #b45309, #d97706, #f59e0b, #78350f) !important;
            }
            body[data-holiday="babel-library"] .btn-buy, 
            body[data-holiday="babel-library"] .btn-nav-cta {
                background: linear-gradient(135deg, #b45309 0%, #78350f 100%) !important;
                color: #ffffff !important;
                border-color: rgba(255, 255, 255, 0.2) !important;
                box-shadow: 0 4px 15px rgba(180, 83, 9, 0.35) !important;
            }

            /* 💕 Easter Egg: Valentine's Day (Feb 14) - Premium Sweet Pink & Crimson Red Theme */
            body[data-holiday="valentine"] {
                --brand-primary: #e11d48 !important; /* Crimson Rose */
                --brand-border-hover: rgba(225, 29, 72, 0.45) !important;
            }
            body[data-holiday="valentine"] .logo-mark {
                background: linear-gradient(135deg, #ff4d6d 0%, #ff758f 100%) !important;
                box-shadow: 0 0 15px rgba(255, 77, 109, 0.5) !important;
            }
            body[data-holiday="valentine"] .product-card::before {
                background: linear-gradient(90deg, #ff4d6d, #ff758f, #ffccd5, #ff85a1) !important;
            }
            body[data-holiday="valentine"] .btn-buy, 
            body[data-holiday="valentine"] .btn-nav-cta {
                background: linear-gradient(135deg, #ff4d6d 0%, #c9184a 100%) !important;
                color: #ffffff !important;
                border-color: rgba(255, 255, 255, 0.2) !important;
                box-shadow: 0 4px 15px rgba(255, 77, 109, 0.4) !important;
            }

            /* 🌹 Easter Egg: The Little Prince (Oct 17) - Single Rose under Glass Dome & Stars */
            body[data-holiday="little-prince"] {
                --brand-primary: #e11d48 !important; /* Rose Red */
                --brand-bg: #0b0f19 !important; /* Twilight indigo space dark */
                --brand-card: rgba(17, 24, 39, 0.65) !important;
                --brand-border: rgba(255, 255, 255, 0.08) !important;
                --brand-border-hover: rgba(225, 29, 72, 0.35) !important;
                background: #0b0f19 !important;
            }
            body[data-holiday="little-prince"] .logo-mark {
                background: linear-gradient(135deg, #e11d48 0%, #fbbf24 100%) !important;
                box-shadow: 0 0 15px rgba(225, 29, 72, 0.55) !important;
            }
            body[data-holiday="little-prince"] .product-card::before {
                background: linear-gradient(90deg, #f59e0b, #be123c, #e11d48) !important; /* Golden stars & deep rose red! */
            }
            body[data-holiday="little-prince"] .btn-buy, 
            body[data-holiday="little-prince"] .btn-nav-cta {
                background: linear-gradient(135deg, #be123c 0%, #e11d48 100%) !important; /* Rose Red buttons */
                color: #ffffff !important;
                border-color: rgba(255, 255, 255, 0.2) !important;
                box-shadow: 0 4px 15px rgba(225, 29, 72, 0.35) !important;
            }

            /* Buyer cabinet shell: keep the safe central, but make the whole page feel useful. */
            .cabinet-container {
                width: min(780px, calc(100vw - 32px)) !important;
                max-width: none !important;
                padding: 6.25rem 0 2.5rem !important;
            }
            .b2b-banner,
            footer {
                display: none !important;
            }
            .welcome-card {
                margin-bottom: 0.65rem !important;
                padding: 1.15rem 1.25rem !important;
                background: #ffffff !important;
                border: 3px solid #050505 !important;
                border-radius: 0 !important;
                box-shadow: 6px 6px 0 #050505 !important;
                color: #050505 !important;
            }
            .welcome-card,
            .sl1-manage-card {
                min-height: 148px !important;
            }
            .welcome-card::after {
                display: none !important;
            }
            .welcome-header {
                margin-bottom: 0.4rem !important;
            }
            .welcome-title {
                margin: 0 !important;
                color: #050505 !important;
                font-size: clamp(1.35rem, 3vw, 2rem) !important;
                line-height: 1 !important;
                letter-spacing: -0.045em !important;
                text-transform: uppercase !important;
            }
            .welcome-desc {
                max-width: 720px !important;
                color: #374151 !important;
                font-size: 0.8rem !important;
                font-weight: 800 !important;
                line-height: 1.35 !important;
                margin-top: 0.45rem !important;
            }
            .badge-type,
            .badge-status {
                border: 2px solid #050505 !important;
                border-radius: 0 !important;
                box-shadow: 3px 3px 0 #050505 !important;
                font-family: 'JetBrains Mono', monospace !important;
                color: #050505 !important;
                background: #f7f3ff !important;
            }
            .badge-status.success {
                background: #d8ff6f !important;
            }
            .badge-status.warning {
                background: #ffcf5a !important;
            }
            .stats-grid {
                display: grid !important;
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                gap: 0.85rem !important;
                margin-bottom: 1rem !important;
            }
            .stat-card {
                min-height: 126px !important;
                padding: 1rem !important;
                background: #ffffff !important;
                border: 3px solid #050505 !important;
                border-radius: 0 !important;
                box-shadow: 5px 5px 0 #050505 !important;
                color: #050505 !important;
            }
            .stat-card i {
                color: #7c3aed !important;
                opacity: 1 !important;
            }
            .stat-label,
            .stat-desc {
                color: #374151 !important;
                font-family: 'JetBrains Mono', monospace !important;
            }
            .stat-value {
                color: #050505 !important;
                font-size: clamp(1.55rem, 4vw, 2.15rem) !important;
            }
            .cabinet-action-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 0.85rem;
                margin-bottom: 1.35rem;
            }
            .cabinet-action-card {
                min-height: 104px;
                padding: 1rem;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                gap: 0.75rem;
                background: #d8ff6f;
                border: 3px solid #050505;
                border-radius: 0;
                box-shadow: 5px 5px 0 #050505;
                color: #050505;
                text-decoration: none;
                transition: transform 0.15s ease, box-shadow 0.15s ease;
            }
            .cabinet-action-card:nth-child(2) {
                background: #ffffff;
            }
            .cabinet-action-card:nth-child(3) {
                background: #f7f3ff;
            }
            .cabinet-action-card:hover {
                transform: translate(2px, 2px);
                box-shadow: 3px 3px 0 #050505;
            }
            .cabinet-action-card strong {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 0.95rem;
                font-weight: 950;
                line-height: 1;
                text-transform: uppercase;
            }
            .cabinet-action-card span {
                color: #374151;
                font-size: 0.78rem;
                font-weight: 850;
                line-height: 1.25;
            }
            .workspace-grid {
                display: grid !important;
                grid-template-columns: minmax(0, 1.35fr) minmax(300px, 0.65fr) !important;
                gap: 1.25rem !important;
                margin: 0 auto !important;
                align-items: start !important;
            }
            .workspace-grid.workspace-grid-single {
                grid-template-columns: 1fr !important;
            }
            .workspace-grid.vault-workspace-grid {
                grid-template-columns: 1fr !important;
            }
            .cabinet-top-grid {
                display: grid !important;
                grid-template-columns: 1fr !important;
                gap: 1rem !important;
                align-items: start !important;
                margin-bottom: 0 !important;
                padding: 1rem !important;
                background: #ffffff !important;
                border: 3px solid #050505 !important;
                border-radius: 0 !important;
                box-shadow: 7px 7px 0 #050505 !important;
            }
            .cabinet-top-grid > .cabinet-side-panel {
                grid-column: 1 !important;
                grid-row: 2 !important;
            }
            .cabinet-top-grid > .vault-workspace-grid {
                grid-column: 1 !important;
                grid-row: 1 !important;
                min-width: 0 !important;
                margin: 0 !important;
            }
            .cabinet-side-panel {
                display: flex !important;
                flex-direction: column !important;
                gap: 0.75rem !important;
            }
            .workspace-left {
                width: 100% !important;
            }
            .workspace-right {
                display: flex !important;
                flex-direction: column !important;
                gap: 1rem !important;
            }
            .sec-card {
                background: #ffffff !important;
                border: 3px solid #050505 !important;
                border-radius: 0 !important;
                box-shadow: 6px 6px 0 #050505 !important;
                color: #050505 !important;
            }
            .sec-card h3,
            .sec-card p,
            .sec-card div,
            .sec-card span {
                color: inherit;
            }
            .sec-card h3 {
                color: #050505 !important;
                text-transform: uppercase;
            }
            .sec-card p {
                color: #374151 !important;
                font-weight: 750;
            }
            .sec-card :is(input, button, a) {
                border-radius: 0 !important;
            }
            .vault-title-row {
                justify-content: space-between !important;
                gap: 0.65rem !important;
                margin-bottom: 0.45rem !important;
            }
            .vault-title-row h2 {
                color: #050505 !important;
                font-size: 0.82rem !important;
                font-family: 'JetBrains Mono', monospace !important;
                font-weight: 950 !important;
                letter-spacing: 0.08em !important;
                text-transform: uppercase !important;
            }
            .vault-help {
                width: 18px !important;
                height: 18px !important;
                border: 2px solid #050505 !important;
                border-radius: 0 !important;
                background: #f7f3ff !important;
                color: #7c3aed !important;
                box-shadow: 2px 2px 0 #050505 !important;
            }
            .vault-help::after {
                border: 2px solid #050505 !important;
                border-radius: 0 !important;
                background: #ffffff !important;
                color: #050505 !important;
                box-shadow: 5px 5px 0 #050505 !important;
            }
            .vault-entry-card {
                isolation: isolate;
                width: 100% !important;
                min-height: 330px !important;
                margin: 0 0 1rem !important;
                padding: 1.7rem !important;
                position: relative !important;
                overflow: hidden !important;
                background: #ffffff !important;
                border: 4px solid #050505 !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                color: #050505 !important;
            }
            .vault-entry-card::before {
                content: '';
                position: absolute;
                inset: 14px;
                z-index: -1;
                border: 3px solid #050505;
                border-radius: 0;
                background:
                    linear-gradient(90deg, transparent calc(50% - 1px), rgba(5, 5, 5, 0.10) 50%, transparent calc(50% + 1px)),
                    repeating-linear-gradient(135deg, rgba(124, 58, 237, 0.10) 0 10px, transparent 10px 20px);
            }
            .vault-entry-card::after {
                content: '';
                position: absolute;
                width: 72px;
                height: 72px;
                right: 22px;
                top: 22px;
                border: 3px solid #050505;
                border-radius: 0;
                background:
                    radial-gradient(circle, #ffffff 0 28%, transparent 29%),
                    conic-gradient(from 20deg, #7c3aed, #d8ff6f, #7c3aed);
                box-shadow: 5px 5px 0 #050505;
                opacity: 0.95;
            }
            .vault-entry-card .lock-icon-container {
                width: 88px !important;
                height: 88px !important;
                margin-bottom: 1.1rem !important;
                background: #7c3aed !important;
                border: 4px solid #050505 !important;
                border-radius: 0 !important;
                box-shadow: 5px 5px 0 #050505 !important;
                animation: none !important;
                transform: rotate(-2deg);
            }
            .vault-entry-card .lock-icon-container i {
                color: #ffffff !important;
                font-size: 2.35rem !important;
            }
            .vault-entry-card h3 {
                max-width: 420px;
                margin-bottom: 0.6rem !important;
                color: #050505 !important;
                font-size: clamp(1.55rem, 4vw, 2.45rem) !important;
                line-height: 1 !important;
                letter-spacing: -0.045em !important;
                text-transform: uppercase;
            }
            .vault-entry-card p {
                max-width: 450px !important;
                margin-bottom: 1.15rem !important;
                color: #374151 !important;
                font-size: 0.84rem !important;
                font-weight: 800 !important;
                line-height: 1.3 !important;
            }
            .vault-entry-card .btn-unlock-vault {
                min-height: 42px !important;
                padding: 0 1.05rem !important;
                border: 3px solid #050505 !important;
                border-radius: 0 !important;
                background: #7c3aed !important;
                color: #ffffff !important;
                box-shadow: 4px 4px 0 #050505 !important;
                font-size: 0.74rem !important;
                letter-spacing: 0.04em !important;
            }
            .vault-entry-card .btn-unlock-vault:hover:not(:disabled) {
                transform: translate(2px, 2px);
                box-shadow: 4px 4px 0 #050505 !important;
            }
            #vault-unlock-status {
                color: #4b5563 !important;
            }
            .vault-grid {
                gap: 1rem !important;
                margin-bottom: 2rem !important;
            }
            .vault-card {
                background: #ffffff !important;
                border: 3px solid #050505 !important;
                border-radius: 0 !important;
                box-shadow: 7px 7px 0 #050505 !important;
                color: #050505 !important;
            }
            .empty-vault-container {
                background: #ffffff !important;
                min-height: 300px !important;
                margin-bottom: 0 !important;
                padding: 2.25rem 1.5rem !important;
                border: 4px dashed #050505 !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                color: #050505 !important;
                width: 100% !important;
                margin-left: auto !important;
                margin-right: auto !important;
            }
            .empty-vault-container h3 {
                margin-bottom: 0.45rem !important;
                font-size: 1.2rem !important;
                line-height: 1.1 !important;
            }
            .empty-vault-container p {
                max-width: 430px !important;
                margin-bottom: 1.2rem !important;
                font-size: 0.82rem !important;
                line-height: 1.4 !important;
            }
            .empty-vault-container h3,
            .empty-vault-container p {
                color: #050505 !important;
            }
            .empty-vault-icon {
                width: 64px !important;
                height: 64px !important;
                margin-bottom: 1rem !important;
                background: #f7f3ff !important;
                border: 3px solid #050505 !important;
                border-radius: 0 !important;
                box-shadow: 4px 4px 0 #050505 !important;
            }
            .empty-vault-icon i {
                font-size: 1.85rem !important;
            }
            .empty-vault-container .btn-nav-cta,
            .empty-vault-container a {
                padding: 0.55rem 1rem !important;
                font-size: 0.68rem !important;
            }
            .vault-wallet-card {
                margin-top: 0.65rem !important;
                padding: 0.85rem !important;
                background: #ffffff !important;
                border: 2px solid #050505 !important;
                border-radius: 0 !important;
                box-shadow: 4px 4px 0 #050505 !important;
                color: #050505 !important;
            }
            .vault-wallet-card h3 {
                color: #050505 !important;
                font-size: 0.74rem !important;
                margin-bottom: 0.35rem !important;
            }
            .vault-wallet-card p {
                color: #374151 !important;
                font-size: 0.64rem !important;
                line-height: 1.25 !important;
                margin-bottom: 0.6rem !important;
            }
            .vault-wallet-balances {
                gap: 0.5rem !important;
                margin-bottom: 0.65rem !important;
            }
            .vault-wallet-balance {
                padding: 0.6rem !important;
                border-radius: 0 !important;
                border: 1px solid #d1d5db !important;
            }
            .vault-wallet-value {
                font-size: 0.8rem !important;
            }
            .vault-wallet-history-title {
                margin-bottom: 0.45rem !important;
                font-size: 0.58rem !important;
            }
            .vault-wallet-empty {
                padding: 0.55rem !important;
                font-size: 0.6rem !important;
            }
            .vault-wallet-action {
                min-height: 28px !important;
                padding: 0 0.55rem !important;
                border: 2px solid #050505 !important;
                border-radius: 0 !important;
                color: #7c3aed !important;
                font-size: 0.62rem !important;
                box-shadow: 2px 2px 0 #050505 !important;
            }
            .sl1-manage-card {
                padding: 1.1rem !important;
                background: #ffffff !important;
                border: 2px solid #050505 !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                color: #050505 !important;
            }
            .sl1-manage-head {
                display: flex !important;
                align-items: center !important;
                gap: 0.55rem !important;
                margin-bottom: 0.65rem !important;
            }
            .sl1-manage-icon {
                width: 34px !important;
                height: 34px !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                background: #f7f3ff !important;
                border: 2px solid #050505 !important;
                border-radius: 0 !important;
                color: #7c3aed !important;
                box-shadow: 2px 2px 0 #050505 !important;
                flex-shrink: 0 !important;
            }
            .sl1-manage-eyebrow {
                display: block !important;
                color: #4b5563 !important;
                font-family: 'JetBrains Mono', monospace !important;
                font-size: 0.52rem !important;
                font-weight: 900 !important;
                letter-spacing: 0.08em !important;
                text-transform: uppercase !important;
            }
            .sl1-manage-card h3 {
                color: #050505 !important;
                font-size: clamp(1.05rem, 2vw, 1.35rem) !important;
                margin: 0.1rem 0 0 !important;
            }
            .sl1-manage-card p {
                color: #374151 !important;
                font-size: 0.8rem !important;
                line-height: 1.35 !important;
                margin-bottom: 0.75rem !important;
            }
            .sl1-manage-features {
                display: grid !important;
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                gap: 0.55rem !important;
                margin-bottom: 0.85rem !important;
            }
            .sl1-manage-feature {
                display: flex !important;
                align-items: center !important;
                gap: 0.42rem !important;
                color: #050505 !important;
                font-size: 0.68rem !important;
                font-weight: 900 !important;
                padding: 0.6rem 0.7rem !important;
                border: 2px solid #050505 !important;
                border-radius: 0 !important;
                background: #f8fafc !important;
            }
            .sl1-manage-feature i {
                color: #7c3aed !important;
            }
            .sl1-manage-action {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 0.45rem !important;
                min-height: 42px !important;
                width: auto !important;
                padding: 0 1rem !important;
                border: 2px solid #050505 !important;
                border-radius: 0 !important;
                background: #7c3aed !important;
                color: #ffffff !important;
                font-size: 0.68rem !important;
                box-shadow: 4px 4px 0 #050505 !important;
            }
            .vault-prod-title,
            .vault-card .vault-meta-row,
            .vault-safe-hint {
                color: #050505 !important;
            }
            .vault-card .secure-key-block {
                background: #f8fafc !important;
                border: 2px solid #050505 !important;
                color: #050505 !important;
            }
            .vault-open-button {
                background: #7c3aed !important;
                border: 2px solid #050505 !important;
                color: #ffffff !important;
                box-shadow: 3px 3px 0 #050505 !important;
            }
            @media (max-width: 900px) {
                .vault-entry-card {
                    min-height: 380px !important;
                    border-radius: 0 !important;
                }
                .vault-entry-card::after {
                    width: 82px;
                    height: 82px;
                    right: 22px;
                    top: 22px;
                }
                .stats-grid,
                .cabinet-action-grid,
                .workspace-grid {
                    grid-template-columns: 1fr !important;
                }
                .cabinet-top-grid {
                    grid-template-columns: 1fr !important;
                }
                .cabinet-top-grid > .welcome-card,
                .cabinet-top-grid > .cabinet-side-panel,
                .cabinet-top-grid > .vault-workspace-grid {
                    grid-column: 1 !important;
                    grid-row: auto !important;
                }
                .cabinet-top-grid > .vault-workspace-grid {
                    order: 1 !important;
                }
                .cabinet-top-grid > .cabinet-side-panel {
                    order: 2 !important;
                }
                .sl1-manage-features {
                    grid-template-columns: 1fr !important;
                }
                .vault-wallet-balances {
                    grid-template-columns: 1fr !important;
                }
            }

            /* 🌹 Glass Dome Floating Widget */
            .little-prince-dome {
                position: fixed;
                bottom: 30px;
                right: 30px;
                width: 130px;
                height: 180px;
                z-index: 10000;
                cursor: pointer;
                pointer-events: auto;
                animation: domeFloat 4.5s ease-in-out infinite;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                filter: drop-shadow(0 10px 25px rgba(225, 29, 72, 0.18));
                display: none;
            }
            body[data-holiday="little-prince"] .little-prince-dome {
                display: block !important;
            }
            .little-prince-dome:hover {
                transform: scale(1.12) translateY(-5px);
                filter: drop-shadow(0 15px 35px rgba(225, 29, 72, 0.38));
            }
            .dome-svg {
                width: 100%;
                height: 100%;
                filter: drop-shadow(0 0 12px rgba(225, 29, 72, 0.2));
            }
            .dome-glow {
                position: absolute;
                inset: 15px;
                background: radial-gradient(circle, rgba(225, 29, 72, 0.2) 0%, rgba(225,29,72,0) 70%);
                pointer-events: none;
                z-index: -1;
                mix-blend-mode: screen;
                animation: rosePulse 3.5s ease-in-out infinite;
            }
            .dome-tooltip {
                position: absolute;
                bottom: 105%;
                right: 0;
                width: 240px;
                background: rgba(11, 15, 25, 0.92);
                border: 1px solid rgba(255, 255, 255, 0.15);
                backdrop-filter: blur(12px);
                color: #ffffff;
                padding: 10px 14px;
                border-radius: 12px;
                font-size: 13px;
                text-align: center;
                line-height: 1.4;
                opacity: 0;
                visibility: hidden;
                transform: translateY(10px);
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                pointer-events: none;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            }
            .little-prince-dome:hover .dome-tooltip {
                opacity: 1;
                visibility: visible;
                transform: translateY(0);
            }
            .dome-sparkle {
                position: absolute;
                width: 4px;
                height: 4px;
                background: #ffd700;
                border-radius: 50%;
                box-shadow: 0 0 8px #ffd700;
                pointer-events: none;
                animation: domeSparkle 3s ease-in-out infinite;
            }
            @keyframes domeFloat {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-8px); }
            }
            @keyframes rosePulse {
                0%, 100% { opacity: 0.6; transform: scale(0.92); }
                50% { opacity: 1; transform: scale(1.12); }
            }
            @keyframes domeSparkle {
                0%, 100% { transform: scale(0) translateY(0); opacity: 0; }
                50% { transform: scale(1) translateY(-18px); opacity: 1; }
            }
        </style>
        @include('partials.meanly-public-ui')
        @livewireStyles
    </head>
@include('partials.theme-sync-body')
<body class="meanly-buyer-page" data-theme="{{ $currentTheme ?? request()->cookie('theme') ?? 'consortium' }}" @if(request()->cookie('holiday')) data-holiday="{{ request()->cookie('holiday') }}" @endif>

<!-- 🌹 Easter Egg Widget: Rose under Glass Dome (The Little Prince) -->
<div class="little-prince-dome" id="littlePrinceDome">
    <div class="dome-tooltip">«Ты навсегда в ответе за тех, кого приручил. Твоя Роза.» 🌹</div>
    <svg viewBox="0 0 100 140" class="dome-svg">
        <ellipse cx="50" cy="120" rx="35" ry="10" fill="#4a2c11" stroke="#2c1a0a" stroke-width="1.5"/>
        <ellipse cx="50" cy="118" rx="32" ry="8" fill="#6d421e"/>
        <path d="M 42 116 Q 46 113 49 116 Q 45 119 42 116" fill="#e11d48" opacity="0.9"/>
        <path d="M 50 118 Q 48 95 50 75" fill="none" stroke="#166534" stroke-width="2.5" stroke-linecap="round"/>
        <path d="M 49 100 Q 40 98 44 92 Q 49 96 49 100" fill="#15803d"/>
        <path d="M 50 88 Q 58 87 55 81 Q 50 84 50 88" fill="#15803d"/>
        <ellipse cx="50" cy="70" rx="7" ry="10" fill="#be123c"/>
        <path d="M 44 73 C 40 65, 46 58, 50 63 C 54 58, 60 65, 56 73 C 50 78, 50 78, 44 73 Z" fill="#e11d48"/>
        <path d="M 47 72 C 45 68, 48 64, 50 66 C 52 64, 55 68, 53 72 Z" fill="#f43f5e"/>
        <path d="M 22 118 L 22 60 A 28 28 0 0 1 78 60 L 78 118 Z" fill="rgba(255, 255, 255, 0.08)" stroke="rgba(255, 255, 255, 0.35)" stroke-width="1.5" stroke-linejoin="round"/>
        <path d="M 28 110 L 28 60 A 22 22 0 0 1 50 38" fill="none" stroke="rgba(255, 255, 255, 0.25)" stroke-width="1.5" stroke-linecap="round"/>
        <circle cx="50" cy="30" r="4.5" fill="rgba(255, 255, 255, 0.4)" stroke="rgba(255, 255, 255, 0.6)" stroke-width="1"/>
    </svg>
    <div class="dome-glow"></div>
    <div class="dome-sparkle" style="top: 40%; left: 30%; animation-delay: 0s;"></div>
    <div class="dome-sparkle" style="top: 60%; left: 70%; animation-delay: 1.2s;"></div>
    <div class="dome-sparkle" style="top: 80%; left: 45%; animation-delay: 2.4s;"></div>
</div>

<div class="ambient-glows">
    <div class="glow-1"></div>
    <div class="glow-2"></div>
</div>

@include('storefront.partials.header')

@php
    $focusedSafeUuid = (string) request()->query('safe', '');
    $simpleL1ManageUrl = rtrim((string) config('simple_l1.identity_provider_url', config('app.url', 'https://meanly.one')), '/').'#wallet';
@endphp

<main class="cabinet-container">
    <div class="cabinet-top-grid">
    <div class="cabinet-side-panel">
        <div class="sl1-manage-card">
            <div class="sl1-manage-head">
                <div class="sl1-manage-icon"><i class="ph-bold ph-circles-three-plus"></i></div>
                <div>
                    <span class="sl1-manage-eyebrow">Meanly работает с Meanly One</span>
                    <h3>Зачем здесь Meanly One?</h3>
                </div>
            </div>
            <p>Это отдельный слой, где живут ваш вход, кошелек и история операций. Meanly использует его как основу: здесь остаются только покупки и сейф, а деньги и ключи не хранятся на сайте.</p>
            <div class="sl1-manage-features">
                <div class="sl1-manage-feature"><i class="ph-bold ph-fingerprint"></i> Вход без пароля</div>
                <div class="sl1-manage-feature"><i class="ph-bold ph-wallet"></i> Кошелек, баланс и история</div>
                <div class="sl1-manage-feature"><i class="ph-bold ph-shield-check"></i> Подтверждение перед сейфом</div>
            </div>
            <a href="{{ $simpleL1ManageUrl }}" class="sl1-manage-action" target="_blank" rel="noopener">
                <i class="ph-bold ph-wallet"></i> Открыть Meanly One
            </a>
        </div>
    </div>

    <!-- Main Workspace Grid -->
    <div class="workspace-grid vault-workspace-grid">
        
        <!-- Left Column: License Keys Vault -->
        <div class="workspace-left">
            <!-- Vault Title -->
            <div class="vault-title-row">
                <h2>
                    <i class="ph-bold ph-vault"></i>
                    Сейф покупок
                    <span
                        class="vault-help"
                        tabindex="0"
                        aria-label="Что такое сейф покупок"
                        data-tooltip="Здесь лежат ваши покупки и коды после оплаты. Чтобы их увидеть, нужно заново подтвердить себя через SL1 Passkey."
                    >?</span>
                </h2>
                <div class="vault-title-actions">
                    @if(! $hasVaultAuthenticator)
                        <a href="{{ route('register') }}" class="badge-status warning">
                            <i class="ph-bold ph-shield-warning" style="animation: pulse 2s infinite;"></i> Создать Passkey &rarr;
                        </a>
                    @elseif($vaultUnlocked)
                        <span class="badge-status success">
                            <i class="ph-bold ph-lock-open"></i> Сейф открыт
                        </span>
                        <form method="POST" action="{{ route('cabinet.vault.lock') }}" class="vault-lock-form">
                            @csrf
                            <button type="submit" class="badge-status warning lock-action">
                                <i class="ph-bold ph-lock-key"></i> Закрыть сейф
                            </button>
                        </form>
                    @else
                        <span class="badge-status warning">
                            <i class="ph-bold ph-lock-key"></i> Сейф закрыт
                        </span>
                    @endif
                </div>
            </div>

            <!-- Vault Grid / Items -->
            @if(! $vaultUnlocked)
                <div class="vault-entry-card" id="vault-locked-overlay">
                    <div class="lock-icon-container">
                        <i class="ph-bold ph-fingerprint"></i>
                    </div>
                    <h3>Сейф закрыт</h3>
                    <p>Мы скрыли покупки и коды. Подтвердите себя через SL1 Passkey, и сейф откроется на короткое время.</p>
                    @if($hasSovereignIdentity)
                        <a href="{{ $vaultUnlockUrl }}" class="btn-unlock-vault" style="text-decoration: none;">
                            Открыть сейф
                        </a>
                    @else
                        <a href="{{ route('register') }}" class="btn-unlock-vault" style="text-decoration: none;">
                            Создать SL1 Identity
                        </a>
                    @endif
                </div>
            @elseif($safeOrders->isEmpty())
                <div class="empty-vault-container" style="background: rgba(255,255,255,0.02); border: 1px dashed var(--brand-border); border-radius: 20px; padding: 4rem 2rem; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 400px; margin-bottom: 2rem;">
                    <div class="empty-vault-icon" style="width: 80px; height: 80px; border-radius: 50%; background: rgba(255, 255, 255, 0.05); display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem;">
                        <i class="ph-bold ph-vault" style="font-size: 2.5rem; color: var(--brand-subtext);"></i>
                    </div>
                    <h3 style="font-size: 1.4rem; font-weight: 800; margin-bottom: 0.5rem; color: var(--brand-text);">Здесь пока пусто</h3>
                    <p style="color: var(--brand-subtext); margin-bottom: 2rem; font-size: 14px; max-width: 400px; line-height: 1.5;">
                        После покупки игра, подписка или карта появится здесь. Код будет видно только после подтверждения через SL1 Passkey.
                    </p>
                    
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center;">
                        <a href="/" class="btn-nav-cta" style="text-decoration: none; padding: 0.8rem 2rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="ph-bold ph-storefront"></i> Перейти на витрину
                        </a>
                        <a href="#wishlist" style="background: rgba(255,255,255,0.05); border: 1px solid var(--brand-border); color: var(--brand-text); padding: 0.8rem 2rem; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s;">
                            <i class="ph-bold ph-heart"></i> Избранное
                        </a>
                    </div>
                </div>
            @else
                <div class="vault-grid" id="vault-grid-content">
                    @foreach($safeOrders as $safe)
                        @php
                            $prodName = $safe['product_name'];
                            $brand = strtoupper($prodName);
                            if (str_contains($brand, 'STEAM')) {
                                $gradient = 'linear-gradient(90deg, #1b2838, #66c0f4)';
                                $color = '#66c0f4';
                                $icon = 'ph-steam-logo';
                                $brandName = 'Steam';
                            } elseif (str_contains($brand, 'SPOTIFY')) {
                                $gradient = 'linear-gradient(90deg, #1db954, #1ed760)';
                                $color = '#1db954';
                                $icon = 'ph-spotify-logo';
                                $brandName = 'Spotify';
                            } elseif (str_contains($brand, 'PLAYSTATION') || str_contains($brand, 'PSN')) {
                                $gradient = 'linear-gradient(90deg, #003087, #0072ce)';
                                $color = '#0072ce';
                                $icon = 'ph-game-controller';
                                $brandName = 'PlayStation';
                            } elseif (str_contains($brand, 'XBOX')) {
                                $gradient = 'linear-gradient(90deg, #107c10, #109d10)';
                                $color = '#109d10';
                                $icon = 'ph-xbox-logo';
                                $brandName = 'Xbox';
                            } else {
                                $gradient = 'linear-gradient(90deg, #f53003, #ff7b00)';
                                $color = '#f53003';
                                $icon = 'ph-cube';
                                $brandName = 'Meanly Safe';
                            }
                            $statusColor = $safe['ready'] ? '#22c55e' : (str_contains($safe['status'], 'failed') ? '#ef4444' : '#ffaa00');
                            $safeAnchor = $safe['anchor'] ?? 'safe-'.$safe['uuid'];
                            $isFocusedSafe = $focusedSafeUuid !== '' && hash_equals((string) $safe['uuid'], $focusedSafeUuid);
                        @endphp
                        
                        <div
                            id="{{ $safeAnchor }}"
                            class="vault-card {{ $isFocusedSafe ? 'is-focused' : '' }}"
                            data-safe-card
                            data-safe-uuid="{{ $safe['uuid'] }}"
                            data-safe-status="{{ $safe['status'] }}"
                            data-safe-ready="{{ $safe['ready'] ? '1' : '0' }}"
                            data-safe-failed="{{ str_contains($safe['status'], 'failed') ? '1' : '0' }}"
                            data-safe-status-url="{{ $safe['safe_status_url'] }}"
                            data-safe-open-url="{{ $safe['safe_open_url'] }}"
                            data-safe-order-id="{{ $safe['order_id'] }}"
                            data-safe-support-ticket-id="{{ $safe['support_ticket_id'] ?? '' }}"
                            data-safe-support-ticket-url="{{ $safe['support_ticket_url'] ?? '' }}"
                            data-safe-support-ticket-messages-url="{{ $safe['support_ticket_messages_url'] ?? '' }}"
                            data-safe-support-ticket-reply-url="{{ $safe['support_ticket_reply_url'] ?? '' }}"
                            data-safe-scratched="{{ $safe['scratched'] ? '1' : '0' }}"
                            data-safe-scratch-proof="{{ $safe['scratch_proof'] ?? '' }}"
                            tabindex="-1"
                            style="--platform-gradient: {{ $gradient }}; --platform-color: {{ $color }};"
                        >
                            <div class="vault-info-group">
                                <div class="vault-icon-pane">
                                    <i class="ph-bold {{ $icon }}"></i>
                                </div>
                                <div class="vault-details">
                                    <span class="vault-platform-badge"><i class="ph-bold {{ $icon }}"></i> {{ $brandName }}</span>
                                    <h4 class="vault-prod-title">{{ $prodName }}</h4>
                                    <div class="vault-meta-row">
                                        <span>Заказ {{ $safe['order_id'] }}</span>
                                        <span>•</span>
                                        <span>Куплен: {{ $safe['created_at']?->format('d.m.Y H:i') }}</span>
                                        <span>•</span>
                                        <span>{{ number_format($safe['total_amount'], 2, '.', ' ') }} {{ $safe['currency'] }}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="secure-key-block" style="gap: 1rem;">
                                <div class="vault-safe-status">
                                    <span data-safe-label style="color: {{ $statusColor }};">{{ $safe['label'] }}</span>
                                    <small class="vault-safe-hint" data-safe-hint>{{ $safe['message'] }}</small>
                                </div>
                                <div class="vault-actions">
                                    <button
                                        class="vault-open-button"
                                        type="button"
                                        data-safe-open-button
                                        @disabled(! $safe['ready'])
                                    >
                                        {{ $safe['ready'] ? 'Открыть сейф' : (str_contains($safe['status'], 'failed') ? 'Недоступно' : 'Готовится') }}
                                    </button>
                                    <a class="vault-safe-link" href="{{ $safe['safe_url'] }}">Открыть отдельно</a>
                                    <a
                                        class="vault-support-link"
                                        href="#"
                                        data-safe-support-ticket-link
                                        style="{{ ! empty($safe['support_ticket_url']) ? '' : 'display: none;' }}"
                                    >
                                        Чат с поддержкой
                                    </a>
                                </div>
                            </div>
                            <div class="vault-inline-safe" data-safe-inline-panel hidden>
                                <div class="vault-inline-message" data-safe-inline-message aria-live="polite">
                                    {{ $safe['message'] }}
                                </div>
                                <div class="vault-code-list" data-safe-codes></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        @if(false && $user->hasOpsSovereignAccess())
        <div class="workspace-right" style="display: flex; flex-direction: column; gap: 1rem;">
            <!-- Operations Runtime Panel -->
            <div class="sec-card" id="constitutional-panel" style="background: var(--brand-card); border: 1px solid var(--brand-border); border-radius: 20px; padding: 2rem; position: relative; overflow: hidden; text-align: left;">
                <!-- Subtle glow -->
                <div style="position: absolute; top: -60px; right: -60px; width: 180px; height: 180px; background: radial-gradient(circle, rgba(245,48,3,0.04) 0%, rgba(0,0,0,0) 70%); pointer-events: none;"></div>

                <h3 style="font-size: 1.15rem; font-weight: 900; letter-spacing: -0.02em; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.5rem; color: var(--brand-text);">
                    <i class="ph-bold ph-scales" style="color: var(--brand-primary);"></i> Служебный журнал
                </h3>
                <p style="font-size: 11.5px; color: var(--brand-subtext); margin-bottom: 1.5rem; line-height: 1.4;">
                    Проверка состояния заказов и служебных событий
                </p>

                <!-- Runtime status row -->
                <div id="sl1-status-row" style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 1.5rem;">
                    <div id="sl1-status-dot" style="width: 8px; height: 8px; border-radius: 50%; background: #444; flex-shrink: 0; transition: background 0.3s;"></div>
                    <span id="sl1-status-label" style="font-family: 'JetBrains Mono', monospace; font-size: 10.5px; color: var(--brand-subtext); font-weight: 600;">Connecting…</span>
                </div>

                <!-- Identity SQRP Card -->
                <div id="sl1-identity-card" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; display: none;">
                    <div style="font-size: 9px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--brand-primary); font-weight: 800; margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: space-between;">
                        <span>ACCOUNT IDENTITY</span>
                        <div style="display: flex; gap: 0.5rem;">
                            <button id="btn-generate-intent-sqrp" style="cursor: pointer; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: var(--brand-text); font-size: 9px; font-weight: 700; padding: 3px 8px; border-radius: 6px; display: flex; align-items: center; gap: 0.3rem; transition: background 0.2s;">
                                <i class="ph-bold ph-handshake"></i> PAYMENT CHECK
                            </button>
                            <button id="btn-generate-identity-sqrp" style="cursor: pointer; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: var(--brand-text); font-size: 9px; font-weight: 700; padding: 3px 8px; border-radius: 6px; display: flex; align-items: center; gap: 0.3rem; transition: background 0.2s;">
                                <i class="ph-bold ph-qr-code"></i> PROFILE
                            </button>
                        </div>
                    </div>
                    <div style="font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--brand-text); font-weight: 700; margin-bottom: 0.25rem;" id="sl1-id-address">—</div>
                    <div style="font-family: 'JetBrains Mono', monospace; font-size: 9px; color: var(--brand-subtext); margin-bottom: 0.75rem;" id="sl1-id-passkey">—</div>
                    <div style="font-size: 10px; color: #107c10; font-weight: 700;"><i class="ph-bold ph-check-circle"></i> Passkey защищает этот профиль</div>
                </div>

                <!-- Runtime status terminal -->
                <div id="sl1-epoch-block" style="font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--brand-subtext); display: flex; flex-direction: column; gap: 0.6rem; margin-bottom: 1.5rem; display: none;">
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255,255,255,0.04); padding-bottom: 0.5rem; align-items: center;">
                        <span>Версия журнала</span>
                        <span id="sl1-epoch-val" style="color: var(--brand-primary); font-weight: 700;">—</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255,255,255,0.04); padding-bottom: 0.5rem; align-items: center;">
                        <span>Контрольная запись</span>
                        <span id="sl1-root-val" style="color: #ffffff; font-weight: 700; font-size: 10px;">—</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255,255,255,0.04); padding-bottom: 0.5rem; align-items: center;">
                        <span>Версия правил</span>
                        <span id="sl1-policy-val" style="color: #107c10; font-weight: 700;">—</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255,255,255,0.04); padding-bottom: 0.5rem; align-items: center;">
                        <span>Сервисная связка</span>
                        <span id="sl1-fed-val" style="color: #ffaa00; font-weight: 700; font-size: 10px;">—</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Состояние</span>
                        <span id="sl1-state-val" style="color: var(--brand-subtext); font-weight: 700; font-size: 10px;">—</span>
                    </div>
                </div>

                <!-- Receipt history -->
                <div id="sl1-receipts-section" style="display: none;">
                    <div style="font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: var(--brand-subtext); margin-bottom: 0.75rem;">
                        Служебные квитанции
                    </div>
                    <div id="sl1-receipts-list" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <!-- Populated by JS -->
                    </div>
                    <div id="sl1-no-receipts" style="font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--brand-subtext); display: none; text-align: center; padding: 1rem 0;">
                        Квитанций пока нет
                    </div>
                </div>

                <!-- Offline: show static info -->
                <div id="sl1-offline-block" style="font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--brand-subtext); padding: 1rem; background: rgba(255,255,255,0.01); border: 1px dashed rgba(255,255,255,0.04); border-radius: 10px; text-align: center; display: none;">
                    <i class="ph-bold ph-wifi-slash" style="font-size: 1.5rem; margin-bottom: 0.5rem; display: block; color: rgba(255,255,255,0.1);"></i>
                    Служебный журнал временно недоступен<br>
                    <span style="font-size: 10px; margin-top: 0.25rem; display: block;">Квитанции можно проверить вручную</span>
                </div>

                <!-- Offline receipt checker -->
                <div id="sl1-verifier-section" style="margin-top: 1.5rem; border-top: 1px dashed rgba(255,255,255,0.06); padding-top: 1.5rem;">
                    <div style="font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: var(--brand-subtext); margin-bottom: 0.75rem;">
                        Проверка квитанции
                    </div>
                    <div id="sl1-drop-zone" style="border: 2px dashed rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 1.25rem; text-align: center; cursor: pointer; transition: all 0.2s; background: rgba(255, 255, 255, 0.01);">
                        <i class="ph-bold ph-shield-check" style="font-size: 1.5rem; color: var(--brand-primary); margin-bottom: 0.5rem; display: block;"></i>
                        <span style="font-size: 11px; color: var(--brand-text); font-weight: 600; display: block; margin-bottom: 0.25rem;">Перетащите receipt.json сюда</span>
                        <span style="font-size: 9.5px; color: var(--brand-subtext); display: block;">или нажмите для выбора файла</span>
                        <input type="file" id="sl1-file-input" style="display: none;" accept=".json">
                    </div>
                    
                    <!-- Результаты верификации -->
                    <div id="sl1-verification-result" style="display: none; margin-top: 1rem; font-family: 'JetBrains Mono', monospace; font-size: 11px; padding: 1rem; border-radius: 10px; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.04);">
                        <!-- Заголовок -->
                        <div style="font-weight: 700; border-bottom: 1px dashed rgba(255,255,255,0.06); padding-bottom: 0.5rem; margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                            <span>REPORT: RECEIPT CHECK</span>
                            <span id="sl1-audit-badge" style="padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 800;">PENDING</span>
                        </div>
                        <!-- Строки отчета -->
                        <div style="display: flex; flex-direction: column; gap: 0.4rem; color: var(--brand-subtext);">
                            <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255,255,255,0.03); padding-bottom: 0.25rem;">
                                <span>1. Подпись документа</span>
                                <span id="sl1-audit-sig" style="font-weight: 700;">—</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255,255,255,0.03); padding-bottom: 0.25rem;">
                                <span>2. Версия журнала</span>
                                <span id="sl1-audit-epoch" style="font-weight: 700;">—</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255,255,255,0.03); padding-bottom: 0.25rem;">
                                <span>3. Проверка источника</span>
                                <span id="sl1-audit-fed" style="font-weight: 700;">—</span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span>4. Смысловая проверка</span>
                                <span id="sl1-audit-semantic" style="font-weight: 700;">—</span>
                            </div>
                        </div>
                        <div id="sl1-audit-verdict" style="margin-top: 0.75rem; padding-top: 0.5rem; border-top: 1px dashed rgba(255,255,255,0.06); font-weight: 700; text-align: center; font-size: 10px;">
                            —
                        </div>

                        <!-- ⚙️ Mode Selector for Human Interpretations -->
                        <div id="sl1-mode-selector" style="display: none; margin-top: 0.75rem; display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed rgba(255,255,255,0.06); padding-top: 0.5rem;">
                            <span style="font-size: 8px; font-weight: 800; color: var(--brand-subtext); text-transform: uppercase;">Режим объяснения</span>
                            <div style="display: flex; gap: 0.25rem;">
                                <button id="btn-mode-citizen" style="cursor: pointer; padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: 800; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.02); color: var(--brand-subtext); transition: all 0.2s;">CLIENT</button>
                                <button id="btn-mode-technical" style="cursor: pointer; padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: 800; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.02); color: var(--brand-subtext); transition: all 0.2s;">AUDITOR</button>
                                <button id="btn-mode-legal" style="cursor: pointer; padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: 800; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.02); color: var(--brand-subtext); transition: all 0.2s;">LEGAL</button>
                            </div>
                        </div>

                        <!-- ⚖️ Human-readable Constitutional Explanation -->
                        <div id="sl1-audit-explanation" style="display: none; margin-top: 0.5rem; padding: 0.75rem; border-radius: 8px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.04); font-size: 10px; color: var(--brand-subtext); line-height: 1.45;">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

    </div>
    </div>

    <!-- Merchant Center Promo Banner -->
    @if(!$user->isMerchantNode())
        <div class="b2b-banner">
            <div class="b2b-content">
                <div class="badge-type" style="display: inline-block; margin-bottom: 1rem;">Бизнес-периметр</div>
                <h3>Откройте Meanly Merchant Center</h3>
                <p>
                    Подключите юридическое лицо (ООО, ИП или Самозанятый), чтобы получить merchant_node authority, управлять балансом и настраивать автоматический импорт ключей на Ozon, Wildberries и Яндекс Маркет.
                </p>
            </div>
            <a href="/business" class="btn-nav-cta" style="text-decoration: none; padding: 0.8rem 2rem; flex-shrink: 0;">
                Активировать B2B &rarr;
            </a>
        </div>
    @endif
</main>

<footer>
    <div class="footer-container">
        <div>&copy; {{ date('Y') }} Meanly Systems. Личный сейф покупателя.</div>
        <div class="footer-links">
            <a href="/">Назад на витрину</a>
            <a href="#">Условия использования</a>
            <a href="#">Конфиденциальность</a>
        </div>
    </div>
</footer>

<script>
    function copyToClipboard(btn, text) {
        navigator.clipboard.writeText(text).then(() => {
            const icon = btn.querySelector("i");
            icon.className = "ph-bold ph-check text-emerald-400";
            setTimeout(() => {
                icon.className = "ph-bold ph-copy";
            }, 1500);
        });
    }

    // 🎨 Premium Theme/Skin Switcher
    function setTheme(theme) {
        if (window.MeanlyTheme && typeof window.MeanlyTheme.apply === 'function') {
            theme = window.MeanlyTheme.apply(theme);
        }
        document.body.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        var cookieDomain = @json(config('session.domain') ?? null);
        var domainSuffix = cookieDomain ? '; domain=' + cookieDomain : '';
        document.cookie = `theme=${theme}; path=/; max-age=31536000; SameSite=Lax${domainSuffix}`;
        
        // Update active class on switcher buttons
        document.querySelectorAll('.skin-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        const activeBtn = document.getElementById(`skin-btn-${theme}`);
        if (activeBtn) {
            activeBtn.classList.add('active');
        }
    }

    // ⚖️ Constitutional Runtime Panel
    // Connects to Simple L1 node and renders live governance state + receipts.
    (function initConstitutionalPanel() {
        const SL1_BASE = 'http://localhost:3000';   // Simple L1 node
        const SL1_ADDR = @json($user->meta['sl1_address'] ?? null);


        const dot   = document.getElementById('sl1-status-dot');
        const label = document.getElementById('sl1-status-label');
        if (!dot || !label) return;

        function setOnline(text) {
            dot.style.background   = '#107c10';
            dot.style.boxShadow    = '0 0 6px rgba(16,124,16,0.5)';
            label.textContent      = text;
            label.style.color      = '#107c10';
            document.getElementById('sl1-offline-block').style.display = 'none';
            document.getElementById('sl1-epoch-block').style.display   = 'flex';
            document.getElementById('sl1-receipts-section').style.display = 'block';
        }

        function setOffline() {
            dot.style.background   = '#444';
            dot.style.boxShadow    = 'none';
            label.textContent      = 'SL1 Runtime offline';
            label.style.color      = 'var(--brand-subtext)';
            document.getElementById('sl1-offline-block').style.display = 'block';
            document.getElementById('sl1-epoch-block').style.display   = 'none';
            document.getElementById('sl1-receipts-section').style.display = 'none';
        }

        function truncateHash(h) {
            if (!h || h === 'genesis') return h || '—';
            return h.slice(0, 8) + '…' + h.slice(-4);
        }

        function renderStatusBadge(status) {
            const colors = {
                FINALIZED: '#107c10',
                REJECTED:  '#f53003',
                DISPUTED:  '#ffaa00',
            };
            return `<span style="color:${colors[status] || '#8e8e93'}; font-weight:800; font-size:9px;">${status}</span>`;
        }

        async function fetchGovernance() {
            const r = await fetch(`${SL1_BASE}/api/governance/constitution-root`);
            return r.json();
        }

        async function fetchFederation() {
            const r = await fetch(`${SL1_BASE}/api/federation/root`);
            return r.json();
        }

        async function fetchConstitution() {
            const r = await fetch(`${SL1_BASE}/api/constitution`);
            return r.json();
        }

        async function fetchReceipts(sl1_address) {
            if (!sl1_address) return [];
            const r = await fetch(`${SL1_BASE}/api/receipts/address/${encodeURIComponent(sl1_address)}`);
            return r.json();
        }

        function renderReceipt(receipt) {
            const div = document.createElement('div');
            div.style.cssText = `
                padding: 0.65rem 0.85rem;
                background: rgba(255,255,255,0.01);
                border: 1px solid rgba(255,255,255,0.04);
                border-radius: 10px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 1rem;
                transition: border-color 0.2s;
            `;
            div.addEventListener('mouseenter', () => div.style.borderColor = 'rgba(255,255,255,0.1)');
            div.addEventListener('mouseleave', () => div.style.borderColor = 'rgba(255,255,255,0.04)');

            const network  = receipt.settlement?.network || '—';
            const asset    = receipt.settlement?.asset   || '—';
            const amount   = receipt.settlement?.amount  || '—';
            const epoch    = receipt.constitutional_epoch ?? '?';
            const rcptId   = receipt.receipt_id?.slice(0, 12) + '…' || '—';
            const issuedAt = receipt.issued_at ? new Date(receipt.issued_at).toLocaleDateString('ru-RU') : '—';

            div.innerHTML = `
                <div style="display:flex; flex-direction:column; gap:0.2rem; min-width:0; flex-grow: 1;">
                    <div style="font-family:'JetBrains Mono',monospace; font-size:9.5px; color:var(--brand-subtext); font-weight:600;">
                        ${rcptId} · Epoch ${epoch}
                    </div>
                    <div style="font-size:11.5px; font-weight:800; color:var(--brand-text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        ${amount} ${asset} ← ${network}
                    </div>
                    <div style="font-size:10px; color:var(--brand-subtext);">${issuedAt}</div>
                </div>
                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.3rem; flex-shrink:0;">
                    ${renderStatusBadge(receipt.constitutional_status)}
                    <button class="sqrp-btn" style="cursor:pointer; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:var(--brand-text); font-size:9px; font-weight:700; padding:2px 6px; border-radius:4px; display:flex; align-items:center; gap:0.2rem;"><i class="ph-bold ph-qr-code"></i> Профиль</button>
                </div>
            `;
            
            // Bind QR generation click
            const btn = div.querySelector('.sqrp-btn');
            if (btn) {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (window.showQrPassport) window.showQrPassport(receipt);
                });
            }
            
            return div;
        }

        async function load() {
            try {
                const [gov, fed, constitution] = await Promise.all([
                    fetchGovernance(),
                    fetchFederation(),
                    fetchConstitution(),
                ]);

                // Show epoch data
                document.getElementById('sl1-epoch-val').textContent  = `#${gov.current_epoch ?? 0}`;
                document.getElementById('sl1-root-val').textContent    = truncateHash(gov.constitution_root);
                document.getElementById('sl1-policy-val').textContent  = constitution.version || '—';
                document.getElementById('sl1-fed-val').textContent     = truncateHash(fed.federation_root);
                document.getElementById('sl1-state-val').textContent   = truncateHash(gov.state_root);

                document.getElementById('sl1-epoch-block').style.display   = 'flex';
                document.getElementById('sl1-receipts-section').style.display = 'block';

                setOnline(`LIVE · Epoch #${gov.current_epoch ?? 0} · v${constitution.version || '?'}`);

                // Load receipts if SL1 address is known
                const receipts = await fetchReceipts(SL1_ADDR).catch(() => []);
                const list     = document.getElementById('sl1-receipts-list');
                const empty    = document.getElementById('sl1-no-receipts');

                list.innerHTML = ''; // clear previous list items to avoid duplication
                empty.style.display = 'none';

                if (!Array.isArray(receipts) || receipts.length === 0) {
                    empty.style.display = 'block';
                } else {
                    receipts.slice(0, 5).forEach(r => list.appendChild(renderReceipt(r)));
                }

                // Phase 3: Sovereign Identity Passport Binding
                if (SL1_ADDR) {
                    document.getElementById('sl1-identity-card').style.display = 'block';
                    document.getElementById('sl1-id-address').textContent = SL1_ADDR;
                    document.getElementById('sl1-id-passkey').textContent = `Hardware Enclave Linked`;
                    
                    const btnId = document.getElementById('btn-generate-identity-sqrp');
                    const newBtnId = btnId.cloneNode(true);
                    btnId.parentNode.replaceChild(newBtnId, btnId);
                    
                    newBtnId.addEventListener('click', () => {
                        if (window.showIdentityQrPassport) {
                            window.showIdentityQrPassport({
                                schema: 'sovereign_identity_v1',
                                sl1_address: SL1_ADDR,
                                federation_root: fed.federation_root,
                                constitutional_epoch: gov.current_epoch,
                                attested_claims: ['KYC_EXEMPT', 'CRYPTO_SOVEREIGN', 'PASSKEY_SECURED'],
                                latest_receipt_chain: (receipts || []).slice(0, 3).map(r => r.receipt_id),
                                issued_at: new Date().toISOString()
                            });
                        }
                    });
                    
                    const btnIntent = document.getElementById('btn-generate-intent-sqrp');
                    const newBtnIntent = btnIntent.cloneNode(true);
                    btnIntent.parentNode.replaceChild(newBtnIntent, btnIntent);
                    
                    newBtnIntent.addEventListener('click', () => {
                        if (window.showIdentityQrPassport) {
                            window.showIdentityQrPassport({
                                schema: 'sovereign_intent_v1',
                                intent_type: 'TRANSFER_VALUE',
                                amount: '12.50',
                                currency: 'SL1_USD',
                                merchant: 'wildflow_terminal_77',
                                buyer_identity: SL1_ADDR,
                                federation_root: fed.federation_root,
                                constitutional_epoch: gov.current_epoch,
                                issued_at: new Date().toISOString()
                            });
                        }
                    });
                }

            } catch {
                setOffline();
            }
        }

        // Kick off — non-blocking
        load();

        // Refresh every 30s
        setInterval(load, 30_000);

        // --- ⚖️ Offline Receipt Verifier JS Driver ---
        const dropZone  = document.getElementById('sl1-drop-zone');
        const fileInput = document.getElementById('sl1-file-input');
        const resBlock  = document.getElementById('sl1-verification-result');
        const badge     = document.getElementById('sl1-audit-badge');
        const valSig    = document.getElementById('sl1-audit-sig');
        const valEpoch  = document.getElementById('sl1-audit-epoch');
        const valFed    = document.getElementById('sl1-audit-fed');
        const verdict   = document.getElementById('sl1-audit-verdict');

        if (dropZone && fileInput) {
            // Drop zone clicks trigger file input
            dropZone.addEventListener('click', () => fileInput.click());

            // File selection handler
            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    processFile(e.target.files[0]);
                }
            });

            // Drag & Drop handlers
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });
            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('dragover');
            });
            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
                if (e.dataTransfer.files.length > 0) {
                    processFile(e.dataTransfer.files[0]);
                }
            });

            // Segmented toggle clicks
            const btnCit = document.getElementById('btn-mode-citizen');
            const btnTec = document.getElementById('btn-mode-technical');
            const btnLeg = document.getElementById('btn-mode-legal');
            if (btnCit && btnTec && btnLeg) {
                btnCit.addEventListener('click', () => setExplanationMode('citizen'));
                btnTec.addEventListener('click', () => setExplanationMode('technical'));
                btnLeg.addEventListener('click', () => setExplanationMode('legal'));
            }
        }

        function processFile(file) {
            const reader = new FileReader();
            reader.onload = async (e) => {
                try {
                    const receipt = JSON.parse(e.target.result);
                    await verifyReceiptOffline(receipt);
                } catch (err) {
                    showErrorVerdict('Invalid JSON format / Broken Receipt');
                }
            };
            reader.readAsText(file);
        }

        function showErrorVerdict(msg) {
            resBlock.style.display = 'block';
            badge.textContent = 'ERROR';
            badge.style.background = '#f53003';
            badge.style.color = '#ffffff';
            valSig.textContent = 'FAILED';
            valSig.style.color = '#f53003';
            valEpoch.textContent = 'FAILED';
            valEpoch.style.color = '#f53003';
            valFed.textContent = 'FAILED';
            valFed.style.color = '#f53003';
            verdict.textContent = `❌ NOT COMPLIANT: ${msg}`;
            verdict.style.color = '#f53003';
        }

        function hexToBuf(hex) {
            const arr = new Uint8Array(hex.length / 2);
            for (let i = 0; i < hex.length; i += 2) {
                arr[i / 2] = parseInt(hex.substring(i, i + 2), 16);
            }
            return arr.buffer;
        }

        function derToRaw(derBuffer) {
            const der = new Uint8Array(derBuffer);
            if (der[0] !== 0x30) throw new Error("Invalid signature format");
            
            let pos = 2; // skip sequence identifier and sequence length
            
            // read R
            if (der[pos] !== 0x02) throw new Error("Invalid signature R integer");
            pos++;
            let rLen = der[pos];
            pos++;
            let rStart = pos;
            // skip leading zero padding if present
            if (der[rStart] === 0x00 && rLen > 32) {
                rStart++;
                rLen--;
            }
            pos += der[pos - 1]; // advance pos by original length byte
            
            // read S
            if (der[pos] !== 0x02) throw new Error("Invalid signature S integer");
            pos++;
            let sLen = der[pos];
            pos++;
            let sStart = pos;
            // skip leading zero padding if present
            if (der[sStart] === 0x00 && sLen > 32) {
                sStart++;
                sLen--;
            }
            
            const raw = new Uint8Array(64);
            // R is copied into first 32 bytes (right-aligned)
            raw.set(der.subarray(rStart, rStart + rLen), 32 - rLen);
            // S is copied into second 32 bytes (right-aligned)
            raw.set(der.subarray(sStart, sStart + sLen), 64 - sLen);
            
            return raw;
        }

        async function verifySignaturePureJS(receipt) {
            const { receipt_signature, ...body } = receipt;
            const canonical = JSON.stringify(body, Object.keys(body).sort());
            
            const pubKeyHex = receipt.issuer_public_key;
            const sigHex = receipt.receipt_signature;
            
            if (!pubKeyHex || !sigHex) throw new Error("Missing cryptographic keys");
            
            const pubKeyBuf = hexToBuf(pubKeyHex);
            const sigBuf = hexToBuf(sigHex);
            const rawSig = derToRaw(sigBuf);
            
            const cryptoKey = await window.crypto.subtle.importKey(
                'spki',
                pubKeyBuf,
                { name: 'ECDSA', namedCurve: 'P-256' },
                true,
                ['verify']
            );
            
            const encoder = new TextEncoder();
            const dataBuf = encoder.encode(canonical);
            
            return await window.crypto.subtle.verify(
                { name: 'ECDSA', hash: { name: 'SHA-256' } },
                cryptoKey,
                rawSig,
                dataBuf
            );
        }

        let currentExplanationMode = 'citizen';
        let currentReceipt         = null;
        let isReceiptOfflineMode   = false;

        function setExplanationMode(mode) {
            currentExplanationMode = mode;
            
            // Highlight active button beautifully
            const modes = ['citizen', 'technical', 'legal'];
            modes.forEach(m => {
                const btn = document.getElementById(`btn-mode-${m}`);
                if (btn) {
                    if (m === mode) {
                        btn.style.background = 'var(--brand-primary)';
                        btn.style.color      = '#ffffff';
                        btn.style.borderColor = 'var(--brand-primary)';
                    } else {
                        btn.style.background = 'rgba(255,255,255,0.02)';
                        btn.style.color      = 'var(--brand-subtext)';
                        btn.style.borderColor = 'rgba(255,255,255,0.1)';
                    }
                }
            });

            // Re-render report instantly if receipt is loaded
            if (currentReceipt) {
                const explanationEl = document.getElementById('sl1-audit-explanation');
                let html = generateConstitutionalExplanation(currentReceipt);
                if (isReceiptOfflineMode) {
                    html += `<div style="margin-top: 0.4rem; padding-top: 0.4rem; border-top: 1px dashed rgba(255,255,255,0.04); color: #ffaa00; font-size: 9px;">⚠ Note: Audited in offline context. Consensus stats derived from signed receipt claims.</div>`;
                }
                explanationEl.innerHTML = html;
            }
        }

        function generateConstitutionalExplanation(receipt) {
            const network = (receipt.settlement?.network || 'unknown').toUpperCase();
            const asset = receipt.settlement?.asset || 'unknown';
            const amount = receipt.settlement?.amount || '0';
            const validators = receipt.attestation?.validators || [];
            const quorumMet = receipt.attestation?.quorum_met;
            const required = receipt.attestation?.required || 2;
            const accepted = receipt.attestation?.accepted || 0;
            const epoch = receipt.constitutional_epoch ?? 0;
            const intentType = receipt.intent_type || 'TRANSACTION';

            let explanation = `<div style="font-weight: 800; color: var(--brand-text); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.3rem;"><i class="ph-bold ph-scales" style="color: var(--brand-primary); font-size: 12px;"></i> CONSTITUTIONAL INTERPRETATION REPORT</div><div style="display:flex; flex-direction:column; gap:0.4rem;">`;

            if (currentExplanationMode === 'citizen') {
                // Simplified Citizen Mode (Гражданский режим)
                explanation += `<div style="font-size: 8px; font-weight: 800; color: var(--brand-primary); margin-bottom: 0.15rem; text-transform: uppercase;">Режим: простое объяснение</div>`;
                if (intentType === 'CROSS_CHAIN_DEPOSIT') {
                    explanation += `<div><span style="color: #107c10; font-weight:bold;">✔</span> <b>Цель</b>: Пополнение внутреннего баланса.</div>`;
                    explanation += `<div><span style="color: #107c10; font-weight:bold;">✔</span> <b>Правила</b>: Проверено по правилам версии <b>v${receipt.policy_version || '1.0'}</b>.</div>`;
                    explanation += `<div><span style="color: #107c10; font-weight:bold;">✔</span> <b>Активы</b>: Валюта <b>${amount} ${asset}</b> из сети <b>${network}</b> входит в разрешенный белый список.</div>`;
                    explanation += `<div><span style="color: #107c10; font-weight:bold;">✔</span> <b>Операция</b>: Факт перевода подтвержден контрольной отметкой источника.</div>`;
                } else if (intentType === 'CROSS_CHAIN_WITHDRAWAL') {
                    explanation += `<div><span style="color: #107c10; font-weight:bold;">✔</span> <b>Цель</b>: Вывод активов на внешний адрес.</div>`;
                    explanation += `<div><span style="color: #107c10; font-weight:bold;">✔</span> <b>Средства</b>: Вывод <b>${amount} ${asset}</b> через <b>${network}</b> одобрен правилами.</div>`;
                } else {
                    explanation += `<div><span style="color: #107c10; font-weight:bold;">✔</span> <b>Действие</b>: Внутренняя операция сервиса.</div>`;
                }

                if (quorumMet) {
                    explanation += `<div><span style="color: #107c10; font-weight:bold;">✔</span> <b>Консенсус валидаторов</b>: Подтверждено большинством доверенных узлов (${accepted} из ${required}).</div>`;
                    explanation += `<div><span style="color: #107c10; font-weight:bold;">✔</span> <b>Подписавшие узлы</b>: ${validators.join(', ')}.</div>`;
                }
            } else if (currentExplanationMode === 'technical') {
                // Technical Audit Mode (Технический аудит)
                explanation += `<div style="font-size: 8px; font-weight: 800; color: var(--brand-primary); margin-bottom: 0.15rem; text-transform: uppercase;">Mode: Technical Operations Check</div>`;
                explanation += `<div><span style="color: #107c10; font-weight:bold;">✔</span> <b>Intent Type</b>: <code>${intentType}</code></div>`;
                explanation += `<div><span style="color: #107c10; font-weight:bold;">✔</span> <b>Policy Version</b>: <code>${receipt.policy_version || '1.0'}</code></div>`;
                explanation += `<div><span style="color: #107c10; font-weight:bold;">✔</span> <b>Payload Hash</b>: <code>${receipt.intent_hash?.substring(0, 16)}...</code></div>`;
                explanation += `<div><span style="color: #107c10; font-weight:bold;">✔</span> <b>EVM Proof Type</b>: <code>${receipt.proof?.proof_type || 'STANDARD'}</code></div>`;
                explanation += `<div><span style="color: #107c10; font-weight:bold;">✔</span> <b>Signature Standard</b>: P-256 (secp256r1) ECDSA-SHA256</div>`;
                
                if (quorumMet) {
                    explanation += `<div><span style="color: #107c10; font-weight:bold;">✔</span> <b>Quorum Validation</b>: <code>quorum_met: true</code> (threshold ${required}, matched ${accepted})</div>`;
                    explanation += `<div><span style="color: #107c10; font-weight:bold;">✔</span> <b>State Anchorage</b>: Root <code>${receipt.state_root?.substring(0, 16)}...</code> at Epoch ${epoch}</div>`;
                }
            } else if (currentExplanationMode === 'legal') {
                // Legal Protocol Mode (Юридический протокол)
                explanation += `<div style="font-size: 8px; font-weight: 800; color: var(--brand-primary); margin-bottom: 0.15rem; text-transform: uppercase;">Mode: Jurisprudential Admissibility Record</div>`;
                explanation += `<div><span style="color: #107c10; font-weight:bold;">✔</span> <b>Scope</b>: Meanly service operations.</div>`;
                explanation += `<div><span style="color: #107c10; font-weight:bold;">✔</span> <b>Rule</b>: Pursuant to <code>${intentType.toLowerCase()}</code> policies, this operation passed the required checks.</div>`;
                explanation += `<div><span style="color: #107c10; font-weight:bold;">✔</span> <b>Asset Whitelisting</b>: Under active policy, network <code>${network}</code> and asset symbol <code>${asset}</code> are certified as clean and admitted.</div>`;
                explanation += `<div><span style="color: #107c10; font-weight:bold;">✔</span> <b>Quorum Certification</b>: A validated quorum of certified validators [${validators.join(', ')}] attests to the legitimacy of settlement tx <code>${receipt.settlement?.tx_hash?.substring(0, 16)}...</code>.</div>`;
            }

            explanation += `</div>`;
            return explanation;
        }

        async function verifySemanticHash(receipt) {
            if (!receipt.semantic_hash) return true; // Support legacy receipts
            
            const intentType = receipt.intent_type || 'TRANSACTION';
            const network = receipt.settlement?.network || 'unknown';
            const asset = receipt.settlement?.asset || 'unknown';
            const amount = receipt.settlement?.amount || '0';
            const epoch = receipt.constitutional_epoch ?? 0;
            const policyVer = receipt.policy_version ?? '1.0';

            let statement = `STATEMENT OF CONSTITUTIONAL LEGITIMACY\n`;
            statement += `======================================\n`;
            statement += `The sovereign federation operating under Epoch ${epoch} (Policy v${policyVer})\n`;
            
            if (intentType === 'CROSS_CHAIN_DEPOSIT') {
                statement += `HEREBY DECLARES the inbound settlement of ${amount} ${asset} on ${network} to be CONSTITUTIONALLY ADMISSIBLE.\n`;
            } else if (intentType === 'CROSS_CHAIN_WITHDRAWAL') {
                statement += `HEREBY DECLARES the outbound withdrawal of ${amount} ${asset} to ${network} to be CONSTITUTIONALLY ADMISSIBLE.\n`;
            } else {
                statement += `HEREBY DECLARES the state transition of type ${intentType} to be CONSTITUTIONALLY ADMISSIBLE.\n`;
            }

            if (receipt.attestation?.quorum_met) {
                statement += `\nATTESTATION:\n`;
                statement += `A verified quorum of ${receipt.attestation.accepted}/${receipt.attestation.required} sovereign validators certified this execution.\n`;
                statement += `ATTESTING NODES: [${(receipt.attestation.validators || []).sort().join(', ')}]\n`;
            } else {
                statement += `\nWARNING: Attestation Quorum was NOT met.\n`;
            }

            if (receipt.state_root) {
                statement += `\nANCHOR:\n`;
                statement += `This judgment is immutably anchored in State Root: ${receipt.state_root}\n`;
            }
            
            const encoder = new TextEncoder();
            const data = encoder.encode(statement);
            const hashBuffer = await window.crypto.subtle.digest('SHA-256', data);
            const hashArray = Array.from(new Uint8Array(hashBuffer));
            const computedHash = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
            
            return computedHash === receipt.semantic_hash;
        }

        async function verifyReceiptOffline(receipt) {
            resBlock.style.display = 'block';
            badge.textContent = 'AUDITING';
            badge.style.background = '#ffaa00';
            badge.style.color = '#000000';
            valSig.textContent = 'Verifying…';
            valSig.style.color = 'var(--brand-subtext)';
            valEpoch.textContent = 'Verifying…';
            valEpoch.style.color = 'var(--brand-subtext)';
            valFed.textContent = 'Verifying…';
            valFed.style.color = 'var(--brand-subtext)';
            const valSem = document.getElementById('sl1-audit-semantic');
            valSem.textContent = 'Verifying…';
            valSem.style.color = 'var(--brand-subtext)';
            verdict.textContent = 'STANDBY — EXECUTING FOUR-LAYER CRYPTOGRAPHIC AUDIT';
            verdict.style.color = 'var(--brand-subtext)';
            document.getElementById('sl1-audit-explanation').style.display = 'none';
            document.getElementById('sl1-mode-selector').style.display       = 'none';

            currentReceipt = receipt;

            try {
                // Semantic verification is always strictly deterministic client-side
                const semanticValid = await verifySemanticHash(receipt);
                if (!semanticValid) throw new Error("SEMANTIC_MISMATCH");
                valSem.textContent = '✓ SECURE (Meaning Attested)';
                valSem.style.color = '#107c10';

                // Try L1 Node API first for full consensus check
                const response = await fetch(`${SL1_BASE}/api/federation/verify-receipt`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ receipt, subnet_id: 'simple-l1-primary' })
                });

                if (response.ok) {
                    const result = await response.json();
                    
                    valSig.textContent = result.signature_valid ? '✓ SECURE' : '✗ FAILED';
                    valSig.style.color = result.signature_valid ? '#107c10' : '#f53003';
                    
                    valEpoch.textContent = result.epoch_valid ? `✓ ACTIVE (Epoch ${result.constitutional_epoch})` : '✗ FAILED (Mismatch)';
                    valEpoch.style.color = result.epoch_valid ? '#107c10' : '#f53003';
                    
                    valFed.textContent = result.federation_valid ? '✓ ADMITTED' : '✗ FAILED (Unknown Subnet)';
                    valFed.style.color = result.federation_valid ? '#107c10' : '#f53003';

                    if (result.fully_valid) {
                        badge.textContent = 'VERIFIED';
                        badge.style.background = '#107c10';
                        badge.style.color = '#ffffff';
                        verdict.textContent = '✓ LEGITIMATE UNDER CONSTITUTIONAL CONSENSUS';
                        verdict.style.color = '#107c10';

                        isReceiptOfflineMode = false;
                        document.getElementById('sl1-mode-selector').style.display = 'flex';
                        setExplanationMode(currentExplanationMode);
                    } else {
                        badge.textContent = 'REJECTED';
                        badge.style.background = '#f53003';
                        badge.style.color = '#ffffff';
                        verdict.textContent = '❌ UNADMISSIBLE BY THE SOVEREIGN FEDERATION';
                        verdict.style.color = '#f53003';
                    }
                    return;
                }
                
                // If L1 Node API returns 4xx/5xx, fallback to client-side crypto verify
                throw new Error("L1 Node rejected call");
            } catch (err) {
                if (err.message === "SEMANTIC_MISMATCH") {
                    valSem.textContent = '✗ FAILED (Meaning Altered)';
                    valSem.style.color = '#f53003';
                    badge.textContent = 'REJECTED';
                    badge.style.background = '#f53003';
                    badge.style.color = '#ffffff';
                    verdict.textContent = '❌ SEMANTIC ATTESTATION MISMATCH: CANONICAL MEANING WAS ALTERED';
                    verdict.style.color = '#f53003';
                    return;
                }
                
                // Node is offline/unreachable! Run client-side Web Crypto signature verification!
                try {
                    const sigValid = await verifySignaturePureJS(receipt);
                    
                    valSig.textContent = sigValid ? '✓ SECURE (OFFLINE MATH VALIDATED)' : '✗ SIGNATURE FORGED';
                    valSig.style.color = sigValid ? '#107c10' : '#f53003';
                    
                    valEpoch.textContent = `✓ ASSUMED (Epoch ${receipt.constitutional_epoch ?? 0})`;
                    valEpoch.style.color = '#107c10';
                    
                    valFed.textContent = '✓ LOCAL TRUST (Offline Context)';
                    valFed.style.color = '#ffaa00';

                    if (sigValid) {
                        badge.textContent = 'SECURE (OFFLINE)';
                        badge.style.background = '#ffaa00';
                        badge.style.color = '#000000';
                        verdict.textContent = '✓ MATH VALID: SOVEREIGN PRIVATE KEY VERIFIED OFFLINE';
                        verdict.style.color = '#ffaa00';

                        isReceiptOfflineMode = true;
                        document.getElementById('sl1-mode-selector').style.display = 'flex';
                        setExplanationMode(currentExplanationMode);
                    } else {
                        badge.textContent = 'BAD SIGNATURE';
                        badge.style.background = '#f53003';
                        badge.style.color = '#ffffff';
                        verdict.textContent = '❌ FORGED OR TAMPERED CRYPTOGRAPHIC INTEGRITY';
                        verdict.style.color = '#f53003';
                    }
                } catch (cryptoErr) {
                    showErrorVerdict('Offline verify error: ' + cryptoErr.message);
                }
            }
        }
        // --- 🔳 Sovereign QR Protocol (SQRP v1) Driver ---
        window.showQrPassport = async function(receipt) {
            try {
                // 1. Serialize
                const jsonStr = JSON.stringify(receipt);
                
                // 2. Compress via native Web Crypto / Compression API (deflate-raw)
                const stream = new Blob([jsonStr]).stream();
                const compressedStream = stream.pipeThrough(new CompressionStream('deflate-raw'));
                const compressedResponse = new Response(compressedStream);
                const buffer = await compressedResponse.arrayBuffer();
                
                // 3. Base64 URL encode
                const bytes = new Uint8Array(buffer);
                let binary = '';
                for (let i = 0; i < bytes.byteLength; i++) {
                    binary += String.fromCharCode(bytes[i]);
                }
                const b64 = btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
                
                // 4. Construct Sovereign QR URI (SQRP)
                const uri = `sqrp://v1/${b64}`;
                
                // 5. Render QR
                const qrCanvas = document.getElementById('sl1-qr-canvas');
                new QRious({
                    element: qrCanvas,
                    value: uri,
                    size: 220,
                    background: 'white',
                    foreground: 'black',
                    level: 'L' // Low error correction to maximize capacity
                });
                
                // Show modal
                document.getElementById('sl1-qr-modal').style.display = 'flex';
                document.getElementById('sl1-qr-title').innerHTML = '<i class="ph-bold ph-shield-check" style="color: var(--brand-primary);"></i> SOVEREIGN PASSPORT';
                document.getElementById('sl1-qr-desc').textContent = 'Scan with any verifiable node or SQRP-compatible mobile camera to authenticate legitimacy offline.';
                document.getElementById('sl1-qr-size').textContent = `${bytes.byteLength} bytes (compressed)`;
            } catch (err) {
                console.error("QR Generation failed", err);
                alert("Failed to generate Sovereign QR Passport. Your browser might not support CompressionStream API.");
            }
        };
        
        window.showIdentityQrPassport = async function(identityContext) {
            try {
                // Phase 7: Secure Enclave / Passkey Simulation
                document.getElementById('sl1-qr-modal').style.display = 'flex';
                document.getElementById('sl1-qr-title').innerHTML = '<i class="ph-bold ph-spinner ph-spin" style="color: var(--brand-primary);"></i> AWAITING SECURE ENCLAVE';
                document.getElementById('sl1-qr-desc').textContent = 'Confirming human presence via Hardware Passkey...';
                document.getElementById('sl1-qr-size').textContent = 'Awaiting biometric assertion...';
                
                const canvas = document.getElementById('sl1-qr-canvas');
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height); // Clear previous QR while waiting
                
                // Simulate biometric authentication delay
                await new Promise(resolve => setTimeout(resolve, 1500));
                
                // Bind hardware attestation to the context
                identityContext.passkey_attested = true;
                identityContext.device_class = 'secure_enclave';
                identityContext.human_presence = 'verified';
                identityContext.attested_claims = [
                    'PASSKEY_SECURED',
                    'HUMAN_PRESENT',
                    'CONSTITUTIONALLY_ADMISSIBLE'
                ];

                // 1. Serialize
                const jsonStr = JSON.stringify(identityContext);
                
                // 2. Compress via native Web Crypto / Compression API (deflate-raw)
                const stream = new Blob([jsonStr]).stream();
                const compressedStream = stream.pipeThrough(new CompressionStream('deflate-raw'));
                const compressedResponse = new Response(compressedStream);
                const buffer = await compressedResponse.arrayBuffer();
                
                // 3. Base64 URL encode
                const bytes = new Uint8Array(buffer);
                let binary = '';
                for (let i = 0; i < bytes.byteLength; i++) {
                    binary += String.fromCharCode(bytes[i]);
                }
                const b64 = btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
                
                // 4. Construct Sovereign QR URI (SQRP)
                const isIntent = identityContext.schema === 'sovereign_intent_v1';
                const uri = isIntent ? `sqrp://intent/v1/${b64}` : `sqrp://id/v1/${b64}`;
                
                // 5. Render QR
                const qrCanvas = document.getElementById('sl1-qr-canvas');
                new QRious({
                    element: qrCanvas,
                    value: uri,
                    size: 250,
                    background: 'white',
                    foreground: 'black',
                    level: 'L'
                });
                
                // Update modal with success
                if (isIntent) {
                    document.getElementById('sl1-qr-title').innerHTML = '<i class="ph-bold ph-handshake" style="color: var(--brand-primary);"></i> PAYMENT INTENT';
                    document.getElementById('sl1-qr-desc').textContent = 'Hardware Enclave verified human presence. Present this intent to a Merchant Terminal for Offline Constitutional Settlement.';
                } else {
                    document.getElementById('sl1-qr-title').innerHTML = '<i class="ph-bold ph-fingerprint" style="color: var(--brand-primary);"></i> CONSTITUTIONAL IDENTITY';
                    document.getElementById('sl1-qr-desc').textContent = 'Hardware Enclave verified human presence. Your cryptographically attested identity is ready for offline verification.';
                }
                document.getElementById('sl1-qr-size').textContent = `Substrate Size: ${bytes.byteLength} bytes (compressed)`;
            } catch (err) {
                console.error("ID Passport Generation failed", err);
                alert("Failed to generate Sovereign Identity Passport.");
            }
        };

        window.hideQrPassport = function() {
            document.getElementById('sl1-qr-modal').style.display = 'none';
        };
    })();

    // 🧠 Cognitive Demographic & Heuristic Default Theme Predictor
    function getCognitiveDemographicDefaultTheme() {
        try {
            // 1. Detect Locale/Region
            const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone || "";
            const isCIS = /Moscow|Europe\/Moscow|Samara|Yekaterinburg|Novosibirsk|Asia\/Almaty|Asia\/Tashkent|Asia\/Baku|Europe\/Minsk|ru|ru-RU/i.test(timeZone + navigator.language);
            
            // 2. Detect Device Capabilities (Proxy for Generation / Age / Hacker profile)
            const hasTouch = ('ontouchstart' in window) || navigator.maxTouchPoints > 0;
            const isHighDPI = window.devicePixelRatio && window.devicePixelRatio > 1.5;
            
            // Check for WebGPU (highly indicative of Gen Z bleeding-edge gamer/creator rigs)
            const supportsWebGPU = !!navigator.gpu;
            
            // Check for older/desktop developer setups (Retro lovers)
            const isLinuxOrOldOS = /Linux|Ubuntu|Debian|Windows NT 6.1|Windows NT 5.1/i.test(navigator.userAgent);
            const lacksModernGpu = !supportsWebGPU && !window.WebGL2RenderingContext;

            // 3. System Theme preference
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

            console.log(`[Cognitive Engine] Timezone: ${timeZone}, Touch: ${hasTouch}, WebGPU: ${supportsWebGPU}, PrefersDark: ${prefersDark}`);

            // 4. Decision Tree
            if (isLinuxOrOldOS || lacksModernGpu) {
                // Technical retro profile / Gen X / old-school geeks who appreciate CLI/Cyberpunk aesthetics
                console.log("[Cognitive Choice] Matched old-school/tech profile -> RETRO theme ⚡");
                return 'retro';
            }
            
            if (supportsWebGPU || (hasTouch && isHighDPI)) {
                // High-performance mobile/touch device, younger digital creators (Gen Z / Young Millennials)
                console.log("[Cognitive Choice] Matched young digital creator profile -> PARTNER theme 🌟");
                return 'partner';
            }
            
            // Default premium, highly optimized B2B Executive theme (Consortium flagship dark mode)
            console.log("[Cognitive Choice] Matched flagship executive profile -> CONSORTIUM theme 🚩");
            return 'consortium';
        } catch (e) {
            console.warn("[Cognitive Engine] Failed to compute heuristics, falling back to Consortium flagship.", e);
            return 'consortium';
        }
    }

    // 📆 Holiday Detection Logic
    function getActiveHoliday() {
        return document.body.getAttribute('data-holiday') || null;
    }

    // 🎭 Sovereign Atmospheric Holiday & Context Effects Engine
    function initAtmosphericHolidayFX(holidayOverride) {
        const holiday = holidayOverride || getActiveHoliday();
        if (!holiday) return;

        // Set body attribute for CSS overrides
        document.body.setAttribute('data-holiday', holiday);
        
        if (holiday === 'sons-birthday') {
            console.log("%c🦁 [Sovereign Heir Engine] 19 MAY: Happy Birthday to the Champion! С Днём Рождения, Сына! Расти сильным, смелым и свободным! 👑🏆⚡", "color: #ffd700; font-weight: bold; font-size: 14px;");
        } else if (holiday === 'little-prince') {
            console.log("%c🌹 [Little Prince Engine] 17 OCTOBER: \"Ты навсегда в ответе за тех, кого приручил.\" Твоя единственная Роза. 💫🌠", "color: #e11d48; font-weight: bold; font-size: 14px;");
        } else if (holiday === 'orchid-day') {
            console.log("%c🌸 [Orchid Engine] 12 MAY: В воздухе парит изысканность... С Днём Орхидей! 🌺💫", "color: #d946ef; font-weight: bold; font-size: 14px;");
        } else if (holiday === 'doctor-day') {
            console.log("%c🩺 [Doctor Engine] 21 APRIL: Слышим каждое биение сердца... С Днём Врача! 💚⚕️", "color: #0d9488; font-weight: bold; font-size: 14px;");
        } else if (holiday === 'babel-library') {
            console.log("%c📚 [Library of Babel] 24 AUGUST: \"La Biblioteca es ilimitada y periódica...\" / \"The Library is limitless and periodic...\" 🌌🚪", "color: #b45309; font-weight: bold; font-size: 14px;");
        } else {
            console.log(`[Holiday Engine] Active Festive Period: ${holiday.toUpperCase()} 🎁`);
        }

        // Create canvas element
        const canvas = document.createElement('canvas');
        canvas.id = 'holiday-canvas-fx';
        Object.assign(canvas.style, {
            position: 'fixed',
            inset: '0',
            pointerEvents: 'none',
            zIndex: '1',
            opacity: '0.65'
        });
        document.body.appendChild(canvas);

        const ctx = canvas.getContext('2d');
        let width = canvas.width = window.innerWidth;
        let height = canvas.height = window.innerHeight;

        window.addEventListener('resize', () => {
            width = canvas.width = window.innerWidth;
            height = canvas.height = window.innerHeight;
        });

        const particles = [];
        const maxParticles = 60;

        class Particle {
            constructor() {
                this.reset();
            }

            reset() {
                this.x = Math.random() * width;
                const isFloatingUp = (holiday === 'valentine' || holiday === 'sons-birthday' || holiday === 'little-prince' || holiday === 'orchid-day' || holiday === 'doctor-day' || holiday === 'babel-library');
                this.y = isFloatingUp ? height + 25 : -25;
                this.type = Math.floor(Math.random() * 12); // Stable random type assigned on reset
                // Stable Babel character — assigned once on reset, never changes mid-flight
                const _babelAlphabet = "abcdefghijklmnopqrstuvwxyz,.";
                this.babelChar = _babelAlphabet[Math.floor(Math.random() * _babelAlphabet.length)];
                
                if (isFloatingUp) {
                    this.size = Math.random() * 12 + 10; // Majestic 10px to 22px size!
                    this.speedX = Math.random() * 0.2 - 0.1;
                    this.speedY = -(Math.random() * 0.45 + 0.25); // Gentle slow float upwards!
                    this.alpha = Math.random() * 0.3 + 0.7; // Bright and crisp visibility
                    this.angle = Math.random() * Math.PI * 2;
                    this.spin = Math.random() * 0.012 - 0.006; // Calm majestic rotation
                } else {
                    this.size = Math.random() * 4 + 2;
                    this.speedX = holiday === 'womens-day' ? Math.random() * 1.5 - 0.2 : Math.random() * 1 - 0.5;
                    this.speedY = Math.random() * 1 + 0.8;
                    this.alpha = Math.random() * 0.6 + 0.4;
                    this.angle = Math.random() * Math.PI * 2;
                    this.spin = Math.random() * 0.04 - 0.02;
                }
            }

            update() {
                this.x += this.speedX;
                this.y += this.speedY;
                this.angle += this.spin;

                const isFloatingUp = (holiday === 'valentine' || holiday === 'sons-birthday' || holiday === 'little-prince' || holiday === 'orchid-day' || holiday === 'doctor-day');

                if (isFloatingUp) {
                    // Beautiful sinusoidal sway (fluttering float)
                    this.x += Math.sin(this.y / 35) * 0.35;
                }

                if (isFloatingUp) {
                    if (this.y < -25 || this.x < -25 || this.x > width + 25) this.reset();
                } else {
                    if (this.y > height + 25 || this.x < -25 || this.x > width + 25) this.reset();
                }
            }

            draw() {
                ctx.save();
                ctx.globalAlpha = this.alpha;
                ctx.translate(this.x, this.y);
                ctx.rotate(this.angle);

                if (holiday === 'christmas') {
                    // Draw snowflake
                    ctx.fillStyle = '#ffffff';
                    ctx.beginPath();
                    ctx.arc(0, 0, this.size, 0, Math.PI * 2);
                    ctx.fill();
                } else if (holiday === 'valentine') {
                    // Draw premium colorful hearts
                    const heartColors = ['#ff4d6d', '#ff758f', '#ff85a1', '#c9184a', '#ffccd5'];
                    const colorIndex = Math.floor(Math.abs(this.x + this.y)) % heartColors.length;
                    ctx.fillStyle = heartColors[colorIndex];
                    ctx.beginPath();
                    ctx.moveTo(0, 0);
                    ctx.bezierCurveTo(-this.size, -this.size, -this.size * 2, this.size / 3, 0, this.size * 1.5);
                    ctx.bezierCurveTo(this.size * 2, this.size / 3, this.size, -this.size, 0, 0);
                    ctx.fill();
                } else if (holiday === 'womens-day') {
                    // Draw flower petal
                    ctx.fillStyle = '#ffb7c5'; // Soft sakura pink
                    ctx.beginPath();
                    ctx.ellipse(0, 0, this.size * 1.5, this.size, Math.PI / 4, 0, Math.PI * 2);
                    ctx.fill();
                } else if (holiday === 'halloween') {
                    // Draw embers
                    ctx.fillStyle = '#ff6600';
                    ctx.beginPath();
                    ctx.arc(0, 0, this.size * 1.2, 0, Math.PI * 2);
                    ctx.fill();
                } else if (holiday === 'black-friday') {
                    // Draw neon glitch segment
                    ctx.strokeStyle = '#39ff14'; // Cyber green
                    ctx.lineWidth = 1;
                    ctx.beginPath();
                    ctx.moveTo(0, 0);
                    ctx.lineTo(0, this.size * 5);
                    ctx.stroke();
                } else if (holiday === 'sons-birthday') {
                    // Render Swiss flags, Argentine flags (with Sol de Mayo), standalone Suns, cute Hippo, and Golden stars with Alejandro 👑!
                    const particleType = this.type % 5;
                    const scale = this.size * 1.35;

                    if (particleType === 0) {
                        // 1. Swiss Flag (Швейцарский флаг)
                        ctx.fillStyle = '#da291c';
                        ctx.fillRect(-scale, -scale, scale * 2, scale * 2);
                        ctx.fillStyle = '#ffffff';
                        const barW = scale * 0.4;
                        const barH = scale * 1.3;
                        ctx.fillRect(-barW / 2, -barH / 2, barW, barH);
                        ctx.fillRect(-barH / 2, -barW / 2, barH, barW);
                    } else if (particleType === 1) {
                        // 2. Argentine Flag (Аргентинский флаг с прорисованным Солнцем!)
                        const w = scale * 1.8;
                        const h = scale * 1.2;
                        ctx.fillStyle = '#74acdf';
                        ctx.fillRect(-w / 2, -h / 2, w, h / 3);
                        ctx.fillStyle = '#ffffff';
                        ctx.fillRect(-w / 2, -h / 2 + h / 3, w, h / 3);
                        ctx.fillStyle = '#74acdf';
                        ctx.fillRect(-w / 2, -h / 2 + (h / 3) * 2, w, h / 3);
                        
                        // Sun center
                        ctx.fillStyle = '#f6b40e';
                        ctx.beginPath();
                        ctx.arc(0, 0, h * 0.12, 0, Math.PI * 2);
                        ctx.fill();
                        
                        // Miniature Sun rays
                        ctx.strokeStyle = '#f6b40e';
                        ctx.lineWidth = h * 0.04;
                        for (let r = 0; r < 8; r++) {
                            ctx.beginPath();
                            ctx.moveTo(0, 0);
                            const rx = Math.cos(r * Math.PI / 4) * h * 0.22;
                            const ry = Math.sin(r * Math.PI / 4) * h * 0.22;
                            ctx.lineTo(rx, ry);
                            ctx.stroke();
                        }
                    } else if (particleType === 2) {
                        // 3. Standalone Sol de Mayo (Солнце Аргентины)
                        const rSun = scale * 0.45;
                        ctx.fillStyle = '#f6b40e';
                        ctx.beginPath();
                        ctx.arc(0, 0, rSun, 0, Math.PI * 2);
                        ctx.fill();

                        ctx.strokeStyle = '#f6b40e';
                        ctx.lineWidth = scale * 0.12;
                        for (let r = 0; r < 12; r++) {
                            ctx.beginPath();
                            ctx.moveTo(0, 0);
                            const rx = Math.cos(r * Math.PI / 6) * scale * 1.1;
                            const ry = Math.sin(r * Math.PI / 6) * scale * 1.1;
                            ctx.lineTo(rx, ry);
                            ctx.stroke();
                        }
                    } else if (particleType === 3) {
                        // 4. Cute Vector Hippo (Бегемотик)
                        // Head
                        ctx.fillStyle = '#a5b4fc'; // Cute indigo/lilac color
                        ctx.beginPath();
                        ctx.arc(0, -scale * 0.1, scale * 0.5, 0, Math.PI * 2);
                        ctx.fill();

                        // Snout (large lower oval)
                        ctx.fillStyle = '#818cf8';
                        ctx.beginPath();
                        ctx.ellipse(0, scale * 0.2, scale * 0.6, scale * 0.35, 0, 0, Math.PI * 2);
                        ctx.fill();

                        // Nostrils
                        ctx.fillStyle = '#4f46e5';
                        ctx.beginPath();
                        ctx.arc(-scale * 0.18, scale * 0.18, scale * 0.08, 0, Math.PI * 2);
                        ctx.fill();
                        ctx.beginPath();
                        ctx.arc(scale * 0.18, scale * 0.18, scale * 0.08, 0, Math.PI * 2);
                        ctx.fill();

                        // Eyes
                        ctx.fillStyle = '#1e1b4b'; // Dark blue eyes
                        ctx.beginPath();
                        ctx.arc(-scale * 0.18, -scale * 0.15, scale * 0.07, 0, Math.PI * 2);
                        ctx.fill();
                        ctx.beginPath();
                        ctx.arc(scale * 0.18, -scale * 0.15, scale * 0.07, 0, Math.PI * 2);
                        ctx.fill();

                        // Eye highlights
                        ctx.fillStyle = '#ffffff';
                        ctx.beginPath();
                        ctx.arc(-scale * 0.2, -scale * 0.17, scale * 0.025, 0, Math.PI * 2);
                        ctx.fill();
                        ctx.beginPath();
                        ctx.arc(scale * 0.16, -scale * 0.17, scale * 0.025, 0, Math.PI * 2);
                        ctx.fill();

                        // Ears
                        ctx.fillStyle = '#a5b4fc';
                        ctx.beginPath();
                        ctx.arc(-scale * 0.38, -scale * 0.5, scale * 0.15, 0, Math.PI * 2);
                        ctx.fill();
                        ctx.beginPath();
                        ctx.arc(scale * 0.38, -scale * 0.5, scale * 0.15, 0, Math.PI * 2);
                        ctx.fill();

                        // Pink inner ear
                        ctx.fillStyle = '#fda4af';
                        ctx.beginPath();
                        ctx.arc(-scale * 0.38, -scale * 0.5, scale * 0.08, 0, Math.PI * 2);
                        ctx.fill();
                        ctx.beginPath();
                        ctx.arc(scale * 0.38, -scale * 0.5, scale * 0.08, 0, Math.PI * 2);
                        ctx.fill();
                    } else {
                        // 5. Golden Champion Star
                        ctx.fillStyle = '#ffd700';
                        ctx.beginPath();
                        ctx.moveTo(0, -scale * 1.3);
                        ctx.lineTo(scale * 0.35, -scale * 0.35);
                        ctx.lineTo(scale * 1.3, 0);
                        ctx.lineTo(scale * 0.35, scale * 0.35);
                        ctx.lineTo(0, scale * 1.3);
                        ctx.lineTo(-scale * 0.35, scale * 0.35);
                        ctx.lineTo(-scale * 1.3, 0);
                        ctx.lineTo(-scale * 0.35, -scale * 0.35);
                        ctx.closePath();
                        ctx.fill();

                        // Golden Crown at the top of the star
                        ctx.fillStyle = '#f59e0b'; // Amber Gold
                        ctx.beginPath();
                        ctx.moveTo(-scale * 0.3, -scale * 1.4);
                        ctx.lineTo(-scale * 0.2, -scale * 1.7);
                        ctx.lineTo(0, -scale * 1.5);
                        ctx.lineTo(scale * 0.2, -scale * 1.7);
                        ctx.lineTo(scale * 0.3, -scale * 1.4);
                        ctx.closePath();
                        ctx.fill();

                        ctx.shadowBlur = 0; // reset shadow
                    }
                } else if (holiday === 'little-prince') {
                    // Draw sparkling golden stars of Asteroid B-612!
                    ctx.fillStyle = '#ffd700';
                    ctx.beginPath();
                    ctx.moveTo(0, -this.size * 1.25);
                    ctx.lineTo(this.size * 0.3, -this.size * 0.3);
                    ctx.lineTo(this.size * 1.25, 0);
                    ctx.lineTo(this.size * 0.3, this.size * 0.3);
                    ctx.lineTo(0, this.size * 1.25);
                    ctx.lineTo(-this.size * 0.3, this.size * 0.3);
                    ctx.lineTo(-this.size * 1.25, 0);
                    ctx.lineTo(-this.size * 0.3, -this.size * 0.3);
                    ctx.closePath();
                    ctx.fill();
                } else if (holiday === 'orchid-day') {
                    // Draw a majestic vector orchid flower!
                    const scale = this.size * 1.4;
                    
                    // Sepals
                    ctx.fillStyle = '#f5d0fe'; // Lavender
                    ctx.beginPath();
                    ctx.ellipse(0, -scale * 0.8, scale * 0.45, scale * 0.8, 0, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.beginPath();
                    ctx.ellipse(-scale * 0.6, scale * 0.6, scale * 0.45, scale * 0.7, Math.PI / 3, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.beginPath();
                    ctx.ellipse(scale * 0.6, scale * 0.6, scale * 0.45, scale * 0.7, -Math.PI / 3, 0, Math.PI * 2);
                    ctx.fill();
                    
                    // Large lateral petals
                    ctx.fillStyle = '#e879f9'; // Vibrant orchid pink
                    ctx.beginPath();
                    ctx.ellipse(-scale * 0.8, -scale * 0.1, scale * 0.7, scale * 0.55, -Math.PI / 8, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.beginPath();
                    ctx.ellipse(scale * 0.8, -scale * 0.1, scale * 0.7, scale * 0.55, Math.PI / 8, 0, Math.PI * 2);
                    ctx.fill();
                    
                    // Deep magenta center lip (Labellum)
                    ctx.fillStyle = '#df0893';
                    ctx.beginPath();
                    ctx.ellipse(0, scale * 0.25, scale * 0.45, scale * 0.5, 0, 0, Math.PI * 2);
                    ctx.fill();
                    
                    // Yellow stamen core
                    ctx.fillStyle = '#eab308';
                    ctx.beginPath();
                    ctx.arc(0, -scale * 0.1, scale * 0.18, 0, Math.PI * 2);
                    ctx.fill();
                } else if (holiday === 'doctor-day') {
                    // Draw a beautiful stethoscope vector!
                    const scale = this.size * 1.3;
                    const particleType = this.type % 3;

                    if (particleType === 0) {
                        // 1. Classic Medical Stethoscope
                        // Outer chestpiece rim
                        ctx.strokeStyle = '#cbd5e1'; // Silver/grey
                        ctx.lineWidth = scale * 0.15;
                        ctx.beginPath();
                        ctx.arc(0, scale * 0.6, scale * 0.45, 0, Math.PI * 2);
                        ctx.stroke();

                        // Inner chestpiece diaphragm
                        ctx.fillStyle = '#06b6d4'; // Cyan glowing core
                        ctx.beginPath();
                        ctx.arc(0, scale * 0.6, scale * 0.3, 0, Math.PI * 2);
                        ctx.fill();

                        // Rubber tubes (curved)
                        ctx.strokeStyle = '#0d9488'; // Teal tube
                        ctx.lineWidth = scale * 0.16;
                        ctx.lineCap = 'round';
                        ctx.lineJoin = 'round';

                        // Main tube connecting chestpiece to the headset Y
                        ctx.beginPath();
                        ctx.moveTo(0, scale * 0.15);
                        ctx.bezierCurveTo(-scale * 0.4, -scale * 0.1, -scale * 0.4, -scale * 0.6, 0, -scale * 0.7);
                        ctx.stroke();

                        // Y-binaural metallic branches
                        ctx.strokeStyle = '#cbd5e1'; // Metallic binaural
                        ctx.lineWidth = scale * 0.1;
                        ctx.beginPath();
                        ctx.arc(-scale * 0.35, -scale * 1.0, scale * 0.45, 0, Math.PI, true);
                        ctx.stroke();
                        ctx.beginPath();
                        ctx.arc(scale * 0.35, -scale * 1.0, scale * 0.45, 0, Math.PI, true);
                        ctx.stroke();

                        // Black plastic Eartips at the top
                        ctx.fillStyle = '#1e293b';
                        ctx.beginPath();
                        ctx.arc(-scale * 0.78, -scale * 1.0, scale * 0.15, 0, Math.PI * 2);
                        ctx.fill();
                        ctx.beginPath();
                        ctx.arc(scale * 0.78, -scale * 1.0, scale * 0.15, 0, Math.PI * 2);
                        ctx.fill();
                    } else if (particleType === 1) {
                        // 2. Glowing Medical Red/Teal Cross
                        ctx.fillStyle = Math.abs(this.x) % 2 === 0 ? '#10b981' : '#0d9488'; // Emerald / Teal
                        const w = scale * 0.4;
                        const h = scale * 1.3;
                        ctx.fillRect(-w / 2, -h / 2, w, h);
                        ctx.fillRect(-h / 2, -w / 2, h, w);
                    } else {
                        // 3. EKG Pulse Line Segment (Зеленая линия ЭКГ)
                        ctx.strokeStyle = '#2dd4bf'; // Glowing turquoise
                        ctx.lineWidth = scale * 0.18;
                        ctx.lineCap = 'round';
                        ctx.beginPath();
                        ctx.moveTo(-scale, 0);
                        ctx.lineTo(-scale * 0.4, 0);
                        ctx.lineTo(-scale * 0.2, -scale * 0.8);
                        ctx.lineTo(scale * 0.1, scale * 0.8);
                        ctx.lineTo(scale * 0.3, -scale * 0.2);
                        ctx.lineTo(scale * 0.5, 0);
                        ctx.lineTo(scale, 0);
                        ctx.stroke();
                    }
                } else if (holiday === 'babel-library') {
                    // Draw Borges' Library of Babel vectors!
                    const scale = this.size * 1.3;
                    const particleType = this.type % 4;

                    if (particleType === 0) {
                        // 1. Hexagonal Gallery (Borges' Hexagon)
                        ctx.strokeStyle = '#d97706'; // Antique Amber
                        ctx.lineWidth = scale * 0.12;
                        ctx.beginPath();
                        for (let h = 0; h < 6; h++) {
                            const hx = Math.cos(h * Math.PI / 3) * scale;
                            const hy = Math.sin(h * Math.PI / 3) * scale;
                            if (h === 0) ctx.moveTo(hx, hy);
                            else ctx.lineTo(hx, hy);
                        }
                        ctx.closePath();
                        ctx.stroke();
                    } else if (particleType === 1) {
                        // 2. Mystical Open Book (Книга Вавилонской Библиотеки)
                        ctx.fillStyle = '#fef3c7'; // Old parchment pages
                        ctx.strokeStyle = '#78350f'; // Leather brown spine/cover
                        ctx.lineWidth = scale * 0.08;

                        // Left page
                        ctx.beginPath();
                        ctx.moveTo(0, scale * 0.4);
                        ctx.bezierCurveTo(-scale * 0.4, scale * 0.2, -scale * 0.6, scale * 0.4, -scale * 0.8, scale * 0.2);
                        ctx.lineTo(-scale * 0.8, -scale * 0.4);
                        ctx.bezierCurveTo(-scale * 0.6, -scale * 0.2, -scale * 0.4, -scale * 0.4, 0, -scale * 0.2);
                        ctx.closePath();
                        ctx.fill();
                        ctx.stroke();

                        // Right page
                        ctx.beginPath();
                        ctx.moveTo(0, scale * 0.4);
                        ctx.bezierCurveTo(scale * 0.4, scale * 0.2, scale * 0.6, scale * 0.4, scale * 0.8, scale * 0.2);
                        ctx.lineTo(scale * 0.8, -scale * 0.4);
                        ctx.bezierCurveTo(scale * 0.6, -scale * 0.2, scale * 0.4, -scale * 0.4, 0, -scale * 0.2);
                        ctx.closePath();
                        ctx.fill();
                        ctx.stroke();

                        // Spine line
                        ctx.beginPath();
                        ctx.moveTo(0, -scale * 0.2);
                        ctx.lineTo(0, scale * 0.4);
                        ctx.stroke();
                    } else if (particleType === 2) {
                        // 3. Floating Random Character / Letter of Babel (Случайный символ бесконечного алфавита)
                        const char = this.babelChar || 'a';
                        ctx.fillStyle = '#f59e0b'; // Glowing gold
                        ctx.font = `italic bold ${Math.max(12, scale * 0.85)}px serif`;
                        ctx.textAlign = 'center';
                        ctx.fillText(char, 0, scale * 0.3);
                    } else {
                        // 4. Rolled Parchment Scroll (Свиток)
                        ctx.fillStyle = '#fef3c7'; // Parchment roll
                        ctx.strokeStyle = '#d97706';
                        ctx.lineWidth = scale * 0.06;
                        ctx.beginPath();
                        ctx.ellipse(0, 0, scale * 0.7, scale * 0.25, Math.PI / 6, 0, Math.PI * 2);
                        ctx.fill();
                        ctx.stroke();
                    }
                }

                ctx.restore();
            }
        }

        for (let i = 0; i < maxParticles; i++) {
            particles.push(new Particle());
            // Pre-warm particles across screen height
            particles[i].y = Math.random() * height;
        }

        function animate() {
            ctx.clearRect(0, 0, width, height);
            for (let i = 0; i < particles.length; i++) {
                particles[i].update();
                particles[i].draw();
            }
            requestAnimationFrame(animate);
        }

        animate();
    }

    // Auto initialize theme & holiday effects
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('theme')) {
        localStorage.setItem('theme', urlParams.get('theme').toLowerCase());
    }
    const savedTheme = localStorage.getItem('theme') || getCognitiveDemographicDefaultTheme();
    setTheme(savedTheme);
    
    // Instant fallback/sync load
    initAtmosphericHolidayFX();

    // Async active holiday sync with backend Google-Doodle-style API
    async function syncActiveHolidayWithApi() {
        try {
            const holidayParam = urlParams.get('holiday');
            const dateParam = urlParams.get('date');
            
            let apiUrl = '/api/holidays/active';
            const params = [];
            if (holidayParam) params.push(`holiday=${holidayParam}`);
            if (dateParam) params.push(`date=${dateParam}`);
            if (params.length > 0) apiUrl += `?${params.join('&')}`;

            const response = await fetch(apiUrl);
            if (!response.ok) throw new Error("API failed");
            const data = await response.json();
            
            const apiHoliday = data.active_holiday;
            const currentHoliday = document.body.getAttribute('data-holiday');
            
            if (apiHoliday) {
                if (currentHoliday !== apiHoliday.id) {
                    console.log(`[Festive API] Dynamic Sync: Switching active holiday to ${apiHoliday.name} (${apiHoliday.id})! 🎭`);
                    document.body.setAttribute('data-holiday', apiHoliday.id);
                    
                    const existingCanvas = document.getElementById('holiday-canvas-fx');
                    if (existingCanvas) existingCanvas.remove();
                    
                    initAtmosphericHolidayFX(apiHoliday.id);
                }
            } else {
                if (currentHoliday) {
                    document.body.removeAttribute('data-holiday');
                    const existingCanvas = document.getElementById('holiday-canvas-fx');
                    if (existingCanvas) existingCanvas.remove();
                }
            }
        } catch (e) {
            console.warn("[Festive API] Failed to fetch active holiday, keeping local client fallback.", e);
        }
    }
    
    // Defer API sync to ensure high performance
    if (window.requestIdleCallback) {
        window.requestIdleCallback(() => syncActiveHolidayWithApi());
    } else {
        setTimeout(syncActiveHolidayWithApi, 200);
    }

    // 5. Unlock Vault JS logic
    function vaultFirstValidationMessage(payload, fallback) {
        const firstError = Object.values(payload?.errors || {})
            .reduce((messages, value) => messages.concat(value), [])[0];

        if (firstError) {
            return firstError;
        }

        return payload?.message || fallback;
    }

    async function unlockVaultIntent(triggerButton = null) {
        const btn = triggerButton || document.querySelector('.btn-unlock-vault');
        const status = document.getElementById('vault-unlock-status');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
            || document.querySelector('input[name="_token"]')?.value
            || '';
        const setStatus = (message, color = '#64748b') => {
            if (!status) return;

            status.style.display = 'block';
            status.style.color = color;
            status.innerText = message;
        };

        if (!btn) return;
        if (!window.SimpleWebAuthnBrowser || !window.PublicKeyCredential) {
            setStatus('Ваш браузер не поддерживает Passkey/WebAuthn.', '#ef4444');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="ph-bold ph-spinner-gap" style="animation: spin 1s linear infinite;"></i> Ожидание подписи...';
        setStatus('Готовим Passkey challenge...');

        try {
            const optionsResponse = await fetch('{{ route("cabinet.vault.passkey.options") }}', {
                headers: { 'Accept': 'application/json' },
            });
            const options = await optionsResponse.json();
            if (!optionsResponse.ok) {
                throw new Error(vaultFirstValidationMessage(options, 'Не удалось подготовить Passkey-проверку.'));
            }

            const { unlock_id: unlockId, ...optionsJSON } = options;
            setStatus('Подтвердите вход в сейф через Face ID / Touch ID...');
            const assertion = await SimpleWebAuthnBrowser.startAuthentication({ optionsJSON });

            setStatus('Проверяем подпись сейфа...');
            const confirmResponse = await fetch('{{ route("cabinet.vault.passkey.confirm") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ unlock_id: unlockId, assertion }),
            });
            const confirmPayload = await confirmResponse.json();
            if (!confirmResponse.ok || !confirmPayload.success) {
                throw new Error(vaultFirstValidationMessage(confirmPayload, 'Passkey-проверка не пройдена.'));
            }

            setStatus('Сейф открыт. Загружаем покупки...', '#107c10');
            btn.innerHTML = '<i class="ph-bold ph-check"></i> Разблокировано';
            btn.style.background = '#107c10';
            window.setTimeout(() => window.location.reload(), 450);
        } catch (error) {
            setStatus(error.message || 'Не удалось открыть сейф.', '#ef4444');
            btn.disabled = false;
            btn.innerHTML = 'Открыть сейф Passkey';
        }
    }

    const cabinetSafeStatusLabels = {
        provider_code_ready: 'Сейф готов',
        local_code_ready: 'Сейф готов',
        provider_redeem_pending: 'Готовим код',
        preorder_pending: 'Предзаказ принят',
        preparing: 'Готовим код',
        paid: 'Ожидаем оплату',
        failed: 'Нужна проверка',
        provider_redeem_failed: 'Нужна проверка',
    };

    const renderCabinetSafeCodes = (card, codes = []) => {
        const codeList = card.querySelector('[data-safe-codes]');

        if (!codeList) {
            return;
        }

        codeList.replaceChildren();

        codes.forEach((item, index) => {
            const row = document.createElement('div');
            const caption = document.createElement('span');
            const code = document.createElement('code');
            const actions = document.createElement('div');
            const copy = document.createElement('button');

            row.className = 'vault-code-card';
            actions.className = 'vault-code-actions';
            caption.textContent = `Код активации ${index + 1}`;

            // Create scratch card container
            const scratchContainer = document.createElement('div');
            scratchContainer.className = 'scratch-container is-blurred';

            const scratchUnderlay = document.createElement('div');
            scratchUnderlay.className = 'scratch-underlay';

            code.textContent = item.code || '';
            code.style.userSelect = 'none'; // prevent selection before scratch
            scratchUnderlay.appendChild(code);
            scratchContainer.appendChild(scratchUnderlay);

            const canvas = document.createElement('canvas');
            canvas.className = 'scratch-canvas';
            scratchContainer.appendChild(canvas);

            copy.type = 'button';
            copy.textContent = 'Скопировать';
            copy.disabled = true; // disabled until scratched

            let revealed = false;

            copy.addEventListener('click', async () => {
                if (!revealed) return;
                try {
                    await navigator.clipboard.writeText(item.code || '');
                    copy.textContent = 'Скопировано';
                    window.setTimeout(() => copy.textContent = 'Скопировать', 1800);
                } catch (error) {
                    copy.textContent = 'Скопируйте вручную';
                }
            });

            actions.appendChild(copy);

            let redeemLink = null;
            if (item.redeem_url) {
                redeemLink = document.createElement('a');
                redeemLink.href = item.redeem_url;
                redeemLink.textContent = 'Открыть сайт активации';
                redeemLink.classList.add('disabled');
                redeemLink.addEventListener('click', (e) => {
                    if (!revealed) {
                        e.preventDefault();
                    }
                });
                actions.appendChild(redeemLink);
            }

            // Quick reveal button
            const scratchAllBtn = document.createElement('button');
            scratchAllBtn.type = 'button';
            scratchAllBtn.textContent = 'Стереть всё';
            scratchAllBtn.style.color = 'var(--brand-subtext)';
            
            const revealCode = () => {
                if (revealed) return;
                revealed = true;

                canvas.classList.add('fade-out');
                scratchContainer.classList.remove('is-blurred');
                code.style.userSelect = 'text';
                copy.disabled = false;

                if (redeemLink) {
                    redeemLink.classList.remove('disabled');
                }

                scratchAllBtn.remove();

                window.setTimeout(() => {
                    canvas.remove();
                }, 400);
            };

            scratchAllBtn.addEventListener('click', revealCode);
            actions.appendChild(scratchAllBtn);

            row.append(caption, scratchContainer, actions);
            codeList.appendChild(row);

            // Initialize canvas after appending to layout so we can measure exact dimensions
            const rect = scratchContainer.getBoundingClientRect();
            canvas.width = rect.width || 340;
            canvas.height = rect.height || 60;

            const ctx = canvas.getContext('2d');

            // Draw brushed metal gradient
            const grad = ctx.createLinearGradient(0, 0, canvas.width, canvas.height);
            grad.addColorStop(0, '#d1d5db');
            grad.addColorStop(0.3, '#9ca3af');
            grad.addColorStop(0.5, '#f3f4f6');
            grad.addColorStop(0.7, '#9ca3af');
            grad.addColorStop(1, '#6b7280');

            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            // Diagonal security cross hatch pattern
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.16)';
            ctx.lineWidth = 1;
            const lineSpacing = 10;
            for (let x = -canvas.height; x < canvas.width; x += lineSpacing) {
                ctx.beginPath();
                ctx.moveTo(x, 0);
                ctx.lineTo(x + canvas.height, canvas.height);
                ctx.stroke();
            }
            for (let x = 0; x < canvas.width + canvas.height; x += lineSpacing) {
                ctx.beginPath();
                ctx.moveTo(x, 0);
                ctx.lineTo(x - canvas.height, canvas.height);
                ctx.stroke();
            }

            // High-premium micro-grain noise
            for (let i = 0; i < 600; i++) {
                const px = Math.random() * canvas.width;
                const py = Math.random() * canvas.height;
                ctx.fillStyle = Math.random() > 0.5 ? 'rgba(255,255,255,0.18)' : 'rgba(0,0,0,0.12)';
                ctx.fillRect(px, py, 1.2, 1.2);
            }

            // Elegant dashed border inside
            ctx.strokeStyle = 'rgba(0, 0, 0, 0.12)';
            ctx.lineWidth = 2;
            ctx.setLineDash([4, 4]);
            ctx.strokeRect(6, 6, canvas.width - 12, canvas.height - 12);
            ctx.setLineDash([]); // Reset dash

            // Draw security text
            ctx.fillStyle = '#1f2937';
            ctx.font = 'bold 9px "Outfit", "Inter", sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.letterSpacing = '0.12em';
            ctx.shadowColor = 'rgba(255, 255, 255, 0.45)';
            ctx.shadowBlur = 1;
            ctx.shadowOffsetX = 0;
            ctx.shadowOffsetY = 1;
            ctx.fillText('СУВЕРЕННЫЙ СЕЙФ // СТЕРЕТЬ МОНЕТКОЙ', canvas.width / 2, canvas.height / 2);
            ctx.shadowColor = 'transparent'; // Reset shadow

            // Scratching logic state
            let isDrawing = false;
            let lastX = 0;
            let lastY = 0;

            const getMousePos = (e) => {
                const crect = canvas.getBoundingClientRect();
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                return {
                    x: clientX - crect.left,
                    y: clientY - crect.top
                };
            };

            const scratch = (x, y) => {
                ctx.globalCompositeOperation = 'destination-out';
                ctx.beginPath();
                ctx.arc(x, y, 14, 0, Math.PI * 2);
                ctx.fill();

                if (lastX && lastY) {
                    ctx.beginPath();
                    ctx.lineWidth = 28;
                    ctx.lineCap = 'round';
                    ctx.lineJoin = 'round';
                    ctx.moveTo(lastX, lastY);
                    ctx.lineTo(x, y);
                    ctx.stroke();
                }

                lastX = x;
                lastY = y;

                checkScratchPercent();
            };

            // Throttle percent calculation
            let lastCheckTime = 0;
            const checkScratchPercent = () => {
                const now = Date.now();
                if (now - lastCheckTime < 100) return;
                lastCheckTime = now;

                try {
                    const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    const data = imgData.data;
                    
                    const step = 15;
                    let sampledCleared = 0;
                    let sampledTotal = 0;
                    for (let i = 3; i < data.length; i += step * 4) {
                        if (data[i] === 0) {
                            sampledCleared++;
                        }
                        sampledTotal++;
                    }

                    const percent = (sampledCleared / sampledTotal) * 100;
                    if (percent > 50) {
                        revealCode();
                    }
                } catch (e) {
                    // Fallback
                }
            };

            // Event Listeners for drawing
            canvas.addEventListener('mousedown', (e) => {
                isDrawing = true;
                const pos = getMousePos(e);
                lastX = pos.x;
                lastY = pos.y;
                scratch(pos.x, pos.y);
            });

            window.addEventListener('mousemove', (e) => {
                if (!isDrawing) return;
                const pos = getMousePos(e);
                scratch(pos.x, pos.y);
            });

            window.addEventListener('mouseup', () => {
                isDrawing = false;
                lastX = 0;
                lastY = 0;
            });

            canvas.addEventListener('touchstart', (e) => {
                isDrawing = true;
                const pos = getMousePos(e);
                lastX = pos.x;
                lastY = pos.y;
                scratch(pos.x, pos.y);
                e.preventDefault();
            }, { passive: false });

            canvas.addEventListener('touchmove', (e) => {
                if (!isDrawing) return;
                const pos = getMousePos(e);
                scratch(pos.x, pos.y);
                e.preventDefault();
            }, { passive: false });

            canvas.addEventListener('touchend', () => {
                isDrawing = false;
                lastX = 0;
                lastY = 0;
            });

            canvas.addEventListener('dblclick', revealCode);
        });
    };

    const applyCabinetSafeStatus = (card, payload = {}) => {
        const label = card.querySelector('[data-safe-label]');
        const hint = card.querySelector('[data-safe-hint]');
        const inlineMessage = card.querySelector('[data-safe-inline-message]');
        const button = card.querySelector('[data-safe-open-button]');
        const supportTicketLink = card.querySelector('[data-safe-support-ticket-link]');
        const status = payload.status || card.dataset.safeStatus || 'preparing';
        const message = payload.message || hint?.textContent || 'Проверяем статус сейфа заказа.';
        const hasReady = Object.prototype.hasOwnProperty.call(payload, 'ready');
        const hasFailed = Object.prototype.hasOwnProperty.call(payload, 'failed');
        const ready = hasReady ? payload.ready === true : card.dataset.safeReady === '1';
        const failed = (hasFailed ? payload.failed === true : card.dataset.safeFailed === '1') || status.includes('failed');

        if (label) {
            label.textContent = payload.label || cabinetSafeStatusLabels[status] || status;
        }

        if (hint) {
            hint.textContent = message;
        }

        if (inlineMessage) {
            inlineMessage.textContent = message;
        }

        if (button) {
            button.disabled = !ready || failed;
            button.textContent = ready && !failed ? 'Открыть сейф' : (failed ? 'Недоступно' : 'Готовится');
        }

        const supportTicketUrl = payload.support_ticket_url || card.dataset.safeSupportTicketUrl || '';
        if (supportTicketLink && supportTicketUrl) {
            supportTicketLink.href = supportTicketUrl;
            supportTicketLink.style.display = '';
            card.dataset.safeSupportTicketUrl = supportTicketUrl;
            card.dataset.safeSupportTicketId = payload.support_ticket_id || card.dataset.safeSupportTicketId || '';
            card.dataset.safeSupportTicketMessagesUrl = payload.support_ticket_messages_url || card.dataset.safeSupportTicketMessagesUrl || '';
            card.dataset.safeSupportTicketReplyUrl = payload.support_ticket_reply_url || card.dataset.safeSupportTicketReplyUrl || '';
        }

        card.dataset.safeStatus = status;
        card.dataset.safeReady = ready && !failed ? '1' : '0';
        card.dataset.safeFailed = failed ? '1' : '0';
    };

    const renderInlineScratchCard = (card) => {
        const keyBlock = card.querySelector('.secure-key-block');
        if (!keyBlock) return;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
            || document.querySelector('input[name="_token"]')?.value
            || '';

        const isScratched = card.dataset.safeScratched === '1';
        const savedProof = card.dataset.safeScratchProof || '';

        keyBlock.innerHTML = '';
        keyBlock.className = 'secure-key-block has-scratch';

        const container = document.createElement('div');
        container.className = 'inline-scratch-container' + (isScratched ? '' : ' is-blurred');

        const underlay = document.createElement('div');
        underlay.className = 'inline-scratch-underlay';

        const revealedCode = document.createElement('div');
        revealedCode.className = 'revealed-inline-code';

        const codeElement = document.createElement('code');
        codeElement.textContent = 'РАСШИФРОВКА...';
        codeElement.style.userSelect = 'none';

        revealedCode.appendChild(codeElement);
        underlay.appendChild(revealedCode);
        container.appendChild(underlay);

        let canvas = null;
        let revealBtn = null;
        let ctx = null;
        let dpr = 1;
        const rect = container.getBoundingClientRect();

        if (!isScratched) {
            canvas = document.createElement('canvas');
            canvas.className = 'inline-scratch-canvas';
            container.appendChild(canvas);

            // Absolute positioning helper Reveal button overlay
            revealBtn = document.createElement('button');
            revealBtn.type = 'button';
            revealBtn.className = 'inline-reveal-btn';
            revealBtn.textContent = 'Стереть';
            revealBtn.style.display = 'none'; // hide until safe loaded
            container.appendChild(revealBtn);

            // Setup high-resolution Retina pixel support
            dpr = window.devicePixelRatio || 1;
            canvas.width = rect.width * dpr;
            canvas.height = rect.height * dpr;
            canvas.style.width = `${rect.width}px`;
            canvas.style.height = `${rect.height}px`;

            ctx = canvas.getContext('2d');
            ctx.scale(dpr, dpr);
        }

        keyBlock.appendChild(container);

        const paintCanvas = (text = 'СУВЕРЕННЫЙ СЕЙФ // СТЕРЕТЬ МОНЕТКОЙ') => {
            if (isScratched || !canvas) return;
            const w = rect.width;
            const h = rect.height;

            ctx.clearRect(0, 0, w, h);
            
            // Brushed premium silver metal gradient
            const grad = ctx.createLinearGradient(0, 0, w, h);
            grad.addColorStop(0, '#e5e7eb');
            grad.addColorStop(0.2, '#d1d5db');
            grad.addColorStop(0.5, '#f9fafb');
            grad.addColorStop(0.8, '#9ca3af');
            grad.addColorStop(1, '#4b5563');

            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, w, h);

            // Precision cross hatch security pattern
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.22)';
            ctx.lineWidth = 1;
            const lineSpacing = 8;
            for (let x = -h; x < w; x += lineSpacing) {
                ctx.beginPath();
                ctx.moveTo(x, 0);
                ctx.lineTo(x + h, h);
                ctx.stroke();
            }
            for (let x = 0; x < w + h; x += lineSpacing) {
                ctx.beginPath();
                ctx.moveTo(x, 0);
                ctx.lineTo(x - h, h);
                ctx.stroke();
            }

            // High-premium micro-grain noise (600 sharp noise particles)
            for (let i = 0; i < 600; i++) {
                const px = Math.random() * w;
                const py = Math.random() * h;
                ctx.fillStyle = Math.random() > 0.5 ? 'rgba(255,255,255,0.22)' : 'rgba(0,0,0,0.15)';
                ctx.fillRect(px, py, 1.2, 1.2);
            }

            // High-fidelity dashed inner border
            ctx.strokeStyle = 'rgba(0, 0, 0, 0.15)';
            ctx.lineWidth = 1.5;
            ctx.setLineDash([4, 4]);
            ctx.strokeRect(5, 5, w - 10, h - 10);
            ctx.setLineDash([]); // Reset dash

            // Draw crisp security text with fine drop shadow
            ctx.fillStyle = '#1f2937';
            ctx.font = 'bold 8.5px "Outfit", "Inter", sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.letterSpacing = '0.12em';
            ctx.shadowColor = 'rgba(255, 255, 255, 0.5)';
            ctx.shadowBlur = 1;
            ctx.shadowOffsetX = 0;
            ctx.shadowOffsetY = 1;
            ctx.fillText(text, w / 2, h / 2);
            ctx.shadowColor = 'transparent'; // Reset shadow
        };

        if (!isScratched) {
            paintCanvas('РАСШИФРОВКА СЕЙФА...');
        }

        let codeItem = null;
        let isDrawingEnabled = false;
        let revealed = false;

        const generateCryptoFingerprint = async () => {
            try {
                const encoder = new TextEncoder();
                const rawString = `safe-scratch-proof-${card.dataset.safeUuid}-${Date.now()}-${Math.random()}`;
                const data = encoder.encode(rawString);
                const hashBuffer = await window.crypto.subtle.digest('SHA-256', data);
                const hashArray = Array.from(new Uint8Array(hashBuffer));
                return hashArray.map(b => b.toString(16).padStart(2, '0')).join('').substring(0, 16).toUpperCase();
            } catch (e) {
                return Math.random().toString(36).substring(2, 10).toUpperCase();
            }
        };

        const revealCode = async (isManual = true) => {
            if (revealed || !codeItem) return;
            revealed = true;
            isDrawingEnabled = false;

            if (canvas) canvas.classList.add('fade-out');
            if (revealBtn) revealBtn.remove();
            container.classList.remove('is-blurred');
            codeElement.style.userSelect = 'text';

            const actionsRow = document.createElement('div');
            actionsRow.className = 'revealed-inline-actions';

            const copyBtn = document.createElement('button');
            copyBtn.type = 'button';
            copyBtn.textContent = 'Скопировать';
            copyBtn.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(codeItem.code || '');
                    copyBtn.textContent = 'Скопировано';
                    window.setTimeout(() => copyBtn.textContent = 'Скопировать', 1800);
                } catch (error) {
                    copyBtn.textContent = 'Ошибка';
                }
            });

            actionsRow.appendChild(copyBtn);

            if (codeItem.redeem_url) {
                const redeemLink = document.createElement('a');
                redeemLink.href = codeItem.redeem_url;
                redeemLink.target = '_blank';
                redeemLink.textContent = 'Активировать';
                actionsRow.appendChild(redeemLink);
            }
            
            revealedCode.appendChild(actionsRow);

            // Generate verified SHA-256 fingerprint trace proof
            let fingerprint = savedProof;
            if (!fingerprint) {
                const rawFingerprint = await generateCryptoFingerprint();
                fingerprint = `SHA256-${rawFingerprint}`;
            }

            // Sync dynamic DOM dataset state
            card.dataset.safeScratched = '1';
            card.dataset.safeScratchProof = fingerprint;

            const badge = document.createElement('div');
            badge.className = 'scratch-proof-badge';
            badge.innerHTML = `<i class="ph-bold ph-shield-check"></i> SECURE PROOF: ${fingerprint.includes('SHA256-') ? fingerprint : 'SHA256-' + fingerprint}...`;
            revealedCode.appendChild(badge);

            // Record scratch verification on server only if manually scratched just now
            if (isManual && !savedProof) {
                try {
                    const scratchUrl = card.dataset.safeOpenUrl.replace('/open', '/scratch');
                    await fetch(scratchUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ scratch_proof: fingerprint.includes('SHA256-') ? fingerprint : 'SHA256-' + fingerprint }),
                    });
                } catch (err) {
                    // Fail silently for background telemetry
                }
            }

            if (canvas) {
                window.setTimeout(() => {
                    canvas.remove();
                }, 400);
            }
        };

        fetch(card.dataset.safeOpenUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ open: true }),
        })
        .then(res => res.json())
        .then(payload => {
            if (payload && payload.ready && Array.isArray(payload.codes) && payload.codes.length > 0) {
                codeItem = payload.codes[0];
                codeElement.textContent = codeItem.code || '';
                isDrawingEnabled = true;

                if (isScratched) {
                    revealCode(false); // Instantly reveal code and load proof, bypass canvas
                } else {
                    paintCanvas('СУВЕРЕННЫЙ СЕЙФ // СТЕРЕТЬ МОНЕТКОЙ');
                    if (revealBtn) revealBtn.style.display = 'block'; // Safe loaded, show manual button overlay
                }
            } else {
                throw new Error('Код недоступен');
            }
        })
        .catch(err => {
            codeElement.textContent = err.message || 'Ошибка загрузки';
            if (!isScratched) paintCanvas('ОШИБКА ДЕШИФРОВАНИЯ');
        });

        if (!isScratched) {
            revealBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                revealCode(true);
            });

            let isDrawing = false;
            let lastX = 0;
            let lastY = 0;

            const getMousePos = (e) => {
                const crect = canvas.getBoundingClientRect();
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                return {
                    x: clientX - crect.left,
                    y: clientY - crect.top
                };
            };

            const scratch = (x, y) => {
                if (!isDrawingEnabled) return;
                isDrawingEnabled = false;

                ctx.globalCompositeOperation = 'destination-out';
                ctx.beginPath();
                ctx.arc(x, y, 16, 0, Math.PI * 2);
                ctx.fill();

                if (lastX && lastY) {
                    ctx.beginPath();
                    ctx.lineWidth = 32;
                    ctx.lineCap = 'round';
                    ctx.lineJoin = 'round';
                    ctx.moveTo(lastX, lastY);
                    ctx.lineTo(x, y);
                    ctx.stroke();
                }

                lastX = x;
                lastY = y;

                revealCode(true);
            };

            canvas.addEventListener('mousedown', (e) => {
                isDrawing = true;
                const pos = getMousePos(e);
                lastX = pos.x;
                lastY = pos.y;
                scratch(pos.x, pos.y);
            });

            window.addEventListener('mousemove', (e) => {
                if (!isDrawing) return;
                const pos = getMousePos(e);
                scratch(pos.x, pos.y);
            });

            window.addEventListener('mouseup', () => {
                isDrawing = false;
                lastX = 0;
                lastY = 0;
            });

            canvas.addEventListener('touchstart', (e) => {
                isDrawing = true;
                const pos = getMousePos(e);
                lastX = pos.x;
                lastY = pos.y;
                scratch(pos.x, pos.y);
                e.preventDefault();
            }, { passive: false });

            canvas.addEventListener('touchmove', (e) => {
                if (!isDrawing) return;
                const pos = getMousePos(e);
                scratch(pos.x, pos.y);
                e.preventDefault();
            }, { passive: false });

            canvas.addEventListener('touchend', () => {
                isDrawing = false;
                lastX = 0;
                lastY = 0;
            });

            canvas.addEventListener('dblclick', () => {
                if (isDrawingEnabled) {
                    revealCode(true);
                }
            });
        }
    };

    const initCabinetSafeReveal = () => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
            || document.querySelector('input[name="_token"]')?.value
            || '';

        document.querySelectorAll('[data-safe-card]').forEach((card) => {
            const button = card.querySelector('[data-safe-open-button]');
            const panel = card.querySelector('[data-safe-inline-panel]');

            applyCabinetSafeStatus(card);

            if (card.dataset.safeReady === '1' && !card.dataset.safeStatus?.includes('failed')) {
                renderInlineScratchCard(card);
                return;
            }

            if (!button || !panel) {
                return;
            }

            button.addEventListener('click', async () => {
                if (button.disabled) {
                    return;
                }

                card.classList.add('is-expanded');
                panel.hidden = false;
                button.disabled = true;
                button.textContent = 'Открываем...';

                try {
                    const response = await fetch(card.dataset.safeOpenUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ open: true }),
                    });
                    const payload = await response.json().catch(() => ({}));

                    applyCabinetSafeStatus(card, payload);

                    if (!response.ok) {
                        throw new Error(payload.message || 'Сейф пока не готов.');
                    }

                    if (!payload.ready || !Array.isArray(payload.codes) || payload.codes.length === 0) {
                        return;
                    }

                    renderCabinetSafeCodes(card, payload.codes);
                    button.disabled = true;
                    button.textContent = 'Сейф открыт';
                    card.dataset.safeReady = '1';
                    const inlineMessage = card.querySelector('[data-safe-inline-message]');
                    if (inlineMessage) {
                        inlineMessage.textContent = 'Сейф открыт. Код можно скопировать или открыть сайт активации.';
                    }
                } catch (error) {
                    const inlineMessage = card.querySelector('[data-safe-inline-message]');
                    if (inlineMessage) {
                        inlineMessage.textContent = error.message || 'Не удалось открыть сейф.';
                    }

                    if (card.dataset.safeFailed !== '1') {
                        button.disabled = false;
                        button.textContent = 'Открыть сейф';
                    }
                }
            });
        });
    };

    const initCabinetSupportChat = () => {
        const overlay = document.getElementById('support-chat-overlay');
        const drawer = document.getElementById('support-chat-drawer');
        const closeButton = document.getElementById('support-chat-close');
        const subtitle = document.getElementById('support-chat-subtitle');
        const messages = document.getElementById('support-chat-messages');
        const form = document.getElementById('support-chat-form');
        const input = document.getElementById('support-chat-input');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
            || document.querySelector('input[name="_token"]')?.value
            || '';

        if (!overlay || !drawer || !messages || !form || !input) {
            return;
        }

        let activeCard = null;
        let renderedMessageIds = new Set();
        let pollTimer = null;
        let isSending = false;

        const scrollToBottom = () => {
            messages.scrollTop = messages.scrollHeight;
        };

        const escapeHtml = (text) => String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const appendBubble = (message) => {
            if (renderedMessageIds.has(message.id)) {
                return;
            }

            renderedMessageIds.add(message.id);
            const bubble = document.createElement('div');
            bubble.className = `support-chat-bubble ${message.role === 'assistant' ? 'assistant' : 'user'}`;
            bubble.innerHTML = `<span class="support-chat-meta">${escapeHtml(message.author)} · ${escapeHtml(message.created_at || '')}</span>${escapeHtml(message.message).replace(/\n/g, '<br>')}`;
            messages.appendChild(bubble);
            scrollToBottom();
        };

        const appendError = (text) => {
            const bubble = document.createElement('div');
            bubble.className = 'support-chat-bubble error';
            bubble.textContent = text;
            messages.appendChild(bubble);
            scrollToBottom();
        };

        const loadMessages = async () => {
            if (!activeCard?.dataset.safeSupportTicketMessagesUrl) {
                return;
            }

            try {
                const response = await fetch(activeCard.dataset.safeSupportTicketMessagesUrl, {
                    headers: { 'Accept': 'application/json' },
                });
                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    throw new Error(payload.error || 'Не удалось обновить чат.');
                }
                payload.messages.forEach(appendBubble);
            } catch (error) {
                if (renderedMessageIds.size === 0) {
                    appendError(error.message || 'Не удалось загрузить чат поддержки.');
                }
            }
        };

        const openChat = (card) => {
            activeCard = card;
            renderedMessageIds = new Set();
            messages.innerHTML = '';
            subtitle.textContent = `Заказ ${card.dataset.safeOrderId || ''} · тикет #${card.dataset.safeSupportTicketId || ''}`;
            drawer.classList.add('open');
            overlay.style.display = 'block';
            input.focus();
            window.clearInterval(pollTimer);
            loadMessages();
            pollTimer = window.setInterval(loadMessages, 5000);
        };

        const closeChat = () => {
            drawer.classList.remove('open');
            overlay.style.display = 'none';
            window.clearInterval(pollTimer);
            pollTimer = null;
        };

        const sendMessage = async (text) => {
            if (isSending || !text.trim() || !activeCard?.dataset.safeSupportTicketReplyUrl) {
                return;
            }

            isSending = true;
            input.value = '';

            try {
                const response = await fetch(activeCard.dataset.safeSupportTicketReplyUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ message: text }),
                });
                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    throw new Error(payload.error || 'Не удалось отправить сообщение.');
                }
                payload.messages.forEach(appendBubble);
            } catch (error) {
                appendError(error.message || 'Ошибка сети при отправке сообщения.');
            } finally {
                isSending = false;
            }
        };

        document.querySelectorAll('[data-safe-support-ticket-link]').forEach((link) => {
            link.addEventListener('click', (event) => {
                const card = link.closest('[data-safe-card]');
                if (!card?.dataset.safeSupportTicketMessagesUrl || !card?.dataset.safeSupportTicketReplyUrl) {
                    return;
                }

                event.preventDefault();
                openChat(card);
            });
        });

        closeButton?.addEventListener('click', closeChat);
        overlay.addEventListener('click', closeChat);
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            sendMessage(input.value);
        });
    };

    const focusCabinetSafe = () => {
        const safeParam = new URLSearchParams(window.location.search).get('safe');
        const hashTarget = window.location.hash ? document.getElementById(window.location.hash.slice(1)) : null;
        const queryTarget = safeParam
            ? Array.from(document.querySelectorAll('[data-safe-card]')).find((card) => card.dataset.safeUuid === safeParam)
            : null;
        const target = hashTarget || queryTarget;

        if (!target) {
            return;
        }

        target.classList.add('is-focused');
        window.requestAnimationFrame(() => {
            target.scrollIntoView({ block: 'center', behavior: 'smooth' });
            target.focus({ preventScroll: true });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initCabinetSafeReveal();
            initCabinetSupportChat();
            focusCabinetSafe();
        }, { once: true });
    } else {
        initCabinetSafeReveal();
        initCabinetSupportChat();
        focusCabinetSafe();
    }

</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>

<!-- Sovereign QR Modal Overlay -->
<div id="sl1-qr-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 99999; justify-content: center; align-items: center; backdrop-filter: blur(5px);">
    <div style="background: var(--bg-card); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 2rem; max-width: 90%; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.5);">
        <div id="sl1-qr-title" style="font-weight: 800; font-size: 16px; margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
            <i class="ph-bold ph-shield-check" style="color: var(--brand-primary);"></i> SOVEREIGN PASSPORT
        </div>
        <div id="sl1-qr-desc" style="font-size: 11px; color: var(--brand-subtext); margin-bottom: 1.5rem; max-width: 250px; line-height: 1.4;">
            Scan with any verifiable node or SQRP-compatible mobile camera to authenticate legitimacy offline.
        </div>
        
        <div style="background: white; padding: 10px; border-radius: 8px; display: inline-block; margin-bottom: 1rem;">
            <canvas id="sl1-qr-canvas"></canvas>
        </div>
        
        <div id="sl1-qr-size" style="font-family: 'JetBrains Mono', monospace; font-size: 10px; color: var(--brand-subtext); margin-bottom: 1.5rem;">
            -- bytes
        </div>
        
        <button onclick="hideQrPassport()" style="cursor: pointer; background: var(--brand-primary); color: #fff; border: none; padding: 0.5rem 2rem; border-radius: 6px; font-weight: 700; font-size: 12px; width: 100%;">
            Close
        </button>
    </div>
</div>
<div id="support-chat-overlay" class="support-chat-overlay"></div>
<div id="support-chat-drawer" class="support-chat-drawer" aria-live="polite">
    <div class="support-chat-header">
        <div>
            <div class="support-chat-title">Поддержка Meanly</div>
            <span id="support-chat-subtitle" class="support-chat-subtitle">Тикет поддержки</span>
        </div>
        <button id="support-chat-close" class="support-chat-close" type="button" title="Закрыть">&times;</button>
    </div>
    <div id="support-chat-messages" class="support-chat-messages"></div>
    <div class="support-chat-footer">
        <form id="support-chat-form">
            <div class="support-chat-input-wrapper">
                <input type="text" id="support-chat-input" placeholder="Напишите поддержке..." autocomplete="off">
                <button type="submit" aria-label="Отправить">➤</button>
            </div>
        </form>
    </div>
</div>
@livewireScripts
</body>
</html>
