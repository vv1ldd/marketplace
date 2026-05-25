@php
    $pageComponent = static::isSimple() ? 'filament-panels::page.simple' : 'filament-panels::page';
@endphp

<x-dynamic-component :component="$pageComponent">
    <!-- SimpleWebAuthn Script Integration -->
    <script src="https://unpkg.com/@simplewebauthn/browser/dist/bundle/index.umd.min.js"></script>
    <script>
        window.startAuthentication = SimpleWebAuthnBrowser.startAuthentication;
        window.startRegistration = SimpleWebAuthnBrowser.startRegistration;
        window.browserSupportsWebAuthn = SimpleWebAuthnBrowser.browserSupportsWebAuthn;
    </script>

    <style>
        .profile-grid {
            display: flex;
            gap: 2rem;
            width: 100%;
            align-items: flex-start;
        }
        .profile-main {
            flex: 2;
        }
        .profile-sidebar {
            flex: 1;
            min-width: 320px;
        }
        @media (max-width: 1024px) {
            .profile-grid {
                flex-direction: column;
            }
            .profile-sidebar {
                min-width: 100%;
            }
        }
    </style>

    <div class="profile-grid">
        <!-- Left: Edit Profile Form (2/3 width) -->
        <div class="profile-main">
            {{ $this->content }}
        </div>

        <!-- Right: Passkeys Management (1/3 width) -->
        <div class="profile-sidebar">
            <section class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" style="padding: 1.5rem;">
                <header class="flex flex-col gap-1" style="text-align: left;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <svg style="width: 18px; height: 18px; color: #f53003; display: inline-block;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                        <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-white" style="font-size: 16px; font-weight: 600; margin: 0;">
                            Ключи доступа (Passkeys)
                        </h3>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400" style="font-size: 12px; line-height: 1.5; margin: 0.5rem 0 0 0;">
                        Используйте Passkeys для мгновенного и безопасного входа в систему без ввода пароля через биометрию.
                    </p>
                </header>

                <div class="fi-section-content" style="margin-top: 1.5rem;">
                    @livewire('passkeys')
                </div>
            </section>
        </div>
    </div>
</x-dynamic-component>
