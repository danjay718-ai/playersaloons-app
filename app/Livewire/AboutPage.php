<?php

declare(strict_types=1);

namespace App\Livewire;

use Livewire\Component;

class AboutPage extends Component
{
    public function render()
    {
        $settings = \App\Modules\Operations\Models\SystemSetting::query()
            ->whereIn('key', ['about.title', 'about.subtitle', 'about.body'])
            ->pluck('value', 'key');
            
        $title = $settings['about.title'] ?? 'About PlayerSaloons';
        $subtitle = $settings['about.subtitle'] ?? 'Our mission is to revolutionize competitive gaming.';
        $body = $settings['about.body'] ?? '<p>Welcome to PlayerSaloons.</p>';

        return view('livewire.about-page', [
            'title' => $title,
            'subtitle' => $subtitle,
            'body' => $body,
        ])->layout('components.layouts.app', ['title' => 'About Us - PlayerSaloons']);
    }
}
