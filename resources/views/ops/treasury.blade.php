<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Treasury Nexus — Meanly Systems</title>
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
        body[data-theme="retro"] .menu-item.active {
            background: #e9d5ff !important;
            border: 3px solid #000000 !important;
            box-shadow: 2px 2px 0px #000000 !important;
            color: #000000 !important;
            border-radius: 0px !important;
        }
        body[data-theme="retro"] .menu-item:hover:not(.active) {
            background: #f3f4f6 !important;
        }
        body[data-theme="retro"] .logo-dot {
            background: #000000 !important;
            border: 2px solid #000000 !important;
        }
        body[data-theme="retro"] input, body[data-theme="retro"] select, body[data-theme="retro"] textarea {
            background: #ffffff !important;
            border: 3px solid #000000 !important;
            border-radius: 0px !important;
            color: #000000 !important;
        }
        body[data-theme="retro"] .badge {
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
            box-shadow: 2px 2px 0px #000000 !important;
            color: #000000 !important;
            font-weight: bold !important;
        }
        body[data-theme="retro"] .badge-rose { background: #fca5a5 !important; }
        body[data-theme="retro"] .badge-green { background: #86efac !important; }
        body[data-theme="retro"] .badge-orange { background: #fde047 !important; }

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

        /* Input Styles */
        .input-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .input-label {
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }
        .input-field {
            background: var(--bg-input);
            border: 1px solid var(--border-card);
            border-radius: 8px;
            padding: 0.8rem 1rem;
            color: var(--text-main);
            font-size: 0.875rem;
            font-family: var(--font-tech), monospace;
            outline: none;
            transition: all 0.2s;
            width: 100%;
            box-sizing: border-box;
        }
        .input-field:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-glow);
        }

        /* --- Custom Grid Heatmap Table --- */
        .matrix-table {
            width: 100%;
            border-collapse: collapse;
            font-family: var(--font-tech), monospace;
            font-size: 0.85rem;
        }
        .matrix-table th {
            padding: 1rem 1.25rem;
            font-size: 0.7rem;
            font-weight: 850;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: right;
            border-bottom: 1px solid var(--border-card);
            color: var(--text-muted);
            background: rgba(0,0,0,0.2);
        }
        .matrix-table th:first-child {
            text-align: left;
            position: sticky;
            left: 0;
            background: var(--bg-card);
            z-index: 10;
        }
        .matrix-table td {
            padding: 1rem 1.25rem;
            text-align: right;
            border-bottom: 1px solid rgba(255,255,255,0.02);
            transition: all 0.15s;
        }
        .matrix-table tr:hover td {
            background: rgba(255, 255, 255, 0.01);
        }
        .matrix-table td.identity-cell {
            color: var(--text-muted);
            opacity: 0.4;
            background: rgba(0,0,0,0.1) !important;
        }
        .matrix-table td.high-rate {
            color: var(--rose);
            font-weight: 700;
        }
        .matrix-table td.low-rate {
            color: var(--green);
            font-weight: 700;
        }

        /* AI chat scaffold */
        .chat-messages {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            height: 350px;
            overflow-y: auto;
            padding: 1rem;
            background: var(--bg-input);
            border: 1px solid var(--border-card);
            border-radius: 12px;
            margin-bottom: 1rem;
        }
        .chat-bubble {
            max-width: 80%;
            padding: 0.85rem 1.2rem;
            border-radius: 14px;
            font-size: 0.85rem;
            line-height: 1.5;
        }
        .chat-bubble.user {
            background: var(--primary-glow);
            color: var(--primary);
            border: 1px solid var(--border-neon);
            align-self: flex-end;
            border-bottom-right-radius: 2px;
        }
        .chat-bubble.system {
            background: rgba(255,255,255,0.03);
            color: var(--text-main);
            border: 1px solid var(--border-card);
            align-self: flex-start;
            border-bottom-left-radius: 2px;
        }
        .suggestion-chip {
            padding: 0.4rem 0.8rem;
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border-card);
            border-radius: 9999px;
            font-size: 0.75rem;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s;
        }
        .suggestion-chip:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-glow);
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
    </style>
