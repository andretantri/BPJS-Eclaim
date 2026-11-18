<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\ClaimGuardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $petugas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ClaimGuardSeeder::class);
        $this->admin = User::where('email', 'admin@cakramedika.com')->firstOrFail();
        $this->petugas = User::where('email', 'nadia@cakramedika.com')->firstOrFail();
    }

    public function test_admin_can_open_user_management_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Users/Index')
                ->has('users.data')
                ->where('filters.q', ''));
    }

    public function test_petugas_cannot_open_user_management_page(): void
    {
        $this->actingAs($this->petugas)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_admin_creates_user_with_selected_role_and_hospital(): void
    {
        $roleId = Role::where('name', 'petugas_casemix')->value('id');

        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), $this->payload(['role_id' => $roleId]))
            ->assertRedirect(route('admin.users.index'));

        $created = User::where('email', 'petugas.baru@cakramedika.com')->firstOrFail();

        $this->assertSame('Petugas Baru', $created->name);
        $this->assertSame((int) $roleId, (int) $created->role_id);
        $this->assertSame((int) $this->admin->hospital_id, (int) $created->hospital_id);
        $this->assertTrue($created->is_active);
        $this->assertTrue(Hash::check('password-baru', $created->password));
    }

    public function test_duplicate_email_is_rejected_when_creating_user(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), $this->payload([
                'email' => $this->petugas->email,
            ]))
            ->assertSessionHasErrors('email');
    }

    public function test_password_confirmation_mismatch_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), $this->payload([
                'password_confirmation' => 'password-beda',
            ]))
            ->assertSessionHasErrors('password');
    }

    public function test_admin_updates_role_and_status_of_another_user(): void
    {
        $target = User::create($this->payload([
            'email' => 'petugas.ubah@cakramedika.com',
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
        ]));
        $verifierRoleId = Role::where('name', 'verifikator_medis')->value('id');

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $target), [
                'name' => $target->name,
                'email' => $target->email,
                'role_id' => $verifierRoleId,
                'hospital_id' => $target->hospital_id,
                'is_active' => false,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role_id' => $verifierRoleId,
            'is_active' => false,
        ]);
    }

    public function test_admin_cannot_change_own_role_or_deactivate_own_account(): void
    {
        $verifierRoleId = Role::where('name', 'verifikator_medis')->value('id');

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->admin), [
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'role_id' => $verifierRoleId,
                'hospital_id' => $this->admin->hospital_id,
                'is_active' => false,
            ])
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
            'role_id' => $this->admin->role_id,
            'is_active' => true,
        ]);
    }

    public function test_admin_deletes_another_user(): void
    {
        $target = User::create($this->payload([
            'email' => 'petugas.hapus@cakramedika.com',
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
        ]));

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $target))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Petugas Baru',
            'email' => 'petugas.baru@cakramedika.com',
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
            'hospital_id' => $this->admin->hospital_id,
            'role_id' => Role::where('name', 'petugas_casemix')->value('id'),
            'nip' => 'CSM-2026-0099',
            'jabatan' => 'Petugas Casemix',
            'is_active' => true,
        ], $overrides);
    }
}
