<x-filament-panels::page>
    <form wire:submit.prevent="calculateRoutes">
        {{ $this->form }}
    </form>

    <div style="margin-top: 2rem;">
        <h2 style="font-size: 1.25rem; font-weight: bold; margin-bottom: 1.5rem;">Available Liquidity Routes</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
            @foreach($routes as $route)
                <x-filament::section style="padding: 0;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                        <div>
                            <h3 style="font-size: 1.125rem; font-weight: bold; {{ $route['is_over_limit'] ? 'color: #9ca3af;' : '' }}">{{ $route['name'] }}</h3>
                            <p style="font-size: 0.875rem; color: var(--text-color, #6b7280);">{{ $route['description'] }}</p>
                        </div>
                        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.5rem;">
                            <x-filament::badge color="{{ $route['color'] }}">
                                {{ $route['trust'] }} Trust
                            </x-filament::badge>
                            @if($route['is_over_limit'])
                                <x-filament::badge color="danger">
                                    Limit Exceeded
                                </x-filament::badge>
                            @endif
                        </div>
                    </div>

                    <div style="margin-bottom: 1.5rem; {{ $route['is_over_limit'] ? 'opacity: 0.5;' : '' }}">
                        <div style="font-size: 2.25rem; font-weight: 900; line-height: 1;">
                            {{ number_format($route['final_amount'], 2) }}
                        </div>
                        <div style="font-size: 0.875rem; font-weight: 500; color: var(--text-color, #6b7280); margin-top: 0.25rem;">
                            Rate: {{ $route['rate_display'] }} | Spread: {{ number_format($route['spread'], 2) }}%
                        </div>
                    </div>

                    <div style="margin-bottom: 1rem; padding: 0.5rem; background-color: #f9fafb; border-radius: 0.5rem; font-size: 0.75rem; display: flex; align-items: center; gap: 0.5rem; {{ $route['is_over_limit'] ? 'border: 1px solid #fee2e2; color: #ef4444;' : 'color: #6b7280;' }}">
                        <x-heroicon-m-banknotes style="width: 1rem; height: 1rem;" />
                        Available Liquidity: <strong>{{ $route['capacity_str'] }}</strong>
                    </div>

                    <div style="margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem;">
                        <div style="display: flex; flex-wrap: wrap; gap: 0.25rem; align-items: center;">
                            <span style="font-size: 0.7rem; font-weight: bold; text-transform: uppercase; color: #9ca3af; margin-right: 0.25rem;">In:</span>
                            @foreach($route['inbound_rails'] as $rail)
                                <span style="font-size: 0.65rem; padding: 0.1rem 0.4rem; background: #f3f4f6; border-radius: 4px; color: #374151; border: 1px solid #e5e7eb;">{{ $rail }}</span>
                            @endforeach
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.25rem; align-items: center;">
                            <span style="font-size: 0.7rem; font-weight: bold; text-transform: uppercase; color: #9ca3af; margin-right: 0.25rem;">Out:</span>
                            @foreach($route['outbound_rails'] as $rail)
                                <span style="font-size: 0.65rem; padding: 0.1rem 0.4rem; background: #eff6ff; border-radius: 4px; color: #1e40af; border: 1px solid #dbeafe;">{{ $rail }}</span>
                            @endforeach
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <div style="display: flex; align-items: center; font-size: 0.875rem;">
                            <div style="width: 100%; background-color: #e5e7eb; border-radius: 9999px; height: 0.5rem; margin-right: 0.5rem; overflow: hidden;">
                                <div style="background-color: #3b82f6; height: 100%; width: {{ $route['observability'] * 100 }}%"></div>
                            </div>
                            <span style="font-weight: 500; color: var(--text-color, #6b7280); white-space: nowrap;">Obs: {{ $route['observability'] * 100 }}%</span>
                        </div>

                        <div style="display: flex; align-items: center; font-size: 0.875rem;">
                            <div style="width: 100%; background-color: #e5e7eb; border-radius: 9999px; height: 0.5rem; margin-right: 0.5rem; overflow: hidden;">
                                <div style="background-color: #ef4444; height: 100%; width: {{ $route['lsi'] }}%"></div>
                            </div>
                            <span style="font-weight: 500; color: var(--text-color, #6b7280); white-space: nowrap;">Stress: {{ number_format($route['lsi'], 0) }}%</span>
                        </div>
                    </div>

                    <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #f3f4f6;">
                        <div style="display: flex; align-items: center; font-size: 0.875rem; font-weight: bold; color: var(--text-color, #374151);">
                            <x-heroicon-m-arrows-right-left style="width: 1rem; height: 1rem; flex-shrink: 0; margin-right: 0.5rem; color: #9ca3af;" />
                            {{ $route['methods'] }}
                        </div>
                    </div>
                </x-filament::section>
            @endforeach
        </div>

        @if(empty($routes))
            <x-filament::section>
                <div style="text-align: center; padding: 3rem 0;">
                    <x-heroicon-o-no-symbol style="width: 3rem; height: 3rem; margin: 0 auto 1rem; color: #9ca3af;" />
                    <p style="color: var(--text-color, #6b7280);">No liquidity routes found for this pair. Try adjusting the amount or currencies.</p>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
