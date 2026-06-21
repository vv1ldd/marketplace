<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontUsernameCheckTest extends TestCase
{
    use RefreshDatabase;
    public function test_username_check_rejects_invalid_format(): void
    {
        $response = $this->postJson('https://api.meanly.test/api/storefront/v1/identity/username/check', [
            'username' => '!!',
        ]);

        $response->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('reason', 'invalid');
    }

    public function test_username_check_rejects_taken_username(): void
    {
        User::factory()->create([
            'username' => 'taken_user',
            'username_key' => 'taken_user',
        ]);

        $response = $this->postJson('https://api.meanly.test/api/storefront/v1/identity/username/check', [
            'username' => 'taken_user',
        ]);

        $response->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('reason', 'taken');
    }

    public function test_username_check_accepts_available_username(): void
    {
        $response = $this->postJson('https://api.meanly.test/api/storefront/v1/identity/username/check', [
            'username' => 'Fresh.User',
        ]);

        $response->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('username', 'fresh.user');
    }
}
