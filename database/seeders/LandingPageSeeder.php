<?php

namespace Database\Seeders;

use App\Modules\CMS\Models\LandingSection;
use App\Modules\CMS\Models\PolicyPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LandingPageSeeder extends Seeder
{
    public function run(): void
    {
        // Startup seeds supply missing defaults; existing CMS content belongs to admins.
        foreach ($this->sections() as $sectionData) {
            $items = $sectionData['items'] ?? [];
            unset($sectionData['items']);

            $section = LandingSection::query()
                ->withTrashed()
                ->firstOrNew(['key' => $sectionData['key']]);

            if (! $section->exists) {
                $section->uuid = Str::uuid()->toString();
                $section->fill($sectionData)->save();
            }
            if ($section->trashed()) {
                continue;
            }

            foreach ($items as $index => $itemData) {
                $itemKey = $itemData['item_key'] ?? Str::slug((string) ($itemData['title'] ?? 'item-'.$index));
                $item = $section->items()
                    ->withTrashed()
                    ->firstOrNew(['item_key' => $itemKey]);

                if ($item->exists) {
                    continue;
                }
                $item->uuid = Str::uuid()->toString();

                $item->fill(array_merge([
                    'item_key' => $itemKey,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ], $itemData));
                $item->save();
            }
        }

        $this->seedMissingPolicyFooterLinks();
    }

    private function seedMissingPolicyFooterLinks(): void
    {
        $footer = LandingSection::query()->where('key', 'footer')->first();
        if (! $footer) {
            return;
        }

        $policyPages = PolicyPage::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        foreach ($policyPages as $index => $policyPage) {
            $itemKey = $this->footerItemKey($policyPage->slug);
            $item = $footer->items()
                ->withTrashed()
                ->firstOrNew(['item_key' => $itemKey]);

            if ($item->exists) {
                continue;
            }
            $item->uuid = Str::uuid()->toString();

            $item->fill([
                'item_key' => $itemKey,
                'label' => $this->footerLabel($policyPage->slug, $policyPage->title),
                'url' => route('policies.show', ['slug' => $policyPage->slug], false),
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);
            $item->save();
        }

    }

    private function footerItemKey(string $slug): string
    {
        return match ($slug) {
            'terms-and-conditions' => 'terms',
            'cookie-policy' => 'cookies',
            'privacy-policy' => 'privacy',
            'refund-and-cancellation-policy' => 'refunds',
            default => Str::slug($slug),
        };
    }

    private function footerLabel(string $slug, string $title): string
    {
        return match ($slug) {
            'terms-and-conditions' => 'Terms',
            'cookie-policy' => 'Cookies',
            'privacy-policy' => 'Privacy',
            'refund-and-cancellation-policy' => 'Refunds',
            default => $title,
        };
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
                'subtitle' => 'Competitive gaming for cash',
                'body' => 'The ultimate battleground for competitive gamers. Join high-stakes tournaments, dominate the bracket, and secure instant payouts.',
                'media_path' => '/compressed_v1.mp4',
                'cta_label' => 'Explore Tournaments',
                'cta_url' => '/tournaments',
                'sort_order' => 1,
                'items' => [
                    ['item_key' => 'primary-action', 'title' => 'Create Account', 'url' => '/register'],
                    ['item_key' => 'rotate-dominate', 'title' => 'DOMINATE.'],
                    ['item_key' => 'rotate-paid', 'title' => 'GET PAID.'],
                    ['item_key' => 'rotate-rank', 'title' => 'RANK UP.'],
                ],
            ],
            [
                'key' => 'games',
                'title' => 'Available Games',
                'subtitle' => 'Choose your arena',
                'body' => 'Tap a table to see what starts next.',
                'cta_label' => 'Browse Tournaments',
                'cta_url' => '/tournaments',
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
                'items' => [],
            ],
            [
                'key' => 'faq',
                'title' => 'Frequently Asked Questions',
                'subtitle' => 'Got questions? Everything you need to know about competing.',
                'cta_label' => 'Knowledge Base',
                'sort_order' => 9,
                'items' => [
                    [
                        'title' => 'How do tournament payouts work?',
                        'body' => 'Prizes are automatically credited to your player wallet as soon as the bracket concludes or match results are confirmed. You can withdraw your earnings anytime via supported withdrawal methods.',
                    ],
                    [
                        'title' => 'How do I submit match results?',
                        'body' => 'After playing your match against your opponent, navigate to the tournament match room or the Submit Result tab in the tournament details, select Won, Lost, or Draw, and attach your match screenshot proof.',
                    ],
                    [
                        'title' => 'What happens if there is a score dispute?',
                        'body' => 'If both players report conflicting scores, our automated system holds the match and flags it for staff review. Admins inspect uploaded screenshots and game server stats to resolve disputes fairly.',
                    ],
                    [
                        'title' => 'Can I cancel my registration before the tournament starts?',
                        'body' => 'Yes. You can cancel your tournament entry before the scheduled start time directly from the tournament page. Eligible refunds are credited directly back to your wallet.',
                    ],
                    [
                        'title' => 'Are tournaments free to enter or paid?',
                        'body' => 'We offer both Free-to-Play daily tournaments with real prize pools and premium entry-fee tournaments for high-stakes competition.',
                    ],
                ],
            ],
            [
                'key' => 'activity',
                'title' => 'Recent public results',
                'body' => 'The next win could be yours. Confirmed player wins appear here.',
                'sort_order' => 13,
            ],
            [
                'key' => 'cta',
                'title' => 'Your seat is open.',
                'body' => 'Join the next bracket in under a minute.',
                'cta_label' => 'Join Now',
                'cta_url' => '/register',
                'sort_order' => 10,
            ],
            [
                'key' => 'competition_empty',
                'title' => 'Your next competition starts here.',
                'subtitle' => 'Next seat',
                'body' => 'New tournaments will appear when registration opens. Explore the games while you wait.',
                'cta_label' => 'Browse Tournaments',
                'cta_url' => '/tournaments',
                'sort_order' => 11,
            ],
            [
                'key' => 'leaderboard_empty',
                'title' => 'The next name could be yours.',
                'body' => 'No weekly leaderboard results yet. Completed tournament matches will shape this board.',
                'cta_label' => 'Find a tournament',
                'cta_url' => '/tournaments',
                'sort_order' => 12,
            ],
            [
                'key' => 'footer',
                'title' => 'GamersRival',
                'body' => 'ALL RIGHTS RESERVED. OPERATED BY GAMERSRIVAL SYSTEMS.',
                'sort_order' => 8,
            ],
        ];
    }
}
