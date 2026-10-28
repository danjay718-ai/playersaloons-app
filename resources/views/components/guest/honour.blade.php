@props(['player'])
<article class="gr-honour gr-reveal {{ $player['rank'] === 1 ? 'is-first' : '' }}">
    <span class="gr-honour-rank" aria-hidden="true">{{ $player['rank'] }}</span>
    <h3 translate="no">{{ $player['name'] }}</h3>
    <p>{{ $player['rank'] === 1 ? __('Leading the board') : __('Tournament contender') }}</p>
    <dl><div><dt>{{ __('Wins') }}</dt><dd>{{ $player['wins'] }}</dd></div><div><dt>{{ __('Win rate') }}</dt><dd>{{ $player['winrate'] }}</dd></div><div><dt>{{ __('Prizes') }}</dt><dd>{{ $player['cash'] }}</dd></div></dl>
</article>
