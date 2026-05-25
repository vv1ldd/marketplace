<x-filament-panels::page>
    <style>
        .b2b-promo-container {
            font-family: 'Instrument Sans', sans-serif;
            color: #fff;
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        
        /* ── HERO BANNER ── */
        .b2b-hero {
            position: relative;
            background: linear-gradient(135deg, #0c0c0c 0%, #050505 50%, #1c0500 100%);
            border: 1px solid #1a1a1a;
            border-radius: 16px;
            padding: 2.5rem;
            overflow: hidden;
        }
        .b2b-hero::after {
            content: '';
            position: absolute;
            right: -100px;
            top: -100px;
            width: 300px;
            height: 300px;
            background: #f53003;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.12;
            pointer-events: none;
        }
        .b2b-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 100px;
            border: 1px solid rgba(245, 48, 3, 0.2);
            background: rgba(245, 48, 3, 0.05);
            color: #f53003;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1.25rem;
        }
        .b2b-hero-title {
            font-size: 1.85rem;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -0.02em;
            color: #ffffff;
            margin-bottom: 0.75rem;
        }
        .b2b-hero-desc {
            font-size: 0.95rem;
            color: #888888;
            max-width: 600px;
            line-height: 1.6;
        }

        /* ── BENEFITS GRID ── */
        .b2b-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.5rem;
        }
        .b2b-card {
            background: #0a0a0a;
            border: 1px solid #1a1a1a;
            border-radius: 12px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            transition: border-color 0.2s;
        }
        .b2b-card:hover {
            border-color: #f53003/30;
        }
        .b2b-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid #1a1a1a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .b2b-card-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #ffffff;
        }
        .b2b-card-desc {
            font-size: 0.85rem;
            color: #888888;
            line-height: 1.5;
        }

        /* ── ACTION CARD ── */
        .b2b-action-card {
            background: linear-gradient(180deg, #0a0a0a 0%, #050505 100%);
            border: 1px solid rgba(245, 48, 3, 0.2);
            border-radius: 16px;
            padding: 2.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .b2b-action-card::before {
            content: '';
            position: absolute;
            left: -50px;
            bottom: -50px;
            width: 150px;
            height: 150px;
            background: #f53003;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.06;
            pointer-events: none;
        }
        .b2b-action-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 0.5rem;
        }
        .b2b-action-desc {
            font-size: 0.9rem;
            color: #888888;
            max-width: 500px;
            margin: 0 auto 1.75rem;
            line-height: 1.6;
        }
        .b2b-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            background: #f53003;
            color: #ffffff !important;
            font-size: 0.875rem;
            font-weight: 700;
            text-decoration: none;
            transition: background 0.2s, transform 0.2s;
            box-shadow: 0 10px 30px rgba(245, 48, 3, 0.15);
        }
        .b2b-btn:hover {
            background: #e22b02;
            transform: translateY(-1px);
            box-shadow: 0 12px 35px rgba(245, 48, 3, 0.25);
        }
    </style>

    @if (!$isB2bPartner)
        <div class="b2b-promo-container">
            {{-- Hero --}}
            <div class="b2b-hero">
                <span class="b2b-hero-badge">💼 Для профессиональных селлеров</span>
                <h2 class="b2b-hero-title">Превратите личный кабинет в мощную B2B-платформу</h2>
                <p class="b2b-hero-desc">
                    Вы вошли как физическое лицо. Активируйте B2B-профиль, чтобы запустить продажи цифровых товаров на Яндекс Маркете, Ozon и Wildberries с автовыдачей ключей за 3 секунды.
                </p>
            </div>

            {{-- Benefits Grid --}}
            <div class="b2b-grid">
                <div class="b2b-card">
                    <div class="b2b-card-icon">🔄</div>
                    <h3 class="b2b-card-title">Авто-синхронизация</h3>
                    <p class="b2b-card-desc">Единый склад цифровых товаров. Продавайте одновременно на всех маркетплейсах без риска пересортицы.</p>
                </div>
                
                <div class="b2b-card">
                    <div class="b2b-card-icon">💼</div>
                    <h3 class="b2b-card-title">Юридическая чистота</h3>
                    <p class="b2b-card-desc">Официальный договор, выплаты на расчетный счет ООО/ИП и закрывающие документы для вашей бухгалтерии.</p>
                </div>
                
                <div class="b2b-card">
                    <div class="b2b-card-icon">🎁</div>
                    <h3 class="b2b-card-title">1 000 ₽ на баланс</h3>
                    <p class="b2b-card-desc">Дарим стартовый баланс для мгновенного запуска и тестирования ваших первых интеграций.</p>
                </div>
            </div>

            {{-- Action --}}
            <div class="b2b-action-card">
                <h3 class="b2b-action-title">Активировать бизнес-кабинет</h3>
                <p class="b2b-action-desc">
                    Система автоматически определит вашу юрисдикцию и предложит мгновенное подключение по ИНН (для РФ) или регистрационным данным в вашем регионе.
                </p>
                <a href="/business" class="b2b-btn">
                    Перейти в B2B режим &rarr;
                </a>
            </div>
        </div>
    @else
        <!-- Active B2B console -->
        <div class="b2b-promo-container" style="animation: fadeIn 0.4s ease forwards;">
            <div class="b2b-hero" style="background: linear-gradient(135deg, #0a0a0a 0%, #030303 60%, #150000 100%);">
                <span class="b2b-hero-badge" style="border-color: rgba(0,255,102,0.2); background: rgba(0,255,102,0.05); color: #00ff66;">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#00ff66] animate-pulse" style="width: 6px; height: 6px; background: #00ff66; border-radius: 50%; display: inline-block; margin-right: 4px;"></span>
                    Бизнес-профиль активен
                </span>
                <h2 class="b2b-hero-title">Бизнес-профиль Meanly</h2>
                <p class="b2b-hero-desc">
                    Вы успешно завершили регистрацию. Ваша компания верифицирована, а вход защищен через Passkey.
                </p>
            </div>

            <div class="b2b-grid">
                <!-- 🏢 ACTIVE COMPANIES -->
                <div class="b2b-card" style="grid-column: span 2;">
                    <div class="b2b-card-icon" style="color: #00ff66;">🏢</div>
                    <h3 class="b2b-card-title">Верифицированные организации</h3>
                    <p class="b2b-card-desc" style="margin-bottom: 0.5rem;">
                        Следующие юридические лица зарегистрированы под вашей учетной записью:
                    </p>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-top: 0.5rem;">
                        @forelse ($entities as $entity)
                            <div style="background: #000; border: 1px solid #1a1a1a; padding: 1rem; border-radius: 8px; display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <h4 style="font-size: 13px; font-weight: 700; color: #fff;">{{ $entity->name }}</h4>
                                    <p style="font-size: 11px; color: #666; margin-top: 2px;">ИНН: {{ $entity->inn }}</p>
                                </div>
                                <span style="font-size: 9px; font-weight: 700; text-transform: uppercase; color: #00ff66; background: rgba(0,255,102,0.08); border: 1px solid rgba(0,255,102,0.2); padding: 2px 6px; border-radius: 4px; letter-spacing: 0.05em;">
                                    {{ $entity->status }}
                                </span>
                            </div>
                        @empty
                            <p style="font-size: 12px; color: #444; font-style: italic;">Нет активных компаний.</p>
                        @endforelse
                    </div>
                </div>

                <!-- Profile ID card -->
                <div class="b2b-card">
                    <div class="b2b-card-icon" style="color: var(--brand-primary);">💎</div>
                    <h3 class="b2b-card-title">ID бизнес-профиля</h3>
                    <p class="b2b-card-desc">
                        Ваш служебный идентификатор профиля:
                    </p>
                    <div style="background: #020202; border: 1px solid #1a1a1a; padding: 0.75rem; border-radius: 6px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 11px; color: var(--brand-primary); word-break: break-all;">
                        {{ $user->meta['l1_address'] ?? 'sl1_not_anchored' }}
                    </div>
                </div>
            </div>

            <!-- 🚪 B2B DIRECT TERMINAL LINK -->
            <div class="b2b-action-card" style="border-color: rgba(245,48,3,0.3); background: linear-gradient(180deg, #0e0402 0%, #050100 100%);">
                <h3 class="b2b-action-title" style="letter-spacing: -0.02em;">Перейти в B2B-кабинет</h3>
                <p class="b2b-action-desc">
                    Управляйте своими складами, синхронизируйте витрины маркетплейсов и просматривайте финансовые отчеты в полноценном интерфейсе продавца.
                </p>
                <a href="{{ route('partner.dashboard') }}" class="b2b-btn" style="background: var(--brand-primary); box-shadow: 0 4px 20px rgba(245,48,3,0.3);">
                    Открыть B2B Терминал ➔
                </a>
            </div>
        </div>
    @endif
</x-filament-panels::page>
