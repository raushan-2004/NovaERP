<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'name'     => 'Test Admin',
            'email'    => 'admin@novatech.com',
            'password' => 'testpassword123',
        ]);
    }

    public function test_health_endpoint_returns_200(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => ['status' => 'ok'],
            ]);
    }

    public function test_login_with_valid_credentials_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'admin@novatech.com',
            'password' => 'testpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email'],
                ],
            ])
            ->assertJson(['success' => true]);
    }

    public function test_login_with_invalid_credentials_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'admin@novatech.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_login_with_missing_fields_returns_422(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['email', 'password']]);
    }

    public function test_me_endpoint_returns_authenticated_user(): void
    {
        $token = $this->adminUser->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'email' => 'admin@novatech.com',
                ],
            ]);
    }

    public function test_me_endpoint_without_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_logout_revokes_token(): void
    {
        // Login to get a real token
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email'    => 'admin@novatech.com',
            'password' => 'testpassword123',
        ]);
        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('data.token');

        // Confirm token works
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(200);

        // Logout — revokes the token
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertStatus(200);

        // Confirm the token record was deleted from DB
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
