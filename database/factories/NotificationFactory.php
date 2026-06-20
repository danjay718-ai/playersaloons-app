<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Community\Models\Notification;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement(['tournament', 'wallet', 'match', 'kyc']),
            'title' => $this->faker->sentence(4),
            'message' => $this->faker->sentence(10),
            'read_at' => null,
        ];
    }
}
