<?php

declare(strict_types=1);

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Modules\Community\Actions\ChatService;
use App\Modules\Community\Models\ChatConversation;
use App\Modules\Community\Models\ChatMessage;
use App\Modules\Community\Models\PlayerFollow;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\HeadToHeadMatch;
use App\Modules\Team\Models\Team;
use App\Modules\Team\Models\TeamMember;
use App\Modules\Wallet\Models\LedgerEntry;
use App\Shared\Enums\HeadToHeadMatchStatus;
use App\Shared\Enums\LedgerType;
use App\Shared\Enums\MatchStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(private readonly ChatService $chat) {}

    public function conversations(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $conversations = $this->chat->userConversations($user)
            ->map(fn (ChatConversation $conversation): array => $this->chat->conversationPayload($conversation, $user))
            ->values();

        /** @var TeamMember|null $currentMember */
        $currentMember = TeamMember::query()
            ->with('team')
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->first();

        $teams = Team::query()
            ->when($currentMember, fn ($query) => $query->whereKey($currentMember->team_id), fn ($query) => $query->whereRaw('1 = 0'))
            ->get(['id', 'uuid', 'name'])
            ->map(fn (Team $team): array => [
                'id' => $team->id,
                'uuid' => $team->uuid,
                'name' => $team->name,
                'joined' => (int) $currentMember?->team_id === (int) $team->id,
                'current' => (int) $currentMember?->team_id === (int) $team->id,
            ])
            ->values();

        return response()->json([
            'conversations' => $conversations,
            'teams' => $teams,
            'current_team' => $currentMember?->team ? [
                'uuid' => $currentMember->team->uuid,
                'name' => $currentMember->team->name,
                'is_captain' => (int) $currentMember->team->captain_user_id === (int) $user->getKey(),
            ] : null,
        ]);
    }

    public function messages(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conversation = $this->findConversation($uuid, $user);
        $beforeId = $request->integer('before_id');

        $messages = ChatMessage::query()
            ->with('user')
            ->where('chat_conversation_id', $conversation->getKey())
            ->when($beforeId > 0, fn ($query) => $query->where('id', '<', $beforeId))
            ->latest('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (ChatMessage $message): array => $this->chat->messagePayload($message));

        $conversation->participants()
            ->where('user_id', $user->getKey())
            ->update(['last_read_at' => now()]);

        return response()->json([
            'conversation' => $this->chat->conversationPayload($conversation, $user),
            'messages' => $messages,
        ]);
    }

    public function send(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conversation = $this->findConversation($uuid, $user);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $message = $this->chat->sendMessage($conversation, $user, (string) $validated['message']);

        return response()->json([
            'message' => $this->chat->messagePayload($message),
        ], 201);
    }

    public function openDirect(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255'],
        ]);

        /** @var User|null $recipient */
        $recipient = User::query()
            ->where('username', (string) $validated['username'])
            ->whereNotNull('email_verified_at')
            ->first();

        if (! $recipient) {
            return response()->json([
                'message' => 'Player not found.',
                'errors' => ['username' => ['Player not found.']],
            ], 422);
        }

        $conversation = $this->chat->directConversation($user, $recipient);

        return response()->json([
            'conversation' => $this->chat->conversationPayload($conversation, $user),
        ], 201);
    }

    public function openTeam(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Team $team */
        $team = Team::query()->where('uuid', $uuid)->firstOrFail();
        $conversation = $this->chat->teamConversation($team, $user);

        return response()->json([
            'conversation' => $this->chat->conversationPayload($conversation, $user),
        ], 201);
    }

    public function joinTeam(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Team $team */
        $team = Team::query()
            ->where('uuid', $uuid)
            ->where('status', 'active')
            ->firstOrFail();

        $conversation = $this->chat->joinTeamConversation($team, $user);

        return response()->json([
            'conversation' => $this->chat->conversationPayload($conversation, $user),
        ], 201);
    }

    public function users(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));

        if (mb_strlen($search) < 2) {
            return response()->json(['users' => []]);
        }

        $users = User::query()
            ->whereNot('id', $user->getKey())
            ->whereNotNull('email_verified_at')
            ->where('username', 'like', '%'.$search.'%')
            ->orderBy('username')
            ->limit(8)
            ->get(['id', 'uuid', 'username'])
            ->map(fn (User $candidate): array => [
                'uuid' => $candidate->uuid,
                'username' => $candidate->username,
                'initials' => strtoupper(substr($candidate->username, 0, 2)),
            ])
            ->values();

        return response()->json(['users' => $users]);
    }

    public function playerProfile(Request $request, string $uuid): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        /** @var User $player */
        $player = User::query()
            ->with('profile')
            ->where('uuid', $uuid)
            ->whereNotNull('email_verified_at')
            ->firstOrFail();

        return response()->json([
            'player' => $this->playerPayload($player, $viewer),
        ]);
    }

    public function followPlayer(Request $request, string $uuid): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        /** @var User $player */
        $player = User::query()
            ->where('uuid', $uuid)
            ->whereNotNull('email_verified_at')
            ->firstOrFail();

        if ((int) $viewer->getKey() === (int) $player->getKey()) {
            return response()->json([
                'message' => 'You cannot follow yourself.',
                'errors' => ['player' => ['You cannot follow yourself.']],
            ], 422);
        }

        PlayerFollow::query()->firstOrCreate([
            'follower_user_id' => $viewer->getKey(),
            'followed_user_id' => $player->getKey(),
        ]);

        return response()->json([
            'player' => $this->playerPayload($player->fresh('profile') ?? $player, $viewer),
        ]);
    }

    private function findConversation(string $uuid, User $user): ChatConversation
    {
        /** @var ChatConversation $conversation */
        $conversation = ChatConversation::query()
            ->with(['participants.user', 'team'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        if (! $this->chat->canAccessConversation($conversation, $user)) {
            abort(403, 'You cannot access this chat.');
        }

        return $conversation;
    }

    /**
     * @return array<string, mixed>
     */
    private function playerPayload(User $player, User $viewer): array
    {
        $tournamentStats = $this->tournamentStats($player);
        $h2hStats = $this->headToHeadStats($player);
        $wins = $tournamentStats['wins'] + $h2hStats['wins'];
        $losses = $tournamentStats['losses'] + $h2hStats['losses'];
        $matches = $wins + $losses;
        $followers = PlayerFollow::query()->where('followed_user_id', $player->getKey())->count();
        $isFollowing = PlayerFollow::query()
            ->where('follower_user_id', $viewer->getKey())
            ->where('followed_user_id', $player->getKey())
            ->exists();

        $prizeTotal = LedgerEntry::query()
            ->join('wallets', 'ledger_entries.wallet_id', '=', 'wallets.id')
            ->where('wallets.user_id', $player->getKey())
            ->where('ledger_entries.type', LedgerType::PRIZE->value)
            ->sum('ledger_entries.amount');

        return [
            'uuid' => $player->uuid,
            'username' => $player->username,
            'display_name' => $player->profile?->display_name ?: $player->username,
            'bio' => $player->profile?->bio,
            'country_code' => $player->profile?->country_code,
            'initials' => strtoupper(substr($player->username, 0, 2)),
            'joined' => $player->created_at?->format('M Y'),
            'is_self' => (int) $viewer->getKey() === (int) $player->getKey(),
            'is_following' => $isFollowing,
            'followers' => $followers,
            'stats' => [
                'wins' => $wins,
                'losses' => $losses,
                'matches' => $matches,
                'win_rate' => $matches > 0 ? round(($wins / $matches) * 100).'%' : '0%',
                'tournament_wins' => $tournamentStats['wins'],
                'h2h_wins' => $h2hStats['wins'],
                'prize_total' => '$'.number_format((float) $prizeTotal, 2),
            ],
        ];
    }

    /**
     * @return array{wins:int,losses:int}
     */
    private function tournamentStats(User $player): array
    {
        $wins = 0;
        $losses = 0;

        GameMatch::query()
            ->whereIn('status', [MatchStatus::COMPLETED->value, MatchStatus::FORFEITED->value])
            ->where(function ($query) use ($player): void {
                $query->whereHas('playerARegistration', fn ($registration) => $registration->where('user_id', $player->getKey()))
                    ->orWhereHas('playerBRegistration', fn ($registration) => $registration->where('user_id', $player->getKey()));
            })
            ->with(['playerARegistration', 'playerBRegistration'])
            ->latest('updated_at')
            ->limit(500)
            ->get()
            ->each(function (GameMatch $match) use ($player, &$wins, &$losses): void {
                $registration = (int) $match->playerARegistration?->user_id === (int) $player->getKey()
                    ? $match->playerARegistration
                    : $match->playerBRegistration;

                if (! $registration) {
                    return;
                }

                if ((int) $registration->id === (int) $match->winner_registration_id) {
                    $wins++;

                    return;
                }

                $losses++;
            });

        return ['wins' => $wins, 'losses' => $losses];
    }

    /**
     * @return array{wins:int,losses:int}
     */
    private function headToHeadStats(User $player): array
    {
        $matches = HeadToHeadMatch::query()
            ->where('status', HeadToHeadMatchStatus::COMPLETED->value)
            ->where(function ($query) use ($player): void {
                $query->where('creator_user_id', $player->getKey())
                    ->orWhere('opponent_user_id', $player->getKey());
            })
            ->get();

        $wins = $matches->where('winner_user_id', $player->getKey())->count();
        $losses = $matches->count() - $wins;

        return ['wins' => $wins, 'losses' => $losses];
    }
}
