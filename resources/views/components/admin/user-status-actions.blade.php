@props(['user', 'menuItem' => false])

@if(! $user->is(auth()->user()) && ! $user->hasRole('SUPER_ADMIN'))
@can('changeStatus', $user)
    @php
        $stateMachine = app(\App\Modules\Identity\StateMachines\UserStateMachine::class);
        $actions = [
            'suspended' => __('Suspend Account'),
            'banned' => __('Ban Account'),
            'active' => __('Restore Account'),
        ];
    @endphp
    @foreach($actions as $status => $label)
        @if($stateMachine->can($user->status, $status) && auth()->user()->can($status === 'active' ? 'users.unsuspend' : 'users.suspend'))
            <button type="button"
                @click.stop="$dispatch('open-account-status', { id: {{ $user->id }}, username: @js($user->username), status: @js($status) }); {{ $menuItem ? 'open = false' : '' }}"
                class="{{ $menuItem ? 'block w-full text-left px-4 py-2.5 text-xs hover:bg-slate-800' : 'rounded-lg border border-slate-700 px-4 py-2.5 text-xs hover:bg-slate-800' }} font-bold uppercase tracking-wider {{ $status === 'active' ? 'text-emerald-400' : 'text-red-400' }}">
                {{ $label }}
            </button>
        @endif
    @endforeach
@endcan
@endif
