<?php

return [
    // Supply credentials through deployment secrets, never source control.
    'super_admin' => [
        'email' => env('BOOTSTRAP_SUPER_ADMIN_EMAIL'),
        'username' => env('BOOTSTRAP_SUPER_ADMIN_USERNAME', 'superadmin'),
        'password' => env('BOOTSTRAP_SUPER_ADMIN_PASSWORD'),
    ],
    'admin' => [
        'email' => env('BOOTSTRAP_ADMIN_EMAIL'),
        'username' => env('BOOTSTRAP_ADMIN_USERNAME', 'staffadmin'),
        'password' => env('BOOTSTRAP_ADMIN_PASSWORD'),
    ],
];
