<?php
declare(strict_types=1);
namespace VP3\Payments;
use RuntimeException;
final class StripeCheckoutProvider implements PaymentProviderInterface
{
    private function unavailable(): never
    {
        if((string)\vp3_config('payments.stripe_secret_key')==='')throw new RuntimeException('Stripe is not configured.');
        throw new RuntimeException('Stripe adapter requires an approved SDK integration and live webhook reconciliation tests.');
    }
    public function createCheckout(array $order,array $customer,array $items): array{$this->unavailable();}
    public function createSubscription(array $order,array $customer,array $plan): array{$this->unavailable();}
    public function refund(string $paymentReference,int $amountCents): array{$this->unavailable();}
    public function cancelSubscription(string $subscriptionReference): array{$this->unavailable();}
}
