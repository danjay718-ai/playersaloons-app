<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\GameCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    protected $model = Game::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = Str::slug($this->faker->unique()->words(2, true)).'-'.Str::lower(Str::random(4));

        return [
            'uuid' => Str::uuid()->toString(),
            'slug' => $slug,
            'game_category_id' => GameCategory::factory(),
            'is_active' => true,
        ];
    }

    public function forCategory(GameCategory|int $category): static
    {
        $categoryId = $category instanceof GameCategory ? $category->id : $category;

        return $this->state(fn (): array => ['game_category_id' => $categoryId]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
