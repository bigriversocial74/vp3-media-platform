<?php
declare(strict_types=1);
namespace VP3\Payments;
interface PaymentProviderInterface
{
    public function createCheckout(array $order,array $customer,array $items): array;
    public function createSubscription(array $order,array $customer,array $plan): array;
    public function refund(string $paymentReference,int $amountCents): array;
    public function cancelSubscription(string $subscriptionReference): array;
}
