<?php

declare(strict_types=1);

namespace App\Livewire\Community;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class GlobalChat extends Component
{
    public string $chatMessage = '';
    public array $messages = [];

    public function mount()
    {
        $this->messages = [
            ['username' => 'NeonSpecter', 'text' => 'Who wants to duel in FIFA? Stake is $10.', 'time' => '12:04', 'avatar' => 'NS'],
            ['username' => 'ViperZero', 'text' => 'I\'m down, invite me!', 'time' => '12:05', 'avatar' => 'VZ'],
            ['username' => 'GamerGod', 'text' => 'Valorant tournament is stacked today, good luck to everyone!', 'time' => '12:06', 'avatar' => 'GG'],
            ['username' => 'HyperDrift', 'text' => 'Let\'s goooo! Streaming the match soon.', 'time' => '12:08', 'avatar' => 'HD'],
        ];
    }

    public function sendMessage(): void
    {
        if (trim($this->chatMessage) === '') {
            return;
        }

        $user = Auth::user();
        $this->messages[] = [
            'username' => $user->username,
            'text' => $this->chatMessage,
            'time' => now()->format('H:i'),
            'avatar' => strtoupper(substr($user->username, 0, 2)),
        ];

        $this->chatMessage = '';
        $this->dispatch('chat-updated');
        
        // Mock bot logic
        $botNames = ['NeonSpecter', 'ViperZero', 'GamerGod', 'HyperDrift', 'SaloonsBot'];
        $botAnswers = ['Nice shot!', 'Good luck!', 'GG!', 'Streaming soon!'];
        $this->messages[] = [
            'username' => $botNames[array_rand($botNames)],
            'text' => $botAnswers[array_rand($botAnswers)],
            'time' => now()->format('H:i'),
            'avatar' => 'BOT',
        ];
    }

    public function render()
    {
        return view('livewire.community.global-chat')->layout('components.layouts.dashboard', [
            'title' => 'Global Chat | PlayerSaloons',
            'dashboard_title' => 'GLOBAL COMMUNICATIONS',
        ]);
    }
}
