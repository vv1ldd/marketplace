<x-filament-widgets::widget>
    <x-filament::section>
        <div class="relative overflow-hidden rounded-xl p-6 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white border border-indigo-500/30">
            
            <!-- Glowing Cyber Grid Background -->
            <div class="absolute inset-0 opacity-15 pointer-events-none" style="background-image: radial-gradient(circle, #6366f1 1px, transparent 1px); background-size: 24px 24px;"></div>
            
            <!-- Cyan/Purple Radial Ambient Glow -->
            <div class="absolute -top-24 -right-24 w-72 h-72 bg-cyan-500/20 rounded-full blur-3xl pointer-events-none"></div>
            
            <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                <div class="space-y-3 flex-1">
                    <!-- Badges -->
                    <div class="flex items-center gap-3">
                        <div class="inline-flex items-center gap-1.5 rounded-full bg-cyan-500/10 px-3 py-1 text-xs font-bold text-cyan-400 border border-cyan-500/20">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-cyan-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-cyan-500"></span>
                            </span>
                            CRYPTO SECURE
                        </div>
                        <div class="text-[10px] tracking-[0.25em] font-black text-indigo-300/70 uppercase">
                            Epistemic Matrix V2
                        </div>
                    </div>

                    <!-- Title -->
                    <h2 class="text-2xl md:text-3xl font-black tracking-tight text-slate-100 uppercase">
                        Integrity Tribunal Matrix
                    </h2>

                    <!-- Description -->
                    <p class="text-sm text-indigo-200/70 leading-relaxed max-w-3xl font-mono">
                        You have entered the cryptographic audit chamber. This workspace continuously computes the 
                        <span class="text-white underline decoration-dashed decoration-indigo-500">deterministic causality hash-chain</span> 
                        across all ledger states, hunting for financial anomalies and securing 100% transaction immutability.
                    </p>
                </div>

                <!-- Shield Integrity Node Icon -->
                <div class="flex-shrink-0 flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-900/40 border border-indigo-500/30 text-cyan-400 shadow-[0_0_15px_rgba(99,102,241,0.2)]">
                    <svg class="w-8 h-8 animate-pulse" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    </svg>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
