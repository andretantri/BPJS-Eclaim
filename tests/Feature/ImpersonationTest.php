<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ClaimGuardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $petugas;

    protected User $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ClaimGuardSeeder::class);
        $this->admin = User::where('email', 'admin@cakramedika.com')->firstOrFail();
        $this->petugas = User::where('email', 'nadia@cakramedika.com')->firstOrFail();
        $this->verifier = User::where('email', 'bima@cakramedika.com')->firstOrFail();
    }

    public function test_admin_impersonates_user_and_returns_to_own_account(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.impersonate', $this->petugas))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->petugas);
        $this->assertNotNull(session('impersonator_id'));

        $this->post(route('impersonation.leave'))
            ->assertRedirect(route('admin.users.index'));

        $this->assertAuthenticatedAs($this->admin->fresh());
        $this->assertNull(session('impersonator_id'));
    }

    public function test_impersonated_session_shares_original_administrator(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.impersonate', $this->petugas))
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.id', $this->petugas->id)
                ->where('auth.isAdmin', false)
                ->where('impersonator.id', $this->admin->id)
                ->where('impersonator.name', $this->admin->name));
    }

    public function test_petugas_cannot_impersonate_another_user(): void
    {
        $this->actingAs($this->petugas)
            ->post(route('admin.users.impersonate', $this->verifier))
            ->assertForbidden();
    }

    public function test_admin_cannot_impersonate_self(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.impersonate', $this->admin))
            ->assertForbidden();
    }

    public function test_admin_cannot_impersonate_inactive_user(): void
    {
        $this->petugas->update(['is_active' => false]);

        $this->actingAs($this->admin)
            ->post(route('admin.users.impersonate', $this->petugas))
            ->assertForbidden();
    }

    public function test_leaving_impersonation_without_active_session_is_forbidden(): void
    {
        $this->actingAs($this->admin)
            ->post(route('impersonation.leave'))
            ->assertForbidden();
    }
}
