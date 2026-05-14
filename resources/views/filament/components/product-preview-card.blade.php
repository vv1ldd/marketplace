<div class="space-y-6" x-data="{ 
    name: '',
    price: 0,
    image: '',
    videos: []
}" 
x-init="
    name = $wire.get('data.name');
    price = $wire.get('data.price_rub');
    image = $wire.get('data.image');
    videos = $wire.get('data.videos') || [];
    
    $watch('$wire.data.name', v => name = v); 
    $watch('$wire.data.price_rub', v => price = v); 
    $watch('$wire.data.image', v => image = v); 
    $watch('$wire.data.videos', v => videos = v || []);
">
    
    <!-- Marketplace Mobile Preview Frame -->
    <div class="relative mx-auto border-gray-900 bg-gray-900 border-[12px] rounded-[3rem] h-[640px] w-[320px] shadow-2xl overflow-hidden ring-4 ring-gray-800 transition-all duration-500">
        <!-- Dynamic Island -->
        <div class="w-[100px] h-[25px] bg-gray-900 top-0 rounded-b-[1.2rem] left-1/2 -translate-x-1/2 absolute z-30 flex items-center justify-center">
            <div class="w-8 h-1 bg-gray-800 rounded-full"></div>
        </div>
        
        <!-- Screen Content -->
        <div class="rounded-[2.2rem] overflow-hidden w-full h-full bg-white dark:bg-gray-950 overflow-y-auto custom-scrollbar relative">
            
            <!-- App Header Mockup -->
            <div class="sticky top-0 z-20 bg-white/90 dark:bg-gray-950/90 backdrop-blur-md px-4 pt-8 pb-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                <div class="w-7 h-7 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </div>
                <div class="text-[9px] font-bold tracking-widest text-gray-400 uppercase">Market Preview</div>
                <div class="w-7 h-7 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-500">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M15 8a3 3 0 10-2.977-2.63l-4.94 2.47a3 3 0 100 4.319l4.94 2.47a3 3 0 10.895-1.789l-4.94-2.47a3.027 3.027 0 000-.74l4.94-2.47C13.456 7.68 14.19 8 15 8z"/></svg>
                </div>
            </div>

            <!-- Product Media -->
            <div class="relative h-[360px] bg-gray-50 dark:bg-gray-900 flex items-center justify-center group overflow-hidden">
                <!-- Video Player -->
                <template x-if="videos && videos.length > 0">
                    <video :src="videos[0].includes('http') ? videos[0] : (videos[0].startsWith('img/') ? '/' + videos[0] : (videos[0].startsWith('/') ? videos[0] : '/storage/' + videos[0]))" 
                           class="w-full h-full object-cover" 
                           autoplay loop muted playsinline>
                    </video>
                </template>
                
                <!-- Static Image -->
                <template x-if="(!videos || (videos && videos.length === 0)) && image">
                    <img :src="image.includes('http') ? image : (image.startsWith('img/') ? '/' + image : (image.startsWith('/') ? image : '/storage/' + image))" 
                         class="w-full h-full object-contain p-2 transition-transform duration-700 group-hover:scale-110" 
                         x-on:error="$el.src='https://placehold.co/600x800/222/fff?text=No+Image'" />
                </template>
                
                <!-- Placeholder when NO media -->
                <template x-if="!image && (!videos || (videos && videos.length === 0))">
                    <div class="flex flex-col items-center justify-center text-gray-200 dark:text-gray-800">
                        <svg class="w-20 h-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <p class="text-[10px] uppercase font-black mt-2 tracking-tighter opacity-50">Waiting for media...</p>
                    </div>
                </template>
            </div>

            <!-- Product Info -->
            <div class="p-4 space-y-3">
                <div class="flex items-baseline space-x-2">
                    <span class="text-xl font-black text-gray-900 dark:text-white" x-text="Math.round(price/100).toLocaleString() + ' ₽'"></span>
                    <span class="text-xs text-gray-400 line-through" x-text="Math.round(price/80).toLocaleString() + ' ₽'"></span>
                </div>
                
                <h1 class="text-xs font-bold text-gray-800 dark:text-gray-200 leading-tight line-clamp-2" x-text="name || 'Название появится здесь'"></h1>
                
                <div class="flex items-center space-x-1 py-1 border-y border-gray-50 dark:border-gray-900">
                    <div class="flex text-yellow-400">
                        <template x-for="i in 5">
                            <svg class="w-3 h-3 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </template>
                    </div>
                    <span class="text-[10px] font-bold text-gray-400">4.9 · 1.2k заказов</span>
                </div>

                <div class="bg-green-50 dark:bg-green-900/20 p-2 rounded-lg">
                    <div class="text-[9px] text-green-600 dark:text-green-400 font-bold flex items-center uppercase tracking-wider">
                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        Бесплатная доставка завтра
                    </div>
                </div>

                <button class="w-full bg-yellow-400 text-black font-black py-3 rounded-xl shadow-lg shadow-yellow-400/20 text-[10px] uppercase tracking-widest mt-2 active:scale-95 transition-transform">
                    Добавить в корзину
                </button>
            </div>
            
            <!-- Generate Button Overlay -->
            <div class="px-4 pb-8">
                <button 
                    onclick="window.dispatchEvent(new CustomEvent('generate-video'))"
                    class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-3 rounded-xl text-[10px] uppercase tracking-tighter flex items-center justify-center space-x-2 transition-colors shadow-lg shadow-green-500/20"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span>Сгенерировать Видео</span>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 0px; }
.line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
</style>
