<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Identity\Models\User;
use Illuminate\Contracts\View\View;

class NewsletterUnsubscribeController extends Controller
{
    public function __invoke(User $user): View
    {
        $user->update([
            'newsletter_subscribed' => false,
            'newsletter_subscribed_at' => null,
        ]);

        return view('newsletter.unsubscribed', ['email' => $user->email]);
    }
}
