<?php

namespace Tests\Feature\PaymentsStripe;

use App\Core\Events\PaymentAuthorized;
use App\Core\Events\PaymentCaptured;
use App\Core\Events\PaymentFailed;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Plugins\PaymentsStripe\StripeServiceProvider;
use Tests\TestCase;

/**
 * Exercises the REAL webhook entry point end to end — including real
 * Stripe-Signature HMAC verification (Stripe's documented algorithm:
 * signed payload = "{timestamp}.{payload}", HMAC-SHA256 with the webhook
 * secret) — with no network call and no real Stripe credentials needed,
 * since signature verification is pure local computation.
 */
class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        // StripeClient's constructor validates the API key eagerly, even
        // though webhook handling itself never calls the Stripe API — it
        // only needs a non-empty placeholder here.
        config([
            'payments-stripe.secret' => 'sk_test_dummy',
            'payments-stripe.webhook_secret' => self::WEBHOOK_SECRET,
        ]);
        $this->app->register(StripeServiceProvider::class);
    }

    /** @param array<string, mixed> $eventPayload */
    private function postSignedWebhook(array $eventPayload): TestResponse
    {
        $payload = json_encode($eventPayload);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", self::WEBHOOK_SECRET);

        return $this->call(
            'POST',
            '/webhooks/stripe',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Stripe-Signature' => "t={$timestamp},v1={$signature}",
            ],
            $payload,
        );
    }

    /** @param array<string, mixed> $overrides */
    private function paymentIntentEvent(string $type, array $overrides = []): array
    {
        return [
            'id' => 'evt_test_'.uniqid(),
            'type' => $type,
            'data' => [
                'object' => [
                    'id' => 'pi_test123',
                    'object' => 'payment_intent',
                    'amount' => 90000,
                    'currency' => 'mad',
                    'status' => 'requires_capture',
                    ...$overrides,
                ],
            ],
        ];
    }

    public function test_amount_capturable_updated_marks_deposit_authorized(): void
    {
        Event::fake([PaymentAuthorized::class]);

        $payment = Payment::factory()->create([
            'type' => 'deposit_authorization',
            'status' => 'pending',
            'amount' => 900.00,
            'provider_reference' => 'pi_test123',
        ]);

        $response = $this->postSignedWebhook(
            $this->paymentIntentEvent('payment_intent.amount_capturable_updated'),
        );

        $response->assertOk();
        $this->assertSame('authorized', $payment->fresh()->status);
        Event::assertDispatched(PaymentAuthorized::class);
    }

    public function test_payment_intent_succeeded_marks_payment_succeeded(): void
    {
        Event::fake([PaymentCaptured::class]);

        $payment = Payment::factory()->create([
            'type' => 'final_charge',
            'status' => 'pending',
            'amount' => 900.00,
            'provider_reference' => 'pi_test123',
        ]);

        $response = $this->postSignedWebhook(
            $this->paymentIntentEvent('payment_intent.succeeded'),
        );

        $response->assertOk();
        $this->assertSame('succeeded', $payment->fresh()->status);
        Event::assertDispatched(PaymentCaptured::class);
    }

    public function test_payment_intent_payment_failed_marks_payment_failed(): void
    {
        Event::fake([PaymentFailed::class]);

        $payment = Payment::factory()->create([
            'status' => 'pending',
            'amount' => 900.00,
            'provider_reference' => 'pi_test123',
        ]);

        $response = $this->postSignedWebhook(
            $this->paymentIntentEvent('payment_intent.payment_failed'),
        );

        $response->assertOk();
        $this->assertSame('failed', $payment->fresh()->status);
        Event::assertDispatched(PaymentFailed::class);
    }

    public function test_webhook_is_idempotent_second_delivery_is_a_noop(): void
    {
        Event::fake([PaymentCaptured::class]);

        $payment = Payment::factory()->create([
            'status' => 'pending',
            'amount' => 900.00,
            'provider_reference' => 'pi_test123',
        ]);

        $eventPayload = $this->paymentIntentEvent('payment_intent.succeeded');

        $this->postSignedWebhook($eventPayload)->assertOk();
        $this->assertSame('succeeded', $payment->fresh()->status);

        // Second delivery of the exact same event — must not error, must not
        // re-fire the event, must not change anything (the Payment is no
        // longer 'pending', so the lookup correctly finds nothing to act on).
        $this->postSignedWebhook($eventPayload)->assertOk();

        Event::assertDispatchedTimes(PaymentCaptured::class, 1);
    }

    public function test_a_duplicate_webhook_with_the_same_event_id_is_ignored_even_if_the_payment_is_still_pending(): void
    {
        Event::fake([PaymentAuthorized::class]);

        $payment = Payment::factory()->create([
            'type' => 'deposit_authorization',
            'status' => 'pending',
            'amount' => 900.00,
            'provider_reference' => 'pi_test123',
        ]);

        $eventPayload = $this->paymentIntentEvent('payment_intent.amount_capturable_updated');

        $this->postSignedWebhook($eventPayload)->assertOk();
        $this->assertSame('authorized', $payment->fresh()->status);

        // The processed event ID is recorded (H7).
        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => $eventPayload['id'],
            'type' => 'payment_intent.amount_capturable_updated',
        ]);

        // Reset the payment row to pending via the query builder. The webhook's
        // compare-and-set updates the DB directly, so the in-memory $payment
        // instance's status was never synced — a plain $payment->update()
        // would see no dirty attributes and issue no UPDATE.
        Payment::where('id', $payment->id)->update(['status' => 'pending']);
        $this->assertSame('pending', $payment->fresh()->status);

        // Now the ONLY thing stopping a duplicate delivery from re-applying
        // the event is the event-ID dedup check in handlePaymentIntentEvent().
        $this->postSignedWebhook($eventPayload)->assertOk();

        // Payment untouched — the duplicate delivery was ignored by event ID,
        // not because the payment was no longer pending.
        $this->assertSame('pending', $payment->fresh()->status);
        Event::assertDispatchedTimes(PaymentAuthorized::class, 1);
    }

    public function test_amount_mismatch_marks_payment_failed_not_succeeded(): void
    {
        Event::fake([PaymentFailed::class, PaymentCaptured::class]);

        $payment = Payment::factory()->create([
            'status' => 'pending',
            'amount' => 900.00, // 90000 cents expected
            'provider_reference' => 'pi_test123',
        ]);

        // Gateway reports a different amount than we expect.
        $response = $this->postSignedWebhook(
            $this->paymentIntentEvent('payment_intent.succeeded', ['amount' => 50000]),
        );

        $response->assertOk();
        $this->assertSame('failed', $payment->fresh()->status);
        Event::assertDispatched(PaymentFailed::class);
        Event::assertNotDispatched(PaymentCaptured::class);
    }

    public function test_invalid_signature_is_rejected_with_400(): void
    {
        $payment = Payment::factory()->create([
            'status' => 'pending',
            'provider_reference' => 'pi_test123',
        ]);

        $payload = json_encode($this->paymentIntentEvent('payment_intent.succeeded'));

        $response = $this->call(
            'POST',
            '/webhooks/stripe',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Stripe-Signature' => 't='.time().',v1=wrong-signature',
            ],
            $payload,
        );

        $response->assertStatus(400);
        // State must be untouched — an unverified request must never mutate anything.
        $this->assertSame('pending', $payment->fresh()->status);
    }
}
