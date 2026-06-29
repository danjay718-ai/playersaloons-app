<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\CMS\Models\PolicyPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PolicyPageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $index => $page) {
            $policyPage = PolicyPage::query()
                ->withTrashed()
                ->firstOrNew(['slug' => $page['slug']]);

            if (! $policyPage->exists) {
                $policyPage->uuid = Str::uuid()->toString();
            }

            if ($policyPage->exists && $policyPage->trashed()) {
                $policyPage->restore();
            }

            $policyPage->fill(array_merge([
                'sort_order' => $index + 1,
                'is_active' => true,
                'published_at' => $policyPage->published_at ?? now(),
            ], $page));
            $policyPage->save();
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function pages(): array
    {
        return [
            [
                'slug' => 'terms-and-conditions',
                'title' => 'Terms and Conditions',
                'summary' => 'The rules for accessing and using PlayerSaloons services.',
                'content' => "By accessing or using PlayerSaloons, you agree to follow these Terms and Conditions, platform rules, tournament rules, fair-play requirements, and any additional instructions shown in the product.\n\nYou are responsible for maintaining accurate account information, protecting your login credentials, following eligibility requirements, and using the platform lawfully. You may not abuse tournament systems, manipulate results, submit false evidence, harass other users, attempt unauthorized access, or interfere with platform operations.\n\nTournament entries, head-to-head duels, wallet activity, disputes, and payout workflows are governed by the applicable rules shown in the platform and related policy pages. PlayerSaloons may review activity, restrict access, suspend accounts, reverse improper transactions, or take other action when needed to protect players, staff, or platform integrity.\n\nWe may update these Terms and Conditions as features, legal requirements, business operations, or compliance obligations change. Continued use of PlayerSaloons after updates means you accept the revised terms.",
            ],
            [
                'slug' => 'cookie-policy',
                'title' => 'Cookie Policy',
                'summary' => 'How PlayerSaloons uses cookies and similar browser storage.',
                'content' => "PlayerSaloons uses cookies and similar technologies to keep accounts secure, remember preferences, support platform analytics, and improve gameplay and tournament operations.\n\nEssential cookies are required for sign-in, session security, fraud prevention, and checkout reliability. Optional analytics or experience cookies may help us understand aggregate platform usage and improve public pages, tournament discovery, and wallet workflows.\n\nYou can control cookies through your browser settings. Blocking essential cookies may prevent account login, tournament registration, wallet checkout, or other core features from working correctly.\n\nWe may update this Cookie Policy as platform features, legal requirements, or third-party services change.",
            ],
            [
                'slug' => 'privacy-policy',
                'title' => 'Privacy Policy',
                'summary' => 'What information we collect, why we use it, and how account data is protected.',
                'content' => "PlayerSaloons collects account, profile, tournament, match, wallet, KYC, support, and device information needed to operate an esports competition platform.\n\nWe use this information to create accounts, verify identity where required, process tournament participation, resolve disputes, protect players from fraud or abuse, provide wallet and payout workflows, send platform notifications, and meet legal or compliance obligations.\n\nWe do not sell personal information. We share data only with service providers, payment processors, infrastructure vendors, compliance partners, or authorities when required to run the platform or comply with applicable law.\n\nPlayers can request account support or data correction through the platform support channels. Some records, including ledger entries, audit logs, dispute evidence, and compliance records, may need to be retained for security, finance, or legal reasons.",
            ],
            [
                'slug' => 'refund-and-cancellation-policy',
                'title' => 'Refund and Cancellation Policy',
                'summary' => 'How tournament cancellations, entry fees, H2H stakes, and wallet refunds are handled.',
                'content' => "Tournament entry fees and head-to-head stakes are handled through the PlayerSaloons ledger-backed wallet. Refund eligibility depends on tournament status, match state, dispute outcome, and platform rules.\n\nIf PlayerSaloons or an organizer cancels a tournament before completion, eligible paid registrations may be refunded to the player's wallet according to the tournament cancellation workflow. Completed tournaments, confirmed matches, forfeits, and resolved disputes are generally final unless an admin review determines otherwise.\n\nHead-to-head waiting challenges may be cancelled or expire before matching, in which case locked stakes are returned through the wallet. Active or disputed duels are reviewed according to the evidence and dispute process, and admins may award a winner or void and refund both stakes.\n\nExternal payment processor fees, payout provider fees, or bank charges may not be refundable unless required by law or explicitly stated by PlayerSaloons.",
            ],
            [
                'slug' => 'disclaimer',
                'title' => 'Disclaimer',
                'summary' => 'Important limits, player responsibility, and platform availability notes.',
                'content' => "PlayerSaloons provides esports tournament, head-to-head match, wallet, and competition management tools. Participation in any tournament, duel, or paid activity is at the player's own discretion and subject to platform rules.\n\nPlatform availability, tournament schedules, third-party payment processing, game publisher services, network connectivity, and player-submitted evidence may affect the experience. PlayerSaloons does not guarantee uninterrupted service, tournament outcomes, player earnings, or third-party platform availability.\n\nPlayers are responsible for following game rules, local laws, eligibility requirements, fair-play standards, and account security practices. Nothing on PlayerSaloons should be treated as financial, legal, tax, or professional advice.\n\nPolicy text may be updated as the product, operations, compliance obligations, or business rules evolve.",
            ],
        ];
    }

}
