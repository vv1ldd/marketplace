<div class="relative w-full h-full flex items-center justify-center bg-gray-100 dark:bg-gray-800">
    @php
        $logo = $getRecord()->brand_logo_url;
        $name = $getRecord()->brand_name;
        $color = '#' . substr(md5($name), 0, 6);
    @endphp

    @if($logo)
        <img src="{{ $logo }}" class="w-full h-full object-cover" alt="{{ $name }}">
        <div class="absolute inset-0 bg-black opacity-10 group-hover:opacity-0 transition-opacity"></div>
    @else
        <div class="w-full h-full flex items-center justify-center text-white font-black text-4xl" style="background-color: {{ $color }}">
            {{ substr($name, 0, 1) }}
        </div>
        <div class="absolute inset-0 bg-white opacity-20 mix-blend-overlay"></div>
    @endif

    <div class="absolute bottom-0 left-0 right-0 p-4 bg-gradient-to-t from-black/60 to-transparent">
        <span class="text-white text-[10px] font-bold uppercase tracking-widest opacity-80">
            {{ $getRecord()->brand_name }}
        </span>
    </div>
</div>
