<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Kernel — Meanly Systems</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <style>
        body {
            display: block !important;
            text-align: left !important;
            min-height: 100vh;
            overflow-x: hidden;
            font-family: 'Outfit', 'Inter', sans-serif !important;
            margin: 0;
        }
        .container {
            max-width: 100% !important;
            width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
            display: flex !important;
            min-height: 100vh;
            text-align: left !important;
        }

        /* --- 🎨 Multi-Skin Theme Engine --- */
        
        /* Theme 1: Meanly Partner 🌟 (Glassmorphism & Amber Glow) */
        body[data-theme="partner"] {
            background: radial-gradient(circle at 80% 10%, rgba(245, 158, 11, 0.08) 0%, transparent 50%), 
                        radial-gradient(circle at 10% 90%, rgba(139, 92, 246, 0.06) 0%, transparent 50%), 
                        #080a14 !important;
            --primary: #f59e0b;
            --primary-rgb: 245, 158, 11;
            --primary-glow: rgba(245, 158, 11, 0.2);
            --bg-main: #080a14;
            --bg-sidebar: rgba(13, 16, 27, 0.6);
            --bg-card: rgba(22, 28, 48, 0.35);
            --bg-input: rgba(10, 13, 24, 0.55);
            --border-card: rgba(255, 255, 255, 0.06);
            --border-neon: rgba(245, 158, 11, 0.2);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --shadow-neo: 0 8px 32px 0 rgba(0, 0, 0, 0.35);
            --shadow-neo-inset: inset 2px 2px 5px rgba(0,0,0,0.5), inset -2px -2px 5px rgba(255,255,255,0.01);
            --green: #10b981;
            --green-glow: rgba(16, 185, 129, 0.15);
            --rose: #f43f5e;
            --rose-glow: rgba(244, 63, 94, 0.15);
            --font-tech: 'Outfit', sans-serif;
        }
        body[data-theme="partner"] .logo-text-partner { display: inline !important; }
        body[data-theme="partner"] .logo-text-consortium { display: none !important; }
        body[data-theme="partner"] .logo-text-retro { display: none !important; }
        body[data-theme="partner"] .menu-item.active {
            background: linear-gradient(90deg, rgba(245, 158, 11, 0.12), rgba(245, 158, 11, 0.01)) !important;
            color: var(--primary) !important;
            border-left: 3px solid var(--primary) !important;
            border-radius: 0 12px 12px 0 !important;
            padding-left: calc(1rem - 3px) !important;
            border-right: none !important;
        }
        body[data-theme="partner"] .logo-sub {
            background: var(--primary) !important;
            color: #000000 !important;
            border-color: var(--primary) !important;
            font-weight: 900 !important;
        }
        body[data-theme="partner"] .card-neo {
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
        }

        /* Theme 2: Consortium flagship 🚩 (Flat Neobrutalism Terminal) */
        body[data-theme="consortium"] {
            background: #030303 !important;
            --primary: #f53003;
            --primary-rgb: 245, 48, 3;
            --primary-glow: rgba(245, 48, 3, 0.25);
            --bg-main: #030303;
            --bg-sidebar: #090909;
            --bg-card: #090909;
            --bg-input: #030303;
            --border-card: rgba(255, 255, 255, 0.05);
            --border-neon: rgba(245, 48, 3, 0.2);
            --text-main: #ffffff;
            --text-muted: #8e8e93;
            --shadow-neo: 0 4px 20px 0 rgba(0, 0, 0, 0.6);
            --shadow-neo-inset: inset 2px 2px 5px rgba(0,0,0,0.5), inset -2px -2px 5px rgba(255,255,255,0.01);
            --green: #10b981;
            --green-glow: rgba(16, 185, 129, 0.15);
            --rose: #f43f5e;
            --rose-glow: rgba(244, 63, 94, 0.15);
            --font-tech: 'JetBrains Mono', monospace;
        }
        body[data-theme="consortium"] .logo-text-partner { display: none !important; }
        body[data-theme="consortium"] .logo-text-consortium { display: inline !important; }
        body[data-theme="consortium"] .logo-text-retro { display: none !important; }
        body[data-theme="consortium"] .card-neo {
            background: var(--bg-card) !important;
            border: 1px solid var(--border-card) !important;
            border-radius: 12px !important;
            box-shadow: var(--shadow-neo) !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
        }
        body[data-theme="consortium"] .card-neo:hover {
            transform: translateY(-2px);
            border-color: rgba(245, 48, 3, 0.3) !important;
        }
        body[data-theme="consortium"] .menu-item.active {
            background: #141414 !important;
            border-right: 3px solid var(--primary) !important;
            color: #ffffff !important;
            border-radius: 6px !important;
            border-left: none !important;
        }
        body[data-theme="consortium"] .logo-dot {
            background: var(--primary) !important;
            box-shadow: 0 0 10px var(--primary-glow) !important;
        }

        /* Theme 3: Consortium Retro ⚡ (Bold light neobrutalism) */
        body[data-theme="retro"] {
            background: #f3f4f6 !important;
            --primary: #7c3aed;
            --primary-rgb: 124, 58, 237;
            --primary-glow: rgba(124, 58, 237, 0.2);
            --bg-main: #f3f4f6;
            --bg-sidebar: #ffffff;
            --bg-card: #ffffff;
            --bg-input: #ffffff;
            --border-card: #000000;
            --border-neon: #000000;
            --text-main: #000000;
            --text-muted: #4b5563;
            --shadow-neo: 4px 4px 0px #000000;
            --shadow-neo-inset: inset 2px 2px 0px rgba(0,0,0,0.1);
            --green: #16a34a;
            --green-glow: rgba(22, 163, 74, 0.1);
            --rose: #dc2626;
            --rose-glow: rgba(220, 38, 38, 0.1);
            --font-tech: 'JetBrains Mono', monospace;
        }
        body[data-theme="retro"] .logo-text-partner { display: none !important; }
        body[data-theme="retro"] .logo-text-consortium { display: none !important; }
        body[data-theme="retro"] .logo-text-retro { display: inline !important; font-weight: 900 !important; font-size: 1.5rem !important; }
        body[data-theme="retro"] .sidebar {
            border-right: 3px solid #000000 !important;
            box-shadow: none !important;
        }
        body[data-theme="retro"] .card-neo {
            background: #ffffff !important;
            border: 3px solid #000000 !important;
            border-radius: 0px !important;
            box-shadow: 4px 4px 0px #000000 !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            color: #000000 !important;
        }
        body[data-theme="retro"] .card-neo:hover {
            transform: translate(-2px, -2px) !important;
            box-shadow: 6px 6px 0px #000000 !important;
        }
        body[data-theme="retro"] .menu-item {
            border-radius: 0px !important;
            border: 1px solid transparent !important;
            color: #000000 !important;
        }
        body[data-theme="retro"] .menu-item:hover {
            background: rgba(0, 0, 0, 0.05) !important;
        }
        body[data-theme="retro"] .menu-item.active {
            background: #e9d5ff !important;
            border: 3px solid #000000 !important;
            box-shadow: 2px 2px 0px #000000 !important;
            color: #000000 !important;
            border-radius: 0px !important;
            font-weight: 800 !important;
        }
        body[data-theme="retro"] .menu-item:hover:not(.active) {
            background: #f3f4f6 !important;
        }
        body[data-theme="retro"] .logo-dot {
            background: #000000 !important;
            border: 2px solid #000000 !important;
        }
        body[data-theme="retro"] .logo-sub {
            background: #000000 !important;
            color: #ffffff !important;
            border-radius: 0px !important;
            border: none !important;
            font-weight: 900 !important;
        }
        body[data-theme="retro"] .top-bar {
            border-bottom: 2px solid #000000 !important;
            background: #ffffff !important;
        }
        body[data-theme="retro"] .top-stat-item {
            border: 3px solid #000000 !important;
            box-shadow: 2px 2px 0px #000000 !important;
            border-radius: 0px !important;
            background: #ffffff !important;
            color: #000000 !important;
        }
        body[data-theme="retro"] .skin-switcher-pill {
            border: 3px solid #000000 !important;
            box-shadow: 2px 2px 0px #000000 !important;
            border-radius: 0px !important;
            background: #ffffff !important;
        }
        body[data-theme="retro"] .input-neo {
            border: 3px solid #000000 !important;
            border-radius: 0px !important;
            background: #ffffff !important;
            color: #000000 !important;
            box-shadow: none !important;
        }
        body[data-theme="retro"] .input-neo:focus {
            box-shadow: 2px 2px 0px #000000 !important;
            border-color: #000000 !important;
        }
        body[data-theme="retro"] .btn-neo {
            border: 3px solid #000000 !important;
            border-radius: 0px !important;
            background: #ffffff !important;
            color: #000000 !important;
            box-shadow: 2px 2px 0px #000000 !important;
            font-weight: 800 !important;
            transition: all 0.15s ease !important;
        }
        body[data-theme="retro"] .btn-neo:hover {
            transform: translate(-1px, -1px) !important;
            box-shadow: 3px 3px 0px #000000 !important;
        }
        body[data-theme="retro"] .btn-primary-neo {
            background: var(--primary) !important;
            color: #ffffff !important;
            border: 3px solid #000000 !important;
            border-radius: 0px !important;
            box-shadow: 2px 2px 0px #000000 !important;
            font-weight: 800 !important;
            transition: all 0.15s ease !important;
        }
        body[data-theme="retro"] .btn-primary-neo:hover {
            transform: translate(-1px, -1px) !important;
            box-shadow: 3px 3px 0px #000000 !important;
            background: var(--primary) !important;
        }
        body[data-theme="retro"] .badge-neo {
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
            font-weight: 900 !important;
            box-shadow: 1px 1px 0px #000000 !important;
        }
        body[data-theme="retro"] .neo-table-container {
            border: 3px solid #000000 !important;
            box-shadow: 4px 4px 0px #000000 !important;
            border-radius: 0px !important;
            background: #ffffff !important;
        }
        body[data-theme="retro"] .neo-table th {
            background: #ffffff !important;
            color: #000000 !important;
            border-bottom: 3px solid #000000 !important;
            font-weight: 900 !important;
        }
        body[data-theme="retro"] .neo-table td {
            color: #111827 !important;
            border-bottom: 1px solid #e5e7eb !important;
        }
        body[data-theme="retro"] .sidebar-footer {
            border-top: 2px solid #000000 !important;
        }

        /* --- 📐 Layout Structures --- */
        /* --- 🧭 Layout Structure --- */
        .sidebar {
            width: 280px;
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border-card);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            box-shadow: var(--shadow-neo);
            transition: all 0.3s ease;
        }
        .sidebar-logo {
            padding: 1.75rem 1.5rem;
            border-bottom: 1px solid var(--border-card);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--primary);
            box-shadow: 0 0 8px var(--primary-glow);
        }
        .sidebar-menu {
            flex: 1;
            padding: 1.5rem 0;
            overflow-y: auto;
        }
        .sidebar-section-title {
            padding: 0.75rem 1.5rem 0.25rem 1.5rem;
            font-size: 0.65rem;
            font-weight: 850;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--text-muted);
            opacity: 0.6;
        }
        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.75rem 1.5rem;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .menu-item svg {
            width: 18px;
            height: 18px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2.2;
            stroke-linecap: round;
            stroke-linejoin: round;
            flex-shrink: 0;
        }
        .menu-item i {
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        .menu-item:hover {
            color: var(--text-main);
            background: rgba(255,255,255,0.02);
        }
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 2.5rem;
            min-height: 100vh;
            box-sizing: border-box;
            background: var(--bg-main);
            color: var(--text-main);
            transition: background 0.3s ease;
            overflow-y: auto;
            max-height: 100vh;
        }
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2.5rem;
        }
        .header-title {
            font-size: 2.25rem;
            font-weight: 900;
            margin: 0;
            letter-spacing: -1px;
            text-transform: uppercase;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-rose {
            background: var(--rose-glow);
            color: var(--rose);
            border: 1px solid rgba(244, 63, 94, 0.15);
        }
        .badge-green {
            background: var(--green-glow);
            color: var(--green);
            border: 1px solid rgba(16, 185, 129, 0.15);
        }
        .badge-orange {
            background: rgba(245, 158, 11, 0.1);
            color: var(--primary);
            border: 1px solid var(--border-neon);
        }

        /* --- 🎨 Neomorphic UI Cards --- */
        .card-neo {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--shadow-neo);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* --- Skin Switcher --- */
        .skin-switcher-pill {
            display: flex;
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--border-card);
            padding: 4px;
            border-radius: 9999px;
            gap: 4px;
        }
        .skin-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 0.65rem;
            font-weight: 900;
            cursor: pointer;
            text-transform: uppercase;
            transition: all 0.2s;
        }
        .skin-btn.active {
            background: var(--primary);
            color: #fff !important;
            box-shadow: 0 4px 12px var(--primary-glow);
        }

        /* CSS Progress meter */
        .meter-bar {
            width: 100%;
            background: rgba(0,0,0,0.2);
            height: 6px;
            border-radius: 9999px;
            overflow: hidden;
            position: relative;
        }
        .meter-fill {
            height: 100%;
            border-radius: 9999px;
            transition: width 0.5s ease;
        }
        
        .sidebar-footer {
            padding: 1.25rem 1.5rem;
            border-top: 1px solid var(--border-card);
            background: rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            box-sizing: border-box;
        }
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--primary) 0%, rgba(255,255,255,0.1) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            color: #ffffff;
            font-size: 0.95rem;
            border: 1px solid var(--border-card);
        }
        .user-info {
            display: flex;
            flex-direction: column;
            max-width: 140px;
        }
        .user-name {
            font-size: 0.82rem;
            font-weight: 750;
            color: var(--text-main);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .user-role {
            font-size: 0.65rem;
            color: var(--text-muted);
        }

        /* Console styling */
        .console-terminal {
            background: #000;
            border: 2px solid var(--border-card);
            border-radius: 8px;
            padding: 1rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            color: #22c55e;
            height: 250px;
            overflow-y: auto;
            line-height: 1.5;
            box-shadow: inset 0 0 10px rgba(0, 255, 0, 0.1);
        }
        .console-line {
            margin-bottom: 4px;
        }
        .console-line.error {
            color: #ef4444;
        }
        .console-line.warning {
            color: #eab308;
        }
        .console-line.info {
            color: #3b82f6;
        }

        /* Unified Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--border-card);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }
        /* --- 📚 Biblioteca de Babel Overlay (August 24 — Borges Birthday) --- */
        body[data-holiday="babel-library"] {
            background: radial-gradient(circle at 50% 50%, rgba(120, 53, 15, 0.12) 0%, transparent 70%), var(--bg-main) !important;
        }
        body[data-holiday="babel-library"] .logo-dot {
            background: #d97706 !important;
            box-shadow: 0 0 10px rgba(217, 119, 6, 0.4) !important;
        }
        body[data-holiday="babel-library"] .btn-primary-neo,
        body[data-holiday="babel-library"] .skin-btn.active {
            background: #b45309 !important;
            box-shadow: 0 4px 14px rgba(180, 83, 9, 0.35) !important;
        }
    </style>
</head>
@include('partials.theme-sync-body')
<body data-theme="{{ $currentTheme ?? request()->cookie('theme') ?? 'consortium' }}" @if(request()->cookie('holiday')) data-holiday="{{ request()->cookie('holiday') }}" @endif>
    <div class="container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="sidebar-logo console-selector-wrapper" style="position: relative; cursor: pointer; display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;" onclick="toggleConsoleDropdown(event)">
                <div style="display: flex; align-items: center; gap: 0.5rem; width: 100%;">
                    <span class="logo-dot"></span>
                    <span class="logo-text-consortium" style="font-family: var(--font-tech), monospace;">Meanly <span class="logo-sub" style="background: var(--primary-glow); color: var(--primary); border: 1px solid var(--border-neon); padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">Kernel ▾</span></span>
                    <span class="logo-text-partner" style="display: none; font-family: var(--font-tech), monospace;">Meanly <span class="logo-sub" style="background: rgba(245, 158, 11, 0.1); color: var(--primary); border: 1px solid var(--border-neon); padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">Partner ▾</span></span>
                    <span class="logo-text-retro" style="display: none; font-family: var(--font-tech), monospace;">Meanly <span class="logo-sub" style="background: #000; color: #fff; border: 2px solid #000; padding: 2px 6px; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px;">Kernel ▾</span></span>
                </div>
                
                <div id="console-dropdown" class="card-neo" style="display: none; position: absolute; top: 100%; right: 0; width: 180px; margin-top: 0.5rem; background: var(--bg-sidebar); border: 1px solid var(--border-card); border-radius: 8px; z-index: 1000; box-shadow: var(--shadow-neo); overflow: hidden; padding: 0.25rem;">
                    <a href="/ops" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 0.85rem; color: var(--text-muted); font-size: 0.75rem; text-decoration: none; font-weight: 800; border-radius: 6px; font-family: var(--font-tech), monospace;">
                        🛡️ OPERATIONS
                    </a>
                    <a href="/tribunal" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 0.85rem; color: var(--text-muted); font-size: 0.75rem; text-decoration: none; font-weight: 800; border-radius: 6px; font-family: var(--font-tech), monospace;">
                        🏛️ TRIBUNAL
                    </a>
                    <a href="/treasury" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 0.85rem; color: var(--text-muted); font-size: 0.75rem; text-decoration: none; font-weight: 800; border-radius: 6px; font-family: var(--font-tech), monospace;">
                        🏦 TREASURY
                    </a>
                    <a href="/kernel" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 0.85rem; color: var(--text-main); font-size: 0.75rem; text-decoration: none; font-weight: 800; background: rgba(255,255,255,0.03); border-radius: 6px; font-family: var(--font-tech), monospace;">
                        ⚙️ KERNEL
                    </a>
                    <a href="/support" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 0.85rem; color: var(--text-muted); font-size: 0.75rem; text-decoration: none; font-weight: 800; border-radius: 6px; font-family: var(--font-tech), monospace;">
                        📞 SUPPORT
                    </a>
                </div>
            </div>

            <div class="sidebar-menu">
                <div class="sidebar-section-title">Ядро системы</div>
                <a onclick="switchTab('substrate')" class="menu-item active" id="menu-substrate">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="20" y1="15" x2="23" y2="15"></line><line x1="1" y1="9" x2="4" y2="9"></line><line x1="1" y1="15" x2="4" y2="15"></line></svg>
                    Substrate Dashboard
                </a>
                <a onclick="switchTab('console-logs')" class="menu-item" id="menu-console-logs">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 14 15 20 9"></polyline><polyline points="14 9 20 9 20 15"></polyline></svg>
                    Node Console logs
                </a>

                <div class="sidebar-section-title">Система</div>
                <a href="/ops" class="menu-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                    Вернуться в Ops
                </a>

                <div class="sidebar-section-title">витрина</div>
                <a href="/" class="menu-item" style="color: var(--primary) !important;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="stroke: var(--primary) !important;"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                    Вернуться на витрину
                </a>
            </div>

            <div class="sidebar-footer" style="justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 12px; cursor: pointer;" onclick="window.location.href='/ops?profile=1'">
                    <div class="user-avatar">
                        {{ mb_substr($user->name ?: ($user->email ?: 'А'), 0, 1) }}
                    </div>
                    <div class="user-info">
                        <span class="user-name">{{ $user->name ?: 'Администратор' }}</span>
                        <span class="user-role">SUPER ADMIN</span>
                    </div>
                </div>
                <div>
                    <form action="{{ route('partner.logout') }}" method="POST" style="margin: 0; display: inline;">
                        @csrf
                        <button type="submit" title="Выйти" style="background: transparent; border: none; padding: 4px; color: var(--text-muted); cursor: pointer; transition: color 0.2s;" onmouseover="this.style.color='#f43f5e'" onmouseout="this.style.color='var(--text-muted)'">
                            <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Main Workspace -->
        <div class="main-content">
            <div class="header-section">
                <div>
                    <span class="badge badge-rose" style="margin-bottom: 0.5rem;">Core Substrate</span>
                    <h1 class="header-title">System Kernel Control</h1>
                </div>

            </div>

            <!-- TAB 1: Substrate Dashboard -->
            <div id="tab-substrate" class="tab-pane">
                <!-- Core Substrate Banner -->
                <div class="card-neo" style="background: linear-gradient(135deg, rgba(15, 23, 42, 0.9) 0%, rgba(30, 41, 59, 0.9) 100%); border-color: rgba(255, 255, 255, 0.05); margin-bottom: 2rem; position: relative; overflow: hidden; padding: 2rem;">
                    <div style="position: absolute; inset: 0; opacity: 0.05; background-image: radial-gradient(circle, #64748b 1px, transparent 1px); background-size: 16px 16px;"></div>
                    
                    <div style="position: relative; z-index: 10; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                                <span class="badge badge-green" style="font-size: 0.65rem;">
                                    <span style="display: inline-block; width: 6px; height: 6px; background: var(--green); border-radius: 50%; margin-right: 4px; animation: pulse 1.5s infinite;"></span>
                                    Core Substrate Active
                                </span>
                            </div>
                            <h2 style="font-size: 1.5rem; font-weight: 900; margin: 0 0 0.5rem 0; text-transform: uppercase; letter-spacing: 0.5px;">Core Active</h2>
                            <p style="margin: 0; font-size: 0.8rem; color: var(--text-muted); max-w: 600px; line-height: 1.6; font-family: var(--font-tech), monospace;">
                                You are currently within the physical hardware limit of the system topography. This domain governs DNS mappings, cluster gateway credentials, low-level API application boundaries, and platform staff identities. Strict domain isolation is in effect.
                            </p>
                        </div>
                        <div class="card-neo" style="padding: 1rem; border-radius: 12px; background: rgba(0,0,0,0.2); display: flex; align-items: center; justify-content: center;">
                            <i class="ph-bold ph-cpu" style="font-size: 2.5rem; color: var(--primary);"></i>
                        </div>
                    </div>
                </div>

                <!-- Neomorphic Telemetry Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                    <!-- Disk Space -->
                    <div class="card-neo">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <span class="input-label" style="font-size: 0.7rem;">Место на диске</span>
                            <i class="ph-bold ph-hard-drive" style="font-size: 1.25rem; color: var(--primary);"></i>
                        </div>
                        <div style="font-size: 1.5rem; font-weight: 900; font-family: var(--font-tech), monospace; margin-bottom: 0.5rem;">
                            {{ $freeSpace }} GB свободно
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                            Всего занято: <strong>{{ $diskPercent }}%</strong>
                        </div>
                        <div class="meter-bar">
                            <div class="meter-fill" style="width: {{ $diskPercent }}%; background: var(--primary);"></div>
                        </div>
                    </div>

                    <!-- Queue Telemetry (Jobs) -->
                    <div class="card-neo">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <span class="input-label" style="font-size: 0.7rem;">Очереди (Jobs)</span>
                            <i class="ph-bold ph-queue" style="font-size: 1.25rem; color: var(--green);"></i>
                        </div>
                        <div style="font-size: 1.5rem; font-weight: 900; font-family: var(--font-tech), monospace; margin-bottom: 0.5rem;">
                            {{ $pendingJobs }} ожидающих
                        </div>
                        <div style="font-size: 0.75rem; color: {{ $failedJobs > 0 ? 'var(--rose)' : 'var(--green)' }}; font-weight: 700; margin-bottom: 0.75rem;">
                            {{ $failedJobs > 0 ? 'Ошибочных задач: '.$failedJobs : 'Ошибок нет' }}
                        </div>
                        <div class="meter-bar">
                            <div class="meter-fill" style="width: {{ $pendingJobs > 0 ? 100 : 0 }}%; background: var(--green);"></div>
                        </div>
                    </div>

                    <!-- Price Synclog -->
                    <div class="card-neo">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <span class="input-label" style="font-size: 0.7rem;">Синхронизация цен</span>
                            <i class="ph-bold ph-currency-dollar" style="font-size: 1.25rem; color: var(--primary);"></i>
                        </div>
                        <div style="font-size: 1.15rem; font-weight: 900; font-family: var(--font-tech), monospace; margin-bottom: 0.5rem; line-height: 1.3;">
                            {{ $lastCurrencyUpdate ? $lastCurrencyUpdate->diffForHumans() : 'Не запускалось' }}
                        </div>
                        <div style="font-size: 0.7rem; color: var(--text-muted); margin-bottom: 0.75rem; text-transform: uppercase;">
                            Обновление кросс-курсов валют
                        </div>
                        <div class="meter-bar">
                            <div class="meter-fill" style="width: 100%; background: var(--primary);"></div>
                        </div>
                    </div>

                    <!-- Catalog Parser -->
                    <div class="card-neo">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <span class="input-label" style="font-size: 0.7rem;">Парсер каталога</span>
                            <i class="ph-bold ph-database" style="font-size: 1.25rem; color: var(--green);"></i>
                        </div>
                        <div style="font-size: 1.15rem; font-weight: 900; font-family: var(--font-tech), monospace; margin-bottom: 0.5rem; line-height: 1.3;">
                            {{ $lastCatalogUpdate ? $lastCatalogUpdate->diffForHumans() : 'Не запускалось' }}
                        </div>
                        <div style="font-size: 0.7rem; color: var(--text-muted); margin-bottom: 0.75rem; text-transform: uppercase;">
                            SyncCatalogsCommand
                        </div>
                        <div class="meter-bar">
                            <div class="meter-fill" style="width: 100%; background: var(--green);"></div>
                        </div>
                    </div>
                </div>

                <!-- Live Core Health Simulation Metrics -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <!-- CPU load -->
                    <div class="card-neo">
                        <h3 style="font-size: 0.85rem; font-weight: 800; text-transform: uppercase; margin: 0 0 1rem 0; letter-spacing: 0.5px;">
                            CPU Load Telemetry
                        </h3>
                        <div style="display: flex; align-items: flex-end; gap: 0.5rem; height: 120px; padding: 0.5rem; background: rgba(0,0,0,0.2); border-radius: 8px;" id="cpu-chart">
                            <!-- Columns dynamic -->
                        </div>
                    </div>

                    <!-- Memory Allocation -->
                    <div class="card-neo">
                        <h3 style="font-size: 0.85rem; font-weight: 800; text-transform: uppercase; margin: 0 0 1rem 0; letter-spacing: 0.5px;">
                            Memory Allocation Topography
                        </h3>
                        <div style="display: flex; align-items: flex-end; gap: 0.5rem; height: 120px; padding: 0.5rem; background: rgba(0,0,0,0.2); border-radius: 8px;" id="ram-chart">
                            <!-- Columns dynamic -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: Node Console Logs -->
            <div id="tab-console-logs" class="tab-pane" style="display: none;">
                <div class="card-neo">
                    <h2 style="font-size: 1.15rem; font-weight: 800; text-transform: uppercase; margin-top: 0; margin-bottom: 1.5rem; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-card); padding-bottom: 0.75rem;">
                        Real-time Node logs console
                    </h2>
                    
                    <div class="console-terminal" id="console-terminal-box">
                        <div class="console-line info">[18:52:01] INFO: Operations engine started successfully.</div>
                        <div class="console-line info">[18:52:05] INFO: Database transaction logging active. Connection pool depth: 64.</div>
                        <div class="console-line warning">[18:52:12] WARNING: Stale observability telemetry detected for RUB spot rails. Attempting auto-recovery.</div>
                        <div class="console-line info">[18:52:15] SUCCESS: Telemetry auto-recovery completed. Observability index normalized.</div>
                    </div>

                    <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                        <input type="text" id="console-cmd-input" class="input-field" placeholder="Выполнить низкоуровневую команду ядра (например, php artisan queue:work)..." onkeydown="if(event.key === 'Enter') runConsoleCommand()">
                        <button onclick="runConsoleCommand()" class="card-neo" style="padding: 0.8rem 1.5rem; display: flex; align-items: center; gap: 0.5rem; cursor: pointer; border-radius: 8px; font-weight: 800; text-transform: uppercase; font-size: 0.8rem; background: var(--primary); color: #fff; border: none;">
                            Выполнить <i class="ph-bold ph-terminal-window"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // --- Multi-Theme Switcher Engine ---
        function setTheme(theme) {
            if (window.MeanlyTheme && typeof window.MeanlyTheme.apply === 'function') {
                theme = window.MeanlyTheme.apply(theme);
            }
            document.body.setAttribute('data-theme', theme);
            document.documentElement.setAttribute('data-theme', theme);
            document.querySelectorAll('.skin-btn').forEach(btn => btn.classList.remove('active'));
            const activeBtn = document.getElementById(`skin-btn-${theme}`);
            if (activeBtn) activeBtn.classList.add('active');
            localStorage.setItem('theme', theme);
            var cookieDomain = @json(config('session.domain') ?? null);
            var domainSuffix = cookieDomain ? '; domain=' + cookieDomain : '';
            document.cookie = `theme=${theme}; path=/; max-age=31536000; SameSite=Lax${domainSuffix}`;

            fetch('{{ route("ops.dashboard.theme") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ theme: theme })
            }).catch(err => console.error('Failed to sync theme with DB:', err));
        }

        // Initialize theme on load
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('theme')) {
            localStorage.setItem('theme', urlParams.get('theme').toLowerCase());
        }
        const dbTheme = "{{ auth()->user()->theme ?? '' }}";
        const savedTheme = dbTheme || localStorage.getItem('theme') || 'consortium';
        setTheme(savedTheme);

        // Toggle Console Switcher Dropdown
        function toggleConsoleDropdown(event) {
            event.stopPropagation();
            const dd = document.getElementById('console-dropdown');
            dd.style.display = dd.style.display === 'none' ? 'block' : 'none';
        }

        window.onclick = function() {
            document.getElementById('console-dropdown').style.display = 'none';
        }

        // Tab Switcher Engine
        function switchTab(tabId) {
            document.querySelectorAll('.tab-pane').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.menu-item').forEach(el => el.classList.remove('active'));
            
            document.getElementById(`tab-${tabId}`).style.display = 'block';
            document.getElementById(`menu-${tabId}`).classList.add('active');
        }

        // Dynamic System Simulation meters
        const cpuChart = document.getElementById('cpu-chart');
        const ramChart = document.getElementById('ram-chart');
        const cpuData = Array(20).fill(15);
        const ramData = Array(20).fill(40);

        function updateCharts() {
            // CPU Simulation
            cpuData.shift();
            cpuData.push(Math.floor(Math.random() * 45) + 10);
            cpuChart.innerHTML = cpuData.map(val => `
                <div style="flex-grow: 1; background: var(--primary); height: ${val}%; border-radius: 2px; min-width: 4px; transition: height 0.3s;"></div>
            `).join('');

            // Memory Simulation
            ramData.shift();
            ramData.push(Math.floor(Math.random() * 15) + 40);
            ramChart.innerHTML = ramData.map(val => `
                <div style="flex-grow: 1; background: var(--green); height: ${val}%; border-radius: 2px; min-width: 4px; transition: height 0.3s;"></div>
            `).join('');
        }

        setInterval(updateCharts, 1000);
        updateCharts();

        // Run low level command execution
        function runConsoleCommand() {
            const input = document.getElementById('console-cmd-input');
            const cmd = input.value.trim();
            if (!cmd) return;

            const box = document.getElementById('console-terminal-box');
            const time = new Date().toLocaleTimeString('ru-RU');

            box.innerHTML += `<div class="console-line" style="color: #fff;">[${time}] root# ${cmd}</div>`;
            input.value = '';
            box.scrollTop = box.scrollHeight;

            // Simulate loading lines
            setTimeout(() => {
                box.innerHTML += `<div class="console-line info">[${time}] EXECUTING: Kernel sandbox running command thread...</div>`;
                box.scrollTop = box.scrollHeight;
            }, 300);

            setTimeout(() => {
                if (cmd.includes('artisan')) {
                    box.innerHTML += `<div class="console-line info">[${time}] SUCCESS: Task scheduled in global queues. Thread closed.</div>`;
                } else {
                    box.innerHTML += `<div class="console-line error">[${time}] ERROR: Access denied. Kernel command line sandbox restricted to verified signatures.</div>`;
                }
                box.scrollTop = box.scrollHeight;
            }, 800);
        }
        function getActiveHoliday() {
            return document.body.getAttribute('data-holiday') || null;
        }

        function initAtmosphericHolidayFX() {
            const holiday = getActiveHoliday();
            if (!holiday) return;
            document.body.setAttribute('data-holiday', holiday);

            if (holiday === 'babel-library') {
                console.log("%c📚 [Library of Babel — Kernel] 24 AUGUST: \"La Biblioteca es ilimitada y periódica...\" 🌌🚪", "color: #b45309; font-weight: bold; font-size: 14px;");
            }

            const canvas = document.createElement('canvas');
            canvas.id = 'holiday-canvas-fx';
            Object.assign(canvas.style, {
                position: 'fixed', inset: '0', pointerEvents: 'none',
                zIndex: '1', opacity: '0.55'
            });
            document.body.appendChild(canvas);
            const ctx = canvas.getContext('2d');
            let width = canvas.width = window.innerWidth;
            let height = canvas.height = window.innerHeight;
            window.addEventListener('resize', () => { width = canvas.width = window.innerWidth; height = canvas.height = window.innerHeight; });

            const particles = [];
            const maxParticles = 40;

            class Particle {
                constructor() { this.reset(); }
                reset() {
                    this.x = Math.random() * width;
                    this.y = height + 20;
                    this.type = Math.floor(Math.random() * 4);
                    this.size = Math.random() * 10 + 8;
                    this.speedY = -(Math.random() * 0.4 + 0.2);
                    this.speedX = Math.random() * 0.15 - 0.075;
                    this.alpha = Math.random() * 0.35 + 0.55;
                    this.angle = Math.random() * Math.PI * 2;
                    this.rotSpeed = (Math.random() - 0.5) * 0.008;
                    // Babel letter — locked at reset
                    const _abc = "abcdefghijklmnopqrstuvwxyz,.";
                    this.babelChar = _abc[Math.floor(Math.random() * _abc.length)];
                }
                update() {
                    this.x += this.speedX;
                    this.y += this.speedY;
                    this.angle += this.rotSpeed;
                    if (this.y < -30) this.reset();
                }
                draw() {
                    ctx.save();
                    ctx.globalAlpha = this.alpha;
                    ctx.translate(this.x, this.y);
                    ctx.rotate(this.angle);
                    const scale = this.size;
                    const pt = this.type % 4;
                    if (pt === 0) {
                        // Hexagon
                        ctx.strokeStyle = '#d97706';
                        ctx.lineWidth = scale * 0.12;
                        ctx.beginPath();
                        for (let h = 0; h < 6; h++) {
                            const hx = Math.cos(h * Math.PI / 3) * scale;
                            const hy = Math.sin(h * Math.PI / 3) * scale;
                            h === 0 ? ctx.moveTo(hx, hy) : ctx.lineTo(hx, hy);
                        }
                        ctx.closePath();
                        ctx.stroke();
                    } else if (pt === 1) {
                        // Open book
                        ctx.fillStyle = '#fef3c7'; ctx.strokeStyle = '#78350f'; ctx.lineWidth = scale * 0.08;
                        ctx.beginPath(); ctx.moveTo(0, scale*0.4);
                        ctx.bezierCurveTo(-scale*0.4,scale*0.2,-scale*0.6,scale*0.4,-scale*0.8,scale*0.2);
                        ctx.lineTo(-scale*0.8,-scale*0.4);
                        ctx.bezierCurveTo(-scale*0.6,-scale*0.2,-scale*0.4,-scale*0.4,0,-scale*0.2);
                        ctx.closePath(); ctx.fill(); ctx.stroke();
                        ctx.beginPath(); ctx.moveTo(0, scale*0.4);
                        ctx.bezierCurveTo(scale*0.4,scale*0.2,scale*0.6,scale*0.4,scale*0.8,scale*0.2);
                        ctx.lineTo(scale*0.8,-scale*0.4);
                        ctx.bezierCurveTo(scale*0.6,-scale*0.2,scale*0.4,-scale*0.4,0,-scale*0.2);
                        ctx.closePath(); ctx.fill(); ctx.stroke();
                    } else if (pt === 2) {
                        // Babel letter (stable)
                        ctx.fillStyle = '#f59e0b';
                        ctx.font = `italic bold ${Math.max(10, scale * 0.85)}px serif`;
                        ctx.textAlign = 'center';
                        ctx.fillText(this.babelChar, 0, scale * 0.3);
                    } else {
                        // Scroll ellipse
                        ctx.fillStyle = '#fef3c7'; ctx.strokeStyle = '#d97706'; ctx.lineWidth = scale * 0.06;
                        ctx.beginPath();
                        ctx.ellipse(0, 0, scale * 0.7, scale * 0.25, Math.PI / 6, 0, Math.PI * 2);
                        ctx.fill(); ctx.stroke();
                    }
                    ctx.restore();
                }
            }

            for (let i = 0; i < maxParticles; i++) {
                const p = new Particle();
                p.y = Math.random() * height; // scatter on first load
                particles.push(p);
            }

            function loop() {
                ctx.clearRect(0, 0, width, height);
                particles.forEach(p => { p.update(); p.draw(); });
                requestAnimationFrame(loop);
            }
            loop();
        }
        initAtmosphericHolidayFX();
    </script>
</body>
</html>
