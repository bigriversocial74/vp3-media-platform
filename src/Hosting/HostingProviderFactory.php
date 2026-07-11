<?php
declare(strict_types=1);
namespace VP3\Hosting;
use RuntimeException;
final class HostingProviderFactory
{
    public static function make(?string $name=null): HostingProviderInterface
    {
        return match($name??(string)\vp3_config('hosting.provider','manual')){
            'manual'=>new ManualHostingProvider(),
            'local_simulator'=>new LocalSimulatorProvider(),
            default=>throw new RuntimeException('Unsupported hosting provider.'),
        };
    }
}
