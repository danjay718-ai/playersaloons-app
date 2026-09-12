<?php

use App\Modules\Community\Models\ChatConversation;
use App\Modules\Community\Services\ChatService;
use App\Modules\Match\Models\GameMatch;
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
    if ($user->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'TOURNAMENT_ORGANIZER'])) {
        return true;
    }

    $match = GameMatch::query()
        ->with(['playerARegistration.rosterMembers', 'playerBRegistration.rosterMembers'])
        ->where('uuid', $uuid)
        ->first();

    return $match !== null && (
        $match->playerARegistration?->includesUser((int) $user->getKey())
        || $match->playerBRegistration?->includesUser((int) $user->getKey())
    );
});

Broadcast::channel('chat.{uuid}', function ($user, string $uuid) {
    /** @var ChatConversation|null $conversation */
    $conversation = ChatConversation::query()
        ->with('team')
        ->where('uuid', $uuid)
        ->first();

    return $conversation !== null && app(ChatService::class)->canAccessConversation($conversation, $user);
});
