<div class="max-w-4xl space-y-6">
    @if(session('success'))
        <div class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
            {{ session('success') }}
        </div>
    @endif

    <section class="rounded-xl border border-slate-800 bg-slate-950/60 p-6">
        <h2 class="text-xl font-bold text-white">Block a Country</h2>
        <p class="mt-2 text-sm text-slate-500">Block all non-admin traffic from a specific country code. Users from this country will see the message provided below.</p>
        
        <form wire:submit="addCountry" class="mt-6 space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-400">ISO Country Code (e.g. US, PH, GB)</label>
                    <input wire:model="countryCode" type="text" maxlength="2" class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white uppercase placeholder:text-slate-600" placeholder="US">
                    @error('countryCode') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-400">Country Name</label>
                    <input wire:model="countryName" type="text" class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white placeholder:text-slate-600" placeholder="United States">
                    @error('countryName') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="text-xs font-bold uppercase tracking-wider text-slate-400">Blocking Message (Visible to User)</label>
                <textarea wire:model="message" rows="3" class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white"></textarea>
                @error('message') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            
            <button type="submit" class="rounded-lg bg-red-600 px-5 py-3 text-xs font-bold uppercase tracking-wider text-white hover:bg-red-500">
                Block Country
            </button>
        </form>
    </section>

    <section class="rounded-xl border border-slate-800 bg-slate-950/60 overflow-hidden">
        <div class="border-b border-slate-800 px-6 py-4">
            <h3 class="font-bold text-white">Blocked Countries</h3>
        </div>
        
        @if($blockedCountries->isEmpty())
            <div class="p-8 text-center text-sm text-slate-500">No countries are currently blocked.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-900/50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Country</th>
                            <th class="px-6 py-3">Message</th>
                            <th class="px-6 py-3">Updated By</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        @foreach($blockedCountries as $country)
                            <tr class="hover:bg-slate-800/20">
                                <td class="whitespace-nowrap px-6 py-4 font-bold text-white">
                                    <span class="fi fi-{{ strtolower($country->country_code) }} mr-2"></span>
                                    {{ $country->country_name }} <span class="text-slate-500">({{ $country->country_code }})</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="line-clamp-2 max-w-xs">{{ $country->message }}</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-400">
                                    {{ $country->updatedBy?->username ?? 'System' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <button wire:click="removeCountry('{{ $country->country_code }}')" wire:confirm="Are you sure you want to unblock {{ $country->country_name }}?" class="text-sm font-semibold text-red-400 hover:text-red-300">
                                        Unblock
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
