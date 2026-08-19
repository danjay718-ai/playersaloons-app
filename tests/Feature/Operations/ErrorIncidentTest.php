<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Livewire\Admin\ErrorIncidentAdmin;
use App\Modules\Identity\Models\User;
use App\Modules\Operations\Models\ErrorIncident;
use App\Modules\Operations\Services\ErrorIncidentReporter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class ErrorIncidentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_unhandled_exception_returns_formal_error_with_reference(): void
    {
        Route::get('/testing/error-incident', function (): never {
            throw new RuntimeException('Internal database password=do-not-expose');
        })->name('testing.error-incident');

        $response = $this->get('/testing/error-incident');

        $response->assertStatus(500)
            ->assertSee('Support reference:')
            ->assertSee('Go to Home Page')
            ->assertSee('Report This Issue')
            ->assertSee('/contact?reference=ERR-', false)
            ->assertDontSee('Return to Terminal')
            ->assertDontSee('do-not-expose');

        $incident = ErrorIncident::query()->firstOrFail();
        $this->assertSame('testing.error-incident', $incident->route);
        $this->assertStringNotContainsString('do-not-expose', (string) $incident->message);
    }

    public function test_forbidden_response_does_not_expose_abort_message(): void
    {
        Route::get('/testing/forbidden-incident', fn () => abort(403, 'Sensitive authorization rule'));

        $this->get('/testing/forbidden-incident')
            ->assertForbidden()
            ->assertSee('Access Denied')
            ->assertDontSee('Sensitive authorization rule');
    }

    public function test_repeated_fingerprints_are_grouped_and_secrets_are_redacted(): void
    {
        $reporter = app(ErrorIncidentReporter::class);
        $first = $this->sampleException();
        $second = $this->sampleException();

        $firstReference = $reporter->capture($first);
        $secondReference = $reporter->capture($second);

        $this->assertSame($firstReference, $secondReference);
        $incident = ErrorIncident::query()->firstOrFail();
        $this->assertSame(2, $incident->occurrences);
        $this->assertStringNotContainsString('private-value', (string) $incident->message);
    }

    public function test_admin_can_filter_and_resolve_an_incident(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('ADMIN');
        $incident = $this->createIncident();

        Livewire::actingAs($admin)
            ->test(ErrorIncidentAdmin::class)
            ->assertSee($incident->reference_id)
            ->set('search', $incident->reference_id)
            ->call('selectIncident', $incident->id)
            ->set('resolutionNotes', 'Fixed in deployment 2026.08.19')
            ->call('resolveIncident')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('error_incidents', [
            'id' => $incident->id,
            'resolved_by' => $admin->id,
            'resolution_notes' => 'Fixed in deployment 2026.08.19',
        ]);
        $this->assertNotNull($incident->fresh()?->resolved_at);
    }

    public function test_player_cannot_access_error_incidents(): void
    {
        $player = User::factory()->create();
        $player->assignRole('PLAYER');

        Livewire::actingAs($player)
            ->test(ErrorIncidentAdmin::class)
            ->assertForbidden();
    }

    public function test_async_failure_listeners_are_registered(): void
    {
        $this->assertTrue(Event::hasListeners(JobFailed::class));
        $this->assertTrue(Event::hasListeners(ScheduledTaskFailed::class));
    }

    public function test_prune_command_removes_expired_incidents(): void
    {
        $incident = $this->createIncident();
        $incident->forceFill(['last_seen_at' => now()->subDays(10)])->save();

        $this->artisan('errors:prune --days=7')
            ->expectsOutput('Pruned 1 error incident(s) older than 7 days.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('error_incidents', ['id' => $incident->id]);
    }

    private function sampleException(): RuntimeException
    {
        return new RuntimeException('token=private-value');
    }

    private function createIncident(): ErrorIncident
    {
        return ErrorIncident::query()->create([
            'reference_id' => 'ERR-TEST-123',
            'fingerprint' => hash('sha256', 'test-incident'),
            'level' => 'error',
            'source' => 'http',
            'status_code' => 500,
            'exception_class' => RuntimeException::class,
            'message' => 'Safe test incident.',
            'route' => 'testing.route',
            'method' => 'GET',
            'path' => '/testing/route',
            'occurrences' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }
}
