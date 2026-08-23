<?php

use Illuminate\Support\Facades\Broadcast;
use App\Modules\Community\Services\ChatService;
use App\Modules\Community\Models\ChatConversation;

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

Broadcast::channel('chat.{uuid}', function ($user, string $uuid) {
    /** @var ChatConversation|null $conversation */
    $conversation = ChatConversation::query()
        ->with('team')
        ->where('uuid', $uuid)
        ->first();

    return $conversation !== null && app(ChatService::class)->canAccessConversation($conversation, $user);
});
