<?php
declare(strict_types=1);
namespace VP3\Payments;
use RuntimeException;
final class PaymentProviderFactory
{
    public static function make(?string $name=null): PaymentProviderInterface
    {
        return match($name??(string)\vp3_config('payments.provider','manual')){
            'manual'=>new ManualPaymentProvider(),
            'stripe'=>new StripeCheckoutProvider(),
            default=>throw new RuntimeException('Unsupported payment provider.'),
        };
    }
}
