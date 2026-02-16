<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use App\Services\BmlConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_payment_requires_accept_terms(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create([
            'registration_fee_amount' => 50,
        ]);

        $response = $this->actingAs($user)->post(route('payments.course.start', $course), [
            '_token' => csrf_token(),
        ]);

        $response->assertSessionHasErrors('accept_terms');
    }

    public function test_start_payment_creates_payment_and_redirects_when_terms_accepted(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create([
            'registration_fee_amount' => 100,
        ]);

        $this->mock(BmlConnectService::class, function ($mock) {
            $mock->shouldReceive('mvrToLaari')->andReturn(10000);
            $mock->shouldReceive('createTransaction')
                ->once()
                ->andReturn('https://bml-uat.example.com/pay/fake-url');
        });

        $response = $this->actingAs($user)->post(route('payments.course.start', $course), [
            'accept_terms' => '1',
            '_token' => csrf_token(),
        ]);

        $response->assertRedirect('https://bml-uat.example.com/pay/fake-url');
        $this->assertDatabaseHas('payments', [
            'course_id' => $course->id,
            'user_id' => $user->id,
        ]);
    }
}
