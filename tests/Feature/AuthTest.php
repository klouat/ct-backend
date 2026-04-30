<?php

namespace Tests\Feature;

use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $vendor = Vendor::create([
            'vendor_name' => 'Acme Vendor',
        ]);

        $response = $this->postJson('/api/auth/register', [
            'username' => 'vendor02',
            'password' => 'password123',
            'role' => 'VENDOR',
            'vendor_id' => $vendor->vendor_id,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.username', 'vendor02')
            ->assertJsonPath('data.user.role', 'VENDOR');
    }

    public function test_user_can_login(): void
    {
        $this->seed();

        $response = $this->postJson('/api/auth/login', [
            'username' => 'admin',
            'password' => 'password123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.username', 'admin');

        $this->assertIsString($response->json('data.access_token'));
    }
}
