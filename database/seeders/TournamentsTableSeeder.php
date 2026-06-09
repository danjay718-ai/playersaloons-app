<?php

namespace Database\Seeders;

use App\Modules\CMS\Models\Game;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Models\Bracket;
use App\Modules\Tournament\Models\Round;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Shared\Enums\LedgerType;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\PaymentStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TournamentsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create a default player user if not exists
        $playerUser = User::query()->where('email', 'player@playersaloons.com')->first();
        if (!$playerUser) {
            $playerUser = User::query()->create([
                'uuid' => (string) Str::uuid(),
                'email' => 'player@playersaloons.com',
                'username' => 'GamerPro',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $playerUser->assignRole('PLAYER');
        }

        // Ensure player user has a wallet
        $playerWallet = DB::table('wallets')->where('user_id', $playerUser->id)->first();
        if (!$playerWallet) {
            $walletId = DB::table('wallets')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'user_id' => $playerUser->id,
                'cached_balance' => 250.00,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $walletId = $playerWallet->id;
            DB::table('wallets')->where('id', $walletId)->update(['cached_balance' => 250.00]);
        }

        // Create some opponent users
        $opponents = [];
        $opponentData = [
            ['email' => 'opp1@playersaloons.com', 'username' => 'ShadowViper'],
            ['email' => 'opp2@playersaloons.com', 'username' => 'NeonPulse'],
            ['email' => 'opp3@playersaloons.com', 'username' => 'CyberStreak'],
            ['email' => 'opp4@playersaloons.com', 'username' => 'ChronoZero'],
        ];

        foreach ($opponentData as $data) {
            $opp = User::query()->where('email', $data['email'])->first();
            if (!$opp) {
                $opp = User::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'email' => $data['email'],
                    'username' => $data['username'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $opp->assignRole('PLAYER');
            }
            // Ensure wallet
            $wallet = DB::table('wallets')->where('user_id', $opp->id)->first();
            if (!$wallet) {
                DB::table('wallets')->insert([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $opp->id,
                    'cached_balance' => 100.00,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $opponents[] = $opp;
        }

        // 2. Fetch games
        $valorant = Game::query()->where('slug', 'valorant')->first();
        $dota2 = Game::query()->where('slug', 'dota-2')->first();
        $pubg = Game::query()->where('slug', 'pubg-mobile')->first();
        $cod = Game::query()->where('slug', 'call-of-duty-mobile')->first();
        $mlbb = Game::query()->where('slug', 'mobile-legends-bang-bang')->first();

        // 3. Define tournament data with image banners and frequencies
        $tournamentsData = [
            [
                'name' => 'Valorant Daily Cup',
                'slug' => 'valorant-daily-cup',
                'game_id' => $valorant->id,
                'status' => TournamentStatus::REGISTRATION_OPEN,
                'frequency' => 'daily',
                'entry_fee' => 0.00,
                'prize_pool' => 100.00,
                'max_participants' => 64,
                'min_participants' => 8,
                'banner_url' => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?q=80&w=600&auto=format&fit=crop',
            ],
            [
                'name' => 'Dota 2 Weekly Clash',
                'slug' => 'dota-2-weekly-clash',
                'game_id' => $dota2->id,
                'status' => TournamentStatus::REGISTRATION_OPEN,
                'frequency' => 'weekly',
                'entry_fee' => 5.00,
                'prize_pool' => 500.00,
                'max_participants' => 32,
                'min_participants' => 8,
                'banner_url' => 'https://images.unsplash.com/photo-1511512578047-dfb367046420?q=80&w=600&auto=format&fit=crop',
            ],
            [
                'name' => 'PUBG Mobile Monthly Championship',
                'slug' => 'pubg-mobile-monthly-championship',
                'game_id' => $pubg->id,
                'status' => TournamentStatus::ONGOING,
                'frequency' => 'monthly',
                'entry_fee' => 10.00,
                'prize_pool' => 2000.00,
                'max_participants' => 128,
                'min_participants' => 16,
                'banner_url' => 'https://images.unsplash.com/photo-1538481199705-c710c4e965fc?q=80&w=600&auto=format&fit=crop',
            ],
            [
                'name' => 'Call of Duty Daily Duel',
                'slug' => 'call-of-duty-daily-duel',
                'game_id' => $cod->id,
                'status' => TournamentStatus::COMPLETED,
                'frequency' => 'daily',
                'entry_fee' => 0.00,
                'prize_pool' => 50.00,
                'max_participants' => 16,
                'min_participants' => 4,
                'banner_url' => 'https://images.unsplash.com/photo-1552820728-8b83bb6b773f?q=80&w=600&auto=format&fit=crop',
            ],
            [
                'name' => 'Mobile Legends Weekly Showdown',
                'slug' => 'mobile-legends-weekly-showdown',
                'game_id' => $mlbb->id,
                'status' => TournamentStatus::COMPLETED,
                'frequency' => 'weekly',
                'entry_fee' => 2.00,
                'prize_pool' => 250.00,
                'max_participants' => 32,
                'min_participants' => 8,
                'banner_url' => 'https://images.unsplash.com/photo-1560253023-3ec5d502959f?q=80&w=600&auto=format&fit=crop',
            ],
            [
                'name' => 'Valorant Masters Monthly',
                'slug' => 'valorant-masters-monthly',
                'game_id' => $valorant->id,
                'status' => TournamentStatus::REGISTRATION_OPEN,
                'frequency' => 'monthly',
                'entry_fee' => 15.00,
                'prize_pool' => 5000.00,
                'max_participants' => 64,
                'min_participants' => 16,
                'banner_url' => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?q=80&w=600&auto=format&fit=crop',
            ],
            [
                'name' => 'Dota 2 Daily Brawl',
                'slug' => 'dota-2-daily-brawl',
                'game_id' => $dota2->id,
                'status' => TournamentStatus::CANCELLED,
                'frequency' => 'daily',
                'entry_fee' => 0.00,
                'prize_pool' => 30.00,
                'max_participants' => 16,
                'min_participants' => 4,
                'banner_url' => 'https://images.unsplash.com/photo-1511512578047-dfb367046420?q=80&w=600&auto=format&fit=crop',
            ],
            [
                'name' => 'COD Mobile Weekly League',
                'slug' => 'cod-mobile-weekly-league',
                'game_id' => $cod->id,
                'status' => TournamentStatus::REGISTRATION_CLOSED,
                'frequency' => 'weekly',
                'entry_fee' => 4.00,
                'prize_pool' => 300.00,
                'max_participants' => 32,
                'min_participants' => 8,
                'banner_url' => 'https://images.unsplash.com/photo-1552820728-8b83bb6b773f?q=80&w=600&auto=format&fit=crop',
            ]
        ];

        foreach ($tournamentsData as $tData) {
            // Check if tournament exists
            $t = Tournament::query()->where('slug', $tData['slug'])->first();
            if ($t) {
                $t->update([
                    'status' => $tData['status'],
                    'frequency' => $tData['frequency'],
                    'banner_url' => $tData['banner_url'],
                    'entry_fee' => $tData['entry_fee'],
                    'prize_pool' => $tData['prize_pool'],
                ]);
            } else {
                $t = Tournament::query()->create(array_merge($tData, [
                    'uuid' => (string) Str::uuid(),
                    'registration_open_at' => now()->subDays(2),
                    'registration_close_at' => now()->addDays(5),
                    'checkin_open_at' => now()->addDays(5),
                    'checkin_close_at' => now()->addDays(5)->addHour(),
                    'start_at' => now()->addDays(5)->addHours(2),
                    'created_by' => 1,
                ]));
            }

            // Register default player in specific tournaments to build their history
            if (in_array($tData['slug'], ['valorant-daily-cup', 'pubg-mobile-monthly-championship', 'call-of-duty-daily-duel', 'mobile-legends-weekly-showdown'])) {
                // Ensure registration exists for player
                $reg = TournamentRegistration::query()
                    ->where('tournament_id', $t->id)
                    ->where('user_id', $playerUser->id)
                    ->first();

                if (!$reg) {
                    $reg = TournamentRegistration::query()->create([
                        'uuid' => (string) Str::uuid(),
                        'tournament_id' => $t->id,
                        'user_id' => $playerUser->id,
                        'status' => RegistrationStatus::CONFIRMED,
                        'payment_status' => $t->entry_fee > 0 ? PaymentStatus::PAID : PaymentStatus::FREE,
                        'registered_at' => now()->subDays(1),
                    ]);
                }

                // Register opponents to create matches
                foreach ($opponents as $index => $opp) {
                    $oppReg = TournamentRegistration::query()
                        ->where('tournament_id', $t->id)
                        ->where('user_id', $opp->id)
                        ->first();

                    if (!$oppReg) {
                        $oppReg = TournamentRegistration::query()->create([
                            'uuid' => (string) Str::uuid(),
                            'tournament_id' => $t->id,
                            'user_id' => $opp->id,
                            'status' => RegistrationStatus::CONFIRMED,
                            'payment_status' => $t->entry_fee > 0 ? PaymentStatus::PAID : PaymentStatus::FREE,
                            'registered_at' => now()->subDays(1),
                        ]);
                    }

                    // Create brackets/matches if it is completed or ongoing
                    if (in_array($t->status, [TournamentStatus::COMPLETED, TournamentStatus::ONGOING])) {
                        // Ensure bracket exists
                        $bracket = Bracket::query()->where('tournament_id', $t->id)->first();
                        if (!$bracket) {
                            $bracket = Bracket::query()->create([
                                'tournament_id' => $t->id,
                                'generated_at' => now()->subDays(1),
                                'created_at' => now()->subDays(1),
                            ]);
                        }

                        // Ensure round exists
                        $round = Round::query()->where('bracket_id', $bracket->id)->where('round_number', 1)->first();
                        if (!$round) {
                            $round = Round::query()->create([
                                'bracket_id' => $bracket->id,
                                'round_number' => 1,
                                'created_at' => now()->subDays(1),
                            ]);
                        }

                        // Ensure match exists
                        // To avoid duplicate matches, check if match already exists for this player and opponent
                        $match = GameMatch::query()
                            ->where('tournament_id', $t->id)
                            ->where('round_id', $round->id)
                            ->where(function ($query) use ($reg, $oppReg) {
                                $query->where('player_a_registration_id', $reg->id)
                                      ->orWhere('player_b_registration_id', $reg->id);
                            })
                            ->first();

                        if (!$match) {
                            // Seed result: User wins some and loses some
                            // COD: User wins
                            // MLBB: User loses
                            // PUBG: User wins
                            $winnerRegId = $reg->id;
                            if ($tData['slug'] === 'mobile-legends-weekly-showdown') {
                                $winnerRegId = $oppReg->id; // opponent wins
                            }

                            GameMatch::query()->create([
                                'uuid' => (string) Str::uuid(),
                                'tournament_id' => $t->id,
                                'round_id' => $round->id,
                                'player_a_registration_id' => $reg->id,
                                'player_b_registration_id' => $oppReg->id,
                                'winner_registration_id' => $winnerRegId,
                                'status' => MatchStatus::COMPLETED,
                                'scheduled_at' => now()->subHours(10),
                                'started_at' => now()->subHours(10),
                                'completed_at' => now()->subHours(9),
                            ]);
                        }
                    }
                }
            }
        }

        // 4. Seed some LedgerEntries (Earnings) for the default player to show stats
        $prizeEntries = DB::table('ledger_entries')
            ->where('wallet_id', $walletId)
            ->where('type', LedgerType::PRIZE->value)
            ->get();

        if ($prizeEntries->isEmpty()) {
            DB::table('ledger_entries')->insert([
                [
                    'uuid' => (string) Str::uuid(),
                    'wallet_id' => $walletId,
                    'reference_type' => Tournament::class,
                    'reference_id' => 4, // Call of Duty Daily Duel
                    'type' => LedgerType::PRIZE->value,
                    'amount' => 50.00,
                    'running_balance' => 300.00,
                    'description' => '1st Place Prize - Call of Duty Daily Duel',
                    'created_at' => now()->subDays(1),
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'wallet_id' => $walletId,
                    'reference_type' => Tournament::class,
                    'reference_id' => 3, // PUBG Mobile Monthly
                    'type' => LedgerType::PRIZE->value,
                    'amount' => 100.00,
                    'running_balance' => 400.00,
                    'description' => 'Stage 1 Win - PUBG Mobile Monthly Championship',
                    'created_at' => now()->subHours(2),
                ]
            ]);
        }
    }
}
