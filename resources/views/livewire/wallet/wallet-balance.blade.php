<span wire:poll.10s.visible class="text-xs font-black font-orbitron tracking-wider {{ (float) $balance < 0 ? 'text-red-400' : 'text-emerald-400' }}">${{ number_format((float) $balance, 2) }}</span>
