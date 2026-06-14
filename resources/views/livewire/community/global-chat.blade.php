<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Chat Section -->
    <div class="bg-[#0c081d] border border-purple-500/15 rounded-2xl p-5 md:p-6 flex flex-col h-[600px]">
        <div class="flex items-center space-x-2 border-b border-purple-500/10 pb-4 mb-4">
            <i data-lucide="message-square" class="w-5 h-5 text-purple-400"></i>
            <h3 class="text-sm font-black font-orbitron tracking-wider text-zinc-150 uppercase">GLOBAL COMMUNICATIONS</h3>
        </div>

        <div class="flex-grow overflow-y-auto space-y-4 pr-2 mb-4 scrollbar-thin scrollbar-thumb-purple-900 scrollbar-track-transparent">
            @foreach($messages as $msg)
                <div class="flex items-start space-x-3">
                    <div class="w-8 h-8 rounded-full bg-purple-900/20 border border-purple-500/20 flex items-center justify-center text-[10px] font-bold font-orbitron text-purple-400 mt-0.5">
                        {{ $msg['avatar'] }}
                    </div>
                    <div>
                        <div class="flex items-baseline space-x-2">
                            <span class="text-xs font-bold text-zinc-300 font-orbitron">{{ $msg['username'] }}</span>
                            <span class="text-[8px] text-zinc-600 font-mono">{{ $msg['time'] }}</span>
                        </div>
                        <p class="text-xs text-zinc-400 mt-0.5">{{ $msg['text'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <form wire:submit="sendMessage" class="flex gap-2">
            <input wire:model="chatMessage" type="text" placeholder="Type a message..." class="flex-grow bg-zinc-950 border border-zinc-800 rounded-xl px-4 py-3 text-xs text-zinc-200 focus:outline-none focus:border-purple-500">
            <button type="submit" class="bg-purple-900 hover:bg-purple-800 text-white p-3 rounded-xl flex items-center justify-center cursor-pointer">
                <i data-lucide="send" class="w-4 h-4"></i>
            </button>
        </form>
    </div>

    <!-- Live Streams Placeholder -->
    <div class="bg-[#0c081d] border border-purple-500/15 rounded-2xl p-5 md:p-6 text-center flex flex-col items-center justify-center">
        <i data-lucide="tv" class="w-12 h-12 text-zinc-700 mb-4"></i>
        <h3 class="text-sm font-black font-orbitron tracking-wider text-zinc-300 uppercase">NO LIVE BROADCASTS</h3>
        <p class="text-[10px] text-zinc-500 mt-2">Check back later for live tournament streams.</p>
    </div>
</div>
