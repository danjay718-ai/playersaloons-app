<?php

declare(strict_types=1);

return [
    'default' => env('APP_LOCALE', 'en'),

    'supported' => [
        'en' => ['native' => 'English', 'english' => 'English', 'flag' => 'us'],
        'fr' => ['native' => 'Francais', 'english' => 'French', 'flag' => 'fr'],
        'es' => ['native' => 'Espanol', 'english' => 'Spanish', 'flag' => 'es'],
        'de' => ['native' => 'Deutsch', 'english' => 'German', 'flag' => 'de'],
        'it' => ['native' => 'Italiano', 'english' => 'Italian', 'flag' => 'it'],
        'nl' => ['native' => 'Nederlands', 'english' => 'Dutch', 'flag' => 'nl'],
        'pt' => ['native' => 'Portugues', 'english' => 'Portuguese', 'flag' => 'pt'],
        'ru' => ['native' => 'Russkiy', 'english' => 'Russian', 'flag' => 'ru'],
        'ja' => ['native' => 'Nihongo', 'english' => 'Japanese', 'flag' => 'jp'],
        'zh' => ['native' => 'Zhongwen', 'english' => 'Chinese', 'flag' => 'cn'],
        'pl' => ['native' => 'Polski', 'english' => 'Polish', 'flag' => 'pl'],
    ],
];
