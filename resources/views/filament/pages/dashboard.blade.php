<x-filament-panels::page class="fi-dashboard-page">
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
        /* Advanced Hover Toolbar */
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


    <div class="dashboard-grid mt-6" id="sortable-dashboard-grid" x-data="dashboardController()">
        @php
            $widgets = $this->getWidgets();
            $defaultSizes = [
                'HealthOverviewWidget' => 12,
                'SalesChartWidget' => 12,
                'TopSellingProductsWidget' => 6,
                'TopDeficitProductsWidget' => 6,
            ];
        @endphp

        @foreach($widgets as $widgetClass)
            @php
                $key = class_basename($widgetClass);
                $defSize = $defaultSizes[$key] ?? 6;
            @endphp
            <div class="widget-container col-span-{{ $defSize }} fi-wi-widget" 
                 data-id="{{ $key }}" 
                 id="widget-{{ $key }}">
                
                <!-- Advanced Hover Toolbar -->
                <div class="widget-controls">
                    <!-- Drag Handle Icon -->
                    <div class="widget-drag-handle" title="Перетащить">
                        <x-filament::icon icon="heroicon-m-bars-2" class="w-4 h-4" />
                    </div>
                    <div class="control-divider"></div>
                    <!-- Size Toggles -->
                    <button onclick="changeWidgetWidth('{{ $key }}', 4)" title="1/3 ширины">⅓</button>
                    <button onclick="changeWidgetWidth('{{ $key }}', 6)" title="1/2 ширины">½</button>
                    <button onclick="changeWidgetWidth('{{ $key }}', 12)" title="На всю ширину">Full</button>
                </div>

                <div class="h-full">
                    @livewire($widgetClass)
                </div>
            </div>
        @endforeach
    </div>

    <script>
        const CONFIG_KEY = 'sovereign-dashboard-settings-v2';

        function loadSettings() {
            return JSON.parse(localStorage.getItem(CONFIG_KEY)) || { order: [], widths: {} };
        }

        function saveSettings(settings) {
            localStorage.setItem(CONFIG_KEY, JSON.stringify(settings));
        }

        document.addEventListener('DOMContentLoaded', function() {
            const gridEl = document.getElementById('sortable-dashboard-grid');
            const settings = loadSettings();

            // 1. Apply stored widths immediately
            Object.keys(settings.widths).forEach(key => {
                const width = settings.widths[key];
                const el = document.getElementById('widget-' + key);
                if (el) {
                    // Clear grid column classes and add the saved one
                    el.classList.remove('col-span-12', 'col-span-8', 'col-span-6', 'col-span-4');
                    el.classList.add('col-span-' + width);
                }
            });

            // 2. Apply stored order
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

            // 3. Initialize SortableJS
            new Sortable(gridEl, {
                animation: 250,
                handle: '.widget-drag-handle, .fi-section-header, .fi-ta-header', // Allow dragging by headers too
                ghostClass: 'sortable-ghost',
                onEnd: function() {
                    const currentOrder = Array.from(gridEl.children).map(el => el.getAttribute('data-id'));
                    const currentSettings = loadSettings();
                    currentSettings.order = currentOrder;
                    saveSettings(currentSettings);
                }
            });
        });

        // Helper function called by size buttons
        window.changeWidgetWidth = function(widgetId, width) {
            const el = document.getElementById('widget-' + widgetId);
            if (!el) return;

            el.classList.remove('col-span-12', 'col-span-8', 'col-span-6', 'col-span-4');
            el.classList.add('col-span-' + width);

            const currentSettings = loadSettings();
            currentSettings.widths[widgetId] = width;
            saveSettings(currentSettings);

            // Dispatch resize event for charts to re-render automatically
            window.dispatchEvent(new Event('resize'));
        };

        window.resetDashboardSettings = function() {
            localStorage.removeItem(CONFIG_KEY);
            window.location.reload();
        };
        
        function dashboardController() {
            return {
                init() {
                    // Standard init
                }
            }
        }

        // ⛓️ Ultra-Dense Masonry Engine: Auto-packing using ResizeObserver
        document.addEventListener('DOMContentLoaded', function() {
            const masonryObserver = new ResizeObserver(entries => {
                for (let entry of entries) {
                    const content = entry.target;
                    const item = content.closest('.widget-container');
                    if (!item) continue;
                    
                    // Measure absolute content offset height + 24px gap to match the vertical grid spacing
                    const height = content.offsetHeight + 24;
                    const currentSpan = item.style.gridRowEnd;
                    const newSpan = `span ${height}`;
                    
                    if (currentSpan !== newSpan) {
                        item.style.gridRowEnd = newSpan;
                    }
                }
            });

            document.querySelectorAll('.widget-container .h-full').forEach(el => {
                masonryObserver.observe(el);
            });
        });
    </script>
</x-filament-panels::page>
