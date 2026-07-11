<?php
declare(strict_types=1);
namespace VP3\Payments;
final class ManualPaymentProvider implements PaymentProviderInterface
{
    private function result(string $operation,array $context=[]): array{return ['ok'=>true,'provider'=>'manual','operation'=>$operation,'status'=>'manual_action_required','context'=>$context];}
    public function createCheckout(array $order,array $customer,array $items): array{return $this->result('create_checkout',['order_number'=>$order['order_number']??null]);}
    public function createSubscription(array $order,array $customer,array $plan): array{return $this->result('create_subscription',['order_number'=>$order['order_number']??null]);}
    public function refund(string $paymentReference,int $amountCents): array{return $this->result('refund',['payment_reference'=>$paymentReference,'amount_cents'=>$amountCents]);}
    public function cancelSubscription(string $subscriptionReference): array{return $this->result('cancel_subscription',['subscription_reference'=>$subscriptionReference]);}
}
