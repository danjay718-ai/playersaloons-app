<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Operations\Services\AiSupportAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiSupportController extends Controller
{
    public function ask(Request $request, AiSupportAssistantService $assistant): JsonResponse
    {
        if (! $assistant->isAvailable()) {
            return response()->json([
                'message' => __('AI Support Assistant is currently unavailable. Please use the Contact page for help.'),
            ], 503);
        }

        /** @var array{message: string, history?: list<array{role: 'user'|'assistant', content: string}>} $validated */
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:1000'],
            'history' => ['nullable', 'array', 'max:8'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:1200'],
        ]);

        /** @var User|null $user */
        $user = $request->user();

        try {
            $result = $assistant->ask(
                $user,
                trim($validated['message']),
                $validated['history'] ?? [],
            );

            return response()->json($result);
        } catch (Throwable $e) {
            Log::warning('AI support assistant request failed.', [
                'scope' => $assistant->resolveScope($user),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => __('Support assistant could not respond right now. Please try again in a moment or visit the Contact page.'),
            ], 503);
        }
    }
}
