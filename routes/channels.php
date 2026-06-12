<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->getKey() === (int) $id;
});

Broadcast::channel('user.{uuid}', function ($user, string $uuid) {
    return (string) $user->getAttribute('uuid') === $uuid;
});

Broadcast::channel('tournament.{uuid}', function ($user, string $uuid) {
    return true; // Anyone authenticated can listen to tournament updates
});

Broadcast::channel('match.{uuid}', function ($user, string $uuid) {
    return true; // Anyone authenticated can listen to match updates
});
