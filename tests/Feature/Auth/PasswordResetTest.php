<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $response = $this->followingRedirects()->get(route('password.request'));

        $response->assertStatus(200);
    }

    public function test_reset_password_sends_otp_for_existing_contact(): void
    {
        $user = User::factory()->create();
        UserContact::create([
            'user_id' => $user->id,
            'type' => 'email',
            'value' => 'reset@example.com',
            'is_primary' => true,
            'verified_at' => now(),
        ]);

        $response = $this->post(route('password.email'), ['identifier' => 'reset@example.com']);

        $response->assertRedirect();
        $this->assertStringContainsString('reset-password', $response->headers->get('Location') ?? '');
    }

    public function test_reset_password_shows_verify_form(): void
    {
        $response = $this->followingRedirects()->get(route('password.reset.verify'));

        $response->assertStatus(200);
    }

    public function test_reset_password_shows_reset_form_after_verify(): void
    {
        session(['password_reset_verified' => true, 'password_reset_contact_id' => 1]);

        $user = User::factory()->create();
        $contact = UserContact::create([
            'user_id' => $user->id,
            'type' => 'email',
            'value' => 'reset2@example.com',
            'is_primary' => true,
            'verified_at' => now(),
        ]);
        session(['password_reset_contact_id' => $contact->id]);

        $response = $this->followingRedirects()->get(route('password.reset'));

        $response->assertStatus(200);
    }
}
