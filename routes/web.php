<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\GameTournamentDefaultController;
use App\Http\Controllers\Admin\KycDocumentController;
use App\Http\Controllers\Admin\V2TournamentOccurrenceController;
use App\Http\Controllers\Admin\V2TournamentTemplateController;
use App\Http\Controllers\Admin\V2TournamentTemplateSlotsController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Community\ChatController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\NewsletterUnsubscribeController;
use App\Http\Controllers\PromotionClickController;
use App\Http\Controllers\StripeWebhookController;
use App\Livewire\AboutPage;
use App\Livewire\Admin\AdminDashboard;
use App\Livewire\Admin\AdminProfile;
use App\Livewire\Admin\AdvertisementAdmin;
use App\Livewire\Admin\AuditLogAdmin;
use App\Livewire\Admin\BlockedCountriesAdmin;
use App\Livewire\Admin\BroadcastNotificationAdmin;
use App\Livewire\Admin\CmsAdmin;
use App\Livewire\Admin\CmsContentAdmin;
use App\Livewire\Admin\ComplianceAdmin;
use App\Livewire\Admin\ContactInquiryAdmin;
use App\Livewire\Admin\ErrorIncidentAdmin;
use App\Livewire\Admin\KycAdmin;
use App\Livewire\Admin\MatchAdmin;
use App\Livewire\Admin\NewsletterAdmin;
use App\Livewire\Admin\PlayerReviewAdmin;
use App\Livewire\Admin\PolicyAdmin;
use App\Livewire\Admin\RolePermissionAdmin;
use App\Livewire\Admin\StaffActivityDashboard;
use App\Livewire\Admin\SystemSettingsAdmin;
use App\Livewire\Admin\TournamentAdmin;
use App\Livewire\Admin\TournamentForm;
use App\Livewire\Admin\TournamentMatches;
use App\Livewire\Admin\TranslationAdmin;
use App\Livewire\Admin\UserAdmin;
use App\Livewire\Admin\WithdrawalAdmin;
use App\Livewire\Auth\EmailVerification;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\PasswordReset;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Livewire\CMS\BlogArticleView;
use App\Livewire\CMS\BlogIndex;
use App\Livewire\CMS\ContentHub;
use App\Livewire\CMS\NewsArticleView;
use App\Livewire\CMS\NewsIndex;
use App\Livewire\Community\ContactPage;
use App\Livewire\Community\GlobalChat;
use App\Livewire\Community\PlayerReviewPage;
use App\Livewire\Dashboard\PlayerDashboard;
use App\Livewire\Game\GameShow;
use App\Livewire\Landing\LandingPage;
use App\Livewire\Match\HeadToHeadList;
use App\Livewire\Match\LeaderboardList;
use App\Livewire\Match\MatchDetail;
use App\Livewire\Policies\PolicyIndex;
use App\Livewire\Policies\PolicyPageView;
use App\Livewire\Profile\ProfileDashboard;
use App\Livewire\Stream\StreamList;
use App\Livewire\Stream\StreamWatch;
use App\Livewire\Team\TeamDashboard;
use App\Livewire\Tournament\MyTournamentsList;
use App\Livewire\Tournament\PlayerTournamentList;
use App\Livewire\Tournament\PublicTournamentList;
use App\Livewire\Tournament\TournamentDetail;
use App\Livewire\Wallet\WalletDashboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ──────────────────────────────────────────────────────────────────────────────
// Public routes — accessible without authentication
// ──────────────────────────────────────────────────────────────────────────────

Route::get('/', LandingPage::class);
Route::get('/about', AboutPage::class)->name('about');
Route::get('/tournaments', PublicTournamentList::class);
Route::get('/games/{game:slug}', GameShow::class)->name('games.show');
Route::get('/streams/{id}', StreamWatch::class)->name('streams.watch');
Route::get('/tournaments/{uuid}/view', TournamentDetail::class)->name('tournaments.view');
Route::get('/blog', BlogIndex::class)->name('blog.index');
Route::get('/blog/{slug}', BlogArticleView::class)->name('blog.show');
Route::get('/news', NewsIndex::class)->name('news.index');
Route::get('/news/{slug}', NewsArticleView::class)->name('news.show');
Route::get('/updates', ContentHub::class)->name('updates.index');
Route::get('/policies', PolicyIndex::class)->name('policies.index');
Route::get('/policies/{slug}', PolicyPageView::class)->name('policies.show');
Route::get('/contact', ContactPage::class)->name('contact');

Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');
Route::post('/language', [LanguageController::class, 'update'])->name('language.update');
Route::get('/promotions/{advertisement:uuid}/click', PromotionClickController::class)->name('promotions.click');
Route::get('/newsletter/unsubscribe/{user:uuid}', NewsletterUnsubscribeController::class)
    ->middleware('signed')
    ->name('newsletter.unsubscribe');

