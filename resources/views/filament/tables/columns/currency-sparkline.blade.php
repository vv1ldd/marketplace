<div class="px-4 py-1">
    @php
        $history = $getRecord()->histories()->latest('record_date')->limit(10)->get()->reverse();
        $points = $history->pluck('spread_percent')->map(fn($v) => (float)$v)->toArray();
        $max = !empty($points) ? max(max($points), 1) : 1;
        $min = !empty($points) ? min($points) : 0;
        $range = ($max - $min) ?: 1;
        
        $width = 100;
        $height = 30;
        $coords = [];
        if (count($points) > 1) {
            foreach ($points as $i => $p) {
                $x = ($i / (count($points) - 1)) * $width;
                $y = $height - (($p - $min) / $range * $height);
                $coords[] = "$x,$y";
            }
        }
        $polyline = implode(' ', $coords);
    @endphp

    @if(count($points) > 1)
        <svg width="{{ $width }}" height="{{ $height }}" class="overflow-visible">
            <polyline
                fill="none"
                stroke="{{ $getRecord()->market_sentiment === 'HOT 🔥' ? '#ef4444' : '#3b82f6' }}"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                points="{{ $polyline }}"
            />
        </svg>
    @else
        <span class="text-xs text-gray-400">No data</span>
    @endif
</div>
