<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Hub — Meanly Systems</title>
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

        /* Support ticket elements */
        .ticket-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--border-card);
            transition: all 0.2s;
            cursor: pointer;
        }
        .ticket-row:hover {
            background: rgba(255,255,255,0.02);
            transform: translateX(4px);
        }
        .ticket-row:last-child {
            border-bottom: none;
        }

        .chat-messages {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            height: 300px;
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
        .chat-bubble.admin {
            background: var(--primary-glow);
            color: var(--primary);
            border: 1px solid var(--border-neon);
            align-self: flex-end;
            border-bottom-right-radius: 2px;
        }
        .chat-bubble.user {
            background: rgba(255,255,255,0.03);
            color: var(--text-main);
            border: 1px solid var(--border-card);
            align-self: flex-start;
            border-bottom-left-radius: 2px;
        }
        .canned-chip {
            padding: 0.4rem 0.8rem;
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border-card);
            border-radius: 9999px;
            font-size: 0.75rem;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s;
        }
        .canned-chip:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-glow);
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
                    <span class="logo-text-consortium" style="font-family: var(--font-tech), monospace;">Meanly <span class="logo-sub" style="background: var(--primary-glow); color: var(--primary); border: 1px solid var(--border-neon); padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">Support ▾</span></span>
                    <span class="logo-text-partner" style="display: none; font-family: var(--font-tech), monospace;">Meanly <span class="logo-sub" style="background: rgba(245, 158, 11, 0.1); color: var(--primary); border: 1px solid var(--border-neon); padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">Partner ▾</span></span>
                    <span class="logo-text-retro" style="display: none; font-family: var(--font-tech), monospace;">Meanly <span class="logo-sub" style="background: #000; color: #fff; border: 2px solid #000; padding: 2px 6px; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px;">Support ▾</span></span>
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
                    <a href="/kernel" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 0.85rem; color: var(--text-muted); font-size: 0.75rem; text-decoration: none; font-weight: 800; border-radius: 6px; font-family: var(--font-tech), monospace;">
                        ⚙️ KERNEL
                    </a>
                    <a href="/support" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 0.85rem; color: var(--text-main); font-size: 0.75rem; text-decoration: none; font-weight: 800; background: rgba(255,255,255,0.03); border-radius: 6px; font-family: var(--font-tech), monospace;">
                        📞 SUPPORT
                    </a>
                </div>
            </div>

            <div class="sidebar-menu">
                <div class="sidebar-section-title">Поддержка</div>
                <a onclick="switchTab('overview')" class="menu-item active" id="menu-overview">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    Overview
                </a>
                <a onclick="switchTab('terminal')" class="menu-item" id="menu-terminal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 14 15 20 9"></polyline><polyline points="14 9 20 9 20 15"></polyline></svg>
                    Incident Terminal
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
                    <span class="badge badge-green" style="margin-bottom: 0.5rem;">SLA FULFILLED: 98.4%</span>
                    <h1 class="header-title">Support Hub Console</h1>
                </div>

            </div>

            <!-- TAB 1: Support Overview -->
            <div id="tab-overview" class="tab-pane">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                    <!-- Total -->
                    <div class="card-neo">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <span class="input-label" style="font-size: 0.7rem;">Всего тикетов</span>
                            <i class="ph-bold ph-folder-open" style="font-size: 1.25rem; color: var(--primary);"></i>
                        </div>
                        <div style="font-size: 2.25rem; font-weight: 900; font-family: var(--font-tech), monospace;">
                            {{ $totalTickets }}
                        </div>
                        <p style="margin: 0.5rem 0 0 0; font-size: 0.75rem; color: var(--text-muted);">
                            Общий архив обращений
                        </p>
                    </div>

                    <!-- Open -->
                    <div class="card-neo">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <span class="input-label" style="font-size: 0.7rem;">Открытые инциденты</span>
                            <i class="ph-bold ph-bell-ringing" style="font-size: 1.25rem; color: var(--rose);"></i>
                        </div>
                        <div style="font-size: 2.25rem; font-weight: 900; font-family: var(--font-tech), monospace; color: var(--rose);">
                            {{ $openTickets }}
                        </div>
                        <p style="margin: 0.5rem 0 0 0; font-size: 0.75rem; color: var(--text-muted);">
                            Требуют немедленной реакции
                        </p>
                    </div>

                    <!-- Closed -->
                    <div class="card-neo">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <span class="input-label" style="font-size: 0.7rem;">Решенные запросы</span>
                            <i class="ph-bold ph-check-circle" style="font-size: 1.25rem; color: var(--green);"></i>
                        </div>
                        <div style="font-size: 2.25rem; font-weight: 900; font-family: var(--font-tech), monospace; color: var(--green);">
                            {{ $closedTickets }}
                        </div>
                        <p style="margin: 0.5rem 0 0 0; font-size: 0.75rem; color: var(--text-muted);">
                            Успешно закрытые тикеты
                        </p>
                    </div>

                    <!-- SLA -->
                    <div class="card-neo">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <span class="input-label" style="font-size: 0.7rem;">SLA Response Time</span>
                            <i class="ph-bold ph-timer" style="font-size: 1.25rem; color: var(--primary);"></i>
                        </div>
                        <div style="font-size: 2.25rem; font-weight: 900; font-family: var(--font-tech), monospace;">
                            11.5м
                        </div>
                        <p style="margin: 0.5rem 0 0 0; font-size: 0.75rem; color: var(--text-muted);">
                            Среднее время первого ответа
                        </p>
                    </div>
                </div>

                <div class="card-neo" style="margin-bottom: 2rem;">
                    <h3 style="font-size: 1.15rem; font-weight: 800; text-transform: uppercase; margin-top: 0; margin-bottom: 1.5rem; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-card); padding-bottom: 0.75rem;">
                        Support load by hour
                    </h3>
                    
                    <div style="display: flex; align-items: flex-end; gap: 0.75rem; height: 160px; padding: 0.5rem; background: rgba(0,0,0,0.15); border-radius: 12px;" id="load-chart">
                        <!-- Dynamic bars -->
                    </div>
                </div>
            </div>

            <!-- TAB 2: Incident Terminal -->
            <div id="tab-terminal" class="tab-pane" style="display: none;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; align-items: start;">
                    <!-- Ticket list card -->
                    <div class="card-neo">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-card); padding-bottom: 0.75rem;">
                            <h3 style="font-size: 1.15rem; font-weight: 800; text-transform: uppercase; margin: 0; letter-spacing: 0.5px;">Active Incidents</h3>
                            <input type="text" id="ticket-search" class="input-field" placeholder="Поиск по теме..." style="max-width: 180px; padding: 0.4rem 0.8rem;" oninput="fetchTickets()">
                        </div>

                        <div id="tickets-list-box" style="display: flex; flex-direction: column; min-height: 200px;">
                            <!-- Filled dynamically by JS -->
                        </div>
                    </div>

                    <!-- Ticket details / Thread card -->
                    <div class="card-neo" id="ticket-details-card" style="display: none;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-card); padding-bottom: 0.75rem;">
                            <div>
                                <h3 style="font-size: 1.1rem; font-weight: 800; text-transform: uppercase; margin: 0;" id="details-subject">—</h3>
                                <p style="margin: 0.25rem 0 0 0; font-size: 0.8rem; color: var(--text-muted);" id="details-meta">—</p>
                            </div>
                            <span class="badge badge-rose" id="details-status">Open</span>
                        </div>

                        <div class="chat-messages" id="details-messages-box">
                            <!-- Message thread dynamically filled -->
                        </div>

                        <!-- Canned Responses -->
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem;">
                            <div class="canned-chip" onclick="applyCanned('Здравствуйте! Мы приняли ваше обращение в работу и детально анализируем транзакционные логи.')">👋 Приветствие</div>
                            <div class="canned-chip" onclick="applyCanned('Пришлите, пожалуйста, скриншот платежного поручения или выписку из банка для верификации.')">📎 Запрос доп. документов</div>
                            <div class="canned-chip" onclick="applyCanned('Проблема успешно решена! Баланс вашего кабинета скорректирован. Спасибо за обращение!')">✅ Проблема решена</div>
                        </div>

                        <div style="display: flex; gap: 1rem;">
                            <textarea id="reply-text-field" class="input-field" placeholder="Введите ответ партнеру..." style="height: 48px; resize: none;"></textarea>
                            <button onclick="sendReply()" class="card-neo" style="padding: 0.8rem 1.5rem; display: flex; align-items: center; gap: 0.5rem; cursor: pointer; border-radius: 8px; font-weight: 800; text-transform: uppercase; font-size: 0.8rem; background: var(--primary); color: #fff; border: none; height: 48px;">
                                Ответить <i class="ph-bold ph-paper-plane-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Select ticket fallback card -->
                    <div class="card-neo" id="select-ticket-fallback" style="text-align: center; padding: 4rem 2rem;">
                        <i class="ph-bold ph-chat-centered-text" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1.5rem;"></i>
                        <h3 style="font-size: 1.25rem; font-weight: 800; text-transform: uppercase; margin: 0 0 0.5rem 0;">Инцидент не выбран</h3>
                        <p style="margin: 0; color: var(--text-muted); font-size: 0.85rem;">
                            Выберите любое активное обращение слева, чтобы открыть терминал связи с партнером, просмотреть историю логов и отправить ответ.
                        </p>
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

            if (tabId === 'terminal') {
                fetchTickets();
            }
        }

        // Load chart simulation
        const loadChart = document.getElementById('load-chart');
        const loadData = [12, 18, 25, 14, 8, 22, 34, 45, 29, 16, 21, 28];
        loadChart.innerHTML = loadData.map(val => `
            <div style="flex-grow: 1; background: var(--primary); height: ${val * 2}px; border-radius: 4px; min-width: 12px; transition: height 0.5s;"></div>
        `).join('');

        // --- Active Incidents Terminal Logic ---
        let currentTicketId = null;

        function fetchTickets() {
            const query = document.getElementById('ticket-search').value;
            const box = document.getElementById('tickets-list-box');
            
            box.innerHTML = `
                <div style="text-align: center; padding: 2rem 0;">
                    <i class="ph-bold ph-spinner ph-spin" style="font-size: 2rem; color: var(--primary); margin-bottom: 0.5rem;"></i>
                    <p style="color: var(--text-muted); font-size: 0.8rem; font-weight: 700;">Синхронизация инцидентов...</p>
                </div>
            `;

            fetch(`/ops/dashboard/tickets/data?search=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                box.innerHTML = '';
                if (!data.data || data.data.length === 0) {
                    box.innerHTML = `
                        <div style="text-align: center; padding: 2rem 0; color: var(--text-muted); font-size: 0.85rem;">
                            Активных инцидентов не найдено
                        </div>
                    `;
                    return;
                }

                data.data.forEach(t => {
                    const isActive = t.id === currentTicketId ? 'background: rgba(255,255,255,0.03); font-weight: bold; border-left: 3px solid var(--primary);' : '';
                    box.innerHTML += `
                        <div class="ticket-row" onclick="openTicket(${t.id})" style="${isActive}">
                            <div>
                                <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-main);">${t.subject}</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">${t.shop_name} • ${t.partner_name}</div>
                            </div>
                            <span class="badge ${t.status === 'open' ? 'badge-rose' : 'badge-green'}" style="font-size: 0.6rem;">${t.status}</span>
                        </div>
                    `;
                });
            });
        }

        function openTicket(id) {
            currentTicketId = id;
            document.getElementById('select-ticket-fallback').style.display = 'none';
            
            const card = document.getElementById('ticket-details-card');
            card.style.display = 'block';

            const box = document.getElementById('details-messages-box');
            box.innerHTML = `
                <div style="text-align: center; padding: 4rem 0;">
                    <i class="ph-bold ph-spinner ph-spin" style="font-size: 2rem; color: var(--primary); margin-bottom: 0.5rem;"></i>
                    <p style="color: var(--text-muted); font-size: 0.8rem; font-weight: 700;">Загрузка сообщений...</p>
                </div>
            `;

            fetch(`/ops/dashboard/tickets/${id}/details`)
            .then(res => res.json())
            .then(data => {
                document.getElementById('details-subject').innerText = data.ticket.subject;
                document.getElementById('details-meta').innerText = `${data.ticket.shop_name} • ${data.ticket.partner_name}`;
                
                const statusBadge = document.getElementById('details-status');
                statusBadge.innerText = data.ticket.status;
                statusBadge.className = `badge ${data.ticket.status === 'open' ? 'badge-rose' : 'badge-green'}`;

                box.innerHTML = '';
                if (!data.messages || data.messages.length === 0) {
                    box.innerHTML = `<div style="text-align: center; padding: 2rem 0; color: var(--text-muted);">История сообщений пуста</div>`;
                } else {
                    data.messages.forEach(m => {
                        const bubbleClass = m.is_admin ? 'admin' : 'user';
                        box.innerHTML += `
                            <div class="chat-bubble ${bubbleClass}">
                                <div style="font-size: 0.65rem; font-weight: 800; color: var(--primary); margin-bottom: 0.25rem;">${m.sender} • ${m.created_at}</div>
                                <div>${m.message}</div>
                            </div>
                        `;
                    });
                }
                box.scrollTop = box.scrollHeight;
                fetchTickets(); // Refresh lists highlighted state
            });
        }

        function applyCanned(text) {
            document.getElementById('reply-text-field').value = text;
        }

        function sendReply() {
            const input = document.getElementById('reply-text-field');
            const msg = input.value.trim();
            if (!msg || !currentTicketId) return;

            fetch(`/ops/dashboard/tickets/${currentTicketId}/reply`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    message: msg
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    input.value = '';
                    openTicket(currentTicketId); // Reload thread
                }
            });
        }
    </script>
</body>
</html>
