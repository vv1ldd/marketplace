<style>
    :is(body, [data-theme-root], #sovereign-auth-root)[data-holiday] {
        --holiday-accent: var(--theme-accent, #7c3aed);
        --holiday-secondary: var(--theme-accent, #a855f7);
        --holiday-on-accent: var(--theme-on-accent, #ffffff);
        --holiday-soft: color-mix(in srgb, var(--holiday-accent) 14%, transparent);
        --holiday-border: color-mix(in srgb, var(--holiday-accent) 44%, var(--theme-border, transparent));
        --holiday-shadow: 0 0 0 1px color-mix(in srgb, var(--holiday-accent) 22%, transparent),
            0 16px 42px color-mix(in srgb, var(--holiday-accent) 18%, transparent);
        --theme-accent: var(--holiday-accent) !important;
        --theme-on-accent: var(--holiday-on-accent) !important;
        --theme-accent-gradient: linear-gradient(135deg, var(--holiday-accent) 0%, var(--holiday-secondary) 100%) !important;
        --theme-control-hover-bg: var(--holiday-soft) !important;
        --theme-menu-active-bg: color-mix(in srgb, var(--holiday-accent) 16%, transparent) !important;
        --theme-menu-active-border: var(--holiday-border) !important;
        --theme-control-shadow: var(--holiday-shadow) !important;
        --brand-primary: var(--holiday-accent) !important;
        --primary: var(--holiday-accent) !important;
        --brand: var(--holiday-accent) !important;
        --brand-soft: var(--holiday-soft) !important;
    }

    :is(body, [data-theme-root], #sovereign-auth-root)[data-holiday="new-year"] {
        --holiday-accent: #059669;
        --holiday-secondary: #dc2626;
        --holiday-on-accent: #ffffff;
    }

    :is(body, [data-theme-root], #sovereign-auth-root)[data-holiday="valentine"] {
        --holiday-accent: #e11d48;
        --holiday-secondary: #f43f5e;
        --holiday-on-accent: #ffffff;
    }

    :is(body, [data-theme-root], #sovereign-auth-root)[data-holiday="defender-day"] {
        --holiday-accent: #64748b;
        --holiday-secondary: #334155;
        --holiday-on-accent: #ffffff;
    }

    :is(body, [data-theme-root], #sovereign-auth-root)[data-holiday="womens-day"] {
        --holiday-accent: #eab308;
        --holiday-secondary: #ec4899;
        --holiday-on-accent: #111827;
    }

    :is(body, [data-theme-root], #sovereign-auth-root)[data-holiday="cosmonautics-day"] {
        --holiday-accent: #6366f1;
        --holiday-secondary: #4f46e5;
        --holiday-on-accent: #ffffff;
    }

    :is(body, [data-theme-root], #sovereign-auth-root)[data-holiday="doctor-day"] {
        --holiday-accent: #06b6d4;
        --holiday-secondary: #0d9488;
        --holiday-on-accent: #042f2e;
    }

    :is(body, [data-theme-root], #sovereign-auth-root)[data-holiday="may-day"] {
        --holiday-accent: #dc2626;
        --holiday-secondary: #ef4444;
        --holiday-on-accent: #ffffff;
    }

    :is(body, [data-theme-root], #sovereign-auth-root)[data-holiday="victory-day"] {
        --holiday-accent: #dc2626;
        --holiday-secondary: #991b1b;
        --holiday-on-accent: #ffffff;
    }

    :is(body, [data-theme-root], #sovereign-auth-root)[data-holiday="orchid-day"] {
        --holiday-accent: #d946ef;
        --holiday-secondary: #8b5cf6;
        --holiday-on-accent: #ffffff;
    }

    :is(body, [data-theme-root], #sovereign-auth-root)[data-holiday="sons-birthday"] {
        --holiday-accent: #74acdf;
        --holiday-secondary: #ffb900;
        --holiday-on-accent: #0f172a;
    }

    :is(body, [data-theme-root], #sovereign-auth-root)[data-holiday="russia-day"] {
        --holiday-accent: #2563eb;
        --holiday-secondary: #dc2626;
        --holiday-on-accent: #ffffff;
    }

    :is(body, [data-theme-root], #sovereign-auth-root)[data-holiday="babel-library"] {
        --holiday-accent: #d97706;
        --holiday-secondary: #292524;
        --holiday-on-accent: #ffffff;
    }

    :is(body, [data-theme-root], #sovereign-auth-root)[data-holiday="little-prince"] {
        --holiday-accent: #e11d48;
        --holiday-secondary: #fbbf24;
        --holiday-on-accent: #ffffff;
    }

    :is(body, [data-theme-root], #sovereign-auth-root)[data-holiday="halloween"] {
        --holiday-accent: #f97316;
        --holiday-secondary: #7c3aed;
        --holiday-on-accent: #111827;
    }

    :is(body, [data-theme-root], #sovereign-auth-root)[data-holiday="national-unity"] {
        --holiday-accent: #f97316;
        --holiday-secondary: #ea580c;
        --holiday-on-accent: #111827;
    }

    :is(body, [data-theme-root], #sovereign-auth-root)[data-holiday="black-friday"] {
        --holiday-accent: #22c55e;
        --holiday-secondary: #a855f7;
        --holiday-on-accent: #03140a;
    }

    :is(body, [data-theme-root], #sovereign-auth-root)[data-holiday="constitution-day"] {
        --holiday-accent: #8b5cf6;
        --holiday-secondary: #f59e0b;
        --holiday-on-accent: #ffffff;
    }

    :is(body, [data-theme-root], #sovereign-auth-root)[data-holiday="new-year-eve"] {
        --holiday-accent: #8b5cf6;
        --holiday-secondary: #a78bfa;
        --holiday-on-accent: #ffffff;
    }

    body[data-holiday] :is(.btn-nav-cta, .btn-primary, .btn-primary-neo, .btn-buy, .btn-submit, .skin-btn.active, .filter-btn.active, .tab-btn.active, .menu-item.active, .dropdown-item.active, button[type="submit"], [role="tab"][aria-selected="true"], [aria-current="page"]) {
        background: var(--theme-accent-gradient) !important;
        border-color: var(--holiday-accent) !important;
        color: var(--holiday-on-accent) !important;
        text-shadow: none !important;
    }

    body[data-holiday] :is(.nav-links a:hover, .btn-nav-login:hover, .menu-item:hover, .dropdown-item:hover, .filter-btn:hover, .tab-btn:hover, [role="tab"]:hover) {
        background: var(--holiday-soft) !important;
        border-color: var(--holiday-border) !important;
        color: var(--theme-text, inherit) !important;
    }

    body[data-holiday] :is(a, button, input, select, textarea) {
        font-family: inherit;
    }
</style>
<script>
    (function() {
        function _c(n) { var m = document.cookie.match(new RegExp('(?:^|;\\s*)' + n + '=([^;]*)')); return m ? decodeURIComponent(m[1]) : null; }
        var THEMES = ['consortium','partner','retro','nordic','synthwave','carbon'];
        var holiday = _c('holiday');
        var localTheme = null;
        try { localTheme = localStorage.getItem('theme'); } catch (e) {}
        var raw = localTheme || _c('theme') || document.documentElement.getAttribute('data-theme') || 'consortium';
        var t = THEMES.indexOf(raw) !== -1 ? raw : 'consortium';

        if (window.MeanlyTheme && typeof window.MeanlyTheme.apply === 'function') {
            window.MeanlyTheme.apply(t);
            return;
        }
        
        document.documentElement.setAttribute('data-theme', t);
        
        if (document.body) {
            document.body.setAttribute('data-theme', t);
            if (holiday) {
                document.body.setAttribute('data-holiday', holiday);
            } else {
                document.body.removeAttribute('data-holiday');
            }
        }
        
        var authRoot = document.getElementById('sovereign-auth-root');
        if (authRoot) {
            authRoot.setAttribute('data-theme', t);
            if (holiday) {
                authRoot.setAttribute('data-holiday', holiday);
            } else {
                authRoot.removeAttribute('data-holiday');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (document.body) {
                document.body.setAttribute('data-theme', t);
                if (holiday) {
                    document.body.setAttribute('data-holiday', holiday);
                } else {
                    document.body.removeAttribute('data-holiday');
                }
            }
            var authRoot = document.getElementById('sovereign-auth-root');
            if (authRoot) {
                authRoot.setAttribute('data-theme', t);
                if (holiday) {
                    authRoot.setAttribute('data-holiday', holiday);
                } else {
                    authRoot.removeAttribute('data-holiday');
                }
            }
            document.querySelectorAll('[data-theme-root]').forEach(function(el) {
                el.setAttribute('data-theme', t);
                if (holiday) {
                    el.setAttribute('data-holiday', holiday);
                } else {
                    el.removeAttribute('data-holiday');
                }
            });
        });
    })();
</script>
