<x-filament-panels::page>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            column-gap: 1.5rem;
            row-gap: 0;
            grid-auto-rows: 1px;
            align-items: stretch;
        }
        .widget-container {
            position: relative;
            transition: transform 0.3s ease;
        }
        /* Golden Standard Hover Toolbar */
        .widget-controls {
            position: absolute;
            top: 12px;
            right: 12px;
            z-index: 50;
            display: flex;
            align-items: center;
            gap: 4px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            padding: 4px 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            opacity: 0;
            transform: translateY(-5px);
            transition: all 0.2s ease;
        }
        .dark .widget-controls {
            background: rgba(30, 41, 59, 0.95);
            border-color: rgba(255, 255, 255, 0.1);
        }
        .widget-container:hover .widget-controls {
            opacity: 1;
            transform: translateY(0);
        }
        .widget-controls button {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            color: #475569;
            padding: 2px 6px;
            border-radius: 4px;
            border: none;
            background: transparent;
            cursor: pointer;
            transition: background 0.2s;
        }
        .dark .widget-controls button {
            color: #cbd5e1;
        }
        .widget-controls button:hover {
            background: rgba(0, 0, 0, 0.05);
        }
        .dark .widget-controls button:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        .widget-drag-handle {
            cursor: grab;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            padding: 4px;
            border-radius: 4px;
        }
        .widget-drag-handle:active {
            cursor: grabbing;
        }
        .widget-drag-handle:hover {
            color: rgb(var(--primary-500));
            background: rgba(0, 0, 0, 0.03);
        }
        .control-divider {
            width: 1px;
            height: 12px;
            background: #e2e8f0;
            margin: 0 2px;
        }
        .dark .control-divider {
            background: #334155;
        }
        .sortable-ghost {
            opacity: 0.4;
            background: rgba(var(--primary-500), 0.05);
            border: 2px dashed rgb(var(--primary-500));
            border-radius: 1rem;
        }
        
        /* Responsive column spans */
        .col-span-12 { grid-column: span 12 / span 12; }
        .col-span-8 { grid-column: span 8 / span 8; }
        .col-span-6 { grid-column: span 6 / span 6; }
        .col-span-4 { grid-column: span 4 / span 4; }

        @media (max-width: 1024px) {
            .col-span-8, .col-span-6, .col-span-4 {
                grid-column: span 12 / span 12 !important;
            }
        }
    </style>


    <div class="dashboard-grid mt-6" id="analytics-unified-grid">
        <!-- 1. Meanly Analytics Overview -->
        <div class="widget-container col-span-12 fi-wi-widget" data-id="meanly-analytics-overview" id="widget-meanly-analytics-overview">
            <div class="widget-controls">
                <div class="widget-drag-handle" title="Перетащить"><x-filament::icon icon="heroicon-m-bars-2" class="w-4 h-4" /></div>
                <div class="control-divider"></div>
                <button onclick="changeWidgetWidth('meanly-analytics-overview', 6)">½</button>
                <button onclick="changeWidgetWidth('meanly-analytics-overview', 12)">Full</button>
            </div>
            <div class="h-full">
                @livewire(\App\Filament\Widgets\MeanlyAnalyticsOverviewWidget::class)
            </div>
        </div>

        <!-- 2. Active Alerts -->
        <div class="widget-container col-span-12 fi-wi-widget" data-id="meanly-operational-alerts" id="widget-meanly-operational-alerts">
            <div class="widget-controls">
                <div class="widget-drag-handle" title="Перетащить"><x-filament::icon icon="heroicon-m-bars-2" class="w-4 h-4" /></div>
                <div class="control-divider"></div>
                <button onclick="changeWidgetWidth('meanly-operational-alerts', 6)">½</button>
                <button onclick="changeWidgetWidth('meanly-operational-alerts', 12)">Full</button>
            </div>
            <div class="h-full">
                @livewire(\App\Filament\Widgets\MeanlyOperationalAlertsWidget::class)
            </div>
        </div>

        <!-- 3. Slow Requests -->
        <div class="widget-container col-span-6 fi-wi-widget" data-id="meanly-analytics-slow" id="widget-meanly-analytics-slow">
            <div class="widget-controls">
                <div class="widget-drag-handle" title="Перетащить"><x-filament::icon icon="heroicon-m-bars-2" class="w-4 h-4" /></div>
                <div class="control-divider"></div>
                <button onclick="changeWidgetWidth('meanly-analytics-slow', 4)">⅓</button>
                <button onclick="changeWidgetWidth('meanly-analytics-slow', 6)">½</button>
                <button onclick="changeWidgetWidth('meanly-analytics-slow', 12)">Full</button>
            </div>
            <div class="h-full">
                @livewire(\App\Filament\Widgets\MeanlyAnalyticsSlowRequestsWidget::class)
            </div>
        </div>

        <!-- 4. Errors -->
        <div class="widget-container col-span-6 fi-wi-widget" data-id="meanly-analytics-errors" id="widget-meanly-analytics-errors">
            <div class="widget-controls">
                <div class="widget-drag-handle" title="Перетащить"><x-filament::icon icon="heroicon-m-bars-2" class="w-4 h-4" /></div>
                <div class="control-divider"></div>
                <button onclick="changeWidgetWidth('meanly-analytics-errors', 4)">⅓</button>
                <button onclick="changeWidgetWidth('meanly-analytics-errors', 6)">½</button>
                <button onclick="changeWidgetWidth('meanly-analytics-errors', 12)">Full</button>
            </div>
            <div class="h-full">
                @livewire(\App\Filament\Widgets\MeanlyAnalyticsErrorsWidget::class)
            </div>
        </div>

        <!-- 5. Stuck Fulfillment -->
        <div class="widget-container col-span-12 fi-wi-widget" data-id="meanly-fulfillment-stuck" id="widget-meanly-fulfillment-stuck">
            <div class="widget-controls">
                <div class="widget-drag-handle" title="Перетащить"><x-filament::icon icon="heroicon-m-bars-2" class="w-4 h-4" /></div>
                <div class="control-divider"></div>
                <button onclick="changeWidgetWidth('meanly-fulfillment-stuck', 6)">½</button>
                <button onclick="changeWidgetWidth('meanly-fulfillment-stuck', 12)">Full</button>
            </div>
            <div class="h-full">
                @livewire(\App\Filament\Widgets\MeanlyFulfillmentStuckWidget::class)
            </div>
        </div>

        <!-- 6. SalesChart -->
        <div class="widget-container col-span-6 fi-wi-widget" data-id="sales-chart" id="widget-sales-chart">
            <div class="widget-controls">
                <div class="widget-drag-handle" title="Перетащить"><x-filament::icon icon="heroicon-m-bars-2" class="w-4 h-4" /></div>
                <div class="control-divider"></div>
                <button onclick="changeWidgetWidth('sales-chart', 4)">⅓</button>
                <button onclick="changeWidgetWidth('sales-chart', 6)">½</button>
                <button onclick="changeWidgetWidth('sales-chart', 12)">Full</button>
            </div>
            <div class="h-full">
                @livewire(\App\Filament\Widgets\SalesChartWidget::class)
            </div>
        </div>

        <!-- 4. ProviderStats -->
        <div class="widget-container col-span-6 fi-wi-widget" data-id="provider-stats" id="widget-provider-stats">
            <div class="widget-controls">
                <div class="widget-drag-handle" title="Перетащить"><x-filament::icon icon="heroicon-m-bars-2" class="w-4 h-4" /></div>
                <div class="control-divider"></div>
                <button onclick="changeWidgetWidth('provider-stats', 4)">⅓</button>
                <button onclick="changeWidgetWidth('provider-stats', 6)">½</button>
                <button onclick="changeWidgetWidth('provider-stats', 12)">Full</button>
            </div>
            <div class="h-full">
                @livewire(\App\Filament\Widgets\ProviderStatsWidget::class)
            </div>
        </div>

        <!-- 6. CurrencyTruth -->
        <div class="widget-container col-span-6 fi-wi-widget" data-id="currency-truth" id="widget-currency-truth">
            <div class="widget-controls">
                <div class="widget-drag-handle" title="Перетащить"><x-filament::icon icon="heroicon-m-bars-2" class="w-4 h-4" /></div>
                <div class="control-divider"></div>
                <button onclick="changeWidgetWidth('currency-truth', 4)">⅓</button>
                <button onclick="changeWidgetWidth('currency-truth', 6)">½</button>
                <button onclick="changeWidgetWidth('currency-truth', 12)">Full</button>
            </div>
            <div class="h-full">
                @livewire(\App\Filament\Widgets\CurrencyTruthChart::class)
            </div>
        </div>

        <!-- 7. TopSellingProducts -->
        <div class="widget-container col-span-6 fi-wi-widget" data-id="top-selling-products" id="widget-top-selling-products">
            <div class="widget-controls">
                <div class="widget-drag-handle" title="Перетащить"><x-filament::icon icon="heroicon-m-bars-2" class="w-4 h-4" /></div>
                <div class="control-divider"></div>
                <button onclick="changeWidgetWidth('top-selling-products', 4)">⅓</button>
                <button onclick="changeWidgetWidth('top-selling-products', 6)">½</button>
                <button onclick="changeWidgetWidth('top-selling-products', 12)">Full</button>
            </div>
            <div class="h-full">
                @livewire(\App\Filament\Widgets\TopSellingProductsWidget::class)
            </div>
        </div>

        <!-- 8. TopDeficitProducts -->
        <div class="widget-container col-span-6 fi-wi-widget" data-id="top-deficit-products" id="widget-top-deficit-products">
            <div class="widget-controls">
                <div class="widget-drag-handle" title="Перетащить"><x-filament::icon icon="heroicon-m-bars-2" class="w-4 h-4" /></div>
                <div class="control-divider"></div>
                <button onclick="changeWidgetWidth('top-deficit-products', 4)">⅓</button>
                <button onclick="changeWidgetWidth('top-deficit-products', 6)">½</button>
                <button onclick="changeWidgetWidth('top-deficit-products', 12)">Full</button>
            </div>
            <div class="h-full">
                @livewire(\App\Filament\Widgets\TopDeficitProductsWidget::class)
            </div>
        </div>
    </div>

    <script>
        // Localized key for the analytics unified layout
        const ANALYTICS_UNIFIED_CONFIG = 'sovereign-analytics-unified-v3';

        function loadSettings() {
            return JSON.parse(localStorage.getItem(ANALYTICS_UNIFIED_CONFIG)) || { order: [], widths: {} };
        }

        function saveSettings(settings) {
            localStorage.setItem(ANALYTICS_UNIFIED_CONFIG, JSON.stringify(settings));
        }

        document.addEventListener('DOMContentLoaded', function() {
            const gridEl = document.getElementById('analytics-unified-grid');
            const settings = loadSettings();

            // 1. Apply saved widths
            Object.keys(settings.widths).forEach(key => {
                const width = settings.widths[key];
                const el = document.getElementById('widget-' + key);
                if (el) {
                    el.classList.remove('col-span-12', 'col-span-8', 'col-span-6', 'col-span-4');
                    el.classList.add('col-span-' + width);
                }
            });

            // 2. Apply saved order (unified single array!)
            if (settings.order && settings.order.length > 0) {
                const itemsMap = {};
                Array.from(gridEl.children).forEach(child => {
                    const id = child.getAttribute('data-id');
                    if (id) itemsMap[id] = child;
                });

                settings.order.forEach(id => {
                    if (itemsMap[id]) {
                        gridEl.appendChild(itemsMap[id]);
                    }
                });
            }

            // 3. Initialize Sortable for the single grid
            new Sortable(gridEl, {
                animation: 250,
                handle: '.widget-drag-handle, .fi-section-header, .fi-ta-header',
                ghostClass: 'sortable-ghost',
                onEnd: function() {
                    const currentOrder = Array.from(gridEl.children).map(el => el.getAttribute('data-id')).filter(i => i);
                    const currentSettings = loadSettings();
                    currentSettings.order = currentOrder;
                    saveSettings(currentSettings);
                }
            });
        });

        window.changeWidgetWidth = function(widgetId, width) {
            const el = document.getElementById('widget-' + widgetId);
            if (!el) return;

            el.classList.remove('col-span-12', 'col-span-8', 'col-span-6', 'col-span-4');
            el.classList.add('col-span-' + width);

            const currentSettings = loadSettings();
            currentSettings.widths[widgetId] = width;
            saveSettings(currentSettings);

            window.dispatchEvent(new Event('resize'));
        };

        window.resetAnalyticsSettings = function() {
            localStorage.removeItem(ANALYTICS_UNIFIED_CONFIG);
            window.location.reload();
        };

        // ⛓️ Ultra-Dense Masonry Engine: Auto-packing using ResizeObserver
        document.addEventListener('DOMContentLoaded', function() {
            const masonryObserver = new ResizeObserver(entries => {
                for (let entry of entries) {
                    const content = entry.target;
                    const item = content.closest('.widget-container');
                    if (!item) continue;
                    
                    // Measure absolute content offset height + 24px gap to perfectly match the vertical grid spacing
                    const height = content.offsetHeight + 24;
                    
                    const currentSpan = item.style.gridRowEnd;
                    const newSpan = `span ${height}`;
                    
                    if (currentSpan !== newSpan) {
                        item.style.gridRowEnd = newSpan;
                    }
                }
            });

            // Observe every widget's content to dynamically pack them edge-to-edge
            document.querySelectorAll('.widget-container .h-full').forEach(el => {
                masonryObserver.observe(el);
            });
        });
    </script>
</x-filament-panels::page>
