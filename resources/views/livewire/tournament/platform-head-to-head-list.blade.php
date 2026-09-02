@include('livewire.tournament.player-tournament-list', [
    'listingType' => 'head_to_head',
    'allowCompetitionSwitch' => false,
    'publicView' => $publicView ?? false,
])
