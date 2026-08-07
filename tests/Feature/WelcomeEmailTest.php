<?php

namespace Tests\Feature;

use App\Mail\WelcomeEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WelcomeEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_queues_welcome_email(): void
    {
        Mail::fake();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/dashboard');

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);

        Mail::assertQueued(WelcomeEmail::class, function (WelcomeEmail $mail) {
            return $mail->hasTo('test@example.com') && $mail->user->email === 'test@example.com';
        });
    }

    public function test_mailable_has_the_expected_subject(): void
    {
        $user = User::factory()->create(['name' => 'Jane']);

        $mail = new WelcomeEmail($user);

        $this->assertSame(
            'Welcome to '.config('app.name').' — Let\'s get you on the road!',
            $mail->envelope()->subject
        );
    }

    public function test_mailable_renders_user_name_and_quick_links(): void
    {
        $user = User::factory()->create(['name' => 'Jane']);

        $html = (new WelcomeEmail($user))->render();

        $this->assertStringContainsString('Welcome aboard, Jane', $html);
        $this->assertStringContainsString('/vehicles', $html);
        $this->assertStringContainsString('/account/driver-verification', $html);
    }
}
