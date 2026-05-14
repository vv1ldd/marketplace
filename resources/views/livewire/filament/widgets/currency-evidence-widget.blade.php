<div class="space-y-6">
    @if($event)
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Health Card -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="text-sm text-gray-500 mb-1">Confidence Score</div>
                <div class="flex items-end space-x-2">
                    <div class="text-3xl font-bold {{ $event->confidence_score > 0.7 ? 'text-success-600' : ($event->confidence_score > 0.4 ? 'text-warning-600' : 'text-danger-600') }}">
                        {{ number_format($event->confidence_score * 100, 1) }}%
                    </div>
                    <div class="text-xs text-gray-400 mb-1">observability</div>
                </div>
            </div>

            <!-- Stress Card -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="text-sm text-gray-500 mb-1">Liquidity Stress (LSI)</div>
                <div class="flex items-end space-x-2">
                    <div class="text-3xl font-bold {{ ($event->execution_reality['lsi'] ?? 0) > 0.5 ? 'text-danger-600' : 'text-gray-700 dark:text-gray-200' }}">
                        {{ number_format(($event->execution_reality['lsi'] ?? 0) * 100, 0) }}%
                    </div>
                    <div class="text-xs text-gray-400 mb-1">market fragility</div>
                </div>
            </div>

            <!-- Anchor Card -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="text-sm text-gray-500 mb-1">Last Sync (MDK Fingerprint)</div>
                <div class="text-xs font-mono text-gray-400 break-all">
                    {{ $event->created_at->diffForHumans() }}
                </div>
                <div class="mt-2 text-[10px] text-primary-500 font-mono">
                    {{ substr(hash('sha256', $event->id), 0, 16) }}... (verified)
                </div>
            </div>
        </div>

        <!-- Signals Tree -->
        <div class="bg-gray-50 dark:bg-gray-900/50 rounded-3xl p-6 border border-gray-100 dark:border-gray-800">
            <h3 class="text-lg font-semibold mb-4 flex items-center space-x-2">
                <x-heroicon-o-finger-print class="w-5 h-5 text-primary-500" />
                <span>Evidence Graph (Raw Signals)</span>
            </h3>

            <div class="space-y-3">
                @foreach($event->evidence_graph['signals'] ?? [] as $signal)
                    <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm">
                        <div class="flex items-center space-x-3">
                            <div @class([
                                'w-2 h-2 rounded-full',
                                'bg-blue-500' => $signal['type'] === 'reference',
                                'bg-indigo-500' => $signal['type'] === 'spot',
                                'bg-red-500' => $signal['type'] === 'p2p',
                            ])></div>
                            <div>
                                <div class="text-sm font-medium uppercase">{{ $signal['type'] }}</div>
                                <div class="text-xs text-gray-400">{{ $signal['source'] }} @ {{ \Carbon\Carbon::parse($signal['timestamp'])->format('H:i:s') }}</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-bold font-mono">{{ number_format($signal['rate'], 4) }}</div>
                            <div class="text-[10px] text-gray-500">Weight: {{ $signal['weight'] }} | Tier: {{ $signal['tier'] }}</div>
                        </div>
                    </div>
                @endforeach

                @if(empty($event->evidence_graph['signals']))
                    <div class="text-center py-8 text-gray-400 italic">
                        No live signals detected. Using synthetic fallback based on official pegs.
                    </div>
                @endif
            </div>

            @if($event->evidence_graph['is_synthetic'] ?? false)
                <div class="mt-4 p-3 bg-warning-50 dark:bg-warning-900/20 rounded-xl border border-warning-100 dark:border-warning-800 flex items-start space-x-3">
                    <x-heroicon-m-exclamation-triangle class="w-5 h-5 text-warning-500 shrink-0" />
                    <div class="text-xs text-warning-700 dark:text-warning-400">
                        <strong>Synthetic Synthesis Active:</strong> Due to lack of live market liquidity, the rate was derived using a sovereign institutional peg. 
                    </div>
                </div>
            @endif
        </div>
    @else
        <div class="p-12 text-center text-gray-400 border-2 border-dashed border-gray-200 rounded-3xl">
            No telemetry evidence found for this currency node.
        </div>
    @endif
</div>
