<x-filament-widgets::widget>
    <div class="sovereign-ai-wrapper" style="--primary-color: #22c55e;">
        <div class="relative group p-1">
            <!-- Glow effect -->
            <div class="absolute -inset-1 blur-xl opacity-50 group-hover:opacity-100 transition duration-1000" style="background: linear-gradient(to right, rgba(22, 163, 74, 0.2), rgba(5, 150, 105, 0.2)); border-radius: 2rem;"></div>
            
            <div class="relative flex flex-col overflow-hidden font-mono shadow-2xl" style="background-color: #020617; color: #4ade80; border-radius: 1.5rem; border: 1px solid rgba(34, 197, 94, 0.3); height: 550px; box-shadow: 0 25px 50px -12px rgba(5, 46, 22, 0.2);">
                
                <!-- CRT Overlay -->
                <div class="absolute inset-0 pointer-events-none z-10 opacity-[0.03]" style="background-image: linear-gradient(rgba(18,16,16,0) 50%, rgba(0,0,0,0.25) 50%), linear-gradient(90deg, rgba(255,0,0,0.06), rgba(0,255,0,0.02), rgba(0,0,255,0.06)); background-size: 100% 2px, 3px 100%;"></div>
 
                <!-- Terminal Header -->
                <div class="px-6 py-4 flex justify-between items-center shrink-0" style="border-bottom: 1px solid rgba(34, 197, 94, 0.2); background-color: rgba(34, 197, 94, 0.05);">
                    <div class="flex items-center gap-x-3">
                        <div class="w-2.5 h-2.5 rounded-full bg-green-500" style="box-shadow: 0 0 10px #22c55e;"></div>
                        <div class="flex flex-col">
                            <span class="text-[10px] uppercase font-black tracking-widest leading-none">Neural Link Active</span>
                            <span class="text-[8px] uppercase font-bold mt-0.5" style="color: rgba(34, 197, 94, 0.6);">Sovereign OS v4.2.0</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-x-4">
                        <div class="hidden sm:flex flex-col items-end">
                            <div class="h-1 w-16 rounded-full overflow-hidden" style="background-color: rgba(20, 83, 45, 0.3);">
                                <div class="h-full bg-green-500/40 w-2/3 animate-pulse" style="background-color: rgba(34, 197, 94, 0.4);"></div>
                            </div>
                        </div>
                        <span class="text-[9px] font-bold opacity-40">NODE: 8D22-0B30</span>
                    </div>
                </div>

                <!-- Chat Messages -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6 custom-scrollbar scroll-smooth" id="matrix-body"
                     x-data="{ 
                        scroll() { 
                            $nextTick(() => { 
                                const el = document.getElementById('matrix-body');
                                if(el) el.scrollTop = el.scrollHeight;
                            });
                        }
                     }"
                     @message-added.window="scroll()">
                    
                    @foreach($chatHistory as $index => $msg)
                        <div class="flex flex-col gap-y-2"
                             x-data="{ 
                                text: '', 
                                fullText: {{ json_encode($msg['content']) }},
                                role: '{{ $msg['role'] }}',
                                isLast: {{ $index === count($chatHistory) - 1 ? 'true' : 'false' }},
                                type() {
                                    if (this.role === 'user' || !this.isLast) {
                                        this.text = this.fullText;
                                        return;
                                    }
                                    let i = 0;
                                    let interval = setInterval(() => {
                                        if (i < this.fullText.length) {
                                            this.text += this.fullText[i];
                                            i++;
                                            $dispatch('message-added');
                                        } else {
                                            clearInterval(interval);
                                        }
                                    }, 10);
                                }
                             }"
                             x-init="type()"
                        >
                            <div class="flex items-center gap-x-2">
                                <span @class([
                                    'text-[9px] font-black uppercase tracking-tighter',
                                    'text-blue-400' => $msg['role'] === 'user',
                                    'text-green-600' => $msg['role'] !== 'user',
                                ])>
                                    {{ $msg['role'] === 'user' ? 'OPERATOR' : 'SOVEREIGN_CORE' }}
                                </span>
                                <span class="text-[8px] opacity-20">[{{ $msg['time'] }}]</span>
                            </div>
                            <div @class([
                                'text-[13px] leading-relaxed pl-3 border-l',
                                'border-blue-500/20 text-slate-300' => $msg['role'] === 'user',
                                'border-green-500/20 text-green-400/90' => $msg['role'] !== 'user',
                            ])>
                                <span x-text="text" class="whitespace-pre-wrap"></span>
                                <span x-show="role === 'assistant' && text.length < fullText.length" class="inline-block w-2 h-4 bg-green-500 ml-1 animate-pulse align-middle"></span>
                            </div>
                        </div>
                    @endforeach

                    @if($isTyping)
                        <div class="flex items-center gap-x-2 text-[10px] text-green-800 animate-pulse">
                            <span>●</span><span>●</span><span>●</span>
                            <span class="uppercase tracking-widest font-bold">Decoding stream...</span>
                        </div>
                    @endif
                </div>

                <!-- Input Field -->
                <div class="p-4 shrink-0" style="background-color: rgba(0, 0, 0, 0.5); border-top: 1px solid rgba(34, 197, 94, 0.1);">
                    <form wire:submit.prevent="sendMessage" class="flex items-center gap-x-3">
                        <span class="text-green-500 font-bold tracking-tighter">>>></span>
                        <input 
                            type="text" 
                            wire:model.defer="message"
                            autofocus
                            placeholder="AWAITING COMMAND..."
                            class="flex-1 bg-transparent border-none p-0 text-[13px] focus:ring-0 focus:outline-none font-mono caret-green-500 uppercase tracking-widest"
                            style="color: #4ade80; outline: none; border: none; background: transparent;"
                        />
                    </form>
                </div>
            </div>
        </div>
    </div>

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(34, 197, 94, 0.1); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(34, 197, 94, 0.2); }
        #matrix-body { scrollbar-width: thin; scrollbar-color: rgba(34, 197, 94, 0.1) transparent; }
    </style>
</x-filament-widgets::widget>
