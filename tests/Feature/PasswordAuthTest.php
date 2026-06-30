<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_email_and_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'avatar']]);
        $this->assertDatabaseHas('users', ['email' => 'budi@example.com']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        User::factory()->create(['email' => 'budi@example.com', 'password' => 'password123']);

        $this->postJson('/api/auth/login', [
            'email' => 'budi@example.com',
            'password' => 'password123',
        ])->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_google_only_account_cannot_login_with_password(): void
    {
        User::factory()->create(['email' => 'google@example.com', 'password' => null, 'google_id' => 'google-id']);

        $this->postJson('/api/auth/login', [
            'email' => 'google@example.com',
            'password' => 'password123',
        ])->assertUnprocessable()->assertJsonPath('status', 'error');
    }
}
