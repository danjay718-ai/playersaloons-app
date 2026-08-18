<?php

declare(strict_types=1);

return [
    /*
    | Player-created wager challenges are retained as a dormant domain so the
    | feature can be re-enabled without coupling it to platform competitions.
    */
    'player_wager' => [
        'enabled' => (bool) env('PLAYER_WAGER_ENABLED', false),
    ],
];
