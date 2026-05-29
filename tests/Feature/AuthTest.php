<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'username' => 'supervisor02',
            'email' => 'supervisor02@example.com',
            'password' => 'password123',
            'role' => 'SUPERVISOR',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.username', 'supervisor02')
            ->assertJsonPath('data.user.role', 'SUPERVISOR');
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
