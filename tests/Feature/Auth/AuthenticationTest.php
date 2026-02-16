<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->followingRedirects()->get(route('login'));

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();
        UserContact::create([
            'user_id' => $user->id,
            'type' => 'email',
            'value' => strtolower($user->email ?? 'test@example.com'),
            'is_primary' => true,
            'verified_at' => now(),
        ]);

        $response = $this->post(route('login'), [
            'identifier' => $user->email ?? 'test@example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();
        UserContact::create([
            'user_id' => $user->id,
            'type' => 'email',
            'value' => strtolower($user->email ?? 'test@example.com'),
            'is_primary' => true,
            'verified_at' => now(),
        ]);

        $this->post(route('login'), [
            'identifier' => $user->email ?? 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
