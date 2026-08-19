<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Modules\Tournament\Support\DefaultTournamentRules;
use App\Support\SafeRichText;
use Tests\TestCase;

final class TournamentRichTextTest extends TestCase
{
    public function test_tournament_rich_text_keeps_formatting_and_removes_unsafe_markup(): void
    {
        $html = SafeRichText::render(
            '<p onclick="alert(1)">Play <strong>fair</strong>.</p><script>alert(2)</script><a href="javascript:alert(3)">unsafe link</a>'
        );

        $this->assertStringContainsString('<p>Play <strong>fair</strong>.</p>', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<a ', $html);
    }

    public function test_default_rules_are_general_and_the_long_content_is_collapsible(): void
    {
        $rules = DefaultTournamentRules::html();

        $this->assertStringContainsString('General Tournament &amp; Head-to-Head Competition Rules', $rules);
        $this->assertStringNotContainsString('F1 25', $rules);

        $this->blade(
            '<x-ui.collapsible-rich-text :content="$content" :threshold="100" :preview-height="120" />',
            ['content' => $rules],
        )
            ->assertSee('View more')
            ->assertSee('PlayerSaloons General Tournament &amp; Head-to-Head Competition Rules', false)
            ->assertDontSee('&lt;h2&gt;', false);
    }
}
