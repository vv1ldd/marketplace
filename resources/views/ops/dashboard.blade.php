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
        .col-6 { grid-column: span 6; }
        .col-4 { grid-column: span 4; }
        .col-3 { grid-column: span 3; }

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
            <div class="sidebar-logo console-selector-wrapper" style="position: relative; display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
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
                
                <div class="sidebar-section-title">Система</div>
                <a href="javascript:void(0)" onclick="switchTab('support')" class="menu-item" id="menu-support">
                    <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    Поддержка
                </a>
                <a href="javascript:void(0)" onclick="switchTab('ai-audit')" class="menu-item" id="menu-ai-audit">
                    <svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="20" y1="15" x2="23" y2="15"></line><line x1="1" y1="9" x2="4" y2="9"></line><line x1="1" y1="15" x2="4" y2="15"></line></svg>
                    Суверенный аудит
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
                                <th>ИНН / КПП</th>
                                <th>Статус</th>
                                <th>Магазинов</th>
                                <th>Свободный баланс</th>
                                <th>Заморожено</th>
                                <th>Действия</th>
                                <th>Дата регистрации</th>
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

            <!-- Tab 7: AI Audit -->
            <div class="tab-pane" id="tab-ai-audit">
                <div class="grid-12">
                    <!-- Left: Interactive Cyber Chat -->
                    <div class="col-8 card-neo" style="display: flex; flex-direction: column; height: 500px; justify-content: space-between;">
                        <div style="font-weight: 850; font-size: 0.95rem; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 8px;">
                            <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981; display: inline-block;"></span>
                            Ops Director AI Core (Llama 3)
                        </div>
                        
                        <div class="chat-container" id="ops-ai-chat-box">
                            <div class="chat-message ai">
                                Приветствую, Глобальный Администратор платформы Meanly. Я — Sovereign AI Operations Director. Я подключен к глобальному реестру событий и готов помочь проанализировать клиринг партнеров, состояние складов и системную безопасность. Напишите ваш запрос.
                            </div>
                        </div>

                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="ops-ai-chat-input" placeholder="Введите ваш операционный запрос (например, 'Проверь баланс партнеров')..." class="input-neo" style="flex:1;" onkeypress="if(event.key==='Enter') sendOpsAiMessage()">
                            <button onclick="sendOpsAiMessage()" class="btn-neo btn-primary-neo">Отправить ➔</button>
                        </div>
                    </div>

                    <!-- Right: Ledger Audit Generator -->
                    <div class="col-4 card-neo" style="display:flex; flex-direction:column; justify-content:space-between; height: 500px;">
                        <div>
                            <div style="font-weight: 850; font-size: 0.95rem; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                Sovereign Ledger Audit
                            </div>
                            <p style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 1.5rem;">
                                ИИ проведет полный криптографический аудит всех записей Sovereign Ledger, сверит торговые обороты, балансы и выявит аномалии поставок.
                            </p>
                            
                            <div id="ops-audit-result-box" style="font-size: 0.8rem; background: rgba(0,0,0,0.3); border: 1px dashed var(--border-neon); padding: 1rem; border-radius: 12px; height: 260px; overflow-y: auto; font-family: var(--font-tech); line-height: 1.6;">
                                <span style="color:var(--text-muted);">ИИ-аудит еще не запущен. Нажмите кнопку ниже для старта глобальной проверки реестра.</span>
                            </div>
                        </div>

                        <button onclick="runGlobalOpsAudit()" class="btn-neo btn-primary-neo" id="ops-audit-btn" style="width: 100%;">
                            Запустить глобальный аудит ⚡
                        </button>
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
                        {{ mb_substr($user->name ?: ($user->email ?: 'А'), 0, 1) }}
                    </div>
                    <div style="font-weight:850; font-size:1.1rem; color:var(--text-main);">{{ $user->name ?: 'Администратор' }}</div>
                    <div style="font-size:0.7rem; color:var(--primary); font-weight:800; text-transform:uppercase; margin-top:4px;">{{ $user->email }}</div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                        <label style="font-size: 0.7rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; margin-bottom: 6px; display: block;">Права Доступа</label>
                        <input type="text" class="input-neo" value="God Mode (Super Admin)" disabled style="opacity: 0.8; font-size: 0.75rem; padding: 8px 12px;">
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
                'shops': 'Магазины партнеров',
                'orders': 'Все заказы',
                'catalog': 'Все товары',
                'support': 'Поддержка и тикеты',
                'ai-audit': 'Глобальный аудит и ИИ-ассистент'
            };
            document.getElementById("page-title-text").innerText = titleMap[tabId] || 'Центр Операций';

            // Lazy Load Tab Data
            if (tabId === 'partners') loadPartners();
            if (tabId === 'shops') loadShops();
            if (tabId === 'orders') loadOrders();
            if (tabId === 'catalog') loadCatalog();
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

        async function loadPartners(page = 1) {
            partnersCurrentPage = page;
            const search = document.getElementById("partners-search-input").value;
            const tbody = document.getElementById("partners-table-body");
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;color:var(--text-muted);">Загрузка организаций...</td></tr>`;

            try {
                const statusParam = partnersStatusFilter ? `&status=${encodeURIComponent(partnersStatusFilter)}` : '';
                const res = await fetch(`/ops/dashboard/partners/data?page=${page}&search=${encodeURIComponent(search)}${statusParam}`);
                const json = await res.json();
                
                tbody.innerHTML = "";
                if (json.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;color:var(--text-muted);">Организации не найдены.</td></tr>`;
                    return;
                }

                json.data.forEach(item => {
                    tbody.innerHTML += `
                        <tr>
                            <td><div style="font-weight:750;color:var(--text-main);">${item.name}</div></td>
                            <td><span style="font-family:var(--font-tech);font-size:0.8rem;">${item.inn} / ${item.kpp}</span></td>
                            <td>${partnerStatusBadge(item)}</td>
                            <td><span class="badge-neo" style="background:rgba(255,255,255,0.02);border:1px solid var(--border-card);">${item.shops_count} шопов</span></td>
                            <td><div style="font-family:var(--font-tech);font-weight:700;color:var(--green);">${item.available_balance.toLocaleString()} ₽</div></td>
                            <td><div style="font-family:var(--font-tech);color:var(--text-muted);">${item.reserved_balance.toLocaleString()} ₽</div></td>
                            <td>
                                ${item.approve_url ? `<button type="button" class="btn-neo" style="padding:6px 10px;font-size:0.65rem;background:rgba(16,185,129,0.12);border-color:rgba(16,185,129,0.35);color:#10b981;" onclick="approvePartner('${item.approve_url}', this)">
                                    Одобрить
                                </button>` : ''}
                                ${!item.approve_url ? '<span style="color:var(--text-muted);">—</span>' : ''}
                            </td>
                            <td><span style="color:var(--text-muted);">${item.created_at}</span></td>
                        </tr>
                    `;
                });

                renderPagination("partners-pagination", json.current_page, json.last_page, loadPartners);
            } catch (e) {
                tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;color:#f43f5e;">Ошибка загрузки данных.</td></tr>`;
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
            const savedTab = localStorage.getItem("ops_active_tab") || "dashboard";
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
