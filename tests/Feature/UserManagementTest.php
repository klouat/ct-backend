<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_user(): void
    {
        $admin = $this->adminUser();
        $token = auth('api')->login($admin);
        $vendor = Vendor::create(['vendor_name' => 'Vendor Alpha']);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/users', [
                'username' => 'vendor01',
                'email' => 'vendor01@example.com',
                'password' => 'password123',
                'role' => 'VENDOR',
                'vendor_id' => $vendor->vendor_id,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.username', 'vendor01')
            ->assertJsonPath('data.role', 'VENDOR')
            ->assertJsonPath('data.vendor_id', $vendor->vendor_id);

        $user = User::where('username', 'vendor01')->firstOrFail();

        $this->assertTrue(Hash::check('password123', $user->password_hash));
    }

    public function test_non_admin_cannot_access_user_management(): void
    {
        $user = User::factory()->create([
            'role' => 'SUPERVISOR',
            'vendor_id' => null,
        ]);
        $token = auth('api')->login($user);

        $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/users')
            ->assertForbidden();
    }

    public function test_update_keeps_password_when_empty(): void
    {
        $admin = $this->adminUser();
        $managedUser = User::factory()->create([
            'password_hash' => Hash::make('password123'),
            'role' => 'SUPERVISOR',
            'vendor_id' => null,
        ]);
        $originalHash = $managedUser->password_hash;
        $token = auth('api')->login($admin);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/users/'.$managedUser->user_id, [
                'username' => 'updated-user',
                'email' => 'updated-user@example.com',
                'password' => '',
                'role' => 'SUPERVISOR',
                'vendor_id' => null,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.username', 'updated-user');

        $managedUser->refresh();

        $this->assertSame($originalHash, $managedUser->password_hash);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = $this->adminUser();
        $token = auth('api')->login($admin);

        $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/users/'.$admin->user_id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot delete your own account.');
    }

    public function test_last_admin_cannot_be_deleted_or_demoted(): void
    {
        $admin = $this->adminUser();
        $token = auth('api')->login($admin);

        $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/users/'.$admin->user_id)
            ->assertStatus(422);

        $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/users/'.$admin->user_id, [
                'username' => $admin->username,
                'email' => $admin->email,
                'password' => '',
                'role' => 'SUPERVISOR',
                'vendor_id' => null,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'At least one administrator account must remain active.');
    }

    private function adminUser(): User
    {
        return User::factory()->create([
            'username' => 'admin01',
            'email' => 'admin01@example.com',
            'password_hash' => Hash::make('password123'),
            'role' => 'ADMIN',
            'vendor_id' => null,
        ]);
    }
}
