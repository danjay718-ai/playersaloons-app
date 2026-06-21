<?php

namespace Tests\Feature\Admin;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KycDocumentRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure roles exist for test
        Role::firstOrCreate(['name' => 'PLAYER']);
        Role::firstOrCreate(['name' => 'ADMIN']);
        
        Storage::fake('local');
    }

    public function test_guests_cannot_access_kyc_documents(): void
    {
        $response = $this->get('/admin/kyc/document/kyc/1/test.jpg');
        $response->assertRedirect('/login');
    }

    public function test_regular_players_cannot_access_kyc_documents(): void
    {
        $player = User::factory()->create();
        $player->assignRole('PLAYER');

        $response = $this->actingAs($player)->get('/admin/kyc/document/kyc/1/test.jpg');
        $response->assertStatus(403);
    }

    public function test_admins_can_access_existing_kyc_documents(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('ADMIN');

        // Create a fake document
        $path = 'kyc/1/test.jpg';
        Storage::disk('local')->put($path, 'fake image content');

        $response = $this->actingAs($admin)->get("/admin/kyc/document/{$path}");
        $response->assertStatus(200);
        $this->assertEquals('fake image content', $response->streamedContent());
    }

    public function test_admins_receive_404_for_missing_documents(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('ADMIN');

        $response = $this->actingAs($admin)->get('/admin/kyc/document/kyc/1/missing.jpg');
        $response->assertStatus(404);
    }
}
