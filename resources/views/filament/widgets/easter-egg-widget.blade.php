@php
    $user = filament()->auth()->user();
@endphp
<x-filament-widgets::widget>
    <div class="relative group">
        <!-- Ambient Glow -->
        <div class="absolute -inset-0.5 bg-gradient-to-r from-amber-500/20 to-orange-600/20 rounded-2xl blur-lg opacity-40 group-hover:opacity-60 transition duration-1000"></div>
        
        <x-filament::section class="relative overflow-hidden !bg-slate-900/40 backdrop-blur-xl border border-white/5 rounded-2xl shadow-2xl shadow-amber-950/10">
            
            <!-- Technical Overlay removed for cleaner UI -->

            <div class="relative flex items-center justify-between gap-8 p-4">
                <div class="flex items-center gap-8 text-left">
                    
                    <!-- Avatar with Controlled Ring -->
                    <div class="relative shrink-0 w-20 h-20">
                        <div class="absolute -inset-1 rounded-full border border-amber-500/20 border-dashed animate-[spin_20s_linear_infinite]"></div>
                        <div class="absolute -inset-1 rounded-full bg-amber-500/10 blur-sm"></div>
                        <img
                            src="{{ filament()->getUserAvatarUrl($user) }}"
                            alt="{{ \Filament\Facades\Filament::getUserName($user) }}"
                            class="relative h-20 w-20 rounded-full border border-slate-800 object-cover"
                        />
                    </div>
                    
                    <!-- Greetings -->
                    <div class="space-y-3">
                        <div class="inline-flex items-center gap-x-2 px-2 py-0.5 rounded-full bg-amber-500/10 border border-amber-500/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                            <span class="text-[9px] font-black uppercase tracking-widest text-amber-500/80">Authorized Session</span>
                        </div>
                        
                        @if($this->isMemorialPeriod())
                            <h2 class="text-4xl font-black tracking-tight text-white dashboard-header-glow">
                                {{ __('admin.widgets.welcome_title') }}, <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-300 to-orange-500">{{ explode(' ', \Filament\Facades\Filament::getUserName($user))[0] }}</span> 🌍
                            </h2>
                            
                            <div class="text-sm font-medium text-slate-400 space-y-3 max-w-3xl leading-relaxed">
                                <p class="opacity-80">
                                    {{ __('admin.widgets.welcome_desc_1') }}
                                </p>
                                <p class="text-slate-500 italic font-mono text-xs border-l-2 border-amber-500/20 pl-4">
                                    {{ __('admin.widgets.welcome_desc_2') }}
                                </p>
                            </div>
                        @else
                            <h2 class="text-3xl font-black tracking-tight text-white dashboard-header-glow">
                                Welcome back, <span class="text-amber-500">{{ explode(' ', \Filament\Facades\Filament::getUserName($user))[0] }}</span>
                            </h2>
                            <p class="text-sm text-slate-400 font-medium max-w-2xl">
                                System protocols operational. All nodes reporting optimal performance.
                            </p>
                        @endif
                    </div>
                </div>
                
                @if($this->isMemorialPeriod())
                    <div class="hidden xl:flex flex-col items-end self-end pb-2">
                        <div class="flex flex-col items-end font-mono text-[10px] space-y-1">
                            <span class="text-amber-500/40 font-black tracking-widest uppercase">Location Terminal</span>
                            <span class="text-slate-500 text-right leading-none uppercase">
                                Puerto Limon<br>
                                San Telmo<br>
                                <span class="text-amber-600/60 font-bold">Buenos Aires 🇦🇷</span>
                            </span>
                        </div>
                    </div>
                @endif
            </div>
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
