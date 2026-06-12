@php
    $code = $code ?? '500';
    $message = $message ?? '';
    
    // Dynamically set properties based on code
    switch ($code) {
        case '403':
            $color = 'red';
            $title = 'Access Denied';
            $header = 'Security Protocol Restrict';
            $description = $message ?: 'Your credentials lack the authorization required to access this sector. Access is prohibited by policy rules.';
            $iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 text-red-500 drop-shadow-[0_0_8px_rgba(239,68,68,0.4)]">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
            </svg>';
            break;
            
        case '404':
            $color = 'indigo';
            $title = 'Page Not Found';
            $header = 'Grid Signal Lost';
            $description = $message ?: 'The coordinates you requested do not exist in the saloon archives. The link may have expired or relocated.';
            $iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 text-indigo-400 drop-shadow-[0_0_8px_rgba(99,102,241,0.4)]">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>';
            break;
            
        case '419':
            $color = 'fuchsia';
            $title = 'Session Expired';
            $header = 'Session Expired';
            $description = $message ?: 'Your connection token has timed out due to inactivity. Please refresh your browser or log in again to authenticate.';
            $iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 text-fuchsia-400 drop-shadow-[0_0_8px_rgba(217,70,239,0.4)]">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>';
            break;
            
        case '500':
        default:
            $color = 'amber';
            $title = 'System Error';
            $header = 'Internal System Fault';
            $description = $message ?: 'Our core processor encountered an unexpected fault in rendering this module. Engineers have been alerted.';
            $iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 text-amber-500 drop-shadow-[0_0_8px_rgba(245,158,11,0.4)]">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.249-8.25-3.286Zm0 13.036h.008v.008H12v-.008Z" />
            </svg>';
            break;
    }
    
    // Class mapping for tailwind dynamic classes
    $colors = [
        'red' => [
            'bg_glow' => 'bg-red-650/10',
            'bg_pulse' => 'bg-red-500/10',
            'border' => 'border-red-500/30',
            'text_code' => 'text-red-500 drop-shadow-[0_0_8px_rgba(239,68,68,0.3)]',
            'btn_bg' => 'from-red-600 to-red-800 hover:from-red-500 hover:to-red-700 shadow-[0_0_15px_rgba(239,68,68,0.15)] border-red-500/25',
        ],
        'indigo' => [
            'bg_glow' => 'bg-indigo-650/10',
            'bg_pulse' => 'bg-indigo-500/10',
            'border' => 'border-indigo-500/30',
            'text_code' => 'text-indigo-400 drop-shadow-[0_0_8px_rgba(99,102,241,0.3)]',
            'btn_bg' => 'from-indigo-600 to-indigo-800 hover:from-indigo-555 hover:to-indigo-700 shadow-[0_0_15px_rgba(99,102,241,0.15)] border-indigo-500/25',
        ],
        'fuchsia' => [
            'bg_glow' => 'bg-fuchsia-650/10',
            'bg_pulse' => 'bg-fuchsia-500/10',
            'border' => 'border-fuchsia-500/30',
            'text_code' => 'text-fuchsia-400 drop-shadow-[0_0_8px_rgba(217,70,239,0.3)]',
            'btn_bg' => 'from-fuchsia-600 to-fuchsia-800 hover:from-fuchsia-555 hover:to-fuchsia-700 shadow-[0_0_15px_rgba(217,70,239,0.15)] border-fuchsia-500/25',
        ],
        'amber' => [
            'bg_glow' => 'bg-amber-650/10',
            'bg_pulse' => 'bg-amber-500/10',
            'border' => 'border-amber-500/30',
            'text_code' => 'text-amber-500 drop-shadow-[0_0_8px_rgba(245,158,11,0.3)]',
            'btn_bg' => 'from-amber-600 to-amber-800 hover:from-amber-555 hover:to-amber-700 shadow-[0_0_15px_rgba(245,158,11,0.15)] border-amber-500/25',
        ],
    ];
    
    $c = $colors[$color] ?? $colors['amber'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | PlayerSaloons</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .font-orbitron {
            font-family: 'Orbitron', sans-serif;
        }
    </style>
</head>
<body class="bg-[#05070c] text-slate-100 min-h-screen antialiased flex flex-col items-center justify-center p-6 relative overflow-hidden">
    <!-- Glowing background effects -->
    <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] {{ $c['bg_glow'] }} rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-0 right-10 w-96 h-96 bg-slate-900/5 rounded-full blur-3xl pointer-events-none"></div>

    <div class="max-w-md w-full text-center relative z-10 space-y-8">
        <!-- Error Code Grid / Icon -->
        <div class="relative inline-flex items-center justify-center mb-4">
            <div class="absolute inset-0 {{ $c['bg_pulse'] }} rounded-3xl blur-xl animate-pulse"></div>
            <div class="w-24 h-24 rounded-3xl border {{ $c['border'] }} bg-[#0c080d] flex items-center justify-center shadow-lg">
                {!! $iconSvg !!}
            </div>
        </div>

        <!-- Text Content -->
        <div class="space-y-3">
            <h1 class="text-4xl md:text-5xl font-black font-orbitron tracking-widest {{ $c['text_code'] }} uppercase">
                {{ $code }}
            </h1>
            <h2 class="text-lg md:text-xl font-bold uppercase tracking-wider text-slate-200">
                {{ $header }}
            </h2>
            <p class="text-xs text-slate-400 leading-relaxed max-w-sm mx-auto">
                {{ $description }}
            </p>
        </div>

        <!-- Actions -->
        <div class="pt-4 flex flex-col gap-3 max-w-xs mx-auto">
            @php
                $dashboardUrl = auth()->check() 
                    ? (auth()->user()->hasAnyRole(['SUPER_ADMIN','ADMIN','MODERATOR','FINANCE_OPERATOR','KYC_REVIEWER','SUPPORT_AGENT','TOURNAMENT_ORGANIZER']) ? '/admin' : '/dashboard')
                    : '/';
            @endphp
            
            <a href="{{ $dashboardUrl }}" class="w-full bg-gradient-to-r {{ $c['btn_bg'] }} text-white font-bold py-3 rounded-xl border text-xs uppercase tracking-widest font-orbitron cursor-pointer transition-all">
                Return to Terminal
            </a>
            
            @if($code === '403' && auth()->check())
                <form method="POST" action="{{ route('logout') }}" class="w-full m-0">
                    @csrf
                    <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 text-slate-300 font-bold py-3 rounded-xl text-xs uppercase tracking-widest font-orbitron cursor-pointer transition-all">
                        Disconnect / Change Account
                    </button>
                </form>
            @elseif($code === '419')
                <a href="/login" class="w-full bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 text-slate-300 font-bold py-3 rounded-xl text-xs uppercase tracking-widest font-orbitron cursor-pointer transition-all">
                    Sign In
                </a>
            @endif
        </div>
    </div>
</body>
</html>