// ──────────────────────────────────────────────────────────────────────────────
// Guest-only routes (redirected away if already authenticated)
// ──────────────────────────────────────────────────────────────────────────────

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
    Route::get('/reset-password', PasswordReset::class)->name('password.request');
    Route::get('/reset-password/{token}', PasswordReset::class)->name('password.reset');
    Route::get('/two-factor-challenge', TwoFactorChallenge::class)->name('two-factor.challenge');
});

// ──────────────────────────────────────────────────────────────────────────────
// Authenticated routes (any logged-in user)
// ──────────────────────────────────────────────────────────────────────────────

Route::middleware('auth')->group(function () {
    Route::get('/verify-email', EmailVerification::class)->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', EmailVerificationController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/logout', LogoutController::class)->name('logout');

    Route::get('/account-restricted', function (Request $request) {
        $block = $request->user()?->complianceBlocks()->active()->latest('created_at')->firstOrFail();

        return view('account-restricted', compact('block'));
    })->name('account.restricted');

    // ── Player-only routes ──────────────────────────────────────────────
    // Verification is enforced for withdrawals and email notifications, not general access.
    Route::middleware(['compliance.clear', 'role.player'])->group(function () {
        Route::get('/dashboard', PlayerDashboard::class)->name('dashboard');
        Route::get('/my-tournaments', MyTournamentsList::class)->name('my-tournaments');
        Route::get('/tournaments/browse', PlayerTournamentList::class)->name('tournaments.browse');
        if (config('features.player_wager.enabled')) {
            Route::get('/head-to-head', HeadToHeadList::class)->name('head-to-head');
        }
        Route::get('/leaderboards', LeaderboardList::class)->name('leaderboards');
        Route::get('/streams', StreamList::class)->name('streams');
        Route::get('/chat', GlobalChat::class)->name('chat');
        Route::get('/chat/api/conversations', [ChatController::class, 'conversations'])->name('chat.conversations');
        Route::get('/chat/api/conversations/{uuid}/messages', [ChatController::class, 'messages'])->name('chat.messages');
        Route::post('/chat/api/conversations/{uuid}/messages', [ChatController::class, 'send'])->name('chat.messages.send');
        Route::post('/chat/api/direct', [ChatController::class, 'openDirect'])->name('chat.direct.open');
        Route::post('/chat/api/teams/{uuid}', [ChatController::class, 'openTeam'])->name('chat.teams.open');
        Route::post('/chat/api/teams/{uuid}/join', [ChatController::class, 'joinTeam'])->name('chat.teams.join');
        Route::get('/chat/api/users', [ChatController::class, 'users'])->name('chat.users');
        Route::get('/chat/api/players/{uuid}', [ChatController::class, 'playerProfile'])->name('chat.players.show');
        Route::post('/chat/api/players/{uuid}/follow', [ChatController::class, 'followPlayer'])->name('chat.players.follow');
        Route::get('/matches/{uuid}', MatchDetail::class);

        Route::get('/wallet', WalletDashboard::class)->name('wallet');
        Route::get('/profile', ProfileDashboard::class);
        Route::get('/reviews', PlayerReviewPage::class)->name('reviews');
        Route::get('/teams', TeamDashboard::class);
    });
});

// ──────────────────────────────────────────────────────────────────────────────
// Admin Control Panel — requires authentication + an admin role.
// The EnsureAdminRole middleware provides defense-in-depth; each Livewire
// component also checks its own fine-grained role via AdminComponent::boot().
// ──────────────────────────────────────────────────────────────────────────────

