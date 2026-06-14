<div class="space-y-10">
    @if(Auth::check() && Auth::user()->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'TOURNAMENT_ORGANIZER']))
        @include('livewire.tournament.partials.admin-content')
    @else
        @include('livewire.tournament.partials.player-content')
    @endif
</div>
