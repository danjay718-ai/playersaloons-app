<?php

declare(strict_types=1);

namespace App\Livewire\Identity;

use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserTheme;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

final class ThemeSwitcher extends Component
{
    public string $theme = UserTheme::PURPLE_DARK->value;

    public string $variant = 'compact';

    public function mount(string $variant = 'compact'): void
    {
        $this->variant = in_array($variant, ['compact', 'cards'], true) ? $variant : 'compact';
        $this->theme = auth()->user()?->theme?->value ?? UserTheme::PURPLE_DARK->value;
    }

    public function setTheme(string $theme): void
    {
        $selected = UserTheme::tryFrom($theme);
        if ($selected === null) {
            $this->addError('theme', 'Select a valid theme.');

            return;
        }

        /** @var User|null $user */
        $user = auth()->user();
        abort_if($user === null, 401);

        $user->forceFill(['theme' => $selected])->save();
        $this->theme = $selected->value;
        $this->resetErrorBag('theme');
        $this->dispatch('theme-preference-updated', theme: $selected->value, metaColor: $selected->metaColor());
    }

    #[On('theme-preference-updated')]
    public function synchronizeTheme(string $theme, ?string $metaColor = null): void
    {
        if (UserTheme::tryFrom($theme) !== null) {
            $this->theme = $theme;
        }
    }

    public function render(): View
    {
        return view('livewire.identity.theme-switcher', [
            'themes' => UserTheme::cases(),
        ]);
    }
}