</head>
<body data-theme="consortium">
@include('partials.theme-sync-body')
    <div class="container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="sidebar-logo console-selector-wrapper" style="position: relative; cursor: pointer; display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;" onclick="toggleConsoleDropdown(event)">
                <div style="display: flex; align-items: center; gap: 0.5rem; width: 100%;">
                    <span class="logo-dot"></span>
                    <span class="logo-text-consortium" style="font-family: var(--font-tech), monospace;">Meanly <span class="logo-sub" style="background: var(--primary-glow); color: var(--primary); border: 1px solid var(--border-neon); padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">Treasury ▾</span></span>
                    <span class="logo-text-partner" style="display: none; font-family: var(--font-tech), monospace;">Meanly <span class="logo-sub" style="background: rgba(245, 158, 11, 0.1); color: var(--primary); border: 1px solid var(--border-neon); padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">Partner ▾</span></span>
                    <span class="logo-text-retro" style="display: none; font-family: var(--font-tech), monospace;">Meanly <span class="logo-sub" style="background: #000; color: #fff; border: 2px solid #000; padding: 2px 6px; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px;">Treasury ▾</span></span>
                </div>
                
                <div id="console-dropdown" class="card-neo" style="display: none; position: absolute; top: 100%; right: 0; width: 180px; margin-top: 0.5rem; background: var(--bg-sidebar); border: 1px solid var(--border-card); border-radius: 8px; z-index: 1000; box-shadow: var(--shadow-neo); overflow: hidden; padding: 0.25rem;">
                    <a href="/ops" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 0.85rem; color: var(--text-muted); font-size: 0.75rem; text-decoration: none; font-weight: 800; border-radius: 6px; font-family: var(--font-tech), monospace;">
                        🛡️ OPERATIONS
                    </a>
                    <a href="/tribunal" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 0.85rem; color: var(--text-muted); font-size: 0.75rem; text-decoration: none; font-weight: 800; border-radius: 6px; font-family: var(--font-tech), monospace;">
                        🏛️ TRIBUNAL
                    </a>
                    <a href="/treasury" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 0.85rem; color: var(--text-main); font-size: 0.75rem; text-decoration: none; font-weight: 800; background: rgba(255,255,255,0.03); border-radius: 6px; font-family: var(--font-tech), monospace;">
                        🏦 TREASURY
                    </a>
                    <a href="/kernel" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 0.85rem; color: var(--text-muted); font-size: 0.75rem; text-decoration: none; font-weight: 800; border-radius: 6px; font-family: var(--font-tech), monospace;">
                        ⚙️ KERNEL
                    </a>
                    <a href="/support" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 0.85rem; color: var(--text-muted); font-size: 0.75rem; text-decoration: none; font-weight: 800; border-radius: 6px; font-family: var(--font-tech), monospace;">
                        📞 SUPPORT
                    </a>
                </div>
            </div>

            <div class="sidebar-menu">
                <div class="sidebar-section-title">Казначейство</div>
                <a onclick="switchTab('pathfinder')" class="menu-item active" id="menu-pathfinder">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"></polygon><line x1="9" y1="3" x2="9" y2="18"></line><line x1="15" y1="6" x2="15" y2="21"></line></svg>
                    Pathfinder
                </a>
                <a onclick="switchTab('matrix')" class="menu-item" id="menu-matrix">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line><line x1="15" y1="3" x2="15" y2="21"></line><line x1="3" y1="9" x2="21" y2="9"></line><line x1="3" y1="15" x2="21" y2="15"></line></svg>
                    FX Cross Matrix
                </a>
                <a onclick="switchTab('ai-analyst')" class="menu-item" id="menu-ai-analyst">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="20" y1="15" x2="23" y2="15"></line><line x1="1" y1="9" x2="4" y2="9"></line><line x1="1" y1="15" x2="4" y2="15"></line></svg>
                    AI FX Analyst
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
                    <span class="badge badge-rose" style="margin-bottom: 0.5rem;">Payment Router</span>
                    <h1 class="header-title">Treasury Nexus Console</h1>
                </div>

            </div>

            <!-- TAB 1: Sovereign Pathfinder -->
            <div id="tab-pathfinder" class="tab-pane">
                <div class="card-neo" style="margin-bottom: 2rem;">
                    <h2 style="font-size: 1.15rem; font-weight: 800; text-transform: uppercase; margin-top: 0; margin-bottom: 1.5rem; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-card); padding-bottom: 0.75rem;">
                        Sovereign Liquidity Router
                    </h2>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)) 80px; gap: 1.5rem; align-items: flex-end;">
                        <div class="input-group">
                            <span class="input-label">From Currency</span>
                            <select id="from-code" class="input-field" onchange="runPathfinderCalculation()">
                                @foreach($currencyOptions as $c)
                                    <option value="{{ $c['code'] }}" {{ $c['code'] === 'RUB' ? 'selected' : '' }}>
                                        {{ $c['flag'] }} {{ $c['code'] }} — {{ $c['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div style="display: flex; justify-content: center; padding-bottom: 5px;">
                            <button onclick="swapCurrencies()" class="card-neo" style="padding: 0.75rem; display: flex; align-items: center; justify-content: center; cursor: pointer; border-radius: 8px;">
                                <i class="ph-bold ph-arrows-left-right" style="font-size: 1.25rem; color: var(--primary);"></i>
                            </button>
                        </div>
                        
                        <div class="input-group">
                            <span class="input-label">To Currency</span>
                            <select id="to-code" class="input-field" onchange="runPathfinderCalculation()">
                                @foreach($currencyOptions as $c)
                                    <option value="{{ $c['code'] }}" {{ $c['code'] === 'USD' ? 'selected' : '' }}>
                                        {{ $c['flag'] }} {{ $c['code'] }} — {{ $c['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="input-group" style="grid-column: span 2;">
                            <span class="input-label">Amount to Send</span>
                            <input type="number" id="send-amount" class="input-field" value="1000" oninput="runPathfinderCalculation()">
                        </div>
                    </div>
                </div>

                <div>
                    <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.5rem; text-transform: uppercase; letter-spacing: 0.5px;">
                        Available Liquidity Routes
                    </h3>
                    
                    <div id="routes-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
                        <!-- Dynamically filled by JS -->
                    </div>
                </div>
            </div>

            <!-- TAB 2: FX Cross Matrix -->
            <div id="tab-matrix" class="tab-pane" style="display: none;">
                <div class="card-neo" style="overflow-x: auto;">
                    <h2 style="font-size: 1.15rem; font-weight: 800; text-transform: uppercase; margin-top: 0; margin-bottom: 1.5rem; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-card); padding-bottom: 0.75rem;">
                        FX Cross-Rate Matrix
                    </h2>
                    
                    <table class="matrix-table">
                        <thead>
                            <tr>
                                <th style="text-align: left;">Base \ Quote</th>
                                @foreach($matrixCurrencies as $col)
                                    <th>{{ $col }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($matrixCurrencies as $row)
                                <tr>
                                    <td style="text-align: left; font-weight: 800; position: sticky; left: 0; background: var(--bg-card); z-index: 10;">
                                        {{ $row }}
                                    </td>
                                    @foreach($matrixCurrencies as $col)
                                        @php
                                            $val = $matrix[$row][$col] ?? 0;
                                            $isBase = $row === $col;
                                            $cellClass = '';
                                            if ($isBase) {
                                                $cellClass = 'identity-cell';
                                            } elseif ($val > 100) {
                                                $cellClass = 'high-rate';
                                            } elseif ($val < 1) {
                                                $cellClass = 'low-rate';
                                            }
                                        @endphp
                                        <td class="{{ $cellClass }}">
                                            @if($isBase)
                                                —
                                            @else
                                                {{ $val < 0.01 ? number_format($val, 6) : number_format($val, 4) }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <div class="card-neo" style="margin-top: 1.5rem; display: flex; align-items: flex-start; gap: 1rem;">
                    <i class="ph-bold ph-info" style="font-size: 1.5rem; color: var(--primary); flex-shrink: 0; margin-top: 2px;"></i>
                    <div>
                        <h4 style="margin: 0 0 0.5rem 0; font-weight: 800; text-transform: uppercase; font-size: 0.85rem;">Как читать матрицу ликвидности:</h4>
                        <p style="margin: 0; font-size: 0.8rem; color: var(--text-muted); line-height: 1.5;">
                            Значения в ячейках показывают стоимость 1 единицы валюты из строки (Base) в валюте из столбца (Quote). Например, пересечение строки <strong>USD</strong> и столбца <strong>RUB</strong> показывает стоимость 1 Доллара в Рублях. Все котировки рассчитываются в реальном времени на основе телеметрии сетей клиринга B2B-партнеров.
                        </p>
                    </div>
                </div>
            </div>

            <!-- TAB 3: AI Analyst -->
            <div id="tab-ai-analyst" class="tab-pane" style="display: none;">
                <div class="card-neo">
                    <h2 style="font-size: 1.15rem; font-weight: 800; text-transform: uppercase; margin-top: 0; margin-bottom: 1.5rem; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-card); padding-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="ph-bold ph-sparkle" style="color: var(--primary);"></i> AI FX Liquidity Analyst
                    </h2>
                    
                    <div class="chat-messages" id="chat-messages-box">
                        <div class="chat-bubble system">
                            Приветствую! Я ваш персональный аналитик казначейства Meanly Systems. Задайте любой вопрос о кросс-курсах, путях обхода лимитов или стресс-тестах ликвидности.
                        </div>
                    </div>
                    
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem;" id="suggestions-box">
                        <div class="suggestion-chip" onclick="sendSuggestedMessage('Сделай анализ ликвидности RUB/USD')">📊 Анализ RUB/USD</div>
                        <div class="suggestion-chip" onclick="sendSuggestedMessage('Как сейчас выгоднее всего вывести AED в RUB?')">🏦 Вывод AED ➔ RUB</div>
                        <div class="suggestion-chip" onclick="sendSuggestedMessage('Проведи стресс-тест транзитных хабов СНГ')">📈 Стресс-тест хабов</div>
                    </div>
                    
                    <div style="display: flex; gap: 1rem;">
                        <input type="text" id="chat-input-field" class="input-field" placeholder="Спросите о ликвидности или маршрутах..." onkeydown="if(event.key === 'Enter') sendAiMessage()">
                        <button onclick="sendAiMessage()" class="card-neo" style="padding: 0.8rem 1.5rem; display: flex; align-items: center; gap: 0.5rem; cursor: pointer; border-radius: 8px; font-weight: 800; text-transform: uppercase; font-size: 0.8rem; background: var(--primary); color: #fff; border: none;">
                            Отправить <i class="ph-bold ph-paper-plane-right"></i>
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

        // Initialize theme and holiday on load
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('theme')) {
            localStorage.setItem('theme', urlParams.get('theme').toLowerCase());
        }
        if (urlParams.has('holiday')) {
            const override = urlParams.get('holiday');
            if (override && override !== 'none') {
                localStorage.setItem('holiday-override', override.toLowerCase());
            } else {
                localStorage.removeItem('holiday-override');
            }
        }
        const savedHoliday = localStorage.getItem('holiday-override');
        if (savedHoliday) {
            document.body.setAttribute('data-holiday', savedHoliday);
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

        // Swap Currency pairs
        function swapCurrencies() {
            const from = document.getElementById('from-code');
            const to = document.getElementById('to-code');
            const temp = from.value;
            from.value = to.value;
            to.value = temp;
            runPathfinderCalculation();
        }

        // Recalculate routes via AJAX
        function runPathfinderCalculation() {
            const fromCode = document.getElementById('from-code').value;
            const toCode = document.getElementById('to-code').value;
            const amount = document.getElementById('send-amount').value || 1000;
            const grid = document.getElementById('routes-grid');

            // Render skeleton loader
            grid.innerHTML = `
                <div class="card-neo" style="grid-column: span 3; text-align: center; padding: 3rem 0;">
                    <i class="ph-bold ph-spinner ph-spin" style="font-size: 2.5rem; color: var(--primary); margin-bottom: 1rem;"></i>
                    <p style="color: var(--text-muted); font-weight: 700;">Поиск оптимальных коридоров ликвидности...</p>
                </div>
            `;

            fetch('{{ route("treasury.pathfinder.calculate") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    from_code: fromCode,
                    to_code: toCode,
                    amount: amount
                })
            })
            .then(res => res.json())
            .then(data => {
                grid.innerHTML = '';
                if (!data.routes || data.routes.length === 0) {
                    grid.innerHTML = `
                        <div class="card-neo" style="grid-column: span 3; text-align: center; padding: 3rem 0;">
                            <i class="ph-bold ph-warning-circle" style="font-size: 2.5rem; color: var(--primary); margin-bottom: 1rem;"></i>
                            <p style="color: var(--text-muted); font-weight: 700;">Коридоры ликвидности для данной пары не обнаружены. Попробуйте скорректировать объем или валюты.</p>
                        </div>
                    `;
                    return;
                }

                data.routes.forEach(route => {
                    const finalAmountStr = Number(route.final_amount).toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    const spreadStr = Number(route.spread).toFixed(2);
                    const lsiVal = Number(route.lsi).toFixed(0);
                    const obsVal = (Number(route.observability) * 100).toFixed(0);

                    // Create dynamic inbound/outbound badges
                    let inboundHtml = '';
                    if (route.inbound_rails) {
                        route.inbound_rails.forEach(rail => {
                            inboundHtml += `<span style="font-size: 0.65rem; padding: 0.1rem 0.4rem; background: rgba(255,255,255,0.03); border: 1px solid var(--border-card); border-radius: 4px; color: var(--text-muted); margin-right: 4px;">${rail}</span>`;
                        });
                    }

                    let outboundHtml = '';
                    if (route.outbound_rails) {
                        route.outbound_rails.forEach(rail => {
                            outboundHtml += `<span style="font-size: 0.65rem; padding: 0.1rem 0.4rem; background: rgba(255,255,255,0.03); border: 1px solid var(--border-card); border-radius: 4px; color: var(--text-muted); margin-right: 4px;">${rail}</span>`;
                        });
                    }

                    // Build route cards with neomorphic styles
                    grid.innerHTML += `
                        <div class="card-neo" style="display: flex; flex-direction: column; justify-content: space-between; position: relative; overflow: hidden; ${route.is_over_limit ? 'opacity: 0.65;' : ''}">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                                <div>
                                    <h4 style="margin: 0; font-size: 1.1rem; font-weight: 800; text-transform: uppercase;">${route.name}</h4>
                                    <p style="margin: 0.25rem 0 0 0; font-size: 0.8rem; color: var(--text-muted);">${route.description}</p>
                                </div>
                                <span class="badge ${route.color === 'success' ? 'badge-green' : 'badge-orange'}">${route.trust}</span>
                            </div>

                            <div style="margin-bottom: 1.5rem;">
                                <div style="font-size: 2rem; font-weight: 900; line-height: 1; font-family: var(--font-tech), monospace;">
                                    ${finalAmountStr} <span style="font-size: 1rem; color: var(--text-muted); font-weight: 500;">${toCode}</span>
                                </div>
                                <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-top: 0.5rem; text-transform: uppercase; font-family: var(--font-tech), monospace;">
                                    Rate: ${route.rate_display} | Spread: ${spreadStr}%
                                </div>
                            </div>

                            <div style="margin-bottom: 1rem; padding: 0.5rem; background: rgba(0,0,0,0.15); border: 1px solid var(--border-card); border-radius: 8px; font-size: 0.75rem; display: flex; align-items: center; gap: 0.5rem; color: var(--text-muted); font-family: var(--font-tech), monospace;">
                                <i class="ph-bold ph-wallet" style="color: var(--primary);"></i>
                                Capacity: <strong>${route.capacity_str}</strong>
                            </div>

                            <div style="margin-bottom: 1.25rem; display: flex; flex-direction: column; gap: 0.5rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); width: 25px;">In:</span>
                                    <div style="display: flex; flex-wrap: wrap; gap: 2px;">${inboundHtml}</div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); width: 25px;">Out:</span>
                                    <div style="display: flex; flex-wrap: wrap; gap: 2px;">${outboundHtml}</div>
                                </div>
                            </div>

                            <div style="display: flex; flex-direction: column; gap: 0.75rem; border-top: 1px solid var(--border-card); padding-top: 1rem;">
                                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.75rem;">
                                    <span style="color: var(--text-muted); font-weight: 700;">Observability:</span>
                                    <span style="font-family: var(--font-tech), monospace; font-weight: 800; color: var(--green);">${obsVal}%</span>
                                </div>
                                <div class="meter-bar">
                                    <div class="meter-fill" style="width: ${obsVal}%; background: var(--green);"></div>
                                </div>

                                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.75rem;">
                                    <span style="color: var(--text-muted); font-weight: 700;">Liquidity Stress:</span>
                                    <span style="font-family: var(--font-tech), monospace; font-weight: 800; color: ${lsiVal > 40 ? 'var(--rose)' : 'var(--primary)'};">${lsiVal}%</span>
                                </div>
                                <div class="meter-bar">
                                    <div class="meter-fill" style="width: ${lsiVal}%; background: ${lsiVal > 40 ? 'var(--rose)' : 'var(--primary)'};"></div>
                                </div>
                            </div>

                            <div style="margin-top: 1.25rem; font-size: 0.75rem; font-weight: 800; color: var(--text-main); font-family: var(--font-tech), monospace; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="ph-bold ph-swap" style="color: var(--primary);"></i>
                                ${route.methods}
                            </div>
                        </div>
                    `;
                });
            });
        }

        // Run calculation once on page load
        runPathfinderCalculation();

        // --- AI Analyst Chat Logic ---
        function sendSuggestedMessage(msg) {
            document.getElementById('chat-input-field').value = msg;
            sendAiMessage();
        }

        function sendAiMessage() {
            const input = document.getElementById('chat-input-field');
            const msg = input.value.trim();
            if (!msg) return;

            const box = document.getElementById('chat-messages-box');
            
            // Add user message bubble
            box.innerHTML += `<div class="chat-bubble user">${msg}</div>`;
            input.value = '';
            box.scrollTop = box.scrollHeight;

            // Typing indicator
            const typingId = 'typing-' + Date.now();
            box.innerHTML += `<div class="chat-bubble system" id="${typingId}"><i class="ph-bold ph-circle-notch ph-spin"></i> Анализирую транзитные каналы...</div>`;
            box.scrollTop = box.scrollHeight;

            // Fetch response from AI router endpoint (reusing AI Chat endpoint from Ops)
            fetch('{{ route("ops.dashboard.ai.chat") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    message: `Ты профессиональный AI-консультант казначейства Meanly Systems (Treasury Nexus). Отвечай в рамках финансовых транзакций, кросс-курсов валют и санкционных ограничений. Вопрос пользователя: ${msg}`
                })
            })
            .then(res => res.json())
            .then(data => {
                const typingIndicator = document.getElementById(typingId);
                if (typingIndicator) typingIndicator.remove();

                const answer = data.answer || "Ошибка связи с ядром AI-советника. Повторите запрос позже.";
                box.innerHTML += `<div class="chat-bubble system">${answer}</div>`;
                box.scrollTop = box.scrollHeight;
            })
            .catch(() => {
                const typingIndicator = document.getElementById(typingId);
                if (typingIndicator) typingIndicator.remove();
                box.innerHTML += `<div class="chat-bubble system">Связь прервана. Проверьте работоспособность локального сервера Ollama.</div>`;
                box.scrollTop = box.scrollHeight;
            });
        }
    </script>
</body>
</html>
