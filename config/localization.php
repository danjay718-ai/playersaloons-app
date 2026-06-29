<?php

declare(strict_types=1);

return [
    'default' => env('APP_LOCALE', 'en'),

    'supported' => [
        'en' => ['native' => 'English', 'english' => 'English'],
        'fr' => ['native' => 'Francais', 'english' => 'French'],
        'es' => ['native' => 'Espanol', 'english' => 'Spanish'],
        'de' => ['native' => 'Deutsch', 'english' => 'German'],
        'it' => ['native' => 'Italiano', 'english' => 'Italian'],
        'nl' => ['native' => 'Nederlands', 'english' => 'Dutch'],
        'pt' => ['native' => 'Portugues', 'english' => 'Portuguese'],
        'ru' => ['native' => 'Russkiy', 'english' => 'Russian'],
        'ja' => ['native' => 'Nihongo', 'english' => 'Japanese'],
        'zh' => ['native' => 'Zhongwen', 'english' => 'Chinese'],
        'pl' => ['native' => 'Polski', 'english' => 'Polish'],
    ],
];
