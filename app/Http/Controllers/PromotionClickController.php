<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Community\Models\Advertisement;
use Illuminate\Http\RedirectResponse;

class PromotionClickController extends Controller
{
    public function __invoke(Advertisement $advertisement): RedirectResponse
    {
        abort_unless(
            Advertisement::query()->currentlyVisible()->whereKey($advertisement->id)->exists() && $advertisement->target_url,
            404
        );

        $advertisement->increment('clicks');

        return redirect()->away($advertisement->target_url);
    }
}
