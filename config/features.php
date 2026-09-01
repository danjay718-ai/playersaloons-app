<?php

declare(strict_types=1);

return [
    'tournament_v2' => [
        'enabled' => (bool) env('TOURNAMENT_V2_ENABLED', false),
        'empty_occurrence_retention_days' => (int) env('TOURNAMENT_V2_EMPTY_OCCURRENCE_RETENTION_DAYS', 30),
    ],

    /*
    | Player-created wager challenges are retained as a dormant domain so the
    | feature can be re-enabled without coupling it to platform competitions.
    */
    'player_wager' => [
        'enabled' => (bool) env('PLAYER_WAGER_ENABLED', false),
    ],
];
