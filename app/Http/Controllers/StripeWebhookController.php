<?php

namespace App\Http\Controllers;

use App\Models\BillingHistory;
use App\Models\Company;
use App\Services\CreditManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends CashierController
{
    /**
     * Handle the incoming webhook request with enhanced security validation.
     */
    public function handleWebhook(Request $request): Response
    {
        try {
            // Verify webhook signature for security
            $this->verifyWebhookSignature($request);
            
            // Log webhook event for monitoring
            Log::info('Stripe webhook received', [
                'event_type' => $request->input('type'),
                'event_id' => $request->input('id'),
            ]);
            
            return parent::handleWebhook($request);
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
                'request_id' => $request->header('Request-ID'),
            ]);
            
            return new Response('Webhook signature verification failed', 400);
        } catch (\Exception $e) {
            Log::error('Stripe webhook processing failed', [
                'error' => $e->getMessage(),
                'event_type' => $request->input('type'),
                'event_id' => $request->input('id'),
            ]);
            
            return new Response('Webhook processing failed', 500);
        }
    }

    /**
     * Verify the webhook signature for security.
     */
    protected function verifyWebhookSignature(Request $request): void
    {
        $signature = $request->header('Stripe-Signature');
        $payload = $request->getContent();
        $secret = config('cashier.webhook.secret');

        // Skip verification if no secret is configured (for testing)
        if (!$secret) {
            return;
        }

        // This will throw SignatureVerificationException if invalid
        \Stripe\Webhook::constructEvent($payload, $signature, $secret);
    }
    /**
     * Handle checkout session completed events.
     */
    public function handleCheckoutSessionCompleted(array $payload): Response
    {
        try {
            // Extract session data directly from payload for more flexibility
            $session = (object) $payload['data']['object'];

            // Handle credit purchases
            if (isset($session->metadata['type']) && $session->metadata['type'] === 'credit_purchase') {
                $this->handleCreditPurchase($session);
            }

            // Handle subscription checkouts
            if (isset($session->mode) && $session->mode === 'subscription') {
                $this->handleSubscriptionCheckout($session);
            }

            Log::info('Checkout session completed processed successfully', [
                'session_id' => $session->id ?? 'unknown',
                'mode' => $session->mode ?? 'unknown',
            ]);

            return new Response('Webhook handled', 200);
        } catch (\Exception $e) {
            Log::error('Failed to process checkout session completed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            
            return new Response('Webhook processing failed', 500);
        }
    }

    /**
     * Handle invoice payment succeeded events.
     */
    public function handleInvoicePaymentSucceeded(array $payload): Response
    {
        $event = Event::constructFrom($payload);
        $invoice = $event->data->object;

        // Find the company by customer ID
        $company = Company::where('stripe_id', $invoice->customer)->first();

        if ($company && $company->subscribed()) {
            // Add monthly credits based on subscription plan
            $subscription = $company->subscription();
            $plan = $company->subscriptionPlan;
            
            if ($plan) {
                $creditManager = app(CreditManager::class);
                $creditManager->addCredits(
                    $company,
                    $plan->monthly_credits,
                    'Monthly subscription credits - ' . $plan->name,
                    [
                        'subscription_id' => $subscription->stripe_id,
                        'invoice_id' => $invoice->id,
                        'plan' => $plan->slug,
                    ]
                );

                // Create billing history record
                BillingHistory::create([
                    'company_id' => $company->id,
                    'type' => 'subscription_payment',
                    'description' => 'Monthly subscription payment - ' . $plan->name,
                    'amount_cents' => $invoice->amount_paid,
                    'currency' => $invoice->currency,
                    'status' => 'succeeded',
                    'stripe_invoice_id' => $invoice->id,
                    'stripe_subscription_id' => $subscription->stripe_id,
                    'credits_added' => $plan->monthly_credits,
                    'metadata' => [
                        'plan' => $plan->slug,
                        'billing_period' => [
                            'start' => $invoice->period_start,
                            'end' => $invoice->period_end,
                        ],
                    ],
                    'processed_at' => now(),
                ]);
            }
        }

        return new Response('Webhook handled', 200);
    }

    /**
     * Handle customer subscription updated events.
     */
    public function handleCustomerSubscriptionUpdated(array $payload): Response
    {
        $event = Event::constructFrom($payload);
        $subscription = $event->data->object;

        // Find the company by customer ID
        $company = Company::where('stripe_id', $subscription->customer)->first();

        if ($company) {
            // Update company plan based on subscription
            $stripePriceId = $subscription->items->data[0]->price->id ?? null;
            
            if ($stripePriceId) {
                $plan = \App\Models\SubscriptionPlan::where('stripe_price_id', $stripePriceId)->first();
                
                if ($plan) {
                    $company->update(['plan' => $plan->slug]);
                }
            }
        }

        parent::handleCustomerSubscriptionUpdated($payload);
        return new Response('Webhook handled', 200);
    }

    /**
     * Handle customer subscription deleted events.
     */
    public function handleCustomerSubscriptionDeleted(array $payload): Response
    {
        $event = Event::constructFrom($payload);
        $subscription = $event->data->object;

        // Find the company by customer ID
        $company = Company::where('stripe_id', $subscription->customer)->first();

        if ($company) {
            // Revert to starter plan when subscription is cancelled
            $company->update(['plan' => 'starter']);
            
            // Log the subscription cancellation
            BillingHistory::create([
                'company_id' => $company->id,
                'type' => 'subscription_cancelled',
                'description' => 'Subscription cancelled - reverted to starter plan',
                'amount_cents' => 0,
                'currency' => 'usd',
                'status' => 'succeeded',
                'stripe_subscription_id' => $subscription->id,
                'credits_added' => 0,
                'metadata' => [
                    'cancelled_at' => $subscription->canceled_at,
                    'cancellation_reason' => $subscription->cancellation_details->reason ?? null,
                ],
                'processed_at' => now(),
            ]);
            
            Log::info('Subscription cancelled', [
                'company_id' => $company->id,
                'subscription_id' => $subscription->id,
            ]);
        }

        parent::handleCustomerSubscriptionDeleted($payload);
        return new Response('Webhook handled', 200);
    }

    /**
     * Handle customer subscription paused events.
     */
    public function handleCustomerSubscriptionPaused(array $payload): Response
    {
        $event = Event::constructFrom($payload);
        $subscription = $event->data->object;

        $company = Company::where('stripe_id', $subscription->customer)->first();

        if ($company) {
            // Log the subscription pause
            BillingHistory::create([
                'company_id' => $company->id,
                'type' => 'subscription_paused',
                'description' => 'Subscription paused',
                'amount_cents' => 0,
                'currency' => 'usd',
                'status' => 'succeeded',
                'stripe_subscription_id' => $subscription->id,
                'credits_added' => 0,
                'metadata' => [
                    'paused_at' => now(),
                    'pause_collection' => $subscription->pause_collection ?? null,
                ],
                'processed_at' => now(),
            ]);
            
            Log::info('Subscription paused', [
                'company_id' => $company->id,
                'subscription_id' => $subscription->id,
            ]);
        }

        return new Response('Webhook handled', 200);
    }

    /**
     * Handle customer subscription resumed events.
     */
    public function handleCustomerSubscriptionResumed(array $payload): Response
    {
        $event = Event::constructFrom($payload);
        $subscription = $event->data->object;

        $company = Company::where('stripe_id', $subscription->customer)->first();

        if ($company) {
            // Update company plan based on resumed subscription
            $stripePriceId = $subscription->items->data[0]->price->id ?? null;
            
            if ($stripePriceId) {
                $plan = \App\Models\SubscriptionPlan::where('stripe_price_id', $stripePriceId)->first();
                
                if ($plan) {
                    $company->update(['plan' => $plan->slug]);
                }
            }
            
            // Log the subscription resumption
            BillingHistory::create([
                'company_id' => $company->id,
                'type' => 'subscription_resumed',
                'description' => 'Subscription resumed',
                'amount_cents' => 0,
                'currency' => 'usd',
                'status' => 'succeeded',
                'stripe_subscription_id' => $subscription->id,
                'credits_added' => 0,
                'metadata' => [
                    'resumed_at' => now(),
                ],
                'processed_at' => now(),
            ]);
            
            Log::info('Subscription resumed', [
                'company_id' => $company->id,
                'subscription_id' => $subscription->id,
            ]);
        }

        return new Response('Webhook handled', 200);
    }

    /**
     * Handle invoice payment failed events.
     */
    public function handleInvoicePaymentFailed(array $payload): Response
    {
        $event = Event::constructFrom($payload);
        $invoice = $event->data->object;

        $company = Company::where('stripe_id', $invoice->customer)->first();

        if ($company) {
            // Log the failed payment
            BillingHistory::create([
                'company_id' => $company->id,
                'type' => 'payment_failed',
                'description' => 'Payment failed for invoice',
                'amount_cents' => $invoice->amount_due,
                'currency' => $invoice->currency,
                'status' => 'failed',
                'stripe_invoice_id' => $invoice->id,
                'credits_added' => 0,
                'metadata' => [
                    'failure_reason' => $invoice->last_finalization_error->message ?? 'Unknown',
                    'attempt_count' => $invoice->attempt_count,
                ],
                'processed_at' => now(),
            ]);
            
            Log::warning('Invoice payment failed', [
                'company_id' => $company->id,
                'invoice_id' => $invoice->id,
                'amount' => $invoice->amount_due,
            ]);
        }

        return new Response('Webhook handled', 200);
    }

    /**
     * Handle credit purchase from checkout session.
     */
    protected function handleCreditPurchase($session): void
    {
        $companyId = $session->metadata['company_id'] ?? null;
        $credits = (int) ($session->metadata['credits'] ?? 0);

        if (!$companyId || !$credits) {
            Log::warning('Invalid credit purchase metadata', [
                'session_id' => $session->id ?? 'unknown',
                'company_id' => $companyId,
                'credits' => $credits,
            ]);
            return;
        }

        $company = Company::find($companyId);

        if (!$company) {
            Log::error('Company not found for credit purchase', [
                'company_id' => $companyId,
                'session_id' => $session->id ?? 'unknown',
            ]);
            return;
        }

        try {
            $creditManager = app(CreditManager::class);
            $creditManager->addCredits(
                $company,
                $credits,
                'Credit purchase - ' . number_format($credits) . ' credits',
                [
                    'checkout_session_id' => $session->id ?? null,
                    'payment_intent_id' => $session->payment_intent ?? null,
                    'amount_paid' => $session->amount_total ?? 0,
                    'currency' => $session->currency ?? 'usd',
                ]
            );

            // Create billing history record
            BillingHistory::create([
                'company_id' => $company->id,
                'type' => 'credit_purchase',
                'description' => 'Credit purchase - ' . number_format($credits) . ' credits',
                'amount_cents' => $session->amount_total ?? 0,
                'currency' => $session->currency ?? 'usd',
                'status' => 'succeeded',
                'stripe_payment_intent_id' => $session->payment_intent ?? null,
                'credits_added' => $credits,
                'metadata' => [
                    'checkout_session_id' => $session->id ?? null,
                    'package' => $session->metadata['credits'] ?? null,
                ],
                'processed_at' => now(),
            ]);

            Log::info('Credit purchase processed successfully', [
                'company_id' => $company->id,
                'credits_added' => $credits,
                'session_id' => $session->id ?? 'unknown',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process credit purchase', [
                'company_id' => $company->id,
                'session_id' => $session->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle subscription checkout completion.
     */
    protected function handleSubscriptionCheckout($session): void
    {
        $company = Company::where('stripe_id', $session->customer)->first();

        if (!$company) {
            Log::error('Company not found for subscription checkout', [
                'customer_id' => $session->customer,
                'session_id' => $session->id,
            ]);
            return;
        }

        try {
            // The subscription should already be created by Cashier
            // We just need to update the company plan
            $subscription = $company->subscription();
            
            if ($subscription) {
                $stripePriceId = $subscription->stripe_price;
                $plan = \App\Models\SubscriptionPlan::where('stripe_price_id', $stripePriceId)->first();
                
                if ($plan) {
                    $company->update(['plan' => $plan->slug]);
                    
                    // Add initial credits for new subscription
                    $creditManager = app(CreditManager::class);
                    $creditManager->addCredits(
                        $company,
                        $plan->monthly_credits,
                        'Initial subscription credits - ' . $plan->name,
                        [
                            'subscription_id' => $subscription->stripe_id,
                            'checkout_session_id' => $session->id,
                            'plan' => $plan->slug,
                        ]
                    );

                    // Create billing history record
                    BillingHistory::create([
                        'company_id' => $company->id,
                        'type' => 'subscription_created',
                        'description' => 'New subscription created - ' . $plan->name,
                        'amount_cents' => $session->amount_total,
                        'currency' => $session->currency,
                        'status' => 'succeeded',
                        'stripe_subscription_id' => $subscription->stripe_id,
                        'credits_added' => $plan->monthly_credits,
                        'metadata' => [
                            'checkout_session_id' => $session->id,
                            'plan' => $plan->slug,
                        ],
                        'processed_at' => now(),
                    ]);

                    Log::info('Subscription checkout processed successfully', [
                        'company_id' => $company->id,
                        'plan' => $plan->slug,
                        'credits_added' => $plan->monthly_credits,
                        'session_id' => $session->id,
                    ]);
                } else {
                    Log::warning('Subscription plan not found', [
                        'stripe_price_id' => $stripePriceId,
                        'company_id' => $company->id,
                    ]);
                }
            } else {
                Log::warning('Subscription not found for company', [
                    'company_id' => $company->id,
                    'session_id' => $session->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to process subscription checkout', [
                'company_id' => $company->id,
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}