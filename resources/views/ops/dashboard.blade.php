<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Operations Command Center — Meanly</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom Operations Console Scaffolding -->
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
        .logo-text-partner, .logo-text-consortium {
            font-weight: 850;
            font-size: 1.1rem;
            letter-spacing: -0.5px;
            color: var(--text-main);
            text-transform: uppercase;
        }
        .logo-sub {
            font-size: 0.6rem;
            font-weight: 800;
            padding: 2px 6px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-card);
            border-radius: 4px;
            vertical-align: middle;
            margin-left: 4px;
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

        /* --- 💻 Main Content --- */
        .main-content {
            margin-left: 280px;
            flex: 1;
            width: calc(100% - 280px);
            max-width: calc(100vw - 280px);
            padding: 2.5rem;
            min-height: 100vh;
            box-sizing: border-box;
            background: var(--bg-main);
            color: var(--text-main);
            transition: background 0.3s ease;
        }
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2.5rem;
            position: sticky;
            top: 0;
            background: var(--bg-main);
            z-index: 50;
            padding: 10px 0;
            border-bottom: 1px solid transparent;
        }
        .page-title {
            font-size: 1.6rem;
            font-weight: 900;
            letter-spacing: -0.75px;
        }
        .top-stats {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .top-stat-item {
            padding: 6px 14px;
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border-card);
            border-radius: 100px;
            font-size: 0.72rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .stat-label {
            color: var(--text-muted);
        }
        .stat-val {
            color: var(--text-main);
        }
        .text-primary { color: var(--primary) !important; }
        .text-warning { color: #f59e0b !important; }
        .text-success { color: var(--green) !important; }

        /* --- 🗂️ SPA Tab Panes --- */
        .tab-pane {
            display: none;
        }
        .tab-pane.active {
            display: block;
            animation: fadeIn 0.4s ease forwards;
        }

        /* --- 🏛️ Neomorphic Elements --- */
        .card-neo {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--shadow-neo);
            transition: all 0.3s ease;
        }
        .grid-12 {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1.5rem;
        }
        .col-12 { grid-column: span 12; }
        .col-8 { grid-column: span 8; }
        .col-7 { grid-column: span 7; }
        .col-6 { grid-column: span 6; }
        .col-5 { grid-column: span 5; }
        .col-4 { grid-column: span 4; }
        .col-3 { grid-column: span 3; }

        .audit-ai-layout {
            align-items: stretch;
        }
        .audit-ai-chat-card {
            display: flex;
            flex-direction: column;
            min-height: 560px;
        }
        .audit-ai-heading {
            font-weight: 850;
            font-size: 0.95rem;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .audit-ai-status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            display: inline-block;
            box-shadow: 0 0 14px rgba(16, 185, 129, 0.45);
        }
        .audit-ai-chat {
            flex: 1;
            min-height: 0;
        }
        .audit-ai-actions {
            display: flex;
            gap: 10px;
            margin-top: 1rem;
        }
        .audit-ai-tools {
            display: grid;
            grid-template-rows: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }
        .audit-tool-card {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            min-height: 0;
        }
        .audit-result-box {
            flex: 1;
            min-height: 130px;
            overflow: auto;
            border-radius: 12px;
            padding: 1rem;
            font-family: var(--font-tech);
            font-size: 0.72rem;
            line-height: 1.6;
        }

        .metric-title {
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }
        .metric-value {
            font-size: 1.75rem;
            font-weight: 900;
            letter-spacing: -0.5px;
            color: var(--text-main);
        }
        
        .badge-neo {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* --- 📋 Premium Tables --- */
        .neo-table-container {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--border-card);
            border-radius: 12px;
            background: rgba(0,0,0,0.15);
        }
        .neo-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.85rem;
        }
        .neo-table th {
            padding: 1rem 1.25rem;
            font-size: 0.7rem;
            font-weight: 850;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-card);
            background: rgba(0,0,0,0.2);
        }
        .neo-table td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,0.02);
            color: var(--text-main);
            vertical-align: middle;
        }
        .neo-table tr:hover td {
            background: rgba(255,255,255,0.01);
        }
        #partners-table {
            table-layout: fixed;
        }
        #partners-table th,
        #partners-table td {
            padding: 0.85rem 0.9rem;
        }
        #partners-table th:nth-child(1) { width: 24%; }
        #partners-table th:nth-child(2) { width: 13%; }
        #partners-table th:nth-child(3) { width: 21%; }
        #partners-table th:nth-child(4) { width: 13%; }
        #partners-table th:nth-child(5) { width: 17%; }
        #partners-table th:nth-child(6) { width: 12%; }
        .ops-cell-muted {
            color: var(--text-muted);
            font-size: 0.68rem;
            line-height: 1.35;
        }
        .ops-mono-truncate {
            display: block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-family: var(--font-tech);
            font-size: 0.68rem;
            color: var(--text-muted);
        }
        .ops-badge-stack {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        @media (max-width: 1200px) {
            .sidebar {
                width: 220px;
            }
            .sidebar-logo,
            .sidebar-footer {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            .sidebar-section-title,
            .menu-item {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            .main-content {
                margin-left: 220px;
                width: calc(100% - 220px);
                max-width: calc(100vw - 220px);
                padding: 1.25rem;
            }
            .neo-table th,
            .neo-table td {
                padding: 0.75rem 0.7rem;
            }
            .audit-ai-layout > .audit-ai-chat-card,
            .audit-ai-layout > .audit-ai-tools {
                grid-column: span 12;
            }
            .audit-ai-tools {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                grid-template-rows: none;
            }
        }

        @media (max-width: 900px) {
            .audit-ai-tools {
                grid-template-columns: 1fr;
            }
            .audit-ai-actions {
                flex-direction: column;
            }
        }

        /* Form Elements */
        .input-neo {
            width: 100%;
            background: var(--bg-input);
            border: 1px solid var(--border-card);
            border-radius: 8px;
            padding: 10px 14px;
            color: var(--text-main);
            font-size: 0.85rem;
            font-weight: 550;
            box-shadow: var(--shadow-neo-inset);
            outline: none;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }
        .input-neo:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 1px var(--primary-glow);
        }
        
        .btn-neo {
            background: linear-gradient(135deg, rgba(255,255,255,0.02) 0%, rgba(255,255,255,0.00) 100%), var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 8px;
            padding: 10px 18px;
            color: var(--text-main);
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: var(--shadow-neo);
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            outline: none;
        }
        .btn-neo:hover {
            border-color: var(--border-neon);
            transform: translateY(-1px);
        }
        .btn-primary-neo {
            background: var(--primary) !important;
            color: #ffffff !important;
            border-color: var(--primary) !important;
        }
        .btn-primary-neo:hover {
            box-shadow: 0 0 15px var(--primary-glow);
        }

        /* Modals */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(8px);
        }
        .modal.active {
            display: flex;
            animation: fadeIn 0.3s ease forwards;
        }
        .modal-card {
            width: 100%;
            max-width: 600px;
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 20px;
            box-shadow: var(--shadow-neo);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            animation: slideUp 0.3s ease forwards;
        }
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-card);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-title {
            font-size: 1.1rem;
            font-weight: 850;
        }
        .modal-close {
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
        }
        .modal-body {
            padding: 1.5rem;
            max-height: 450px;
            overflow-y: auto;
        }
        .modal-footer {
            padding: 1.25rem 1.5rem;
            border-top: 1px solid var(--border-card);
            background: rgba(0,0,0,0.1);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* CSS Keyframes */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .skin-switcher-pill button.active-skin {
            background: var(--primary) !important;
            color: #ffffff !important;
        }
        
        .chat-container {
            height: 380px;
            overflow-y: auto;
            border: 1px solid var(--border-card);
            border-radius: 12px;
            padding: 1.5rem;
            background: rgba(0,0,0,0.2);
            margin-bottom: 1rem;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .chat-message {
            max-width: 80%;
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 0.85rem;
            line-height: 1.5;
        }
        .chat-message.user {
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-card);
            align-self: flex-end;
            border-bottom-right-radius: 2px;
        }
        .chat-message.ai {
            background: rgba(var(--primary-rgb), 0.08);
            border: 1px solid var(--border-neon);
            align-self: flex-start;
            border-bottom-left-radius: 2px;
            font-family: var(--font-tech);
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
    @livewireStyles
</head>
<body data-theme="consortium">
@include('partials.theme-sync-body')

    <div class="container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="sidebar-logo" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; width: 100%;">
                    <span class="logo-dot"></span>
                    <span class="logo-text-consortium" style="font-family: var(--font-tech), monospace;">Meanly <span class="logo-sub" style="background: var(--primary-glow); color: var(--primary); border: 1px solid var(--border-neon); padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">Ops</span></span>
                    <span class="logo-text-partner" style="display: none; font-family: var(--font-tech), monospace;">Meanly <span class="logo-sub" style="background: rgba(245, 158, 11, 0.1); color: var(--primary); border: 1px solid var(--border-neon); padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">Ops</span></span>
                    <span class="logo-text-retro" style="display: none; font-family: var(--font-tech), monospace;">Meanly <span class="logo-sub" style="background: #000; color: #fff; border: 2px solid #000; padding: 2px 6px; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px;">Ops</span></span>
                </div>
            </div>

            <div class="sidebar-menu">
                <!-- Core Sections -->
                <div class="sidebar-section-title">Аналитика</div>
                <a href="javascript:void(0)" onclick="switchTab('dashboard')" class="menu-item active" id="menu-dashboard">
                    <svg viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                    Инфопанель
                </a>
                
                <div class="sidebar-section-title">Магазины и B2B</div>
                <a href="javascript:void(0)" onclick="switchTab('partners')" class="menu-item" id="menu-partners">
                    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    Организации
                </a>
                <a href="javascript:void(0)" onclick="switchTab('finance-liquidity')" class="menu-item" id="menu-finance-liquidity">
                    <svg viewBox="0 0 24 24"><path d="M12 2v20"></path><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6"></path></svg>
                    Финансы и ликвидность
                </a>
                <a href="javascript:void(0)" onclick="switchTab('channels')" class="menu-item" id="menu-channels">
                    <svg viewBox="0 0 24 24"><path d="M4 9h16"></path><path d="M4 15h16"></path><path d="M10 3 8 21"></path><path d="m16 3-2 18"></path></svg>
                    Каналы
                </a>
                <a href="javascript:void(0)" onclick="switchTab('shops')" class="menu-item" id="menu-shops">
                    <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    Магазины
                </a>
                
                <div class="sidebar-section-title">Мастер-ключ</div>
                <a href="javascript:void(0)" onclick="switchTab('orders')" class="menu-item" id="menu-orders">
                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"></rect><line x1="7" y1="8" x2="17" y2="8"></line><line x1="7" y1="12" x2="17" y2="12"></line><line x1="7" y1="16" x2="13" y2="16"></line></svg>
                    Все заказы
                </a>
                
                <div class="sidebar-section-title">Каталог и контент</div>
                <a href="javascript:void(0)" onclick="switchTab('catalog')" class="menu-item" id="menu-catalog">
                    <svg viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                    Все товары
                </a>
                <a href="javascript:void(0)" onclick="switchTab('inventory')" class="menu-item" id="menu-inventory">
                    <svg viewBox="0 0 24 24"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path><path d="M3.3 7 12 12l8.7-5"></path><path d="M12 22V12"></path></svg>
                    Склады и ваучеры
                </a>
                <a href="javascript:void(0)" onclick="switchTab('providers')" class="menu-item" id="menu-providers">
                    <svg viewBox="0 0 24 24"><path d="M12 2v20"></path><path d="M2 12h20"></path><path d="M4.93 4.93l14.14 14.14"></path><path d="M19.07 4.93L4.93 19.07"></path></svg>
                    Провайдеры
                </a>
                <a href="javascript:void(0)" onclick="switchTab('decision-console')" class="menu-item" id="menu-decision-console">
                    <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                    Decision Console
                </a>
                <a href="javascript:void(0)" onclick="switchTab('search-integrations')" class="menu-item" id="menu-search-integrations">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path><path d="M11 7v8"></path><path d="M7 11h8"></path></svg>
                    Search Integrations
                </a>
                
                <div class="sidebar-section-title">Система</div>
                <a href="javascript:void(0)" onclick="switchTab('support')" class="menu-item" id="menu-support">
                    <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    Поддержка
                </a>
                <a href="javascript:void(0)" onclick="switchTab('ai-audit')" class="menu-item" id="menu-ai-audit">
                    <svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="20" y1="15" x2="23" y2="15"></line><line x1="1" y1="9" x2="4" y2="9"></line><line x1="1" y1="15" x2="4" y2="15"></line></svg>
                    Аудит и ИИ
                </a>

                <div class="sidebar-section-title">витрина</div>
                <a href="/" class="menu-item" id="menu-exit" style="color: var(--primary) !important;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="stroke: var(--primary) !important;"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                    Вернуться на витрину
                </a>
            </div>

            <div class="sidebar-footer" style="justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 12px; cursor: pointer;" onclick="openProfileModal()">
                    <div class="user-avatar">
                        {{ mb_substr($user->name ?: ($user->first_name ?: 'А'), 0, 1) }}
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

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Top Header Stats Bar -->
            <div class="top-bar">
                <div class="page-title" id="page-title-text">Инфопанель</div>
                
                <div class="top-stats">
                    <div class="top-stat-item" style="font-family: var(--font-tech);">
                        <span class="stat-label">Всего оборота:</span>
                        <span class="stat-val text-primary">{{ number_format($stats['total_volume'] ?? 0.00, 2, '.', ' ') }} ₽</span>
                    </div>
                    <div class="top-stat-item" style="font-family: var(--font-tech);">
                        <span class="stat-label">Партнеры (ИП):</span>
                        <span class="stat-val text-success">{{ $stats['total_partners'] ?? 0 }}</span>
                    </div>
                </div>
            </div>

            <!-- Tab 1: Dashboard -->
            <div class="tab-pane active" id="tab-dashboard">
                <!-- Welcome Banner -->
                <div class="card-neo" style="background: linear-gradient(135deg, rgba(245, 48, 3, 0.03) 0%, rgba(9, 9, 9, 0.8) 100%), var(--bg-card); border: 1px solid var(--border-card); margin-bottom: 2rem; padding: 2rem; position: relative; overflow: hidden; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="display: flex; gap: 8px; margin-bottom: 12px;">
                            <span class="badge-neo" style="background: rgba(245, 48, 3, 0.1); color: var(--primary); border: 1px solid rgba(245, 48, 3, 0.2);">
                                СУПЕР АДМИНИСТРАТОР
                            </span>
                            <span class="badge-neo" style="background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2);">
                                Ledger Fabric Live
                            </span>
                        </div>
                        <h2 style="font-size: 1.8rem; font-weight: 900; margin: 0 0 8px 0; letter-spacing: -0.5px;">
                            Meanly Systems Operations
                        </h2>
                        <p style="color: var(--text-muted); margin: 0; font-size: 0.95rem; font-weight: 500;">
                            Панель глобального мониторинга суверенных реестров, транзакций и клиринга B2B-партнеров.
                        </p>
                    </div>
                </div>

                <!-- 4-Column Quick Metrics -->
                <div class="grid-12" style="margin-bottom: 2rem;">
                    <div class="col-3 card-neo">
                        <div class="metric-title">Глобальный оборот</div>
                        <div class="metric-value">{{ number_format($stats['total_volume'] ?? 0.00, 2, '.', ' ') }} ₽</div>
                        <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 8px;">Сумма платежей в системе</div>
                    </div>
                    <div class="col-3 card-neo">
                        <div class="metric-title">Всего заказов</div>
                        <div class="metric-value">{{ $stats['total_orders'] ?? 0 }}</div>
                        <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 8px;">Количество транзакций</div>
                    </div>
                    <div class="col-3 card-neo">
                        <div class="metric-title">Организаций / Шопов</div>
                        <div class="metric-value">{{ $stats['total_partners'] ?? 0 }} / {{ $stats['total_shops'] ?? 0 }}</div>
                        <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 8px;">Зарегистрировано B2B партнеров</div>
                    </div>
                    <div class="col-3 card-neo">
                        <div class="metric-title">Всего товаров</div>
                        <div class="metric-value">{{ $stats['total_products'] ?? 0 }}</div>
                        <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 8px;">Активных позиций в каталоге</div>
                    </div>
                </div>

                <!-- Sales Chart & Recent Logs -->
                <div class="grid-12">
                    <div class="col-8 card-neo">
                        <div style="font-weight: 850; font-size: 0.95rem; margin-bottom: 1.5rem; text-transform: uppercase; letter-spacing: 0.5px;">
                            Динамика продаж (Sovereign Ledger)
                        </div>
                        <div style="height: 300px; position: relative;">
                            <canvas id="opsSalesChart"></canvas>
                        </div>
                    </div>
                    <div class="col-4 card-neo" style="display: flex; flex-direction: column;">
                        <div style="font-weight: 850; font-size: 0.95rem; margin-bottom: 1.5rem; text-transform: uppercase; letter-spacing: 0.5px;">
                            Системный Ledger Лог
                        </div>
                        <div style="flex: 1; overflow-y: auto; max-height: 300px; display: flex; flex-direction: column; gap: 10px;" id="dashboard-ledger-list">
                            @forelse($ledgerTransactions as $tx)
                                <div style="background: rgba(255,255,255,0.01); border: 1px solid var(--border-card); padding: 10px; border-radius: 8px; font-size: 0.75rem; font-family: var(--font-tech);">
                                    <div style="display:flex; justify-content:space-between; margin-bottom: 4px;">
                                        <span style="color:var(--primary); font-weight:700;">{{ $tx->event_type }}</span>
                                        <span style="color:var(--text-muted);">{{ $tx->created_at->format('H:i:s') }}</span>
                                    </div>
                                    <div style="color:var(--text-main); font-weight:550;">{{ $tx->legalEntity->name ?? 'SYSTEM' }}</div>
                                    <div style="color:var(--text-muted); font-size:0.65rem; margin-top: 4px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                        {{ json_encode($tx->payload ?? []) }}
                                    </div>
                                </div>
                            @empty
                                <div style="color:var(--text-muted); text-align:center; padding: 2rem;">Операций пока нет.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Partners -->
            <div class="tab-pane" id="tab-partners">
                <div class="card-neo" style="margin-bottom: 2rem; display:flex; justify-content:space-between; align-items:center; gap: 15px; flex-wrap:wrap;">
                    <div style="flex:1; min-width:250px;">
                        <input type="text" id="partners-search-input" placeholder="Поиск юридических лиц (по названию, ИНН)..." class="input-neo" oninput="loadPartners()">
                    </div>
                    <div style="display:flex; gap: 8px; flex-wrap:wrap;">
                        <button type="button" class="btn-neo partners-filter active" data-status="" onclick="setPartnersStatusFilter('')">
                            Все
                        </button>
                        <button type="button" class="btn-neo partners-filter" data-status="pending_moderation" onclick="setPartnersStatusFilter('pending_moderation')">
                            На модерации: {{ $stats['pending_partners'] ?? 0 }}
                        </button>
                    </div>
                </div>

                <div class="neo-table-container">
                    <table class="neo-table" id="partners-table">
                        <thead>
                            <tr>
                                <th>Организация</th>
                                <th>Статус</th>
                                <th>Meanly API</th>
                                <th>Точки продаж</th>
                                <th>Settlement</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody id="partners-table-body">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>

                <!-- Dynamic Pagination -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 1.5rem;" id="partners-pagination">
                    <!-- Dynamic buttons -->
                </div>
            </div>

            <!-- Tab: Finance & Liquidity -->
            <div class="tab-pane" id="tab-finance-liquidity">
                <div class="card-neo" style="margin-bottom: 2rem; display:flex; justify-content:space-between; align-items:flex-start; gap:18px; flex-wrap:wrap;">
                    <div>
                        <div class="metric-title" style="color: var(--primary);">Finance & Liquidity</div>
                        <h2 style="font-size:1.7rem;font-weight:950;margin:8px 0;letter-spacing:-.04em;">Финансы и ликвидность</h2>
                        <p style="color:var(--text-muted);margin:0;max-width:840px;line-height:1.6;">Единая панель partner balances, reserves, pending balance requests, settlement events, currency readiness, rails and corridors “откуда → куда”.</p>
                    </div>
                    <button type="button" class="btn-neo btn-primary-neo" onclick="loadFinanceLiquidity()">Обновить финансы</button>
                </div>

                <div class="grid-12" style="margin-bottom: 2rem;" id="treasury-summary-cards">
                    <div class="col-12 card-neo" style="color:var(--text-muted);">Финансы загрузятся при открытии вкладки.</div>
                </div>

                <div class="grid-12">
                    <div class="col-6 card-neo">
                        <div style="font-weight:850;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1rem;">Balance Requests</div>
                        <div class="neo-table-container">
                            <table class="neo-table">
                                <thead><tr><th>Партнер</th><th>Тип</th><th>Сумма</th><th>Статус</th><th>Дата</th></tr></thead>
                                <tbody id="treasury-requests-body"><tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Нет данных.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-6 card-neo">
                        <div style="font-weight:850;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1rem;">Settlement Events</div>
                        <div class="neo-table-container">
                            <table class="neo-table">
                                <thead><tr><th>Событие</th><th>Партнер</th><th>Сумма</th><th>Дата</th></tr></thead>
                                <tbody id="treasury-events-body"><tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Нет данных.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div style="font-weight:850;text-transform:uppercase;letter-spacing:.5px;margin:2rem 0 1rem;">Liquidity readiness</div>
                <div class="grid-12" style="margin-bottom: 2rem;" id="liquidity-summary-cards">
                    <div class="col-12 card-neo" style="color:var(--text-muted);">Liquidity metrics загрузятся при открытии вкладки.</div>
                </div>
                <div class="grid-12" style="margin-bottom: 2rem;">
                    <div class="col-7 card-neo">
                        <div style="font-weight:850;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1rem;">Currency Readiness</div>
                        <div class="neo-table-container">
                            <table class="neo-table">
                                <thead><tr><th>Currency</th><th>Route</th><th>Rate</th><th>Readiness</th><th>Capacity</th></tr></thead>
                                <tbody id="liquidity-currency-body"><tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Нет данных.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-5 card-neo">
                        <div style="font-weight:850;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1rem;">Liquidity Methods</div>
                        <div class="neo-table-container">
                            <table class="neo-table">
                                <thead><tr><th>Method</th><th>Type</th><th>Currencies</th><th>Status</th></tr></thead>
                                <tbody id="liquidity-methods-body"><tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Нет данных.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="grid-12" style="margin-bottom: 2rem;">
                    <div class="col-6 card-neo">
                        <div style="font-weight:850;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1rem;">Currency Corridors</div>
                        <div class="neo-table-container">
                            <table class="neo-table">
                                <thead><tr><th>Node</th><th>Currency</th><th>Direction</th><th>Capacity</th></tr></thead>
                                <tbody id="liquidity-corridors-body"><tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Нет данных.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-6 card-neo">
                        <div style="font-weight:850;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1rem;">Intent Corridors</div>
                        <div class="neo-table-container">
                            <table class="neo-table">
                                <thead><tr><th>Intent</th><th>Corridor</th><th>Score</th><th>Friction</th></tr></thead>
                                <tbody id="liquidity-intent-corridors-body"><tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Нет данных.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div style="font-weight:850;text-transform:uppercase;letter-spacing:.5px;margin:0 0 1rem;">Partner Balance Liquidity</div>
                <div class="neo-table-container">
                    <table class="neo-table">
                        <thead>
                            <tr><th>Партнер</th><th>Валюта</th><th>Available</th><th>Reserved</th><th>API Holds</th><th>Native</th><th>Shops</th><th>Статус</th></tr>
                        </thead>
                        <tbody id="liquidity-table-body"><tr><td colspan="8" style="text-align:center;color:var(--text-muted);">Ликвидность загрузится при открытии вкладки.</td></tr></tbody>
                    </table>
                </div>
            </div>

            <!-- Tab: Channels -->
            <div class="tab-pane" id="tab-channels">
                <div class="card-neo" style="margin-bottom: 2rem; display:flex; justify-content:space-between; align-items:flex-start; gap:18px; flex-wrap:wrap;">
                    <div>
                        <div class="metric-title" style="color: var(--primary);">Channels</div>
                        <h2 style="font-size:1.7rem;font-weight:950;margin:8px 0;letter-spacing:-.04em;">Каналы продаж</h2>
                        <p style="color:var(--text-muted);margin:0;max-width:760px;line-height:1.6;">Потерянная панель каналов: Meanly Storefront, Yandex Market, offline/CMS/messenger adapters and channel health.</p>
                    </div>
                    <button type="button" class="btn-neo btn-primary-neo" onclick="loadChannels()">Обновить каналы</button>
                </div>
                <div class="grid-12">
                    <div class="col-5 card-neo">
                        <div style="font-weight:850;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1rem;">Channel Matrix</div>
                        <div class="neo-table-container">
                            <table class="neo-table">
                                <thead><tr><th>Канал</th><th>Group</th><th>Links</th><th>Errors</th></tr></thead>
                                <tbody id="channels-table-body"><tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Нет данных.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-7 card-neo">
                        <div style="font-weight:850;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1rem;">Shop Channel Health</div>
                        <div class="neo-table-container">
                            <table class="neo-table">
                                <thead><tr><th>Магазин</th><th>Партнер</th><th>Meanly</th><th>Yandex</th><th>IDs</th></tr></thead>
                                <tbody id="channel-shops-body"><tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Нет данных.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Shops -->
            <div class="tab-pane" id="tab-shops">
                <div class="card-neo" style="margin-bottom: 2rem; display:flex; justify-content:space-between; align-items:center; gap: 15px; flex-wrap:wrap;">
                    <div style="flex:1; min-width:250px;">
                        <input type="text" id="shops-search-input" placeholder="Поиск магазинов (по названию, партнеру)..." class="input-neo" oninput="loadShops()">
                    </div>
                </div>

                <div class="neo-table-container">
                    <table class="neo-table" id="shops-table">
                        <thead>
                            <tr>
                                <th>Название магазина</th>
                                <th>Владелец (Юрлицо)</th>
                                <th>Статус</th>
                                <th>Режим песочницы</th>
                                <th>Разрешенные регионы</th>
                                <th>Разрешенные категории</th>
                            </tr>
                        </thead>
                        <tbody id="shops-table-body">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 1.5rem;" id="shops-pagination"></div>
            </div>

            <!-- Tab 4: Orders -->
            <div class="tab-pane" id="tab-orders">
                <div class="card-neo" style="margin-bottom: 2rem; display:flex; justify-content:space-between; align-items:center; gap: 15px; flex-wrap:wrap;">
                    <div style="flex:1; min-width:250px; display:flex; gap:10px;">
                        <input type="text" id="orders-search-input" placeholder="Поиск заказов (по ID, SKU, магазину)..." class="input-neo" style="flex:1;" oninput="loadOrders()">
                    </div>
                    <!-- Status Filter Tabs -->
                    <div style="display:flex; gap: 8px;" id="order-status-tabs">
                        <button onclick="filterOrdersStatus('', this)" class="btn-neo btn-primary-neo">Все</button>
                        <button onclick="filterOrdersStatus('active', this)" class="btn-neo">В работе</button>
                        <button onclick="filterOrdersStatus('completed', this)" class="btn-neo">Выполненные</button>
                        <button onclick="filterOrdersStatus('cancelled', this)" class="btn-neo">Отмененные</button>
                        <button onclick="filterOrdersStatus('sandbox', this)" class="btn-neo">Тестовые</button>
                    </div>
                </div>

                <div class="neo-table-container">
                    <table class="neo-table" id="orders-table">
                        <thead>
                            <tr>
                                <th>ID Заказа</th>
                                <th>Партнер / Магазин</th>
                                <th>SKU товара</th>
                                <th>Цена RUB</th>
                                <th>Ключ активации</th>
                                <th>Статус</th>
                                <th>Тип</th>
                                <th>Дата транзакции</th>
                            </tr>
                        </thead>
                        <tbody id="orders-table-body">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 1.5rem;" id="orders-pagination"></div>

                <div class="card-neo" style="margin-top: 2rem;">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:1rem;">
                        <div>
                            <div class="metric-title" style="color: var(--primary);">Unified Operations Feed</div>
                            <h3 style="font-size:1.15rem;font-weight:900;margin:.3rem 0 0;">Meanly API + Ledger + Fulfillment history</h3>
                        </div>
                        <button type="button" class="btn-neo" onclick="loadOperationHistory()">Refresh feed</button>
                    </div>
                    <div class="neo-table-container">
                        <table class="neo-table" id="operations-table">
                            <thead>
                                <tr>
                                    <th>Source</th>
                                    <th>Reference</th>
                                    <th>Partner / Provider</th>
                                    <th>SKU</th>
                                    <th>Amount</th>
                                    <th>Status / Failure</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody id="operations-table-body">
                                <tr><td colspan="7" style="text-align:center;color:var(--text-muted);">Feed загрузится вместе с заказами.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab 5: Catalog -->
            <div class="tab-pane" id="tab-catalog">
                <div class="card-neo" style="margin-bottom: 2rem; display:flex; justify-content:space-between; align-items:center; gap: 15px; flex-wrap:wrap;">
                    <div style="flex:1; min-width:250px;">
                        <input type="text" id="catalog-search-input" placeholder="Поиск по общему каталогу (название, SKU)..." class="input-neo" oninput="loadCatalog()">
                    </div>
                </div>

                <div class="neo-table-container">
                    <table class="neo-table" id="catalog-table">
                        <thead>
                            <tr>
                                <th>Товар</th>
                                <th>SKU</th>
                                <th>Цена RUB</th>
                                <th>Складской остаток</th>
                                <th>Магазин партнера</th>
                                <th>Интеграция Yandex</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody id="catalog-table-body">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 1.5rem;" id="catalog-pagination"></div>
            </div>

            <!-- Tab: Inventory -->
            <div class="tab-pane" id="tab-inventory">
                <div class="card-neo" style="margin-bottom: 2rem; display:flex; justify-content:space-between; align-items:flex-start; gap:18px; flex-wrap:wrap;">
                    <div>
                        <div class="metric-title" style="color: var(--primary);">Inventory Control</div>
                        <h2 style="font-size:1.7rem;font-weight:950;margin:8px 0;letter-spacing:-.04em;">Склады, остатки и ваучеры</h2>
                        <p style="color:var(--text-muted);margin:0;max-width:760px;line-height:1.6;">Глобальная версия partner warehouse/voucher registry: master warehouses, low stock rows, voucher pool status and stock sync evidence.</p>
                    </div>
                    <button type="button" class="btn-neo btn-primary-neo" onclick="loadInventory()">Обновить inventory</button>
                </div>
                <div class="grid-12" style="margin-bottom:2rem;" id="inventory-summary-cards">
                    <div class="col-12 card-neo" style="color:var(--text-muted);">Inventory metrics загрузятся при открытии вкладки.</div>
                </div>
                <div class="grid-12" style="margin-bottom:2rem;">
                    <div class="col-5 card-neo">
                        <div style="font-weight:850;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1rem;">Warehouses</div>
                        <div class="neo-table-container">
                            <table class="neo-table">
                                <thead><tr><th>Warehouse</th><th>Shop</th><th>Rows</th><th>Status</th></tr></thead>
                                <tbody id="inventory-warehouses-body"><tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Нет данных.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-7 card-neo">
                        <div style="font-weight:850;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1rem;">Stock Rows</div>
                        <div class="neo-table-container">
                            <table class="neo-table">
                                <thead><tr><th>Product</th><th>Warehouse</th><th>Partner</th><th>Count</th></tr></thead>
                                <tbody id="inventory-stock-body"><tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Нет данных.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="card-neo">
                    <div style="font-weight:850;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1rem;">Voucher Registry</div>
                    <div class="neo-table-container">
                        <table class="neo-table">
                            <thead><tr><th>Voucher</th><th>SKU</th><th>Shop</th><th>Reserve</th><th>Status</th></tr></thead>
                            <tbody id="inventory-vouchers-body"><tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Нет данных.</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab: Providers -->
            <div class="tab-pane" id="tab-providers">
                <div class="card-neo" style="margin-bottom: 2rem; display:flex; justify-content:space-between; align-items:flex-start; gap:18px; flex-wrap:wrap;">
                    <div style="flex:1; min-width:280px;">
                        <div class="metric-title" style="color: var(--primary);">Provider Plane</div>
                        <h2 style="font-size: 1.7rem; font-weight: 950; margin: 8px 0; letter-spacing: -0.04em;">Провайдеры товаров</h2>
                        <p style="color: var(--text-muted); margin: 0; max-width: 760px; line-height: 1.6;">
                            Технический пульт upstream: Digital Goods Source, синк каталогов, source health и partner balances. Деньги живут только на уровне партнера в Organizations.
                        </p>
                    </div>
                    <button type="button" class="btn-neo btn-primary-neo" onclick="loadProviders()">Обновить статус</button>
                </div>

                <div class="grid-12" style="margin-bottom: 2rem;">
                    <div class="col-8 card-neo">
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:1rem;">
                            <div style="font-weight:850; text-transform:uppercase; letter-spacing:.5px;">Upstream Providers</div>
                            <input type="text" id="providers-search-input" class="input-neo" placeholder="Поиск провайдера..." style="max-width:260px;" oninput="loadProviders()">
                        </div>
                        <div class="neo-table-container">
                            <table class="neo-table" id="providers-table">
                                <thead>
                                    <tr>
                                        <th>Провайдер</th>
                                        <th>Каталог</th>
                                        <th>Credentials</th>
                                        <th>Terminal</th>
                                        <th>Синк</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody id="providers-table-body">
                                    <tr><td colspan="6" style="text-align:center;color:var(--text-muted);">Откройте вкладку для загрузки провайдеров.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-4 card-neo" style="display:flex; flex-direction:column; gap:1rem;">
                        <div>
                            <div style="font-weight:850; text-transform:uppercase; letter-spacing:.5px; margin-bottom:.5rem;">Meanly API Support Plane</div>
                            <p style="color:var(--text-muted); font-size:.78rem; line-height:1.55; margin:0;">
                                Provider tab показывает upstream и документацию. Партнерский баланс и пополнения управляются из Organizations как единый settlement account.
                            </p>
                        </div>
                        <div id="provider-kernel-support" style="min-height:120px; background:rgba(0,0,0,.18); border:1px solid var(--border-card); border-radius:12px; padding:1rem; color:var(--text-muted); font-family:var(--font-tech); font-size:.72rem; line-height:1.55;">
                            Meanly API docs/devices support plane загрузится вместе с провайдерами.
                        </div>
                    </div>
                </div>

                <div id="providers-operation-output" class="card-neo" style="display:none; font-family:var(--font-tech); font-size:.72rem; color:var(--text-muted); white-space:pre-wrap; max-height:260px; overflow:auto;"></div>
            </div>

            <!-- Tab: Decision Console -->
            <div class="tab-pane" id="tab-decision-console">
                <div class="card-neo" style="margin-bottom: 2rem; display:flex; justify-content:space-between; align-items:flex-start; gap: 18px; flex-wrap:wrap;">
                    <div>
                        <div class="metric-title" style="color: var(--primary);">Governance Interface</div>
                        <h2 style="font-size: 1.7rem; font-weight: 950; margin: 8px 0; letter-spacing: -0.04em;">Decision Console</h2>
                        <p style="color: var(--text-muted); margin: 0; max-width: 720px; line-height: 1.6;">
                            Authorize or reject recommended market-model changes. Approval changes only governance state; it does not mutate SearchProfile, ranking, catalog facts, or provider supply.
                        </p>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <button type="button" class="btn-neo btn-primary-neo" onclick="loadGrowth()">Refresh growth graph</button>
                        @foreach (['proposed', 'approved', 'rejected', 'applied'] as $decisionStatus)
                            <span class="badge-neo" style="background:rgba(255,255,255,0.03); color:var(--text-main); border:1px solid var(--border-card);">
                                {{ strtoupper($decisionStatus) }} · {{ (int) ($decisionStatusCounts[$decisionStatus] ?? 0) }}
                            </span>
                        @endforeach
                    </div>
                </div>

                <div class="grid-12" style="margin-bottom: 2rem;" id="growth-summary-cards">
                    <div class="col-12 card-neo" style="color:var(--text-muted);">Growth graph загрузится при открытии вкладки.</div>
                </div>

                <div class="grid-12" style="margin-bottom: 2rem;">
                    <div class="col-6 card-neo">
                        <div style="font-weight:850;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1rem;">Demand Gaps</div>
                        <div class="neo-table-container">
                            <table class="neo-table">
                                <thead><tr><th>Query</th><th>Demand</th><th>Lost GMV</th><th>Diagnosis</th></tr></thead>
                                <tbody id="growth-demand-body"><tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Нет данных.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-6 card-neo">
                        <div style="font-weight:850;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1rem;">Opportunity Cases</div>
                        <div class="neo-table-container">
                            <table class="neo-table">
                                <thead><tr><th>Case</th><th>Owner</th><th>Score</th><th>Status</th></tr></thead>
                                <tbody id="growth-cases-body"><tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Нет данных.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="grid-12" style="margin-bottom: 2rem;">
                    <div class="col-6 card-neo">
                        <div style="font-weight:850;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1rem;">Search Demand Recommendations</div>
                        <div class="neo-table-container">
                            <table class="neo-table">
                                <thead><tr><th>Recommendation</th><th>Insight</th><th>Impact</th><th>Status</th></tr></thead>
                                <tbody id="growth-recommendations-body"><tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Нет данных.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-6 card-neo">
                        <div style="font-weight:850;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1rem;">Operational Alerts</div>
                        <div class="neo-table-container">
                            <table class="neo-table">
                                <thead><tr><th>Alert</th><th>Surface</th><th>Count</th><th>Status</th></tr></thead>
                                <tbody id="growth-alerts-body"><tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Нет данных.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div style="display:flex; flex-direction:column; gap: 1rem;">
                    @forelse($decisionRecommendations as $recommendation)
                        <div class="card-neo" style="padding: 1.25rem;">
                            <div style="display:flex; justify-content:space-between; gap: 1rem; align-items:flex-start; flex-wrap:wrap;">
                                <div style="min-width:260px; flex:1;">
                                    <div style="font-family: var(--font-tech); color: var(--primary); font-size: .72rem; font-weight: 900; letter-spacing: .08em;">
                                        {{ $recommendation->type }}
                                    </div>
                                    <div style="font-size: 1.35rem; font-weight: 950; margin: .35rem 0; letter-spacing: -0.03em;">
                                        {{ $recommendation->query }}
                                    </div>
                                    <div style="display:flex; gap:8px; flex-wrap:wrap; font-size:.75rem;">
                                        <span class="badge-neo" style="background:rgba(255,255,255,0.03); color:var(--text-muted); border:1px solid var(--border-card);">{{ $recommendation->insight_type }}</span>
                                        <span class="badge-neo" style="background:rgba(245,48,3,.08); color:var(--primary); border:1px solid rgba(245,48,3,.22);">{{ $recommendation->status }}</span>
                                        <span class="badge-neo" style="background:rgba(255,255,255,0.03); color:var(--text-muted); border:1px solid var(--border-card);">updated {{ optional($recommendation->updated_at)->diffForHumans() }}</span>
                                    </div>
                                </div>
                                <div style="display:flex; gap:.75rem; align-items:stretch;">
                                    <div style="min-width:90px; text-align:center; border:1px solid var(--border-card); border-radius:12px; padding:.75rem;">
                                        <div style="font-size:1.25rem; font-weight:950;">{{ number_format((float) $recommendation->impact_score, 1) }}</div>
                                        <div style="font-size:.62rem; color:var(--text-muted); text-transform:uppercase; font-weight:800;">Impact</div>
                                    </div>
                                    <div style="min-width:90px; text-align:center; border:1px solid var(--border-card); border-radius:12px; padding:.75rem;">
                                        <div style="font-size:1.25rem; font-weight:950;">{{ number_format((float) $recommendation->confidence, 1) }}</div>
                                        <div style="font-size:.62rem; color:var(--text-muted); text-transform:uppercase; font-weight:800;">Confidence</div>
                                    </div>
                                </div>
                            </div>

                            <div class="grid-12" style="margin-top: 1rem;">
                                <div class="col-6" style="background:rgba(255,255,255,.02); border:1px solid var(--border-card); border-radius:12px; padding:1rem; overflow:auto;">
                                    <div class="metric-title" style="margin-bottom:.6rem;">Expected Entity</div>
                                    <pre style="white-space:pre-wrap; color:var(--text-muted); font-family:var(--font-tech); font-size:.72rem; margin:0;">{{ json_encode($recommendation->expected_entity ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                </div>
                                <div class="col-6" style="background:rgba(255,255,255,.02); border:1px solid var(--border-card); border-radius:12px; padding:1rem; overflow:auto;">
                                    <div class="metric-title" style="margin-bottom:.6rem;">Evidence</div>
                                    <pre style="white-space:pre-wrap; color:var(--text-muted); font-family:var(--font-tech); font-size:.72rem; margin:0;">{{ json_encode($recommendation->evidence ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                </div>
                            </div>

                            @if($recommendation->status !== \App\Models\SearchDemandRecommendation::STATUS_APPLIED)
                                <div style="display:flex; justify-content:flex-end; gap:.75rem; margin-top:1rem;">
                                    <form method="POST" action="{{ route('ops.decision-console.recommendations.approve', $recommendation) }}">
                                        @csrf
                                        <button class="btn-neo" style="background:#10b981; color:#fff; border-color:rgba(16,185,129,.35);" type="submit">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('ops.decision-console.recommendations.reject', $recommendation) }}">
                                        @csrf
                                        <button class="btn-neo" style="background:#f43f5e; color:#fff; border-color:rgba(244,63,94,.35);" type="submit">Reject</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="card-neo" style="padding: 2rem; color: var(--text-muted); text-align:center;">
                            No recommendations yet. Run <code>php artisan search-signals:recommend</code> to generate proposals from interpreted demand signals.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Tab: Search Integrations -->
            <div class="tab-pane" id="tab-search-integrations">
                <div class="card-neo" style="margin-bottom: 2rem; display:flex; justify-content:space-between; align-items:flex-start; gap:18px; flex-wrap:wrap;">
                    <div>
                        <div class="metric-title" style="color: var(--primary);">External Search Signals</div>
                        <h2 style="font-size:1.7rem;font-weight:950;margin:8px 0;letter-spacing:-.04em;">Search Integrations</h2>
                        <p style="color:var(--text-muted);margin:0;max-width:780px;line-height:1.6;">ZeroLayer sources, external query demand, SERP telemetry and recommendation orchestration in one Ops surface.</p>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button type="button" class="btn-neo btn-primary-neo" onclick="loadSearchIntegrations()">Refresh</button>
                        <button type="button" class="btn-neo" onclick="runSearchSignalAction('/ops/dashboard/search-signals/promote-zero-layer', {limit: 250}, this)">Promote ZeroLayer</button>
                        <button type="button" class="btn-neo" onclick="runSearchSignalAction('/ops/dashboard/search-signals/analyze', {limit: 25, days: 90, source: 'all'}, this)">Analyze</button>
                        <button type="button" class="btn-neo" onclick="runSearchSignalAction('/ops/dashboard/search-signals/recommend', {limit: 25, days: 90, source: 'all', min_score: 1}, this)">Recommend</button>
                    </div>
                </div>

                <div class="grid-12" style="margin-bottom:2rem;" id="search-summary-cards">
                    <div class="col-12 card-neo" style="color:var(--text-muted);">Search integrations загрузятся при открытии вкладки.</div>
                </div>

                <div class="grid-12" style="margin-bottom:2rem;">
                    <div class="col-7 card-neo">
                        <div style="font-weight:850;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1rem;">ZeroLayer Integrations</div>
                        <div class="neo-table-container">
                            <table class="neo-table">
                                <thead><tr><th>Integration</th><th>Source</th><th>Credentials</th><th>Signals</th><th>Action</th></tr></thead>
                                <tbody id="search-integrations-body"><tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Нет данных.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-5 card-neo">
                        <div style="font-weight:850;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1rem;">Connect Source</div>
                        <div style="display:grid;gap:10px;">
                            <input id="zero-layer-connect-name" class="input-neo" placeholder="Integration name, e.g. Meanly GA4">
                            <select id="zero-layer-connect-source" class="input-neo" onchange="updateZeroLayerConnectorHint()">
                                <option value="google_analytics">Google Analytics 4</option>
                                <option value="google_search_console">Google Search Console</option>
                                <option value="google_ads">Google Ads</option>
                                <option value="yandex_webmaster">Yandex Webmaster</option>
                                <option value="indexnow">IndexNow</option>
                                <option value="bing_web_search">Bing Web Search</option>
                                <option value="yahoo_search">Yahoo Search</option>
                                <option value="duckduckgo_search">DuckDuckGo Search</option>
                                <option value="yandex_direct">Yandex Direct</option>
                                <option value="meta_ads">Meta Ads</option>
                                <option value="tiktok_ads">TikTok Ads</option>
                            </select>
                            <textarea id="zero-layer-connect-credentials" class="input-neo" style="min-height:94px;font-family:var(--font-tech);font-size:.72rem;" placeholder='{"access_token":"..."}'></textarea>
                            <textarea id="zero-layer-connect-settings" class="input-neo" style="min-height:94px;font-family:var(--font-tech);font-size:.72rem;" placeholder='{"property_id":"123456789"}'></textarea>
                            <div id="zero-layer-connect-hint" style="font-size:.72rem;color:var(--text-muted);line-height:1.5;"></div>
                            <button type="button" class="btn-neo btn-primary-neo" onclick="saveZeroLayerIntegration(this)">Save Integration</button>
                        </div>
                    </div>
                </div>

                <div class="grid-12" style="margin-bottom:2rem;">
                    <div class="col-5 card-neo">
                        <div style="font-weight:850;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1rem;">Source Totals</div>
                        <div class="neo-table-container">
                            <table class="neo-table">
                                <thead><tr><th>Pipeline</th><th>Source</th><th>Signals</th><th>Clicks</th></tr></thead>
                                <tbody id="search-source-totals-body"><tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Нет данных.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-7 card-neo">
                        <div style="font-weight:850;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1rem;">ZeroLayer Signals</div>
                        <div class="neo-table-container">
                            <table class="neo-table">
                                <thead><tr><th>Query</th><th>Source</th><th>Position</th><th>Demand</th></tr></thead>
                                <tbody id="search-zero-layer-body"><tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Нет данных.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="grid-12" style="margin-bottom:2rem;">
                    <div class="col-12 card-neo">
                        <div style="font-weight:850;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1rem;">External Demand Signals</div>
                        <div class="neo-table-container">
                            <table class="neo-table">
                                <thead><tr><th>Query</th><th>Source</th><th>Geo</th><th>Demand</th></tr></thead>
                                <tbody id="search-external-body"><tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Нет данных.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card-neo">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:1rem;">
                        <div style="font-weight:850;text-transform:uppercase;letter-spacing:.5px;">Pull Search Signals</div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                            <select id="search-pull-provider" class="input-neo" style="width:220px;">
                                <option value="google_search_console">Google Search Console</option>
                                <option value="yandex_webmaster">Yandex Webmaster</option>
                                <option value="google_suggest">Google Suggest</option>
                                <option value="yandex_suggest">Yandex Suggest</option>
                            </select>
                            <input id="search-pull-query" class="input-neo" style="width:220px;" placeholder="seed query for suggest">
                            <button type="button" class="btn-neo" onclick="pullSearchSignals(this)">Pull</button>
                        </div>
                    </div>
                    <div class="neo-table-container">
                        <table class="neo-table">
                            <thead><tr><th>Recommendation</th><th>Insight</th><th>Impact</th><th>Status</th></tr></thead>
                            <tbody id="search-recommendations-body"><tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Нет данных.</td></tr></tbody>
                        </table>
                    </div>
                    <div id="search-action-output" style="display:none;margin-top:1rem;background:rgba(0,0,0,.2);border:1px solid var(--border-card);border-radius:12px;padding:1rem;font-family:var(--font-tech);font-size:.72rem;color:var(--text-muted);white-space:pre-wrap;max-height:220px;overflow:auto;"></div>
                </div>
            </div>

            <!-- Tab 6: Support -->
            <div class="tab-pane" id="tab-support">
                <div class="card-neo" style="margin-bottom: 2rem; display:flex; justify-content:space-between; align-items:center; gap: 15px; flex-wrap:wrap;">
                    <div style="flex:1; min-width:250px;">
                        <input type="text" id="support-search-input" placeholder="Поиск тикетов по теме или партнеру..." class="input-neo" oninput="loadTickets()">
                    </div>
                </div>

                <div class="neo-table-container">
                    <table class="neo-table" id="support-table">
                        <thead>
                            <tr>
                                <th>Тема обращения</th>
                                <th>Магазин / Партнер</th>
                                <th>Статус тикета</th>
                                <th>Дата создания</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody id="support-table-body">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 1.5rem;" id="support-pagination"></div>
            </div>

            <!-- Tab 7: Audit & AI -->
            <div class="tab-pane" id="tab-ai-audit">
                <div class="grid-12 audit-ai-layout">
                    <!-- Left: Interactive Ops AI Chat -->
                    <div class="col-7 card-neo audit-ai-chat-card">
                        <div class="audit-ai-heading">
                            <span class="audit-ai-status-dot"></span>
                            Ops AI Assistant
                        </div>
                        
                        <div class="chat-container audit-ai-chat" id="ops-ai-chat-box">
                            <div class="chat-message ai">
                                Я подключен к операционным данным Meanly: партнерам, заказам, складам, провайдерам и ledger-событиям. Спросите про риски, аномалии, клиринг или состояние системы.
                            </div>
                        </div>

                        <div class="audit-ai-actions">
                            <input type="text" id="ops-ai-chat-input" placeholder="Например: проверь риски по партнерам и складам..." class="input-neo" style="flex:1;" onkeypress="if(event.key==='Enter') sendOpsAiMessage()">
                            <button onclick="sendOpsAiMessage()" class="btn-neo btn-primary-neo">Отправить ➔</button>
                        </div>
                    </div>

                    <!-- Right: Unified Audit Tools -->
                    <div class="col-5 audit-ai-tools">
                        <div class="card-neo audit-tool-card">
                            <div>
                                <div class="metric-title" style="color: var(--primary);">Ledger Integrity</div>
                                <h3 style="font-size:1.05rem; font-weight:950; margin:.35rem 0;">Chain Validator</h3>
                                <p style="font-size:.8rem; color:var(--text-muted); margin:0; line-height:1.5;">
                                    Проверяет криптографическую цепочку ledger и broken fingerprint links. Это audit evidence, не mutation path.
                                </p>
                            </div>
                            <button class="btn-neo btn-primary-neo" type="button" onclick="validateTribunalChain()" style="align-self:flex-start;">Validate chain</button>
                            <div id="tribunal-chain-result" class="audit-result-box" style="background:rgba(0,0,0,.25); border:1px solid var(--border-card); color:var(--text-muted);">
                                Awaiting validation run...
                            </div>
                        </div>

                        <div class="card-neo audit-tool-card">
                            <div>
                                <div class="metric-title" style="color: var(--primary);">Operational Audit</div>
                                <h3 style="font-size:1.05rem; font-weight:950; margin:.35rem 0;">Global Ledger Audit</h3>
                                <p style="font-size:.8rem; color:var(--text-muted); margin:0; line-height:1.5;">
                                    ИИ сверяет ledger events, обороты, балансы и операционные аномалии поставок.
                                </p>
                            </div>
                            <div id="ops-audit-result-box" class="audit-result-box" style="background: rgba(0,0,0,0.3); border: 1px dashed var(--border-neon);">
                                <span style="color:var(--text-muted);">Глобальный аудит еще не запущен.</span>
                            </div>
                            <button onclick="runGlobalOpsAudit()" class="btn-neo btn-primary-neo" id="ops-audit-btn" style="width: 100%;">
                                Запустить глобальный аудит
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 🎫 Modal: Ticket Details & Answer -->
    <div class="modal" id="ticket-modal">
        <div class="modal-card">
            <div class="modal-header">
                <div class="modal-title" id="modal-ticket-title">Детали обращения</div>
                <button class="modal-close" onclick="closeModal('ticket-modal')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            <div class="modal-body" style="display:flex; flex-direction:column; gap:15px;" id="modal-ticket-body">
                <!-- Ticket details and messages -->
            </div>
            <div class="modal-footer" style="flex-direction:column; gap:12px; align-items:stretch;">
                <textarea id="modal-ticket-reply-textarea" placeholder="Напишите ответ партнеру..." class="input-neo" style="height: 80px; resize: none;"></textarea>
                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button class="btn-neo" onclick="closeModal('ticket-modal')">Закрыть</button>
                    <button class="btn-neo btn-primary-neo" id="modal-ticket-reply-btn" onclick="submitTicketReply()">Ответить и решить тикет ➔</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 👤 Modal: User Info (Admin Profile Settings Custom) -->
    <div class="modal" id="profile-modal">
        <div class="modal-card" style="max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header">
                <div class="modal-title">Настройки Профиля</div>
                <button class="modal-close" onclick="closeModal('profile-modal')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            <div class="modal-body" style="display:flex; flex-direction:column; gap:18px;">
                <div style="text-align:center; padding-bottom:0.5rem;">
                    <div class="user-avatar" style="width: 72px; height: 72px; font-size: 2rem; margin: 0 auto 12px auto; border-radius: 16px;">
                        {{ mb_substr($user->name ?: ($user->first_name ?: 'А'), 0, 1) }}
                    </div>
                    <div style="font-weight:850; font-size:1.1rem; color:var(--text-main);">{{ $user->name ?: 'Администратор' }}</div>
                    <div style="font-size:0.7rem; color:var(--primary); font-weight:800; text-transform:uppercase; margin-top:4px;">{{ $user->sovereignIdentityAddress() }}</div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                        <label style="font-size: 0.7rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; margin-bottom: 6px; display: block;">Права Доступа</label>
                        <input type="text" class="input-neo" value="Sovereign Validator" disabled style="opacity: 0.8; font-size: 0.75rem; padding: 8px 12px;">
                    </div>
                    <div>
                        <label style="font-size: 0.7rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; margin-bottom: 6px; display: block;">Системный ID</label>
                        <input type="text" class="input-neo" value="{{ $user->meta['l1_address'] ?? 'simple-l1:ops:main-node' }}" disabled style="opacity: 0.8; font-family: monospace; font-size: 0.75rem; padding: 8px 12px;">
                    </div>
                </div>

                <div>
                    <label style="font-size: 0.7rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; margin-bottom: 6px; display: block;">Цветовая Схема (Оформление)</label>
                    <div class="skin-switcher-pill" style="display: flex; align-items: center; background: rgba(255,255,255,0.03); border: 1px solid var(--border-card); border-radius: 100px; padding: 4px; gap: 4px; box-shadow: var(--shadow-neo-inset); width: 100%; box-sizing: border-box;">
                        <button onclick="setTheme('partner')" class="skin-btn" id="skin-btn-partner" style="flex: 1; background: transparent; border: none; color: var(--text-muted); font-size: 0.65rem; font-weight: 800; padding: 8px 10px; border-radius: 100px; cursor: pointer; transition: all 0.3s; text-transform: uppercase; letter-spacing: 0.5px;">
                            Partner 🌟
                        </button>
                        <button onclick="setTheme('consortium')" class="skin-btn" id="skin-btn-consortium" style="flex: 1; background: transparent; border: none; color: var(--text-muted); font-size: 0.65rem; font-weight: 800; padding: 8px 10px; border-radius: 100px; cursor: pointer; transition: all 0.3s; text-transform: uppercase; letter-spacing: 0.5px;">
                            Flagship 🚩
                        </button>
                        <button onclick="setTheme('retro')" class="skin-btn" id="skin-btn-retro" style="flex: 1; background: transparent; border: none; color: var(--text-muted); font-size: 0.65rem; font-weight: 800; padding: 8px 10px; border-radius: 100px; cursor: pointer; transition: all 0.3s; text-transform: uppercase; letter-spacing: 0.5px;">
                            Retro ⚡
                        </button>
                    </div>
                </div>

                <div style="border-top: 1px dashed var(--border-card); padding-top: 15px;">
                    <label style="font-size: 0.7rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <svg viewBox="0 0 24 24" style="width: 14px; height: 14px; fill: none; stroke: currentColor; stroke-width: 2.5;"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path></svg>
                        Аппаратные Ключи Доступа (Passkey)
                    </label>
                    <div style="background: rgba(0,0,0,0.2); border: 1px solid var(--border-card); border-radius: 12px; padding: 15px; width: 100%; box-sizing: border-box;">
                        @livewire('passkeys')
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="margin-top: 10px;">
                <button class="btn-neo btn-primary-neo" onclick="closeModal('profile-modal')" style="width:100%;">Готово</button>
            </div>
        </div>
    </div>

    <!-- --- 🧠 Dynamic Javascript SPA Core --- -->
    <script>
        // Theme Switcher & Persistence
        function setTheme(theme) {
            if (window.MeanlyTheme && typeof window.MeanlyTheme.apply === 'function') {
                theme = window.MeanlyTheme.apply(theme);
            }
            localStorage.setItem("theme", theme);
            document.body.setAttribute("data-theme", theme);
            document.documentElement.setAttribute("data-theme", theme);
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
                btn.classList.remove("active-skin", "bg-[#f53003]", "text-white", "bg-[#ff9f0a]", "text-black", "bg-[#7c3aed]", "text-white");
                btn.style.background = "transparent";
                btn.style.color = "";
            });
            
            const activeBtn = document.getElementById("skin-btn-" + currentTheme);
            if (activeBtn) {
                activeBtn.classList.add("active-skin");
                if (currentTheme === "partner") {
                    activeBtn.style.background = "#ff9f0a";
                    activeBtn.style.color = "#000000";
                } else if (currentTheme === "retro") {
                    activeBtn.style.background = "#7c3aed";
                    activeBtn.style.color = "#ffffff";
                } else {
                    activeBtn.style.background = "#f53003";
                    activeBtn.style.color = "#ffffff";
                }
            }
        }

        // SPA Tab Switching
        function switchTab(tabId) {
            if (tabId === 'tribunal') {
                tabId = 'ai-audit';
            }
            if (tabId === 'treasury' || tabId === 'liquidity') {
                tabId = 'finance-liquidity';
            }
            localStorage.setItem("ops_active_tab", tabId);
            
            // Toggle active menu items
            document.querySelectorAll(".menu-item").forEach(item => item.classList.remove("active"));
            const menuBtn = document.getElementById("menu-" + tabId);
            if (menuBtn) menuBtn.classList.add("active");
            
            // Toggle active pane
            document.querySelectorAll(".tab-pane").forEach(pane => pane.classList.remove("active"));
            const pane = document.getElementById("tab-" + tabId);
            if (pane) pane.classList.add("active");
            
            // Adjust title text
            const titleMap = {
                'dashboard': 'Инфопанель',
                'partners': 'Организации (Партнеры)',
                'finance-liquidity': 'Финансы и ликвидность',
                'channels': 'Каналы продаж',
                'shops': 'Магазины партнеров',
                'orders': 'Все заказы',
                'catalog': 'Все товары',
                'inventory': 'Склады и ваучеры',
                'providers': 'Провайдеры товаров',
                'decision-console': 'Decision Console',
                'search-integrations': 'Search Integrations',
                'support': 'Поддержка и тикеты',
                'ai-audit': 'Аудит и ИИ'
            };
            document.getElementById("page-title-text").innerText = titleMap[tabId] || 'Центр Операций';

            // Lazy Load Tab Data
            if (tabId === 'partners') loadPartners();
            if (tabId === 'finance-liquidity') loadFinanceLiquidity();
            if (tabId === 'channels') loadChannels();
            if (tabId === 'shops') loadShops();
            if (tabId === 'orders') loadOrders();
            if (tabId === 'catalog') loadCatalog();
            if (tabId === 'inventory') loadInventory();
            if (tabId === 'providers') loadProviders();
            if (tabId === 'decision-console') loadGrowth();
            if (tabId === 'search-integrations') loadSearchIntegrations();
            if (tabId === 'support') loadTickets();
        }

        // Modals management
        function openModal(id) {
            document.getElementById(id).classList.add("active");
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove("active");
        }
        function openProfileModal() {
            openModal("profile-modal");
        }

        // --- Finance / Liquidity / Channels loaders ---
        async function loadFinanceLiquidity() {
            await Promise.all([loadTreasury(), loadLiquidity()]);
        }

        async function loadTreasury() {
            const cards = document.getElementById('treasury-summary-cards');
            const requestsBody = document.getElementById('treasury-requests-body');
            const eventsBody = document.getElementById('treasury-events-body');
            cards.innerHTML = `<div class="col-12 card-neo" style="color:var(--text-muted);">Загрузка treasury...</div>`;

            try {
                const res = await fetch('/ops/dashboard/treasury/data');
                const json = await res.json();
                if (!res.ok) throw new Error(json.error || 'Treasury load failed');
                const s = json.summary || {};

                cards.innerHTML = `
                    <div class="col-3 card-neo"><div class="metric-title">Available</div><div class="metric-value">${Number(s.available_balance || 0).toLocaleString()} ₽</div><div style="font-size:.65rem;color:var(--text-muted);margin-top:8px;">Partner balances</div></div>
                    <div class="col-3 card-neo"><div class="metric-title">Reserved</div><div class="metric-value">${Number(s.reserved_balance || 0).toLocaleString()} ₽</div><div style="font-size:.65rem;color:var(--text-muted);margin-top:8px;">Holds and settlements</div></div>
                    <div class="col-3 card-neo"><div class="metric-title">Pending Requests</div><div class="metric-value">${Number(s.pending_requests || 0).toLocaleString()}</div><div style="font-size:.65rem;color:var(--text-muted);margin-top:8px;">${Number(s.pending_amount || 0).toLocaleString()} total requested</div></div>
                    <div class="col-3 card-neo"><div class="metric-title">Native Reserved</div><div class="metric-value">${Number(s.native_reserved || 0).toLocaleString()}</div><div style="font-size:.65rem;color:var(--text-muted);margin-top:8px;">SL1/native liquidity</div></div>
                `;

                requestsBody.innerHTML = (json.requests || []).length
                    ? json.requests.map((item) => `
                        <tr>
                            <td><div style="font-weight:750;">${escapeHtml(item.partner)}</div></td>
                            <td>${escapeHtml(item.type)}</td>
                            <td><span style="font-family:var(--font-tech);">${Number(item.amount || 0).toLocaleString()} ${escapeHtml(item.currency || '')}</span></td>
                            <td>${opsStatusBadge(item.status)}</td>
                            <td><span style="color:var(--text-muted);">${escapeHtml(item.created_at)}</span></td>
                        </tr>
                    `).join('')
                    : `<tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Balance requests отсутствуют.</td></tr>`;

                eventsBody.innerHTML = (json.settlement_events || []).length
                    ? json.settlement_events.map((item) => `
                        <tr>
                            <td><span style="font-family:var(--font-tech);font-size:.72rem;">${escapeHtml(item.event_type)}</span></td>
                            <td>${escapeHtml(item.partner)}</td>
                            <td>${Number(item.amount || 0).toLocaleString()} ${escapeHtml(item.currency || '')}</td>
                            <td><span style="color:var(--text-muted);">${escapeHtml(item.created_at)}</span></td>
                        </tr>
                    `).join('')
                    : `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Settlement events отсутствуют.</td></tr>`;
            } catch (error) {
                cards.innerHTML = `<div class="col-12 card-neo" style="color:#f43f5e;">Ошибка загрузки Treasury.</div>`;
            }
        }

        async function loadLiquidity() {
            const tbody = document.getElementById('liquidity-table-body');
            const cards = document.getElementById('liquidity-summary-cards');
            const currencyBody = document.getElementById('liquidity-currency-body');
            const methodsBody = document.getElementById('liquidity-methods-body');
            const corridorsBody = document.getElementById('liquidity-corridors-body');
            const intentBody = document.getElementById('liquidity-intent-corridors-body');
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;color:var(--text-muted);">Загрузка ликвидности...</td></tr>`;
            currencyBody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Загрузка валют...</td></tr>`;
            methodsBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Загрузка методов...</td></tr>`;
            corridorsBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Загрузка corridors...</td></tr>`;
            intentBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Загрузка intent graph...</td></tr>`;

            try {
                const res = await fetch('/ops/dashboard/liquidity/data');
                const json = await res.json();
                if (!res.ok) throw new Error(json.error || 'Liquidity load failed');
                const s = json.summary || {};
                cards.innerHTML = `
                    <div class="col-3 card-neo"><div class="metric-title">Currencies</div><div class="metric-value">${Number(s.execution_ready_currencies || 0)}/${Number(s.currencies || 0)}</div><div style="font-size:.65rem;color:var(--text-muted);margin-top:8px;">execution ready</div></div>
                    <div class="col-3 card-neo"><div class="metric-title">Methods</div><div class="metric-value">${Number(s.liquidity_methods || 0)}</div><div style="font-size:.65rem;color:var(--text-muted);margin-top:8px;">active rails</div></div>
                    <div class="col-3 card-neo"><div class="metric-title">Intent Nodes</div><div class="metric-value">${Number(s.intent_nodes || 0)}</div><div style="font-size:.65rem;color:var(--text-muted);margin-top:8px;">liquidity graph</div></div>
                    <div class="col-3 card-neo"><div class="metric-title">Ready Corridors</div><div class="metric-value">${Number(s.intent_corridors_ready || 0)}</div><div style="font-size:.65rem;color:var(--text-muted);margin-top:8px;">execution paths</div></div>
                `;

                currencyBody.innerHTML = (json.currencies || []).length
                    ? json.currencies.map((item) => `
                        <tr>
                            <td><div style="font-weight:750;">${escapeHtml(item.code)}</div><div style="font-size:.68rem;color:var(--text-muted);">${escapeHtml(item.name || '')}</div></td>
                            <td><span style="font-family:var(--font-tech);font-size:.72rem;">${escapeHtml(item.base_asset || '—')} → ${escapeHtml(item.quote_asset || item.code || '—')}</span></td>
                            <td><span style="font-family:var(--font-tech);">${Number(item.rate_to_rub || 0).toLocaleString()}</span></td>
                            <td>${item.execution_ready ? successBadge(item.market_regime || 'ready') : opsStatusBadge(item.market_regime || 'not_ready')}<div style="font-size:.68rem;color:var(--text-muted);margin-top:4px;">conf ${Number(item.confidence_score || 0).toLocaleString()} · stress ${Number(item.stress_index || 0).toLocaleString()}</div></td>
                            <td><span style="font-family:var(--font-tech);">${Number(item.max_executable_size || 0).toLocaleString()}</span><div style="font-size:.68rem;color:var(--text-muted);">slip ${Number(item.estimated_slippage || 0).toLocaleString()} · ${Number(item.settlement_time_hours || 0)}h</div></td>
                        </tr>
                    `).join('')
                    : `<tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Валютные метрики отсутствуют.</td></tr>`;

                methodsBody.innerHTML = (json.methods || []).length
                    ? json.methods.map((item) => `
                        <tr>
                            <td><div style="font-weight:750;">${escapeHtml(item.name || item.slug)}</div><div style="font-size:.68rem;color:var(--text-muted);">${escapeHtml(item.slug)}</div></td>
                            <td>${escapeHtml(item.type || '—')}</td>
                            <td>${Number(item.currencies_count || 0)}</td>
                            <td>${item.is_active ? successBadge(item.is_global ? 'global' : 'active') : mutedBadge('inactive')}</td>
                        </tr>
                    `).join('')
                    : `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Liquidity methods отсутствуют.</td></tr>`;

                corridorsBody.innerHTML = (json.corridors || []).length
                    ? json.corridors.map((item) => `
                        <tr>
                            <td><div style="font-weight:750;">${escapeHtml(item.provider_node || '—')}</div><div style="font-size:.68rem;color:var(--text-muted);">trust ${Number(item.trust_tier || 0)}</div></td>
                            <td><span style="font-family:var(--font-tech);font-size:.72rem;">${escapeHtml(item.routing_asset || 'USDT')} → ${escapeHtml(item.currency_code || '—')}</span></td>
                            <td>${escapeHtml(item.direction || '—')}<div style="font-size:.68rem;color:var(--text-muted);">fee ${Number(item.base_fee_percent || 0)}% · SLA ${Number(item.sla_minutes || 0)}m</div></td>
                            <td>${item.is_active ? successBadge(`${Number(item.min_volume || 0).toLocaleString()}-${Number(item.max_volume || 0).toLocaleString()}`) : mutedBadge('inactive')}</td>
                        </tr>
                    `).join('')
                    : `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Currency corridors отсутствуют.</td></tr>`;

                intentBody.innerHTML = (json.intent_corridors || []).length
                    ? json.intent_corridors.map((item) => `
                        <tr>
                            <td><div style="font-weight:750;">${escapeHtml(item.entity_label || item.intent_key || '—')}</div><div style="font-size:.68rem;color:var(--text-muted);">${escapeHtml(item.intent_type || '')}</div></td>
                            <td><span style="font-family:var(--font-tech);font-size:.72rem;">${escapeHtml(item.corridor_key || '—')}</span><div style="font-size:.68rem;color:var(--text-muted);">${escapeHtml(item.route_type || item.source || '')}</div></td>
                            <td>${item.execution_ready ? successBadge(Number(item.route_score || 0).toLocaleString()) : mutedBadge(Number(item.route_score || 0).toLocaleString())}</td>
                            <td><span style="${Number(item.friction_score || 0) > 50 ? 'color:#f43f5e;' : 'color:var(--text-muted);'}">${Number(item.friction_score || 0).toLocaleString()}</span></td>
                        </tr>
                    `).join('')
                    : `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Intent corridors отсутствуют.</td></tr>`;

                tbody.innerHTML = (json.data || []).length
                    ? json.data.map((item) => `
                        <tr>
                            <td><div style="font-weight:750;">${escapeHtml(item.partner)}</div><div style="font-size:.68rem;color:var(--text-muted);">#${item.id}</div></td>
                            <td>${escapeHtml(item.currency)}</td>
                            <td><span style="font-family:var(--font-tech);color:var(--green);">${Number(item.available_balance || 0).toLocaleString()}</span></td>
                            <td><span style="font-family:var(--font-tech);">${Number(item.reserved_balance || 0).toLocaleString()}</span></td>
                            <td><span style="font-family:var(--font-tech);">${Number(item.api_active_reservations || 0).toLocaleString()}</span></td>
                            <td>${Number(item.native_available || 0).toLocaleString()} / ${Number(item.native_reserved || 0).toLocaleString()}</td>
                            <td>${Number(item.shops_count || 0).toLocaleString()}</td>
                            <td>${opsStatusBadge(item.status)}</td>
                        </tr>
                    `).join('')
                    : `<tr><td colspan="8" style="text-align:center;color:var(--text-muted);">Ликвидность не найдена.</td></tr>`;
            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;color:#f43f5e;">Ошибка загрузки ликвидности.</td></tr>`;
                currencyBody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:#f43f5e;">Ошибка загрузки валют.</td></tr>`;
                methodsBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:#f43f5e;">Ошибка загрузки методов.</td></tr>`;
                corridorsBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:#f43f5e;">Ошибка загрузки corridors.</td></tr>`;
                intentBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:#f43f5e;">Ошибка загрузки intent graph.</td></tr>`;
            }
        }

        async function loadGrowth() {
            const cards = document.getElementById('growth-summary-cards');
            const demandBody = document.getElementById('growth-demand-body');
            const casesBody = document.getElementById('growth-cases-body');
            const recommendationsBody = document.getElementById('growth-recommendations-body');
            const alertsBody = document.getElementById('growth-alerts-body');
            demandBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Загрузка demand gaps...</td></tr>`;
            casesBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Загрузка opportunity cases...</td></tr>`;
            recommendationsBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Загрузка recommendations...</td></tr>`;
            alertsBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Загрузка alerts...</td></tr>`;

            try {
                const res = await fetch('/ops/dashboard/growth/data');
                const json = await res.json();
                if (!res.ok) throw new Error(json.error || 'Growth load failed');
                const s = json.summary || {};
                cards.innerHTML = `
                    <div class="col-3 card-neo"><div class="metric-title">Demand Gaps</div><div class="metric-value">${Number(s.demand_gaps || 0).toLocaleString()}</div><div style="font-size:.65rem;color:var(--text-muted);margin-top:8px;">search/currency/category gaps</div></div>
                    <div class="col-3 card-neo"><div class="metric-title">Lost GMV</div><div class="metric-value">${Number(s.lost_gmv || 0).toLocaleString()}</div><div style="font-size:.65rem;color:var(--text-muted);margin-top:8px;">estimated opportunity</div></div>
                    <div class="col-3 card-neo"><div class="metric-title">Open Cases</div><div class="metric-value">${Number(s.open_cases || 0).toLocaleString()}</div><div style="font-size:.65rem;color:var(--text-muted);margin-top:8px;">overdue ${Number(s.overdue_cases || 0).toLocaleString()}</div></div>
                    <div class="col-3 card-neo"><div class="metric-title">Open Alerts</div><div class="metric-value">${Number(s.open_alerts || 0).toLocaleString()}</div><div style="font-size:.65rem;color:var(--text-muted);margin-top:8px;">proposals ${Number(s.proposed_recommendations || 0).toLocaleString()}</div></div>
                `;

                demandBody.innerHTML = (json.demand_gaps || []).length
                    ? json.demand_gaps.map((item) => `
                        <tr>
                            <td><div style="font-weight:750;">${escapeHtml(item.query || '—')}</div><div style="font-size:.68rem;color:var(--text-muted);">${escapeHtml(item.brand || '')} ${escapeHtml(item.region || '')}</div></td>
                            <td>${Number(item.search_volume || 0).toLocaleString()}<div style="font-size:.68rem;color:var(--text-muted);">zero ${Number(item.zero_results_count || 0).toLocaleString()} · avg ${Number(item.average_results_count || 0).toLocaleString()}</div></td>
                            <td><span style="font-family:var(--font-tech);">${Number(item.lost_gmv || 0).toLocaleString()}</span><div style="font-size:.68rem;color:var(--text-muted);">score ${Number(item.score || 0).toLocaleString()}</div></td>
                            <td>${opsStatusBadge(item.priority || item.diagnosis || 'observed')}<div style="font-size:.68rem;color:var(--text-muted);margin-top:4px;">${escapeHtml(item.diagnosis || '')}</div></td>
                        </tr>
                    `).join('')
                    : `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Demand gaps отсутствуют.</td></tr>`;

                casesBody.innerHTML = (json.opportunity_cases || []).length
                    ? json.opportunity_cases.map((item) => `
                        <tr>
                            <td><div style="font-weight:750;">${escapeHtml(item.query || '—')}</div><div style="font-size:.68rem;color:var(--text-muted);">#${Number(item.id || 0)}</div></td>
                            <td>${escapeHtml(item.owner_team || 'unassigned')}<div style="font-size:.68rem;color:${item.overdue ? '#f43f5e' : 'var(--text-muted)'};">${item.sla_due_at ? new Date(item.sla_due_at).toLocaleString() : 'no SLA'}</div></td>
                            <td>${Number(item.before_score || 0).toLocaleString()}<div style="font-size:.68rem;color:var(--text-muted);">GMV ${Number(item.before_gmv || 0).toLocaleString()}</div></td>
                            <td>${opsStatusBadge(item.overdue ? 'overdue' : item.status)}</td>
                        </tr>
                    `).join('')
                    : `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Opportunity cases отсутствуют.</td></tr>`;

                recommendationsBody.innerHTML = (json.recommendations || []).length
                    ? json.recommendations.map((item) => `
                        <tr>
                            <td><div style="font-weight:750;">${escapeHtml(item.query || '—')}</div><div style="font-size:.68rem;color:var(--text-muted);">${escapeHtml(item.type || '')}</div></td>
                            <td>${escapeHtml(item.insight_type || '—')}</td>
                            <td>${Number(item.impact_score || 0).toLocaleString()}<div style="font-size:.68rem;color:var(--text-muted);">conf ${Number(item.confidence || 0).toLocaleString()}</div></td>
                            <td>${opsStatusBadge(item.status)}</td>
                        </tr>
                    `).join('')
                    : `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Recommendations отсутствуют.</td></tr>`;

                alertsBody.innerHTML = (json.alerts || []).length
                    ? json.alerts.map((item) => `
                        <tr>
                            <td><div style="font-weight:750;">${escapeHtml(item.title || item.type || '—')}</div><div style="font-size:.68rem;color:var(--text-muted);">${escapeHtml(item.severity || '')}</div></td>
                            <td>${escapeHtml(item.surface || 'global')}</td>
                            <td>${Number(item.occurrence_count || 0).toLocaleString()}</td>
                            <td>${opsStatusBadge(item.status)}</td>
                        </tr>
                    `).join('')
                    : `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Operational alerts отсутствуют.</td></tr>`;
            } catch (error) {
                demandBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:#f43f5e;">Ошибка загрузки demand gaps.</td></tr>`;
                casesBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:#f43f5e;">Ошибка загрузки cases.</td></tr>`;
                recommendationsBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:#f43f5e;">Ошибка загрузки recommendations.</td></tr>`;
                alertsBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:#f43f5e;">Ошибка загрузки alerts.</td></tr>`;
            }
        }

        async function loadSearchIntegrations() {
            const cards = document.getElementById('search-summary-cards');
            const integrationsBody = document.getElementById('search-integrations-body');
            const totalsBody = document.getElementById('search-source-totals-body');
            const zeroLayerBody = document.getElementById('search-zero-layer-body');
            const externalBody = document.getElementById('search-external-body');
            const recommendationsBody = document.getElementById('search-recommendations-body');
            integrationsBody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Загрузка integrations...</td></tr>`;
            totalsBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Загрузка totals...</td></tr>`;
            zeroLayerBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Загрузка ZeroLayer...</td></tr>`;
            externalBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Загрузка demand signals...</td></tr>`;
            recommendationsBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Загрузка recommendations...</td></tr>`;

            try {
                const res = await fetch('/ops/dashboard/search-integrations/data');
                const json = await res.json();
                if (!res.ok) throw new Error(json.error || 'Search integrations load failed');
                window.zeroLayerConnectors = json.connectors || {};
                updateZeroLayerConnectorHint();
                const s = json.summary || {};
                cards.innerHTML = `
                    <div class="col-3 card-neo"><div class="metric-title">Integrations</div><div class="metric-value">${Number(s.active_zero_layer_integrations || 0)}/${Number(s.zero_layer_integrations || 0)}</div><div style="font-size:.65rem;color:var(--text-muted);margin-top:8px;">active/total</div></div>
                    <div class="col-3 card-neo"><div class="metric-title">ZeroLayer Signals</div><div class="metric-value">${Number(s.zero_layer_signals || 0).toLocaleString()}</div><div style="font-size:.65rem;color:var(--text-muted);margin-top:8px;">raw search/paid/indexing</div></div>
                    <div class="col-3 card-neo"><div class="metric-title">Demand Signals</div><div class="metric-value">${Number(s.external_search_signals || 0).toLocaleString()}</div><div style="font-size:.65rem;color:var(--text-muted);margin-top:8px;">recommendation input</div></div>
                    <div class="col-3 card-neo"><div class="metric-title">Proposals</div><div class="metric-value">${Number(s.recommendations_proposed || 0).toLocaleString()}</div><div style="font-size:.65rem;color:var(--text-muted);margin-top:8px;">pending decisions</div></div>
                `;

                integrationsBody.innerHTML = (json.integrations || []).length
                    ? json.integrations.map((item) => `
                        <tr>
                            <td><div style="font-weight:750;">${escapeHtml(item.name || '—')}</div><div class="ops-cell-muted">${escapeHtml(item.last_synced_at || 'never synced')}</div></td>
                            <td>${escapeHtml(item.source || '—')}<div>${opsStatusBadge(item.status)}</div></td>
                            <td><span class="ops-mono-truncate" title="${escapeHtml((item.credential_keys || []).join(', '))}">${escapeHtml((item.credential_keys || []).join(', ') || 'no credentials')}</span></td>
                            <td>${Number(item.signals_count || 0).toLocaleString()}</td>
                            <td><button type="button" class="btn-neo" style="padding:6px 10px;font-size:.65rem;" onclick="runSearchSignalAction('${escapeHtml(item.sync_url)}', {}, this)">Sync</button></td>
                        </tr>
                    `).join('')
                    : `<tr><td colspan="5" style="text-align:center;color:var(--text-muted);">ZeroLayer integrations отсутствуют.</td></tr>`;

                totalsBody.innerHTML = (json.source_totals || []).length
                    ? json.source_totals.map((item) => `
                        <tr>
                            <td>${escapeHtml(item.pipeline || '—')}</td>
                            <td>${escapeHtml(item.source || '—')}</td>
                            <td>${Number(item.total || 0).toLocaleString()}<div class="ops-cell-muted">imp ${Number(item.impressions || 0).toLocaleString()}</div></td>
                            <td>${Number(item.clicks || 0).toLocaleString()}</td>
                        </tr>
                    `).join('')
                    : `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Source totals отсутствуют.</td></tr>`;

                zeroLayerBody.innerHTML = (json.zero_layer_signals || []).length
                    ? json.zero_layer_signals.map((item) => `
                        <tr>
                            <td><div style="font-weight:750;">${escapeHtml(item.query_text || '—')}</div><div class="ops-cell-muted">${escapeHtml(item.page_url || '')}</div></td>
                            <td>${escapeHtml(item.source || '—')}<div class="ops-cell-muted">${escapeHtml(item.signal_type || '')}</div></td>
                            <td>${item.position ?? '—'}</td>
                            <td>imp ${Number(item.impressions || 0).toLocaleString()}<div class="ops-cell-muted">clicks ${Number(item.clicks || 0).toLocaleString()}</div></td>
                        </tr>
                    `).join('')
                    : `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">ZeroLayer signals отсутствуют.</td></tr>`;

                externalBody.innerHTML = (json.external_signals || []).length
                    ? json.external_signals.map((item) => `
                        <tr>
                            <td><div style="font-weight:750;">${escapeHtml(item.query || '—')}</div><div class="ops-cell-muted">${escapeHtml(item.landing_url || '')}</div></td>
                            <td>${escapeHtml(item.source || '—')}</td>
                            <td>${escapeHtml([item.country, item.locale].filter(Boolean).join(' / ') || '—')}</td>
                            <td>vol ${Number(item.volume || 0).toLocaleString()}<div class="ops-cell-muted">imp ${Number(item.impressions || 0).toLocaleString()} · clicks ${Number(item.clicks || 0).toLocaleString()}</div></td>
                        </tr>
                    `).join('')
                    : `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">External demand signals отсутствуют.</td></tr>`;

                recommendationsBody.innerHTML = (json.recommendations || []).length
                    ? json.recommendations.map((item) => `
                        <tr>
                            <td><div style="font-weight:750;">${escapeHtml(item.query || '—')}</div><div class="ops-cell-muted">${escapeHtml(item.type || '')}</div></td>
                            <td>${escapeHtml(item.insight_type || '—')}</td>
                            <td>${Number(item.impact_score || 0).toLocaleString()}<div class="ops-cell-muted">conf ${Number(item.confidence || 0).toLocaleString()}</div></td>
                            <td>${opsStatusBadge(item.status)}</td>
                        </tr>
                    `).join('')
                    : `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Recommendations отсутствуют.</td></tr>`;
            } catch (error) {
                integrationsBody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:#f43f5e;">Ошибка загрузки integrations.</td></tr>`;
                totalsBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:#f43f5e;">Ошибка загрузки totals.</td></tr>`;
                zeroLayerBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:#f43f5e;">Ошибка загрузки ZeroLayer.</td></tr>`;
                externalBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:#f43f5e;">Ошибка загрузки demand signals.</td></tr>`;
                recommendationsBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:#f43f5e;">Ошибка загрузки recommendations.</td></tr>`;
            }
        }

        async function runSearchSignalAction(url, payload = {}, button = null) {
            const output = document.getElementById('search-action-output');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const original = button ? button.textContent : '';
            if (button) {
                button.disabled = true;
                button.textContent = 'Running...';
            }
            output.style.display = 'block';
            output.textContent = 'Running action...';

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify(payload),
                });
                const json = await res.json();
                output.textContent = JSON.stringify(json, null, 2);
                if (!res.ok || json.success === false) throw new Error(json.error || 'Action failed');
                await loadSearchIntegrations();
            } catch (error) {
                output.textContent = error.message || 'Action failed';
            } finally {
                if (button) {
                    button.disabled = false;
                    button.textContent = original;
                }
            }
        }

        async function saveZeroLayerIntegration(button) {
            const name = document.getElementById('zero-layer-connect-name')?.value?.trim() || '';
            const source = document.getElementById('zero-layer-connect-source')?.value || 'google_analytics';
            const credentialsRaw = document.getElementById('zero-layer-connect-credentials')?.value || '{}';
            const settingsRaw = document.getElementById('zero-layer-connect-settings')?.value || '{}';
            let credentials = {};
            let settings = {};

            try {
                credentials = credentialsRaw.trim() ? JSON.parse(credentialsRaw) : {};
                settings = settingsRaw.trim() ? JSON.parse(settingsRaw) : {};
            } catch (error) {
                const output = document.getElementById('search-action-output');
                output.style.display = 'block';
                output.textContent = 'Invalid JSON in credentials/settings: ' + error.message;
                return;
            }

            await runSearchSignalAction('/ops/dashboard/zero-layer/connect', {
                name,
                source,
                status: 'active',
                credentials,
                settings,
            }, button);

            document.getElementById('zero-layer-connect-credentials').value = '';
        }

        function updateZeroLayerConnectorHint() {
            const source = document.getElementById('zero-layer-connect-source')?.value || 'google_analytics';
            const hint = document.getElementById('zero-layer-connect-hint');
            if (!hint) return;

            const def = (window.zeroLayerConnectors || {})[source] || {};
            const credentials = (def.credentials || []).join(', ') || 'none';
            const settings = (def.settings || []).join(', ') || 'none';
            const exampleCredentials = JSON.stringify(def.example_credentials || {}, null, 2);
            const exampleSettings = JSON.stringify(def.example_settings || {}, null, 2);

            hint.innerHTML = `
                <div><strong>${escapeHtml(def.label || source)}</strong></div>
                <div>credentials keys: <span class="ops-mono-truncate">${escapeHtml(credentials)}</span></div>
                <div>settings keys: <span class="ops-mono-truncate">${escapeHtml(settings)}</span></div>
                <details style="margin-top:6px;"><summary>JSON examples</summary><pre style="white-space:pre-wrap;margin:6px 0 0;">credentials: ${escapeHtml(exampleCredentials)}\nsettings: ${escapeHtml(exampleSettings)}</pre></details>
            `;
        }

        function pullSearchSignals(button) {
            const provider = document.getElementById('search-pull-provider')?.value || 'google_search_console';
            const query = document.getElementById('search-pull-query')?.value || '';
            runSearchSignalAction('/ops/dashboard/search-signals/pull', {
                provider,
                query,
                limit: 100,
            }, button);
        }

        async function loadInventory() {
            const cards = document.getElementById('inventory-summary-cards');
            const warehousesBody = document.getElementById('inventory-warehouses-body');
            const stockBody = document.getElementById('inventory-stock-body');
            const vouchersBody = document.getElementById('inventory-vouchers-body');
            warehousesBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Загрузка складов...</td></tr>`;
            stockBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Загрузка остатков...</td></tr>`;
            vouchersBody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Загрузка ваучеров...</td></tr>`;

            try {
                const res = await fetch('/ops/dashboard/inventory/data');
                const json = await res.json();
                if (!res.ok) throw new Error(json.error || 'Inventory load failed');
                const s = json.summary || {};
                cards.innerHTML = `
                    <div class="col-3 card-neo"><div class="metric-title">Warehouses</div><div class="metric-value">${Number(s.active_warehouses || 0)}/${Number(s.warehouses || 0)}</div><div style="font-size:.65rem;color:var(--text-muted);margin-top:8px;">active/total</div></div>
                    <div class="col-3 card-neo"><div class="metric-title">Stock Units</div><div class="metric-value">${Number(s.stock_units || 0).toLocaleString()}</div><div style="font-size:.65rem;color:var(--text-muted);margin-top:8px;">warehouse count</div></div>
                    <div class="col-3 card-neo"><div class="metric-title">Low Stock</div><div class="metric-value">${Number(s.low_stock_rows || 0).toLocaleString()}</div><div style="font-size:.65rem;color:var(--text-muted);margin-top:8px;">rows below 5</div></div>
                    <div class="col-3 card-neo"><div class="metric-title">Vouchers</div><div class="metric-value">${Number(s.available_vouchers || 0)}/${Number(s.vouchers || 0)}</div><div style="font-size:.65rem;color:var(--text-muted);margin-top:8px;">available/total</div></div>
                `;

                warehousesBody.innerHTML = (json.warehouses || []).length
                    ? json.warehouses.map((item) => `
                        <tr>
                            <td><div style="font-weight:750;">${escapeHtml(item.name || '—')}</div><div style="font-size:.68rem;color:var(--text-muted);">${escapeHtml(item.channel || '')}</div></td>
                            <td>${escapeHtml(item.shop || '—')}<div style="font-size:.68rem;color:var(--text-muted);">${escapeHtml(item.partner || '')}</div></td>
                            <td>${Number(item.stock_rows || 0).toLocaleString()}</td>
                            <td>${item.is_active ? successBadge(item.is_main ? 'master' : 'active') : mutedBadge('inactive')}</td>
                        </tr>
                    `).join('')
                    : `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Склады отсутствуют.</td></tr>`;

                stockBody.innerHTML = (json.stock || []).length
                    ? json.stock.map((item) => `
                        <tr>
                            <td><div style="font-weight:750;">${escapeHtml(item.product || '—')}</div><div style="font-size:.68rem;color:var(--text-muted);">${escapeHtml(item.sku || '')}</div></td>
                            <td>${escapeHtml(item.warehouse || '—')}<div style="font-size:.68rem;color:var(--text-muted);">${escapeHtml(item.shop || '')}</div></td>
                            <td>${escapeHtml(item.partner || '—')}</td>
                            <td>${Number(item.count || 0) < 5 ? `<span style="color:#f43f5e;font-family:var(--font-tech);">${Number(item.count || 0)}</span>` : `<span style="font-family:var(--font-tech);">${Number(item.count || 0)}</span>`}</td>
                        </tr>
                    `).join('')
                    : `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Остатки отсутствуют.</td></tr>`;

                vouchersBody.innerHTML = (json.vouchers || []).length
                    ? json.vouchers.map((item) => `
                        <tr>
                            <td><div style="font-family:var(--font-tech);font-size:.72rem;">${escapeHtml(item.transaction_ref || `#${item.id}`)}</div><div style="font-size:.68rem;color:var(--text-muted);">${escapeHtml(item.warehouse || '')}</div></td>
                            <td>${escapeHtml(item.sku || '—')}</td>
                            <td>${escapeHtml(item.shop || '—')}<div style="font-size:.68rem;color:var(--text-muted);">${escapeHtml(item.partner || '')}</div></td>
                            <td>${Number(item.reserved_amount || 0).toLocaleString()} ${escapeHtml(item.reserve_currency || item.currency || '')}</td>
                            <td>${opsStatusBadge(item.is_used ? 'used' : item.status)}</td>
                        </tr>
                    `).join('')
                    : `<tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Ваучеры отсутствуют.</td></tr>`;
            } catch (error) {
                warehousesBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:#f43f5e;">Ошибка загрузки складов.</td></tr>`;
                stockBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:#f43f5e;">Ошибка загрузки остатков.</td></tr>`;
                vouchersBody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:#f43f5e;">Ошибка загрузки ваучеров.</td></tr>`;
            }
        }

        async function loadChannels() {
            const channelsBody = document.getElementById('channels-table-body');
            const shopsBody = document.getElementById('channel-shops-body');
            channelsBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Загрузка каналов...</td></tr>`;
            shopsBody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Загрузка магазинов...</td></tr>`;

            try {
                const res = await fetch('/ops/dashboard/channels/data');
                const json = await res.json();
                if (!res.ok) throw new Error(json.error || 'Channels load failed');
                channelsBody.innerHTML = (json.channels || []).map((item) => `
                    <tr>
                        <td><div style="font-weight:750;">${escapeHtml(item.label)}</div><div style="font-size:.68rem;color:var(--text-muted);">${escapeHtml(item.key)}</div></td>
                        <td>${escapeHtml(item.group)}</td>
                        <td>${Number(item.enabled_links || 0).toLocaleString()} / ${Number(item.product_links || 0).toLocaleString()}</td>
                        <td>${Number(item.errors || 0) > 0 ? `<span style="color:#f43f5e;">${item.errors}</span>` : '<span style="color:var(--green);">0</span>'}</td>
                    </tr>
                `).join('');

                shopsBody.innerHTML = (json.shops || []).length
                    ? json.shops.map((item) => `
                        <tr>
                            <td><div style="font-weight:750;">${escapeHtml(item.name)}</div><div style="font-size:.68rem;color:var(--text-muted);">#${item.id}</div></td>
                            <td>${escapeHtml(item.partner)}</td>
                            <td>${item.meanly_storefront ? successBadge('enabled') : mutedBadge('off')}</td>
                            <td>${item.yandex_configured ? successBadge(item.yandex_verified ? 'verified' : 'configured') : mutedBadge('not configured')}</td>
                            <td><span style="font-family:var(--font-tech);font-size:.68rem;">b:${escapeHtml(item.business_id || '—')} c:${escapeHtml(item.campaign_id || '—')} w:${escapeHtml(item.ym_warehouse_id || '—')}</span></td>
                        </tr>
                    `).join('')
                    : `<tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Магазины не найдены.</td></tr>`;
            } catch (error) {
                channelsBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:#f43f5e;">Ошибка загрузки каналов.</td></tr>`;
                shopsBody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:#f43f5e;">Ошибка загрузки каналов.</td></tr>`;
            }
        }

        function successBadge(label) {
            return `<span class="badge-neo" style="background:rgba(16,185,129,0.1);color:#10b981;border:1px solid rgba(16,185,129,0.25);">${escapeHtml(label)}</span>`;
        }

        function mutedBadge(label) {
            return `<span class="badge-neo" style="background:rgba(255,255,255,0.02);color:var(--text-muted);border:1px solid var(--border-card);">${escapeHtml(label)}</span>`;
        }

        function opsStatusBadge(status) {
            const normalized = String(status || '').toLowerCase();
            if (['active', 'approved', 'settled', 'completed'].includes(normalized)) return successBadge(status || 'active');
            if (['pending', 'pending_moderation', 'processing'].includes(normalized)) {
                return `<span class="badge-neo" style="background:rgba(245,158,11,0.12);color:#f59e0b;border:1px solid rgba(245,158,11,0.3);">${escapeHtml(status || 'pending')}</span>`;
            }
            if (['failed', 'rejected', 'refunded', 'inactive'].includes(normalized)) {
                return `<span class="badge-neo" style="background:rgba(244,63,94,0.1);color:#f43f5e;border:1px solid rgba(244,63,94,0.25);">${escapeHtml(status || 'failed')}</span>`;
            }
            return mutedBadge(status || '—');
        }

        // --- 📋 Tab 2: Partners AJAX loader ---
        let partnersCurrentPage = 1;
        let partnersStatusFilter = '';
        function setPartnersStatusFilter(status) {
            partnersStatusFilter = status;
            document.querySelectorAll('.partners-filter').forEach((button) => {
                button.classList.toggle('active', button.dataset.status === status);
            });
            loadPartners(1);
        }

        function partnerStatusBadge(item) {
            if (item.status === 'pending_moderation') {
                return `<span class="badge-neo" style="background:rgba(245,158,11,0.12);color:#f59e0b;border:1px solid rgba(245,158,11,0.3);">На модерации</span>`;
            }
            if (item.status === 'active' || item.is_active) {
                return `<span class="badge-neo" style="background:rgba(16,185,129,0.1);color:#10b981;border:1px solid rgba(16,185,129,0.25);">${item.status_label || 'Активна'}</span>`;
            }
            return `<span class="badge-neo" style="background:rgba(255,255,255,0.02);color:var(--text-muted);border:1px solid var(--border-card);">${item.status_label || 'Не активна'}</span>`;
        }

        function partnerApiPlane(item) {
            const api = item.api_identity || {};
            const tokenBadge = api.token_configured
                ? `<span class="badge-neo" style="background:rgba(16,185,129,0.1);color:#10b981;border:1px solid rgba(16,185,129,0.25);">token</span>`
                : `<span class="badge-neo" style="background:rgba(244,63,94,0.1);color:#f43f5e;border:1px solid rgba(244,63,94,0.25);">no token</span>`;
            const signatureBadge = api.financial_secret_configured
                ? `<span class="badge-neo" style="background:rgba(14,165,233,0.1);color:#38bdf8;border:1px solid rgba(14,165,233,0.25);">HMAC</span>`
                : `<span class="badge-neo" style="background:rgba(255,255,255,0.02);color:var(--text-muted);border:1px solid var(--border-card);">no HMAC</span>`;
            const whitelistBadge = Number(api.ip_whitelist_count || 0) > 0
                ? `<span class="badge-neo" style="background:rgba(168,85,247,0.1);color:#c084fc;border:1px solid rgba(168,85,247,0.25);">${api.ip_whitelist_count} IP</span>`
                : `<span class="badge-neo" style="background:rgba(255,255,255,0.02);color:var(--text-muted);border:1px solid var(--border-card);">open IP</span>`;
            const client = escapeHtml(api.client_id || item.id);
            const external = api.kernel_external_id ? escapeHtml(api.kernel_external_id) : '';

            return `
                <div class="ops-badge-stack" style="margin-bottom:5px;">${tokenBadge}${signatureBadge}${whitelistBadge}</div>
                <span class="ops-mono-truncate" title="client:${client}">client:${client}</span>
                ${external ? `<span class="ops-mono-truncate" title="meanly:${external}">meanly:${external}</span>` : ''}
            `;
        }

        function partnerSettlementPlane(item) {
            const settlement = item.settlement || {};
            const currency = settlement.currency || 'RUB';
            return `
                <div style="display:grid;gap:3px;">
                    <div style="font-family:var(--font-tech);font-size:0.76rem;color:var(--green);">free ${Number(item.available_balance || 0).toLocaleString()} ₽</div>
                    <div class="ops-cell-muted">frozen ${Number(item.reserved_balance || 0).toLocaleString()} ₽</div>
                    <div class="ops-cell-muted">holds ${Number(settlement.active_reservations_amount || 0).toLocaleString()} ${escapeHtml(currency)} · ${Number(settlement.active_reservations_count || 0)}</div>
                </div>
            `;
        }

        window.opsPartnerRows = {};

        function partnerFinanceButtons(item) {
            const disabled = item.action_urls ? '' : 'disabled';
            return `
                <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                    <button type="button" class="btn-neo" ${disabled} style="padding:6px 10px;font-size:0.65rem;" onclick="runPartnerFinanceAction(${item.id}, 'top_up', this)">Top-up balance</button>
                </div>
            `;
        }

        async function loadPartners(page = 1) {
            partnersCurrentPage = page;
            const search = document.getElementById("partners-search-input").value;
            const tbody = document.getElementById("partners-table-body");
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">Загрузка организаций...</td></tr>`;

            try {
                const statusParam = partnersStatusFilter ? `&status=${encodeURIComponent(partnersStatusFilter)}` : '';
                const res = await fetch(`/ops/dashboard/partners/data?page=${page}&search=${encodeURIComponent(search)}${statusParam}`);
                const json = await res.json();
                
                tbody.innerHTML = "";
                window.opsPartnerRows = {};
                if (json.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">Организации не найдены.</td></tr>`;
                    return;
                }

                json.data.forEach(item => {
                    window.opsPartnerRows[item.id] = item;
                    tbody.innerHTML += `
                        <tr>
                            <td>
                                <div style="font-weight:800;color:var(--text-main);line-height:1.2;">${escapeHtml(item.name)}</div>
                                <div class="ops-cell-muted">ИНН ${escapeHtml(item.inn || '—')} / КПП ${escapeHtml(item.kpp || '—')}</div>
                                <div class="ops-cell-muted">${escapeHtml(item.created_at || '')}</div>
                            </td>
                            <td>${partnerStatusBadge(item)}</td>
                            <td>${partnerApiPlane(item)}</td>
                            <td>
                                <div class="ops-badge-stack">
                                    <span class="badge-neo" style="background:rgba(255,255,255,0.02);border:1px solid var(--border-card);">${Number(item.shops_count || 0)} shops</span>
                                    <span class="badge-neo" style="background:rgba(255,255,255,0.02);border:1px solid var(--border-card);">${Number(item.terminals_count || 0)} terminals</span>
                                </div>
                            </td>
                            <td>${partnerSettlementPlane(item)}</td>
                            <td>
                                ${partnerFinanceButtons(item)}
                                ${item.approve_url ? `<button type="button" class="btn-neo" style="padding:6px 10px;font-size:0.65rem;background:rgba(16,185,129,0.12);border-color:rgba(16,185,129,0.35);color:#10b981;" onclick="approvePartner('${item.approve_url}', this)">
                                    Одобрить
                                </button>` : ''}
                            </td>
                        </tr>
                    `;
                });

                renderPagination("partners-pagination", json.current_page, json.last_page, loadPartners);
            } catch (e) {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:#f43f5e;">Ошибка загрузки данных.</td></tr>`;
            }
        }

        async function runPartnerFinanceAction(partnerId, action, button) {
            const item = window.opsPartnerRows?.[partnerId];
            const url = item?.action_urls?.[action];
            if (!url) return;

            const label = 'top-up partner balance';
            const amount = window.prompt(`Amount for ${item.name} ${label}:`);
            if (!amount) return;
            const reference = window.prompt('Reference key for idempotency:', `OPS-${action.toUpperCase()}-${partnerId}-${Date.now()}`);
            if (!reference) return;

            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            button.disabled = true;
            const previousLabel = button.textContent;
            button.textContent = '...';

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ amount, reference }),
                });

                const json = await res.json();
                if (!res.ok || !json.success) {
                    throw new Error(json.error || 'Finance action failed');
                }
                loadPartners(partnersCurrentPage);
            } catch (error) {
                alert(error.message || 'Finance action failed');
                button.disabled = false;
                button.textContent = previousLabel;
            }
        }

        async function approvePartner(url, button) {
            if (button?.dataset.confirm !== 'true') {
                if (button) {
                    button.dataset.confirm = 'true';
                    button.textContent = 'Подтвердить';
                    window.setTimeout(() => {
                        if (button.dataset.confirm === 'true') {
                            button.dataset.confirm = 'false';
                            button.textContent = 'Одобрить';
                        }
                    }, 3500);
                }
                return;
            }

            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            if (button) {
                button.disabled = true;
                button.textContent = 'Одобряем...';
            }

            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
            });

            if (!res.ok) {
                if (button) {
                    button.disabled = false;
                    button.dataset.confirm = 'false';
                    button.textContent = 'Ошибка';
                }
                return;
            }

            loadPartners(partnersCurrentPage);
        }

        // --- 📋 Tab 3: Shops AJAX loader ---
        let shopsCurrentPage = 1;
        async function loadShops(page = 1) {
            shopsCurrentPage = page;
            const search = document.getElementById("shops-search-input").value;
            const tbody = document.getElementById("shops-table-body");
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">Загрузка магазинов...</td></tr>`;

            try {
                const res = await fetch(`/ops/dashboard/shops/data?page=${page}&search=${encodeURIComponent(search)}`);
                const json = await res.json();

                tbody.innerHTML = "";
                if (json.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">Магазины не найдены.</td></tr>`;
                    return;
                }

                json.data.forEach(item => {
                    tbody.innerHTML += `
                        <tr>
                            <td><div style="font-weight:750;color:var(--text-main);">${item.name}</div></td>
                            <td><span style="font-size:0.8rem;color:var(--text-muted);">${item.legal_entity_name}</span></td>
                            <td>
                                <span class="badge-neo" style="${item.is_active ? 'background:rgba(16,185,129,0.1);color:#10b981;border:1px solid rgba(16,185,129,0.2);' : 'background:rgba(244,63,94,0.1);color:#f43f5e;border:1px solid rgba(244,63,94,0.2);'}">
                                    ${item.is_active ? 'Активен' : 'Отключен'}
                                </span>
                            </td>
                            <td>
                                <span class="badge-neo" style="${item.is_sandbox ? 'background:rgba(245,158,11,0.1);color:#f59e0b;border:1px solid rgba(245,158,11,0.2);' : 'background:rgba(16,185,129,0.1);color:#10b981;border:1px solid rgba(16,185,129,0.2);'}">
                                    ${item.is_sandbox ? 'Песочница' : 'Production'}
                                </span>
                            </td>
                            <td><span style="font-size:0.75rem;color:var(--text-muted);">${item.allowed_regions.join(', ') || 'Все'}</span></td>
                            <td><span style="font-size:0.75rem;color:var(--text-muted);">${item.allowed_categories.join(', ') || 'Все'}</span></td>
                        </tr>
                    `;
                });

                renderPagination("shops-pagination", json.current_page, json.last_page, loadShops);
            } catch (e) {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:#f43f5e;">Ошибка загрузки магазинов.</td></tr>`;
            }
        }

        // --- 📋 Tab 4: Orders AJAX loader ---
        let ordersCurrentPage = 1;
        let ordersActiveStatus = '';
        async function loadOrders(page = 1) {
            ordersCurrentPage = page;
            const search = document.getElementById("orders-search-input").value;
            const tbody = document.getElementById("orders-table-body");
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;color:var(--text-muted);">Загрузка заказов...</td></tr>`;
            loadOperationHistory(search);

            try {
                const res = await fetch(`/ops/dashboard/orders/data?page=${page}&status=${ordersActiveStatus}&search=${encodeURIComponent(search)}`);
                const json = await res.json();

                tbody.innerHTML = "";
                if (json.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;color:var(--text-muted);">Заказы не найдены.</td></tr>`;
                    return;
                }

                json.data.forEach(item => {
                    let statusBadge = '';
                    if (item.status_id == 4) {
                        statusBadge = `<span class="badge-neo" style="background:rgba(16,185,129,0.1);color:#10b981;border:1px solid rgba(16,185,129,0.2);">Выполнен</span>`;
                    } else if (item.status_id == 5) {
                        statusBadge = `<span class="badge-neo" style="background:rgba(244,63,94,0.1);color:#f43f5e;border:1px solid rgba(244,63,94,0.2);">Отменен</span>`;
                    } else {
                        statusBadge = `<span class="badge-neo" style="background:rgba(245,158,11,0.1);color:#f59e0b;border:1px solid rgba(245,158,11,0.2);">В работе</span>`;
                    }

                    tbody.innerHTML += `
                        <tr>
                            <td><div style="font-family:var(--font-tech);font-weight:750;color:var(--text-main);">${item.order_id}</div></td>
                            <td>
                                <div style="font-weight:700;">${item.shop_name}</div>
                                <div style="font-size:0.65rem;color:var(--text-muted);">${item.partner_name}</div>
                            </td>
                            <td><span style="font-family:var(--font-tech);font-size:0.8rem;">${item.sku}</span></td>
                            <td><div style="font-family:var(--font-tech);font-weight:700;color:var(--primary);">${item.price_rub.toLocaleString()} ₽</div></td>
                            <td><span style="font-family:monospace;font-size:0.75rem;background:rgba(255,255,255,0.02);padding:2px 6px;border-radius:4px;border:1px solid var(--border-card);">${item.code}</span></td>
                            <td>${statusBadge}</td>
                            <td>
                                <span class="badge-neo" style="${item.is_test ? 'background:rgba(245,158,11,0.08);color:#f59e0b;border:1px solid rgba(245,158,11,0.15);' : 'background:rgba(16,185,129,0.08);color:#10b981;border:1px solid rgba(16,185,129,0.15);'}">
                                    ${item.is_test ? 'Sandbox' : 'Real-B2B'}
                                </span>
                            </td>
                            <td><span style="color:var(--text-muted);">${item.created_at}</span></td>
                        </tr>
                    `;
                });

                renderPagination("orders-pagination", json.current_page, json.last_page, loadOrders);
            } catch (e) {
                tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;color:#f43f5e;">Ошибка загрузки заказов.</td></tr>`;
            }
        }

        async function loadOperationHistory(searchOverride = null) {
            const search = searchOverride ?? document.getElementById("orders-search-input")?.value ?? '';
            const tbody = document.getElementById("operations-table-body");
            if (!tbody) return;

            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--text-muted);">Загрузка operation feed...</td></tr>`;

            try {
                const res = await fetch(`/ops/dashboard/operations/data?search=${encodeURIComponent(search)}`);
                const json = await res.json();

                if (!res.ok) {
                    throw new Error(json.error || 'Operations feed failed');
                }

                if (!json.data || json.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--text-muted);">Операции не найдены.</td></tr>`;
                    return;
                }

                tbody.innerHTML = json.data.map((item) => {
                    const failed = item.failure_reason || ['failed', 'error', 'cancelled'].includes(String(item.status || '').toLowerCase());
                    return `
                        <tr>
                            <td>
                                <span class="badge-neo" style="background:rgba(255,255,255,0.02);border:1px solid var(--border-card);">${escapeHtml(item.source)}</span>
                                <div style="font-size:.68rem;color:var(--text-muted);margin-top:4px;">${escapeHtml(item.type)}</div>
                            </td>
                            <td><span style="font-family:var(--font-tech);font-size:.75rem;">${escapeHtml(item.reference)}</span></td>
                            <td>
                                <div style="font-weight:700;">${escapeHtml(item.partner)}</div>
                                <div style="font-size:.68rem;color:var(--text-muted);">${escapeHtml(item.provider)}</div>
                            </td>
                            <td><span style="font-family:var(--font-tech);font-size:.75rem;">${escapeHtml(item.sku)}</span></td>
                            <td><div style="font-family:var(--font-tech);font-weight:750;color:var(--primary);">${Number(item.amount || 0).toLocaleString()} ${escapeHtml(item.currency || '')}</div></td>
                            <td>
                                <span class="badge-neo" style="${failed ? 'background:rgba(244,63,94,.1);color:#f43f5e;border:1px solid rgba(244,63,94,.25);' : 'background:rgba(16,185,129,.08);color:#10b981;border:1px solid rgba(16,185,129,.2);'}">${escapeHtml(item.status || 'recorded')}</span>
                                ${item.failure_reason ? `<div style="font-size:.68rem;color:#f43f5e;margin-top:5px;">${escapeHtml(item.failure_reason)}</div>` : ''}
                            </td>
                            <td><span style="color:var(--text-muted);">${escapeHtml(item.created_at)}</span></td>
                        </tr>
                    `;
                }).join('');
            } catch (e) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#f43f5e;">Ошибка загрузки operation feed.</td></tr>`;
            }
        }

        function filterOrdersStatus(status, btn) {
            ordersActiveStatus = status;
            document.querySelectorAll("#order-status-tabs button").forEach(b => b.classList.remove("btn-primary-neo"));
            btn.classList.add("btn-primary-neo");
            loadOrders(1);
        }

        // --- 📋 Tab 5: Catalog AJAX loader ---
        let catalogCurrentPage = 1;
        async function loadCatalog(page = 1) {
            catalogCurrentPage = page;
            const search = document.getElementById("catalog-search-input").value;
            const tbody = document.getElementById("catalog-table-body");
            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--text-muted);">Загрузка каталога товаров...</td></tr>`;

            try {
                const res = await fetch(`/ops/dashboard/catalog/data?page=${page}&search=${encodeURIComponent(search)}`);
                const json = await res.json();

                tbody.innerHTML = "";
                if (json.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--text-muted);">Товары не найдены.</td></tr>`;
                    return;
                }

                json.data.forEach(item => {
                    tbody.innerHTML += `
                        <tr>
                            <td><div style="font-weight:750;color:var(--text-main);">${item.name}</div></td>
                            <td><span style="font-family:var(--font-tech);font-size:0.8rem;">${item.sku}</span></td>
                            <td><div style="font-family:var(--font-tech);font-weight:700;color:var(--primary);">${item.price_rub.toLocaleString()} ₽</div></td>
                            <td><span class="badge-neo" style="${item.stock < 5 ? 'background:rgba(244,63,94,0.1);color:#f43f5e;' : 'background:rgba(255,255,255,0.02);'}">${item.stock} шт</span></td>
                            <td>
                                <div style="font-weight:600;">${item.shop_name}</div>
                                <div style="font-size:0.65rem;color:var(--text-muted);">${item.partner_name}</div>
                            </td>
                            <td>
                                <span class="badge-neo" style="${item.has_errors ? 'background:rgba(244,63,94,0.1);color:#f43f5e;border:1px solid rgba(244,63,94,0.2);' : 'background:rgba(16,185,129,0.1);color:#10b981;border:1px solid rgba(16,185,129,0.2);'}">
                                    ${item.has_errors ? 'Ошибки импорта' : 'Синхронизирован'}
                                </span>
                            </td>
                            <td>
                                <span class="badge-neo" style="${item.is_active ? 'background:rgba(16,185,129,0.1);color:#10b981;' : 'background:rgba(255,255,255,0.02);'}">
                                    ${item.is_active ? 'Витрина' : 'Скрыт'}
                                </span>
                            </td>
                        </tr>
                    `;
                });

                renderPagination("catalog-pagination", json.current_page, json.last_page, loadCatalog);
            } catch (e) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#f43f5e;">Ошибка загрузки каталога.</td></tr>`;
            }
        }

        // --- Provider Plane Ops ---
        async function loadProviders() {
            const search = document.getElementById("providers-search-input")?.value || '';
            const tbody = document.getElementById("providers-table-body");
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">Загрузка провайдеров...</td></tr>`;

            try {
                const res = await fetch(`/ops/dashboard/providers/data?search=${encodeURIComponent(search)}`);
                const json = await res.json();

                if (!res.ok) {
                    throw new Error(json.error || 'Provider data failed');
                }

                if (!json.data || json.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">Провайдеры не найдены.</td></tr>`;
                    return;
                }

                tbody.innerHTML = json.data.map((item) => providerRowHtml(item, json.kernel)).join('');
                renderProviderKernelSupport(json.kernel);
            } catch (e) {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:#f43f5e;">Ошибка загрузки провайдеров.</td></tr>`;
            }
        }

        function renderProviderKernelSupport(kernel) {
            const container = document.getElementById('provider-kernel-support');
            if (!container) return;

            const docs = kernel?.support_planes?.docs || {};
            const devices = kernel?.support_planes?.devices || {};
            container.innerHTML = `
                <div style="font-weight:850;color:var(--text-main);margin-bottom:6px;">Meanly API Docs / Devices Support</div>
                <div>mode: ${escapeHtml(kernel?.mode || 'http')} · host: ${escapeHtml(kernel?.compatibility_host || 'api.meanly.one')}</div>
                <div style="margin-top:8px;">terminals: ${Number(devices.terminals_active || 0).toLocaleString()} active / ${Number(devices.terminals_total || 0).toLocaleString()} total</div>
                <div style="margin-top:8px;display:grid;gap:3px;">
                    ${Object.entries(docs).map(([label, path]) => `<div>${escapeHtml(label)}: <span style="color:var(--primary);">${escapeHtml(path)}</span></div>`).join('')}
                </div>
            `;
        }

        function providerHealthHtml(item) {
            const health = item.health || {};
            const badges = [
                ['catalog', health.catalog_ready],
                ['creds', health.credentials_ready],
                ['terminal', health.terminal_ready],
            ].map(([label, ok]) => `<span class="badge-neo" style="${ok ? 'background:rgba(16,185,129,.08);color:#10b981;' : 'background:rgba(244,63,94,.08);color:#f43f5e;'}border:1px solid var(--border-card);">${label} ${ok ? 'ok' : 'missing'}</span>`).join(' ');

            return `
                <div style="display:flex;flex-wrap:wrap;gap:4px;max-width:220px;">${badges}</div>
                ${health.last_error ? `<div style="font-size:.68rem;color:#f43f5e;margin-top:5px;">${escapeHtml(health.last_error)}</div>` : ''}
            `;
        }

        function providerRowHtml(item, kernel) {
            const activeStyle = item.is_active
                ? 'background:rgba(16,185,129,0.1);color:#10b981;border:1px solid rgba(16,185,129,0.25);'
                : 'background:rgba(244,63,94,0.1);color:#f43f5e;border:1px solid rgba(244,63,94,0.25);';
            const credentialKeys = Object.entries(item.credentials || {})
                .map(([key, ok]) => `<span class="badge-neo" style="${ok ? 'background:rgba(16,185,129,.08);color:#10b981;' : 'background:rgba(255,255,255,.02);color:var(--text-muted);'}border:1px solid var(--border-card);">${escapeHtml(key)} ${ok ? 'ok' : 'missing'}</span>`)
                .join(' ');
            const terminal = item.terminal || {};
            const upstreamDisabled = item.health?.supports_upstream_pull ? '' : 'disabled';

            return `
                <tr>
                    <td>
                        <div style="font-weight:850;color:var(--text-main);">${escapeHtml(item.name)}</div>
                        <div style="font-family:var(--font-tech);font-size:.7rem;color:var(--text-muted);">${escapeHtml(item.type)} · Meanly API ${escapeHtml(kernel?.mode || 'http')}</div>
                        <span class="badge-neo" style="${activeStyle}">${item.is_active ? 'active' : 'inactive'}</span>
                    </td>
                    <td>
                        <div style="font-family:var(--font-tech);font-weight:800;color:var(--primary);">${Number(item.active_provider_products_count || 0).toLocaleString()} active</div>
                        <div style="font-size:.68rem;color:var(--text-muted);">${Number(item.provider_products_count || 0).toLocaleString()} total · source ${escapeHtml(item.catalog_source)}</div>
                    </td>
                    <td><div style="display:flex;flex-wrap:wrap;gap:4px;max-width:280px;">${credentialKeys}</div></td>
                    <td>
                        <div style="display:flex;gap:4px;flex-wrap:wrap;">
                            <span class="badge-neo" style="${terminal.id_configured ? 'background:rgba(16,185,129,.08);color:#10b981;' : 'background:rgba(255,255,255,.02);color:var(--text-muted);'}border:1px solid var(--border-card);">id ${terminal.id_configured ? 'ok' : 'missing'}</span>
                            <span class="badge-neo" style="${terminal.pin_configured ? 'background:rgba(16,185,129,.08);color:#10b981;' : 'background:rgba(255,255,255,.02);color:var(--text-muted);'}border:1px solid var(--border-card);">pin ${terminal.pin_configured ? 'ok' : 'missing'}</span>
                        </div>
                        <div style="font-family:var(--font-tech);font-size:.68rem;color:var(--text-muted);margin-top:4px;">${escapeHtml(terminal.id_masked || 'not configured')}</div>
                    </td>
                    <td>
                        <div style="font-weight:750;">${escapeHtml(item.sync_status || 'idle')}</div>
                        <div style="font-size:.68rem;color:var(--text-muted);">${escapeHtml(item.last_sync_at || '—')}</div>
                        <div style="margin-top:5px;">${providerHealthHtml(item)}</div>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <button type="button" class="btn-neo" style="padding:6px 10px;font-size:.65rem;" onclick="runProviderSync('${item.sync_url}', 'embedded', this)">Embedded sync</button>
                            <button type="button" class="btn-neo btn-primary-neo" ${upstreamDisabled} style="padding:6px 10px;font-size:.65rem;" onclick="runProviderSync('${item.sync_url}', 'pull-upstream', this)">Pull upstream</button>
                        </div>
                    </td>
                </tr>
            `;
        }

        async function runProviderSync(url, mode, button) {
            if (mode === 'pull-upstream' && !confirm('Pull upstream может сходить в реальный EZPin и обновить provider_products. Продолжить?')) {
                return;
            }

            const output = document.getElementById('providers-operation-output');
            output.style.display = 'block';
            output.textContent = `Running provider sync: ${mode}...`;
            if (button) {
                button.disabled = true;
                button.textContent = 'Running...';
            }

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ mode }),
                });
                const json = await res.json();
                output.textContent = json.output || json.message || JSON.stringify(json, null, 2);
                await loadProviders();
            } catch (e) {
                output.textContent = `Provider sync failed: ${e.message || e}`;
            } finally {
                if (button) {
                    button.disabled = false;
                    button.textContent = mode === 'pull-upstream' ? 'Pull EZPin' : 'Embedded sync';
                }
            }
        }

        // --- 📋 Tab 6: Support Tickets AJAX loader ---
        let ticketsCurrentPage = 1;
        async function loadTickets(page = 1) {
            ticketsCurrentPage = page;
            const search = document.getElementById("support-search-input").value;
            const tbody = document.getElementById("support-table-body");
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Загрузка тикетов...</td></tr>`;

            try {
                const res = await fetch(`/ops/dashboard/tickets/data?page=${page}&search=${encodeURIComponent(search)}`);
                const json = await res.json();

                tbody.innerHTML = "";
                if (json.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Обращения отсутствуют.</td></tr>`;
                    return;
                }

                json.data.forEach(item => {
                    let badge = '';
                    if (item.status === 'open' || item.status === 'pending') {
                        badge = `<span class="badge-neo" style="background:rgba(245,158,11,0.1);color:#f59e0b;border:1px solid rgba(245,158,11,0.2);">Требует ответа</span>`;
                    } else {
                        badge = `<span class="badge-neo" style="background:rgba(16,185,129,0.1);color:#10b981;border:1px solid rgba(16,185,129,0.2);">Решен</span>`;
                    }

                    tbody.innerHTML += `
                        <tr>
                            <td><div style="font-weight:750;color:var(--text-main);">${item.subject}</div></td>
                            <td>
                                <div style="font-weight:600;">${item.shop_name}</div>
                                <div style="font-size:0.65rem;color:var(--text-muted);">${item.partner_name}</div>
                            </td>
                            <td>${badge}</td>
                            <td><span style="color:var(--text-muted);">${item.created_at}</span></td>
                            <td>
                                <button onclick="openTicketModal(${item.id})" class="btn-neo" style="padding: 6px 12px; font-size: 0.72rem;">
                                    Открыть диалог ➔
                                </button>
                            </td>
                        </tr>
                    `;
                });

                renderPagination("support-pagination", json.current_page, json.last_page, loadTickets);
            } catch (e) {
                tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:#f43f5e;">Ошибка загрузки тикетов.</td></tr>`;
            }
        }

        // --- 🎫 Ticket Dialog Modal Operations ---
        let activeTicketId = null;
        async function openTicketModal(ticketId) {
            activeTicketId = ticketId;
            const container = document.getElementById("modal-ticket-body");
            container.innerHTML = `<div style="text-align:center;padding:2rem;color:var(--text-muted);">Загрузка переписки...</div>`;
            document.getElementById("modal-ticket-reply-textarea").value = "";
            openModal("ticket-modal");

            try {
                const res = await fetch(`/ops/dashboard/tickets/${ticketId}/details`);
                const json = await res.json();

                document.getElementById("modal-ticket-title").innerText = `Тикет #${json.ticket.id} — ${json.ticket.subject}`;
                
                container.innerHTML = `
                    <div style="font-size: 0.75rem; background:rgba(255,255,255,0.01); border:1px solid var(--border-card); padding:12px; border-radius:10px; margin-bottom: 10px;">
                        <span style="color:var(--text-muted);">Организация:</span> <strong>${json.ticket.partner_name}</strong> | 
                        <span style="color:var(--text-muted);">Магазин:</span> <strong>${json.ticket.shop_name}</strong> | 
                        <span style="color:var(--text-muted);">Текущий статус:</span> <strong>${json.ticket.status}</strong>
                    </div>
                    <div id="ticket-chat-messages" style="display:flex; flex-direction:column; gap:12px; max-height: 250px; overflow-y:auto; padding: 5px;">
                        ${json.messages.map(m => `
                            <div style="padding: 10px; border-radius: 8px; font-size: 0.8rem; max-width: 85%; ${m.is_admin ? 'background:rgba(var(--primary-rgb), 0.08); border:1px solid var(--border-neon); align-self: flex-end; border-bottom-right-radius: 2px;' : 'background:rgba(255,255,255,0.03); border:1px solid var(--border-card); align-self: flex-start; border-bottom-left-radius: 2px;'}">
                                <div style="display:flex; justify-content:space-between; gap:20px; font-size:0.65rem; color:var(--text-muted); margin-bottom: 6px;">
                                    <strong>${m.sender}</strong>
                                    <span>${m.created_at}</span>
                                </div>
                                <div style="white-space: pre-wrap; font-weight:550; color:var(--text-main);">${m.message}</div>
                            </div>
                        `).join('')}
                    </div>
                `;
                
                // Auto scroll
                setTimeout(() => {
                    const el = document.getElementById("ticket-chat-messages");
                    if (el) el.scrollTop = el.scrollHeight;
                }, 100);

            } catch (e) {
                container.innerHTML = `<div style="text-align:center;padding:2rem;color:#f43f5e;">Сбой загрузки переписки.</div>`;
            }
        }

        async function submitTicketReply() {
            const textarea = document.getElementById("modal-ticket-reply-textarea");
            const btn = document.getElementById("modal-ticket-reply-btn");
            const message = textarea.value.trim();

            if (!message) {
                alert("Напишите ответ");
                return;
            }

            btn.disabled = true;
            btn.innerText = "Отправка...";

            try {
                const res = await fetch(`/ops/dashboard/tickets/${activeTicketId}/reply`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ message })
                });
                
                if (res.ok) {
                    closeModal("ticket-modal");
                    loadTickets(ticketsCurrentPage);
                } else {
                    alert("Ошибка при отправке ответа");
                }
            } catch (e) {
                console.error(e);
            } finally {
                btn.disabled = false;
                btn.innerText = "Ответить и решить тикет ➔";
            }
        }

        // --- 🤖 Tab 7: AI Global Auditor and Cyber Chat ---
        async function runGlobalOpsAudit() {
            const box = document.getElementById("ops-audit-result-box");
            const btn = document.getElementById("ops-audit-btn");

            btn.disabled = true;
            btn.innerText = "Идет ИИ-аудит операций...";
            box.innerHTML = `<div style="text-align:center;padding-top:4rem;"><div class="loader" style="width:28px;height:28px;margin: 0 auto 10px auto; border: 3px solid rgba(255,255,255,0.1); border-top-color: var(--primary); border-radius:50%; animation: spin 1s linear infinite;"></div>Считывание и проверка журнала операций...</div>`;

            try {
                const res = await fetch('/ops/dashboard/ai/audit', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                const json = await res.json();

                if (json.success) {
                    box.innerHTML = `<div style="white-space: pre-wrap; color:var(--text-main); font-weight:550;">${json.result}</div>`;
                } else {
                    box.innerHTML = `<span style="color:#f43f5e;">Ошибка аудита: ${json.error}</span>`;
                }
            } catch (e) {
                box.innerHTML = `<span style="color:#f43f5e;">Ошибка взаимодействия с ИИ.</span>`;
            } finally {
                btn.disabled = false;
                btn.innerText = "Запустить глобальный аудит ⚡";
            }
        }

        async function sendOpsAiMessage() {
            const input = document.getElementById("ops-ai-chat-input");
            const box = document.getElementById("ops-ai-chat-box");
            const message = input.value.trim();

            if (!message) return;

            input.value = "";
            box.innerHTML += `<div class="chat-message user">${message}</div>`;
            box.scrollTop = box.scrollHeight;

            const aiId = "ai-msg-" + Date.now();
            box.innerHTML += `<div class="chat-message ai" id="${aiId}">...думает...</div>`;
            box.scrollTop = box.scrollHeight;

            try {
                const res = await fetch('/ops/dashboard/ai/chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ message })
                });
                const json = await res.json();

                const aiMsgEl = document.getElementById(aiId);
                if (json.success) {
                    aiMsgEl.innerHTML = json.response;
                } else {
                    aiMsgEl.innerHTML = `<span style="color:#f43f5e;">Ошибка: ${json.error}</span>`;
                }
            } catch (e) {
                const aiMsgEl = document.getElementById(aiId);
                aiMsgEl.innerHTML = `<span style="color:#f43f5e;">Сбой связи с моделью.</span>`;
            } finally {
                box.scrollTop = box.scrollHeight;
            }
        }

        // Helper Pagination Renderer
        function renderPagination(elId, current, last, clickCallbackName) {
            const container = document.getElementById(elId);
            container.innerHTML = "";

            if (last <= 1) return;

            let html = `<div style="font-size:0.75rem; color:var(--text-muted);">Страница ${current} из ${last}</div>`;
            html += `<div style="display:flex; gap:6px;">`;

            if (current > 1) {
                html += `<button onclick="${clickCallbackName.name}(${current - 1})" class="btn-neo" style="padding:6px 12px; font-size:0.72rem;">&larr; Назад</button>`;
            }
            if (current < last) {
                html += `<button onclick="${clickCallbackName.name}(${current + 1})" class="btn-neo" style="padding:6px 12px; font-size:0.72rem;">Вперед &rarr;</button>`;
            }
            html += `</div>`;
            container.innerHTML = html;
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            })[char]);
        }

        async function validateTribunalChain() {
            const result = document.getElementById('tribunal-chain-result');
            result.innerHTML = '<span class="loading-spinner"></span> Validating sovereign ledger chain...';

            try {
                const response = await fetch('{{ route('ops.dashboard.tribunal.validate-chain') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                });
                const json = await response.json();
                const logs = Array.isArray(json.logs) ? json.logs : [];
                result.innerHTML = logs.map((entry) => {
                    const color = entry.type === 'error' ? '#f43f5e' : (entry.type === 'success' ? '#10b981' : '#9ca3af');
                    return `<div style="color:${color}; margin-bottom:8px;">${escapeHtml(entry.message || '')}</div>`;
                }).join('') || escapeHtml(json.message || 'No validation output.');
            } catch (error) {
                result.innerHTML = `<span style="color:#f43f5e;">Validator failed: ${escapeHtml(error.message)}</span>`;
            }
        }

        // Global Chart JS Setup
        document.addEventListener("DOMContentLoaded", () => {
            // Restore theme or defaults
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('theme')) {
                localStorage.setItem('theme', urlParams.get('theme').toLowerCase());
            }
            const dbTheme = "{{ auth()->user()->theme ?? '' }}";
            const savedTheme = dbTheme || localStorage.getItem("theme") || "consortium";
            setTheme(savedTheme);

            // Restore active SPA tab or defaults
            const requestedTab = @json($activeOpsTab);
            const savedTab = requestedTab || localStorage.getItem("ops_active_tab") || "dashboard";
            switchTab(savedTab);

            // Check if profile modal is requested via URL parameter
            if (new URLSearchParams(window.location.search).has('profile')) {
                openProfileModal();
                // Clean up query string without reloading page
                window.history.replaceState({}, document.title, window.location.pathname);
            }

            // Sales Chart initialization
            const ctx = document.getElementById('opsSalesChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['12.05', '13.05', '14.05', '15.05', '16.05', '17.05', '18.05'],
                    datasets: [{
                        label: 'RUB Объем транзакций',
                        data: [12050, 11900, 12420, 12600, 12900, 13150, 13200],
                        borderColor: '#f53003',
                        backgroundColor: 'rgba(245, 48, 3, 0.05)',
                        borderWidth: 3,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#f53003',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(255,255,255,0.02)' },
                            ticks: { color: '#8e8e93', font: { family: 'JetBrains Mono', size: 10 } }
                        },
                        y: {
                            grid: { color: 'rgba(255,255,255,0.02)' },
                            ticks: { color: '#8e8e93', font: { family: 'JetBrains Mono', size: 10 } }
                        }
                    }
                }
            });
        });

    </script>

    <style>
        /* Small styling loaders */
        .loader {
            border: 2px solid rgba(255,255,255,0.1);
            border-top-color: var(--primary);
            border-radius: 50%;
            width: 14px;
            height: 14px;
            animation: spin 0.8s linear infinite;
            display: inline-block;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    @livewireScripts
</body>
</html>
