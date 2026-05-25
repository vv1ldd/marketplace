<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integrity Tribunal — Meanly Systems</title>
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
            box-shadow: none !important;
        }
        body[data-theme="retro"] input:focus, body[data-theme="retro"] textarea:focus {
            box-shadow: 2px 2px 0px #000000 !important;
        }
        body[data-theme="retro"] button, body[data-theme="retro"] .chat-send-btn {
            background: var(--primary) !important;
            color: #ffffff !important;
            border: 3px solid #000000 !important;
            border-radius: 0px !important;
            box-shadow: 3px 3px 0px #000000 !important;
            font-weight: 800 !important;
            transition: all 0.15s ease !important;
        }
        body[data-theme="retro"] button:hover, body[data-theme="retro"] .chat-send-btn:hover {
            transform: translate(-1px, -1px) !important;
            box-shadow: 4px 4px 0px #000000 !important;
        }
        body[data-theme="retro"] button:active, body[data-theme="retro"] .chat-send-btn:active {
            transform: translate(2px, 2px) !important;
            box-shadow: 1px 1px 0px #000000 !important;
        }
        body[data-theme="retro"] .sidebar-footer {
            border-top: 2px solid #000000 !important;
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
        body[data-theme="retro"] .badge-primary { background: #e9d5ff !important; }
        body[data-theme="retro"] .table-wrapper {
            border: 3px solid #000000 !important;
            box-shadow: 4px 4px 0px #000000 !important;
            border-radius: 0px !important;
            background: #ffffff !important;
            overflow-x: auto;
            width: 100%;
        }
        body[data-theme="retro"] .data-table {
            background: #ffffff !important;
            color: #000000 !important;
        }
        body[data-theme="retro"] .data-table th {
            background: #ffffff !important;
            color: #000000 !important;
            border-bottom: 3px solid #000000 !important;
            font-weight: 900 !important;
            font-family: var(--font-tech), monospace;
        }
        body[data-theme="retro"] .data-table td {
            color: #111827 !important;
            border-bottom: 1px solid #e5e7eb !important;
        }
        body[data-theme="retro"] .data-table tr:hover td {
            background: #f9fafb !important;
        }

        /* --- 📦 Structured CSS Layout --- */
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
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .menu-item:hover {
            color: var(--text-main);
            background: rgba(255,255,255,0.02);
        }
        .sidebar-footer {
            padding: 1.25rem 1.5rem;
            border-top: 1px solid var(--border-card);
            background: rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            justify-content: space-between;
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
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 2.5rem;
            min-height: 100vh;
            box-sizing: border-box;
            background: var(--bg-main);
            color: var(--text-main);
            transition: background 0.3s ease;
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }
        .header-title {
            margin: 0;
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            font-family: var(--font-tech), sans-serif;
            text-transform: uppercase;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            width: 100%;
        }
        .card-neo {
            padding: 1.5rem;
            border-radius: 16px;
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            box-shadow: var(--shadow-neo);
            transition: all 0.2s ease;
            position: relative;
        }
        .stat-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }
        .stat-value {
            font-size: 1.75rem;
            font-weight: 800;
            font-family: var(--font-tech), monospace;
            color: var(--text-main);
        }

        /* Tables & Lists */
        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--border-card);
            border-radius: 12px;
            background: rgba(0,0,0,0.15);
            margin-top: 1rem;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.85rem;
        }
        .data-table th {
            padding: 1rem 1.25rem;
            font-size: 0.7rem;
            font-weight: 850;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-card);
            background: rgba(0,0,0,0.2);
        }
        .data-table td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,0.02);
            color: var(--text-main);
            vertical-align: middle;
        }
        .data-table tr:hover td {
            background: rgba(255,255,255,0.01);
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-green {
            background: var(--green-glow);
            color: var(--green);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .badge-rose {
            background: var(--rose-glow);
            color: var(--rose);
            border: 1px solid rgba(244, 63, 94, 0.3);
        }
        .badge-primary {
            background: var(--primary-glow);
            color: var(--primary);
            border: 1px solid rgba(245, 48, 3, 0.3);
        }

        /* AI Oracle Chat UI */
        .chat-container {
            display: flex;
            flex-direction: column;
            height: 480px;
            border-radius: 12px;
            overflow: hidden;
            background: rgba(0,0,0,0.15);
            border: 1px solid var(--border-card);
        }
        .chat-messages {
            flex-grow: 1;
            padding: 1.5rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .chat-message {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            max-width: 80%;
            padding: 0.85rem 1.1rem;
            border-radius: 12px;
            font-size: 0.9rem;
            line-height: 1.45;
        }
        .chat-message.ai {
            align-self: flex-start;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-card);
            color: var(--text-main);
        }
        .chat-message.user {
            align-self: flex-end;
            background: var(--primary-glow);
            border: 1px solid rgba(245, 48, 3, 0.2);
            color: var(--text-main);
        }
        .chat-meta {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }
        .chat-input-area {
            padding: 1rem;
            background: rgba(0,0,0,0.3);
            border-top: 1px solid var(--border-card);
            display: flex;
            gap: 0.75rem;
        }
        .chat-input {
            flex-grow: 1;
            background: var(--bg-input);
            border: 1px solid var(--border-card);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            color: var(--text-main);
            font-family: inherit;
            outline: none;
        }
        .chat-send-btn {
            background: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 0 1.5rem;
            font-weight: 700;
            cursor: pointer;
            transition: opacity 0.2s ease;
        }
        .chat-send-btn:hover {
            opacity: 0.9;
        }

        /* Validator Console Log styles */
        .terminal-box {
            background: #030303;
            border: 1px solid var(--border-card);
            border-radius: 12px;
            padding: 1.5rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            line-height: 1.6;
            color: #10b981;
            height: 380px;
            overflow-y: auto;
            box-shadow: inset 0px 4px 20px rgba(0,0,0,0.8);
        }
        .terminal-line {
            margin: 0.25rem 0;
            white-space: pre-wrap;
        }
        .terminal-line.error { color: #f43f5e; }
        .terminal-line.info { color: #3b82f6; }
        .terminal-line.summary { color: #f59e0b; font-weight: 700; border-top: 1px dashed rgba(255,255,255,0.15); padding-top: 0.5rem; }

        /* Modal styling */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.75);
            backdrop-filter: blur(10px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-card {
            width: 90%;
            max-width: 680px;
            max-height: 80vh;
            overflow-y: auto;
            padding: 2rem;
        }

        /* Skin switch */
        .skin-switcher-pill {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            background: rgba(0,0,0,0.2);
            border: 1px solid var(--border-card);
            border-radius: 100px;
            padding: 0.25rem;
        }
        .skin-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            padding: 0.35rem 0.85rem;
            border-radius: 100px;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .skin-btn.active-skin {
            background: var(--primary);
            color: #ffffff;
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
        <!-- 🛡️ Sidebar Scaffold -->
        <div class="sidebar">
            <div class="sidebar-logo console-selector-wrapper" style="position: relative; cursor: pointer; display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;" onclick="toggleConsoleDropdown(event)">
                <div style="display: flex; align-items: center; gap: 0.5rem; width: 100%;">
                    <span class="logo-dot"></span>
                    <span class="logo-text-partner" style="font-family: var(--font-tech), monospace;">Meanly <span style="font-weight: 400">Tribunal</span> <span class="logo-sub" style="background: rgba(245, 158, 11, 0.1); color: var(--primary); border: 1px solid var(--border-neon); padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">Auditor ▾</span></span>
                    <span class="logo-text-consortium" style="font-family: var(--font-tech), monospace;">Meanly Systems <span style="font-weight: 400">Tribunal</span> <span class="logo-sub" style="background: var(--primary-glow); color: var(--primary); border: 1px solid var(--border-neon); padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">Auditor ▾</span></span>
                    <span class="logo-text-retro" style="display: none; font-family: var(--font-tech), monospace;">Meanly.T <span class="logo-sub" style="background: #000; color: #fff; border: 2px solid #000; padding: 2px 6px; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px;">Audit ▾</span></span>
                </div>
                
                <div id="console-dropdown" class="card-neo" style="display: none; position: absolute; top: 100%; right: 0; width: 180px; margin-top: 0.5rem; background: var(--bg-sidebar); border: 1px solid var(--border-card); border-radius: 8px; z-index: 1000; box-shadow: var(--shadow-neo); overflow: hidden; padding: 0.25rem;">
                    <a href="/ops" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 0.85rem; color: var(--text-muted); font-size: 0.75rem; text-decoration: none; font-weight: 800; border-radius: 6px; font-family: var(--font-tech), monospace;">
                        🛡️ OPERATIONS
                    </a>
                    <a href="/tribunal" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 0.85rem; color: var(--text-main); font-size: 0.75rem; text-decoration: none; font-weight: 800; background: rgba(255,255,255,0.03); border-radius: 6px; font-family: var(--font-tech), monospace;">
                        🏛️ TRIBUNAL
                    </a>
                    <a href="/treasury" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 0.85rem; color: var(--text-muted); font-size: 0.75rem; text-decoration: none; font-weight: 800; border-radius: 6px; font-family: var(--font-tech), monospace;">
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
                <div class="sidebar-section-title">Аудит</div>
                <a onclick="switchTab('registry')" id="tab-btn-registry" class="menu-item active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    Реестр Ledger
                </a>
                <a onclick="switchTab('validator')" id="tab-btn-validator" class="menu-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    Крипто-валидатор
                </a>
                <a onclick="switchTab('oracle')" id="tab-btn-oracle" class="menu-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="20" y1="15" x2="23" y2="15"></line><line x1="1" y1="9" x2="4" y2="9"></line><line x1="1" y1="15" x2="4" y2="15"></line></svg>
                    ИИ-Оракул
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

        <!-- 🚀 Main Operations Substrate -->
        <div class="main-content">
            <div class="header-section">
                <div>
                    <span class="badge badge-rose" style="margin-bottom: 0.5rem;">Security Hub</span>
                    <h1 class="header-title">Integrity Tribunal Console</h1>
                </div>

            </div>

            <!-- Stats Block -->
            <div class="stats-grid">
                <div class="card-neo">
                    <div class="stat-label">Всего записей журнала</div>
                    <div class="stat-value">{{ $stats['total_blocks'] }}</div>
                </div>
                <div class="card-neo">
                    <div class="stat-label">Оборот RUB</div>
                    <div class="stat-value">{{ number_format($stats['total_volume_rub'], 2, '.', ' ') }} ₽</div>
                </div>
                <div class="card-neo">
                    <div class="stat-label">Целостность цепи</div>
                    <div class="stat-value" style="color: var(--green); font-size: 1.15rem;">{{ $stats['chain_status'] }}</div>
                </div>
            </div>

            <!-- 📋 TAB 1: LEDGER REGISTRY -->
            <div id="tab-content-registry" class="tab-pane">
                <div class="card-neo">
                    <h3 style="margin-top: 0; font-family: var(--font-tech), monospace; font-size: 1rem; text-transform: uppercase;">События журнала операций</h3>
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Событие</th>
                                    <th>Инициатор</th>
                                    <th>Сумма</th>
                                    <th>Текущий Хэш (Fingerprint)</th>
                                    <th>Предыдущий Хэш</th>
                                    <th>Дата записи</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($ledgerTransactions as $entry)
                                <tr onclick="showLedgerDetails({{ $entry->id }})" style="cursor: pointer;">
                                    <td style="font-family: var(--font-tech), monospace; font-weight: 700;">#{{ $entry->id }}</td>
                                    <td>
                                        <span class="badge badge-primary">{{ $entry->event_type }}</span>
                                    </td>
                                    <td style="font-size: 0.75rem; color: var(--text-muted);">{{ $entry->trigger_source }}</td>
                                    <td style="font-family: var(--font-tech), monospace; font-weight: 700;">
                                        {{ number_format($entry->amount_base, 2, '.', ' ') }} {{ $entry->currency }}
                                    </td>
                                    <td style="font-family: var(--font-tech), monospace; font-size: 0.75rem; color: var(--green);">
                                        <code>{{ substr($entry->fingerprint, 0, 10) }}...</code>
                                    </td>
                                    <td style="font-family: var(--font-tech), monospace; font-size: 0.75rem; color: var(--text-muted);">
                                        <code>{{ $entry->previous_fingerprint ? substr($entry->previous_fingerprint, 0, 10) . '...' : 'GENESIS' }}</code>
                                    </td>
                                    <td style="color: var(--text-muted);">{{ $entry->created_at->format('d.m.Y H:i') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 📋 TAB 2: LIVE VALIDATOR -->
            <div id="tab-content-validator" class="tab-pane" style="display: none;">
                <div class="card-neo" style="display: flex; flex-direction: column; gap: 1.5rem;">
                    <div>
                        <h3 style="margin: 0 0 0.5rem 0; font-family: var(--font-tech), monospace; font-size: 1rem; text-transform: uppercase;">Проверка журнала</h3>
                        <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">Глубокий аудит контрольных отметок журнала для выявления попыток модификации или фальсификации данных.</p>
                    </div>

                    <button onclick="runTribunalAudit()" class="chat-send-btn" style="align-self: flex-start; padding: 0.85rem 2rem;">
                        <i class="ph-bold ph-shield-check" style="margin-right: 0.5rem;"></i>
                        Запустить валидацию цепи
                    </button>

                    <div class="terminal-box" id="validator-terminal">
                        <div class="terminal-line">[SYSTEM INFO]: Консоль готова. Ожидание запуска аудита...</div>
                    </div>
                </div>
            </div>

            <!-- 📋 TAB 3: AI ORACLE CHAT -->
            <div id="tab-content-oracle" class="tab-pane" style="display: none;">
                <div class="card-neo" style="display: flex; flex-direction: column; gap: 1.5rem;">
                    <div>
                        <h3 style="margin: 0 0 0.5rem 0; font-family: var(--font-tech), monospace; font-size: 1rem; text-transform: uppercase;">ИИ-Оракул Безопасности Llama 3</h3>
                        <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">Интерактивный аналитик по вопросам безопасности, аудита операций и комплаенса расчетов.</p>
                    </div>

                    <div class="chat-container">
                        <div class="chat-messages" id="chat-messages">
                            <div class="chat-message ai">
                                <div class="chat-meta">ИИ-Оракул Трибунала</div>
                                Приветствую, Аудитор. Я готов провести криптографическую экспертизу, оценить целостность блоков реестра или дать отчет по финансовым потокам. Какой узел системы вас интересует?
                            </div>
                        </div>
                        
                        <div class="chat-input-area">
                            <input type="text" id="chat-input" class="chat-input" placeholder="Введите ваш запрос о безопасности транзакций..." onkeypress="handleChatKey(event)">
                            <button onclick="sendOracleMessage()" class="chat-send-btn" id="send-btn">Отправить</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div class="modal-overlay" id="details-modal" onclick="closeModal(event)">
        <div class="modal-card card-neo" onclick="event.stopPropagation()">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-card); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                <h3 style="margin: 0; font-family: var(--font-tech), monospace; text-transform: uppercase;" id="modal-title">Детали записи журнала</h3>
                <button onclick="closeModal(null)" style="background: transparent; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.25rem;"><i class="ph-bold ph-x"></i></button>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div style="display: grid; grid-template-columns: 150px 1fr; gap: 0.5rem; font-size: 0.85rem;">
                    <div style="color: var(--text-muted); font-weight: 700;">Тип события:</div>
                    <div id="modal-event" style="font-weight: 700; color: var(--primary);"></div>
                    
                    <div style="color: var(--text-muted); font-weight: 700;">Инициатор (DID):</div>
                    <div id="modal-source" style="font-family: var(--font-tech), monospace;"></div>

                    <div style="color: var(--text-muted); font-weight: 700;">Сумма:</div>
                    <div id="modal-amount" style="font-family: var(--font-tech), monospace; font-weight: 700;"></div>
                    
                    <div style="color: var(--text-muted); font-weight: 700;">Хэш блока (FP):</div>
                    <div id="modal-fp" style="font-family: var(--font-tech), monospace; font-size: 0.75rem; color: var(--green); word-break: break-all;"></div>
                    
                    <div style="color: var(--text-muted); font-weight: 700;">Предыдущий хэш:</div>
                    <div id="modal-prev-fp" style="font-family: var(--font-tech), monospace; font-size: 0.75rem; color: var(--text-muted); word-break: break-all;"></div>
                </div>

                <div style="border-top: 1px solid var(--border-card); padding-top: 1rem;">
                    <div style="color: var(--text-muted); font-weight: 700; font-size: 0.85rem; margin-bottom: 0.5rem;">Детерминированная полезная нагрузка (Payload):</div>
                    <pre id="modal-payload" style="background: rgba(0,0,0,0.3); padding: 1rem; border-radius: 8px; font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; overflow-x: auto; color: var(--text-main); border: 1px solid var(--border-card); margin: 0;"></pre>
                </div>
            </div>
        </div>
    </div>

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script>
        // Store ledger items in memory
        const ledgerItems = @json($ledgerTransactions);

        // Theme management
        function setTheme(theme) {
            if (window.MeanlyTheme && typeof window.MeanlyTheme.apply === 'function') {
                theme = window.MeanlyTheme.apply(theme);
            }
            localStorage.setItem("theme", theme);
            document.documentElement.setAttribute("data-theme", theme);
            document.body.setAttribute("data-theme", theme);
            var cookieDomain = @json(config('session.domain') ?? null);
            var domainSuffix = cookieDomain ? '; domain=' + cookieDomain : '';
            document.cookie = `theme=${theme}; path=/; max-age=31536000; SameSite=Lax${domainSuffix}`;
            updateActiveThemeButton();

            fetch('{{ route("ops.dashboard.theme") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ theme: theme })
            }).catch(err => console.error('Failed to sync theme with DB:', err));
        }
        function updateActiveThemeButton() {
            const currentTheme = document.body.getAttribute("data-theme") || localStorage.getItem("theme") || "consortium";
            document.querySelectorAll(".skin-btn").forEach(btn => {
                btn.classList.remove("active-skin");
            });
            const activeBtn = document.getElementById("skin-btn-" + currentTheme);
            if (activeBtn) activeBtn.classList.add("active-skin");
        }
        document.addEventListener("DOMContentLoaded", () => {
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
            const savedTheme = dbTheme || localStorage.getItem("theme") || "consortium";
            setTheme(savedTheme);
        });

        // Tab Switcher
        function switchTab(tabName) {
            document.querySelectorAll('.tab-pane').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.menu-item').forEach(el => el.classList.remove('active'));
            
            document.getElementById('tab-content-' + tabName).style.display = 'block';
            document.getElementById('tab-btn-' + tabName).classList.add('active');
        }

        // Ledger modal details
        function showLedgerDetails(id) {
            const entry = ledgerItems.find(x => x.id === id);
            if (!entry) return;

            document.getElementById('modal-title').innerText = "Блок Ledger #" + entry.id;
            document.getElementById('modal-event').innerText = entry.event_type;
            document.getElementById('modal-source').innerText = entry.trigger_source;
            document.getElementById('modal-amount').innerText = Number(entry.amount_base).toLocaleString() + " " + entry.currency;
            document.getElementById('modal-fp').innerText = entry.fingerprint;
            document.getElementById('modal-prev-fp').innerText = entry.previous_fingerprint || 'GENESIS BLOCK (⚓)';
            document.getElementById('modal-payload').innerText = JSON.stringify(entry.payload || {}, null, 2);

            document.getElementById('details-modal').style.display = 'flex';
        }

        function closeModal(event) {
            if (!event || event.target.id === 'details-modal' || event.target.closest('button')) {
                document.getElementById('details-modal').style.display = 'none';
            }
        }

        // Live Chain Validator Execution
        function runTribunalAudit() {
            const term = document.getElementById('validator-terminal');
            term.innerHTML = '<div class="terminal-line info">[SYSTEM INFO]: Подключение к валидатору...</div>';
            
            fetch('/tribunal/audit/validate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(res => res.json())
            .then(data => {
                term.innerHTML = '';
                let delay = 0;
                
                data.logs.forEach((log) => {
                    setTimeout(() => {
                        const line = document.createElement('div');
                        line.className = 'terminal-line ' + log.type;
                        line.innerText = log.message;
                        term.appendChild(line);
                        term.scrollTop = term.scrollHeight;
                    }, delay);
                    delay += 80; // Nice visual typewriter effect!
                });
            })
            .catch(err => {
                term.innerHTML += '<div class="terminal-line error">❌ Ошибка: Не удалось связаться с валидатором ядра.</div>';
            });
        }

        // AI Chat Oracle interaction
        function handleChatKey(e) {
            if (e.key === 'Enter') sendOracleMessage();
        }

        function sendOracleMessage() {
            const input = document.getElementById('chat-input');
            const message = input.value.trim();
            if (!message) return;

            input.value = '';
            
            // Add user message
            const chatBox = document.getElementById('chat-messages');
            const userMsg = document.createElement('div');
            userMsg.className = 'chat-message user';
            userMsg.innerHTML = '<div class="chat-meta">Вы (Аудитор)</div>' + escapeHtml(message);
            chatBox.appendChild(userMsg);
            chatBox.scrollTop = chatBox.scrollHeight;

            // Loading message
            const loadingMsg = document.createElement('div');
            loadingMsg.className = 'chat-message ai';
            loadingMsg.id = 'oracle-loading-msg';
            loadingMsg.innerHTML = '<div class="chat-meta">ИИ-Оракул Трибунала</div>⏳ Подключение к ИИ-Ядру...';
            chatBox.appendChild(loadingMsg);
            chatBox.scrollTop = chatBox.scrollHeight;

            fetch('/tribunal/audit/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ message: message })
            })
            .then(res => res.json())
            .then(data => {
                loadingMsg.removeAttribute('id');
                loadingMsg.innerHTML = '<div class="chat-meta">ИИ-Оракул Трибунала</div>' + formatAiResponse(data.response || data.error);
                chatBox.scrollTop = chatBox.scrollHeight;
            })
            .catch(err => {
                loadingMsg.removeAttribute('id');
                loadingMsg.className = 'chat-message ai error';
                loadingMsg.innerHTML = '<div class="chat-meta">ИИ-Оракул Трибунала</div>❌ Не удалось получить ответ от Llama 3.';
                chatBox.scrollTop = chatBox.scrollHeight;
            });
        }

        function escapeHtml(str) {
            return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        function formatAiResponse(text) {
            // Very basic markdown formatting replacement
            return escapeHtml(text).replace(/\n/g, '<br>').replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        }
        // 🌐 Sovereign Console Context Selector toggle handler
        function toggleConsoleDropdown(event) {
            if (event) event.stopPropagation();
            const dropdown = document.getElementById('console-dropdown');
            if (dropdown) {
                dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
            }
        }
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('console-dropdown');
            if (dropdown && !event.target.closest('.console-selector-wrapper')) {
                dropdown.style.display = 'none';
            }
        });
    </script>
</body>
</html>
