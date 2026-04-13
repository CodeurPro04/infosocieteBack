<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Services\PaymentFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_webhook_returns_200_even_when_fulfillment_fails(): void
    {
        config()->set('stripe.webhook_secret', 'whsec_test_secret');

        Payment::query()->create([
            'stripe_intent_id' => 'pi_test_123',
            'status' => 'requires_payment_method',
            'amount' => 49.99,
            'currency' => 'eur',
            'email' => 'client@example.com',
            'metadata' => [],
        ]);

        $mock = Mockery::mock(PaymentFulfillmentService::class);
        $mock->shouldReceive('fulfill')
            ->once()
            ->andThrow(new \RuntimeException('SMTP timeout'));
        $this->app->instance(PaymentFulfillmentService::class, $mock);

        $payload = json_encode([
            'id' => 'evt_test_123',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'object' => 'payment_intent',
                    'id' => 'pi_test_123',
                    'status' => 'succeeded',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = $this->call(
            'POST',
            '/api/payments/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $this->signatureHeader($payload, 'whsec_test_secret'),
            ],
            $payload
        );

        $response->assertOk()->assertJson(['received' => true]);

        $this->assertSame('succeeded', Payment::query()->where('stripe_intent_id', 'pi_test_123')->value('status'));
    }

    public function test_webhook_resolves_payment_intent_from_nested_event_payload(): void
    {
        config()->set('stripe.webhook_secret', 'whsec_test_secret');

        Payment::query()->create([
            'stripe_intent_id' => 'pi_nested_123',
            'status' => 'succeeded',
            'amount' => 49.99,
            'currency' => 'eur',
            'email' => 'client@example.com',
            'metadata' => [],
        ]);

        $mock = Mockery::mock(PaymentFulfillmentService::class);
        $mock->shouldNotReceive('fulfill');
        $this->app->instance(PaymentFulfillmentService::class, $mock);

        $payload = json_encode([
            'id' => 'evt_test_nested',
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'object' => 'charge',
                    'id' => 'ch_test_123',
                    'status' => 'succeeded',
                    'payment_intent' => 'pi_nested_123',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = $this->call(
            'POST',
            '/api/payments/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $this->signatureHeader($payload, 'whsec_test_secret'),
            ],
            $payload
        );

        $response->assertOk()->assertJson(['received' => true]);

        $payment = Payment::query()->where('stripe_intent_id', 'pi_nested_123')->firstOrFail();

        $this->assertSame('charge.refunded', $payment->metadata['webhook_event'] ?? null);
    }

    private function signatureHeader(string $payload, string $secret): string
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return "t={$timestamp},v1={$signature}";
    }
}
