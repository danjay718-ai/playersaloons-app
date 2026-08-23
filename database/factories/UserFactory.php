<?php

namespace Database\Factories;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'status' => 'active',
        ];
    }

    /**
     * Configure the factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            try {
                if ($user->roles()->count() === 0) {
                    $role = \Spatie\Permission\Models\Role::query()->firstOrCreate([
                        'name' => 'PLAYER',
                        'guard_name' => 'web',
                    ]);
                    $user->assignRole($role);
                }
            } catch (\Throwable) {
                // Ignore if roles table is not yet migrated/available in isolated environments
            }
        });
    }

    /**
     * Indicate that the user has a specific role.
     */
    public function withRole(string $role): static
    {
        return $this->afterCreating(function (User $user) use ($role) {
            try {
                $r = \Spatie\Permission\Models\Role::query()->firstOrCreate([
                    'name' => $role,
                    'guard_name' => 'web',
                ]);
                $user->syncRoles([$r]);
            } catch (\Throwable) {
                // Ignore if roles table is not yet migrated/available
            }
        });
    }

    /**
     * Indicate that the user is an admin.
     */
    public function admin(): static
    {
        return $this->withRole('ADMIN');
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
