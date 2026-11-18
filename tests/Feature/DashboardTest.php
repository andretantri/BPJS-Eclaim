<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ClaimGuardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_export_uses_seeded_hospital_name(): void
    {
        $this->seed(ClaimGuardSeeder::class);
        $user = User::where('email', 'nadia@cakramedika.com')->firstOrFail();
        $hospital = $user->hospital;
        $hospital->update(['nama_rs' => 'RS Uji Ekspor']);

        $response = $this->actingAs($user)->get(route('dashboard.summary.download'));

        $response->assertOk()
            ->assertSee('RS Uji Ekspor')
            ->assertDontSee('RS Cakra Medika');
    }
}
