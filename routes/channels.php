<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->getKey() === (int) $id;
});

Broadcast::channel('user.{uuid}', function ($user, string $uuid) {
    return (string) $user->getAttribute('uuid') === $uuid;
});
