<?php

namespace Tests\Feature\PaymentsStripe;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Plugins\PaymentsStripe\StripeGateway;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Service\PaymentIntentService;
use Stripe\Service\RefundService;
use Stripe\StripeClient;
use Tests\TestCase;

/**
 * Tests the LOCAL side effects (Payment rows created with correct fields)
 * of each gateway operation, with the Stripe API call itself mocked via a
 * Mockery double of StripeClient — no real network call, no real API key
 * needed. This is a genuine limitation worth being honest about: this
 * proves our code calls the Stripe SDK correctly and records the right
 * local state, but does NOT prove Stripe's real API behaves the way we
 * assume. A real integration test against Stripe's test-mode API (which
 * needs real STRIPE_SECRET test credentials, not present in this
 * environment) would be the next level of proof — see CLAUDE.md's Phase 7
 * section.
 */
class StripeGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorize_deposit_creates_a_pending_authorization_with_manual_capture(): void
    {
        $booking = Booking::factory()->create();

        $paymentIntents = Mockery::mock(PaymentIntentService::class);
        $paymentIntents->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $args) => $args['amount'] === 90000
                && $args['capture_method'] === 'manual'
                && $args['metadata']['booking_id'] === (string) $booking->id))
            ->andReturn(PaymentIntent::constructFrom(['id' => 'pi_test123', 'object' => 'payment_intent']));

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('getService')->with('paymentIntents')->andReturn($paymentIntents);

        $gateway = new StripeGateway($stripe);
        $payment = $gateway->authorizeDeposit($booking, 900.00);

        $this->assertSame('deposit_authorization', $payment->type);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('900.00', $payment->amount);
        $this->assertSame('pi_test123', $payment->provider_reference);
    }

    public function test_capture_deposit_calls_capture_and_records_a_succeeded_capture_row(): void
    {
        $authorization = Payment::factory()->create([
            'type' => 'deposit_authorization',
            'status' => 'authorized',
            'amount' => 900.00,
            'provider_reference' => 'pi_test123',
        ]);

        $paymentIntents = Mockery::mock(PaymentIntentService::class);
        $paymentIntents->shouldReceive('capture')
            ->once()
            ->with('pi_test123', ['amount_to_capture' => 90000])
            ->andReturn(PaymentIntent::constructFrom(['id' => 'pi_test123', 'object' => 'payment_intent']));

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('getService')->with('paymentIntents')->andReturn($paymentIntents);

        $gateway = new StripeGateway($stripe);
        $capture = $gateway->captureDeposit($authorization);

        $this->assertSame('deposit_capture', $capture->type);
        $this->assertSame('succeeded', $capture->status);
        $this->assertSame('900.00', $capture->amount);
        $this->assertSame('pi_test123', $capture->provider_reference);
    }

    public function test_capture_deposit_supports_a_partial_amount(): void
    {
        $authorization = Payment::factory()->create([
            'type' => 'deposit_authorization',
            'status' => 'authorized',
            'amount' => 900.00,
            'provider_reference' => 'pi_test123',
        ]);

        $paymentIntents = Mockery::mock(PaymentIntentService::class);
        $paymentIntents->shouldReceive('capture')
            ->once()
            ->with('pi_test123', ['amount_to_capture' => 20000]) // only 200.00 (damage cost)
            ->andReturn(PaymentIntent::constructFrom(['id' => 'pi_test123', 'object' => 'payment_intent']));

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('getService')->with('paymentIntents')->andReturn($paymentIntents);

        $gateway = new StripeGateway($stripe);
        $capture = $gateway->captureDeposit($authorization, 200.00);

        $this->assertSame('200.00', $capture->amount);
    }

    public function test_release_deposit_calls_cancel_and_records_a_release_row(): void
    {
        $authorization = Payment::factory()->create([
            'type' => 'deposit_authorization',
            'status' => 'authorized',
            'amount' => 900.00,
            'provider_reference' => 'pi_test123',
        ]);

        $paymentIntents = Mockery::mock(PaymentIntentService::class);
        $paymentIntents->shouldReceive('cancel')
            ->once()
            ->with('pi_test123')
            ->andReturn(PaymentIntent::constructFrom(['id' => 'pi_test123', 'object' => 'payment_intent']));

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('getService')->with('paymentIntents')->andReturn($paymentIntents);

        $gateway = new StripeGateway($stripe);
        $release = $gateway->releaseDeposit($authorization);

        $this->assertSame('deposit_release', $release->type);
        $this->assertSame('succeeded', $release->status);
    }

    public function test_charge_final_creates_an_automatic_capture_intent(): void
    {
        $booking = Booking::factory()->create();

        $paymentIntents = Mockery::mock(PaymentIntentService::class);
        $paymentIntents->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $args) => $args['amount'] === 63000
                && ! array_key_exists('capture_method', $args)))
            ->andReturn(PaymentIntent::constructFrom(['id' => 'pi_final456', 'object' => 'payment_intent']));

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('getService')->with('paymentIntents')->andReturn($paymentIntents);

        $gateway = new StripeGateway($stripe);
        $payment = $gateway->chargeFinal($booking, 630.00);

        $this->assertSame('final_charge', $payment->type);
        $this->assertSame('pi_final456', $payment->provider_reference);
    }

    public function test_refund_calls_refunds_create_and_records_a_refund_row(): void
    {
        $original = Payment::factory()->create([
            'type' => 'final_charge',
            'status' => 'succeeded',
            'amount' => 630.00,
            'provider_reference' => 'pi_final456',
        ]);

        $refunds = Mockery::mock(RefundService::class);
        $refunds->shouldReceive('create')
            ->once()
            ->with(['payment_intent' => 'pi_final456', 'amount' => 63000])
            ->andReturn(Refund::constructFrom(['id' => 're_test789', 'object' => 'refund']));

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('getService')->with('refunds')->andReturn($refunds);

        $gateway = new StripeGateway($stripe);
        $refund = $gateway->refund($original, 630.00);

        $this->assertSame('refund', $refund->type);
        $this->assertSame('succeeded', $refund->status);
        $this->assertSame('630.00', $refund->amount);
    }

    public function test_sync_authorization_status_marks_authorized_when_stripe_reports_requires_capture(): void
    {
        $authorization = Payment::factory()->create([
            'type' => 'deposit_authorization',
            'status' => 'pending',
            'amount' => 900.00,
            'provider_reference' => 'pi_sync1',
        ]);

        $paymentIntents = Mockery::mock(PaymentIntentService::class);
        $paymentIntents->shouldReceive('retrieve')
            ->once()
            ->with('pi_sync1')
            ->andReturn(PaymentIntent::constructFrom([
                'id' => 'pi_sync1',
                'object' => 'payment_intent',
                'status' => 'requires_capture',
                'amount' => 90000,
            ]));

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('getService')->with('paymentIntents')->andReturn($paymentIntents);

        $gateway = new StripeGateway($stripe);
        $result = $gateway->syncAuthorizationStatus($authorization);

        $this->assertSame('authorized', $result->status);
    }

    public function test_sync_authorization_status_is_a_no_op_when_stripe_still_reports_a_pending_state(): void
    {
        $authorization = Payment::factory()->create([
            'type' => 'deposit_authorization',
            'status' => 'pending',
            'amount' => 900.00,
            'provider_reference' => 'pi_sync2',
        ]);

        $paymentIntents = Mockery::mock(PaymentIntentService::class);
        $paymentIntents->shouldReceive('retrieve')
            ->once()
            ->with('pi_sync2')
            ->andReturn(PaymentIntent::constructFrom([
                'id' => 'pi_sync2',
                'object' => 'payment_intent',
                'status' => 'requires_action', // e.g. still mid-3DS
                'amount' => 90000,
            ]));

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('getService')->with('paymentIntents')->andReturn($paymentIntents);

        $gateway = new StripeGateway($stripe);
        $result = $gateway->syncAuthorizationStatus($authorization);

        $this->assertSame('pending', $result->status);
    }

    public function test_sync_authorization_status_does_not_call_stripe_again_once_already_resolved(): void
    {
        $authorization = Payment::factory()->create([
            'type' => 'deposit_authorization',
            'status' => 'authorized',
            'amount' => 900.00,
            'provider_reference' => 'pi_sync3',
        ]);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldNotReceive('getService');

        $gateway = new StripeGateway($stripe);
        $result = $gateway->syncAuthorizationStatus($authorization);

        $this->assertSame('authorized', $result->status);
    }

    public function test_sync_authorization_status_fails_on_an_amount_mismatch_same_as_the_webhook_path(): void
    {
        $authorization = Payment::factory()->create([
            'type' => 'deposit_authorization',
            'status' => 'pending',
            'amount' => 900.00,
            'provider_reference' => 'pi_sync4',
        ]);

        $paymentIntents = Mockery::mock(PaymentIntentService::class);
        $paymentIntents->shouldReceive('retrieve')
            ->once()
            ->with('pi_sync4')
            ->andReturn(PaymentIntent::constructFrom([
                'id' => 'pi_sync4',
                'object' => 'payment_intent',
                'status' => 'requires_capture',
                'amount' => 12345, // does not match our computed 90000
            ]));

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('getService')->with('paymentIntents')->andReturn($paymentIntents);

        $gateway = new StripeGateway($stripe);
        $result = $gateway->syncAuthorizationStatus($authorization);

        $this->assertSame('failed', $result->status);
    }
}