Route::middleware(['auth', 'role.admin'])->prefix('admin')->group(function () {
    Route::get('/', AdminDashboard::class);
    Route::get('/profile', AdminProfile::class);
    Route::get('/tournaments', TournamentAdmin::class)->name('admin.tournaments');
    Route::get('/tournaments/create', TournamentForm::class)->name('admin.tournaments.create');
    Route::get('/tournaments/v2/create', [V2TournamentTemplateController::class, 'create'])->name('admin.tournaments.v2.create');
    Route::post('/tournaments/v2', [V2TournamentTemplateController::class, 'store'])->name('admin.tournaments.v2.store');
    Route::get('/tournaments/v2/templates/{template}/slots', [V2TournamentTemplateSlotsController::class, 'show'])->name('admin.tournaments.v2.templates.slots');
    Route::get('/tournaments/v2/templates/{template}/slots/create', [V2TournamentTemplateSlotsController::class, 'create'])->name('admin.tournaments.v2.templates.slots.create');
    Route::post('/tournaments/v2/templates/{template}/slots', [V2TournamentTemplateSlotsController::class, 'store'])->name('admin.tournaments.v2.templates.slots.store');
    Route::get('/tournaments/v2/occurrences/{tournament}/edit', [V2TournamentOccurrenceController::class, 'edit'])->name('admin.tournaments.v2.occurrences.edit');
    Route::get('/tournaments/v2/occurrences/{tournament}', [V2TournamentOccurrenceController::class, 'show'])->name('admin.tournaments.v2.occurrences.show');
    Route::put('/tournaments/v2/occurrences/{tournament}', [V2TournamentOccurrenceController::class, 'update'])->name('admin.tournaments.v2.occurrences.update');
    Route::get('/games/{game}/tournament-defaults', [GameTournamentDefaultController::class, 'edit'])->name('admin.games.tournament-defaults.edit');
    Route::put('/games/{game}/tournament-defaults', [GameTournamentDefaultController::class, 'update'])->name('admin.games.tournament-defaults.update');
    Route::get('/tournaments/{id}/edit', TournamentForm::class)->name('admin.tournaments.edit');
    Route::get('/tournaments/{id}/matches', TournamentMatches::class)->name('admin.tournaments.matches');
    Route::get('/matches', MatchAdmin::class);
    Route::get('/streams', StreamList::class)->name('admin.streams');
    Route::get('/streams/{id}', StreamWatch::class)->name('admin.streams.watch');
    Route::get('/kyc', KycAdmin::class);
    Route::get('/kyc/document/{path}', KycDocumentController::class)->where('path', '.*')->name('admin.kyc.document');
    Route::get('/withdrawals', WithdrawalAdmin::class);
    Route::get('/users', UserAdmin::class);
    Route::get('/roles-permissions', RolePermissionAdmin::class)->name('admin.roles-permissions');
    Route::get('/compliance', ComplianceAdmin::class)->name('admin.compliance');
    Route::get('/audit-logs', AuditLogAdmin::class);
    Route::get('/error-logs', ErrorIncidentAdmin::class)->name('admin.error-logs');
    Route::get('/cms/content', CmsContentAdmin::class)->name('admin.cms.content');
    Route::get('/cms/{section?}', CmsAdmin::class)
        ->whereIn('section', ['landing', 'games', 'platforms', 'navigation', 'about'])
        ->name('admin.cms');
    Route::get('/translations', TranslationAdmin::class)->name('admin.translations');
    Route::get('/policies', PolicyAdmin::class);
    Route::get('/notifications', BroadcastNotificationAdmin::class)->name('admin.notifications');
    Route::get('/contact-inquiries', ContactInquiryAdmin::class)->name('admin.contact-inquiries');
    Route::get('/newsletters', NewsletterAdmin::class)->name('admin.newsletters');
    Route::get('/system-settings', SystemSettingsAdmin::class)->name('admin.system-settings');
    Route::get('/geo-blocking', BlockedCountriesAdmin::class)->name('admin.system.geoblocking');
    Route::get('/advertisements', AdvertisementAdmin::class)->name('admin.advertisements');
    Route::get('/player-reviews', PlayerReviewAdmin::class)->name('admin.player-reviews');
    Route::get('/staff-activity', StaffActivityDashboard::class)->name('admin.staff-activity');
});
