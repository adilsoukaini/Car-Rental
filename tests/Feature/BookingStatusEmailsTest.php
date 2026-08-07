<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Events\BookingCancelled;
use App\Core\Events\VehicleCheckedOut;
use App\Core\Events\VehicleReturned;
use App\Core\Listeners\SendBookingCancelledEmail;
use App\Core\Listeners\SendBookingCheckedOutEmail;
use App\Core\Listeners\SendBookingReturnedEmail;
use App\Mail\BookingCancelled as BookingCancelledMailable;
use App\Mail\BookingCheckedOut;
use App\Mail\BookingReturned;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Plugins\BookingEngine\BookingEngineServiceProvider;
use Tests\TestCase;

class BookingStatusEmailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Needed for the cancelled email's refund-percent computation via the
        // booking.cancellationPolicy filter pipe.
        $this->app->register(BookingEngineServiceProvider::class);
    }

    // --- BookingCheckedOut ---

    public function test_checked_out_listener_sends_to_guest_email_with_signed_track_url(): void
    {
        Mail::fake();

        $booking = Booking::factory()->create([
            'user_id' => null,
            'guest_email' => 'guest@example.com',
        ]);

        (new SendBookingCheckedOutEmail)->handle(new VehicleCheckedOut($booking));

        Mail::assertQueued(BookingCheckedOut::class, function (BookingCheckedOut $mail) use ($booking) {
            $html = $mail->render();

            return $mail->hasTo('guest@example.com')
                && $mail->booking->is($booking)
                && str_contains($html, 'bookings/'.$booking->id)
                && str_contains($html, 'signature=');
        });
    }

    public function test_checked_out_listener_falls_back_to_user_email(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'user@example.com']);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'guest_email' => null,
        ]);

        (new SendBookingCheckedOutEmail)->handle(new VehicleCheckedOut($booking));

        Mail::assertQueued(BookingCheckedOut::class, fn (BookingCheckedOut $mail) => $mail->hasTo('user@example.com'));
    }

    public function test_checked_out_listener_sends_nothing_without_recipient(): void
    {
        Mail::fake();

        $booking = Booking::factory()->create([
            'user_id' => null,
            'guest_email' => null,
        ]);

        (new SendBookingCheckedOutEmail)->handle(new VehicleCheckedOut($booking));

        Mail::assertNothingSent();
    }

    public function test_checked_out_mailable_has_the_expected_subject(): void
    {
        $booking = Booking::factory()->create();

        $mail = new BookingCheckedOut($booking);

        $this->assertSame('Your rental has started — Booking #'.$booking->booking_number, $mail->envelope()->subject);
    }

    // --- BookingReturned ---

    public function test_returned_listener_sends_to_guest_email(): void
    {
        Mail::fake();

        $booking = Booking::factory()->create([
            'user_id' => null,
            'guest_email' => 'guest@example.com',
        ]);

        (new SendBookingReturnedEmail)->handle(new VehicleReturned($booking));

        Mail::assertQueued(BookingReturned::class, function (BookingReturned $mail) use ($booking) {
            $html = $mail->render();

            return $mail->hasTo('guest@example.com')
                && $mail->booking->is($booking)
                && str_contains($html, $booking->vehicle->make);
        });
    }

    public function test_returned_listener_sends_nothing_without_recipient(): void
    {
        Mail::fake();

        $booking = Booking::factory()->create([
            'user_id' => null,
            'guest_email' => null,
        ]);

        (new SendBookingReturnedEmail)->handle(new VehicleReturned($booking));

        Mail::assertNothingSent();
    }

    public function test_returned_mailable_has_the_expected_subject(): void
    {
        $booking = Booking::factory()->create();

        $mail = new BookingReturned($booking);

        $this->assertSame('Your rental is complete — Booking #'.$booking->booking_number, $mail->envelope()->subject);
    }

    public function test_returned_mailable_renders_review_link_when_url_present(): void
    {
        $booking = Booking::factory()->create();

        $html = (new BookingReturned($booking))
            ->with(['reviewUrl' => 'https://example.com/vehicles/'.$booking->vehicle_id])
            ->render();

        $this->assertStringContainsString('Leave a review', $html);
        $this->assertStringContainsString('/vehicles/'.$booking->vehicle_id, $html);
    }

    // --- BookingCancelled ---

    public function test_cancelled_listener_sends_full_refund_note_when_deposit_held(): void
    {
        Mail::fake();

        $booking = Booking::factory()->create([
            'user_id' => null,
            'guest_email' => 'guest@example.com',
            'pickup_at' => now()->addDays(10),
        ]);
        Payment::factory()->create([
            'booking_id' => $booking->id,
            'type' => 'deposit_authorization',
            'status' => 'succeeded',
            'amount' => 200.00,
        ]);

        (new SendBookingCancelledEmail)->handle(new BookingCancelled($booking));

        Mail::assertQueued(BookingCancelledMailable::class, function (BookingCancelledMailable $mail) {
            return $mail->hasTo('guest@example.com')
                && str_contains($mail->render(), 'Your security deposit has been fully released.');
        });
    }

    public function test_cancelled_listener_sends_partial_refund_note_for_close_cancellation(): void
    {
        Mail::fake();

        $booking = Booking::factory()->create([
            'user_id' => null,
            'guest_email' => 'guest@example.com',
            'pickup_at' => now()->addDays(3),
        ]);
        Payment::factory()->create([
            'booking_id' => $booking->id,
            'type' => 'deposit_authorization',
            'status' => 'succeeded',
            'amount' => 200.00,
        ]);

        (new SendBookingCancelledEmail)->handle(new BookingCancelled($booking));

        Mail::assertQueued(BookingCancelledMailable::class, function (BookingCancelledMailable $mail) {
            return str_contains($mail->render(), '50% of your security deposit');
        });
    }

    public function test_cancelled_listener_omits_refund_note_when_no_deposit_held(): void
    {
        Mail::fake();

        $booking = Booking::factory()->create([
            'user_id' => null,
            'guest_email' => 'guest@example.com',
            'pickup_at' => now()->addDays(10),
        ]);

        (new SendBookingCancelledEmail)->handle(new BookingCancelled($booking));

        Mail::assertQueued(BookingCancelledMailable::class, function (BookingCancelledMailable $mail) {
            return ! str_contains($mail->render(), 'security deposit has been');
        });
    }

    public function test_cancelled_listener_sends_nothing_without_recipient(): void
    {
        Mail::fake();

        $booking = Booking::factory()->create([
            'user_id' => null,
            'guest_email' => null,
        ]);

        (new SendBookingCancelledEmail)->handle(new BookingCancelled($booking));

        Mail::assertNothingSent();
    }

    public function test_cancelled_mailable_has_the_expected_subject(): void
    {
        $booking = Booking::factory()->create();

        $mail = new BookingCancelledMailable($booking);

        $this->assertSame('Booking #'.$booking->booking_number.' has been cancelled', $mail->envelope()->subject);
    }
}
