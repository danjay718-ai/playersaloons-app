<div class="bg-[#090d16] min-h-screen text-slate-200">
    <!-- Hero Section -->
    <div class="relative bg-gradient-to-b from-[#0f172a] to-[#090d16] border-b border-slate-800/50 pt-20 pb-16 overflow-hidden">
        <!-- Abstract Background Effects -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-40 -right-40 w-96 h-96 bg-indigo-600/10 blur-[100px] rounded-full"></div>
            <div class="absolute top-20 -left-40 w-96 h-96 bg-cyan-600/10 blur-[100px] rounded-full"></div>
        </div>
        
        <div class="max-w-4xl mx-auto px-6 relative z-10 text-center">
            <span class="inline-block py-1 px-3 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-[10px] font-black uppercase tracking-widest mb-6">About Us</span>
            <h1 class="text-4xl md:text-5xl font-black text-white mb-6 uppercase tracking-wider">{{ $title }}</h1>
            <p class="text-lg md:text-xl text-slate-400 max-w-2xl mx-auto leading-relaxed">{{ $subtitle }}</p>
        </div>
    </div>

    <!-- Content Section -->
    <div class="max-w-4xl mx-auto px-6 py-16">
        <div class="bg-[#0f172a]/50 backdrop-blur-md border border-slate-800/50 rounded-2xl p-8 md:p-12 shadow-[0_0_50px_rgba(0,0,0,0.5)] prose prose-invert prose-indigo prose-headings:font-black prose-headings:uppercase prose-headings:tracking-wider prose-a:text-indigo-400 max-w-none">
            {!! $body !!}
        </div>
    </div>
</div>
