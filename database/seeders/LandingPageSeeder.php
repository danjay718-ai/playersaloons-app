<?php

namespace Database\Seeders;

use App\Modules\CMS\Models\LandingSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LandingPageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->sections() as $sectionData) {
            $items = $sectionData['items'] ?? [];
            unset($sectionData['items']);

            $section = LandingSection::query()->updateOrCreate(
                ['key' => $sectionData['key']],
                array_merge(['uuid' => Str::uuid()->toString()], $sectionData)
            );

            foreach ($items as $index => $itemData) {
                $section->items()->updateOrCreate(
                    ['item_key' => $itemData['item_key'] ?? Str::slug((string) ($itemData['title'] ?? 'item-'.$index))],
                    array_merge([
                        'uuid' => Str::uuid()->toString(),
                        'sort_order' => $index + 1,
                        'is_active' => true,
                    ], $itemData)
                );
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sections(): array
    {
        return [
            [
                'key' => 'hero',
                'title' => 'PLAY. WIN. CASH OUT.',
                'subtitle' => 'Phase 1 MVP is Live',
                'body' => 'The ultimate battleground for competitive gamers. Join high-stakes tournaments, dominate the bracket, and secure instant payouts.',
                'media_path' => '/compressed_v1.mp4',
                'cta_label' => 'Explore Tournaments',
                'cta_url' => '/tournaments',
                'sort_order' => 1,
            ],
            [
                'key' => 'games',
                'title' => 'Available Games',
                'subtitle' => 'Choose your arena',
                'body' => 'Browse the active game catalog and enter tournaments built for serious competitive play.',
                'sort_order' => 2,
            ],
            [
                'key' => 'how_it_works',
                'title' => 'How It Works',
                'subtitle' => 'From account to payout',
                'body' => 'A short path from signup to competition.',
                'sort_order' => 3,
                'items' => [
                    ['item_key' => 'create-account', 'title' => 'Create Account', 'body' => 'Register, verify your profile, and prepare your player wallet.', 'icon' => 'user-plus'],
                    ['item_key' => 'join-event', 'title' => 'Join A Match', 'body' => 'Enter tournaments or accept head-to-head duels for your selected game.', 'icon' => 'gamepad-2'],
                    ['item_key' => 'submit-results', 'title' => 'Submit Results', 'body' => 'Upload proof, confirm outcomes, and let the bracket advance.', 'icon' => 'upload-cloud'],
                    ['item_key' => 'cash-out', 'title' => 'Cash Out', 'body' => 'Winning entries are credited through the ledger-backed wallet.', 'icon' => 'wallet'],
                ],
            ],
            [
                'key' => 'stats',
                'title' => 'Live Platform Stats',
                'subtitle' => 'Computed from real activity',
                'body' => 'Numbers update from matches, wallets, users, and games.',
                'sort_order' => 4,
                'items' => [
                    ['item_key' => 'matches_played', 'label' => 'Matches Played', 'icon' => 'swords'],
                    ['item_key' => 'winnings_paid', 'label' => 'Winnings Paid', 'icon' => 'badge-dollar-sign'],
                    ['item_key' => 'active_players', 'label' => 'Active Players', 'icon' => 'users'],
                    ['item_key' => 'active_games', 'label' => 'Active Games', 'icon' => 'trophy'],
                ],
            ],
            [
                'key' => 'top_players',
                'title' => 'Top Players This Week',
                'subtitle' => 'Leaderboard spotlight',
                'body' => 'Top performers based on completed tournament matches and weekly prize activity.',
                'sort_order' => 5,
            ],
            [
                'key' => 'features',
                'title' => 'Built For Competitive Play',
                'subtitle' => 'Core platform features',
                'body' => 'The tools players need to compete, track progress, and get paid.',
                'sort_order' => 6,
                'items' => [
                    ['item_key' => 'tournaments', 'title' => 'Tournaments', 'body' => 'Join scheduled events with brackets, check-ins, match flow, and automated advancement.', 'icon' => 'trophy', 'url' => '/tournaments'],
                    ['item_key' => 'head-to-head', 'title' => 'Head-to-Head Matches', 'body' => 'Create or accept direct duels with stake locking, proof uploads, and dispute review.', 'icon' => 'swords', 'url' => '/head-to-head'],
                    ['item_key' => 'leaderboards', 'title' => 'Leaderboards', 'body' => 'Track wins, losses, win rate, and cash performance across the platform.', 'icon' => 'award', 'url' => '/leaderboards'],
                    ['item_key' => 'wallet', 'title' => 'Player Wallet', 'body' => 'Deposits, prizes, refunds, and payouts flow through an auditable ledger.', 'icon' => 'wallet', 'url' => '/wallet'],
                ],
            ],
            [
                'key' => 'reviews',
                'title' => 'Player Reviews',
                'subtitle' => 'Community signal',
                'body' => 'Editable testimonials from players and organizers.',
                'sort_order' => 7,
                'items' => [
                    ['item_key' => 'review-1', 'title' => 'Bracket flow feels fast.', 'subtitle' => 'Tournament Player', 'body' => 'The match pages make it clear where to go next after every result.', 'icon' => 'quote'],
                    ['item_key' => 'review-2', 'title' => 'H2H makes casual nights competitive.', 'subtitle' => 'Duel Player', 'body' => 'Stake locking and proof upload make direct matches feel structured.', 'icon' => 'quote'],
                    ['item_key' => 'review-3', 'title' => 'Admin review is straightforward.', 'subtitle' => 'Organizer', 'body' => 'Disputes, KYC, and tournament operations are easy to monitor.', 'icon' => 'quote'],
                ],
            ],
            [
                'key' => 'footer',
                'title' => 'PlayerSaloons',
                'body' => 'ALL RIGHTS RESERVED. OPERATED BY PLAYERSALOONS SYSTEMS.',
                'sort_order' => 8,
                'items' => [
                    ['item_key' => 'terms', 'label' => 'Terms', 'url' => '/policies/terms-and-conditions'],
                    ['item_key' => 'cookies', 'label' => 'Cookies', 'url' => '/policies/cookie-policy'],
                    ['item_key' => 'privacy', 'label' => 'Privacy', 'url' => '/policies/privacy-policy'],
                    ['item_key' => 'refunds', 'label' => 'Refunds', 'url' => '/policies/refund-and-cancellation-policy'],
                    ['item_key' => 'disclaimer', 'label' => 'Disclaimer', 'url' => '/policies/disclaimer'],
                ],
            ],
        ];
    }
}
