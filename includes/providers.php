<?php
declare(strict_types=1);

use VP3\Hosting\HostingProviderFactory;

function vp3_subdomain_check(string $subdomain): array
{
    return HostingProviderFactory::make()->subdomainCheck($subdomain, (string)vp3_config('hosting.base_domain'));
}
function vp3_subdomain_create(array $account): array { return HostingProviderFactory::make()->subdomainCreate($account); }
function vp3_hosting_provision(array $account): array { return HostingProviderFactory::make()->provision($account); }
function vp3_product_install(array $account,array $release): array { return HostingProviderFactory::make()->install($account,$release); }
function vp3_product_upgrade(array $account,array $release): array { return HostingProviderFactory::make()->upgrade($account,$release); }
function vp3_hosting_suspend(array $account): array { return HostingProviderFactory::make()->suspend($account); }
function vp3_hosting_restore(array $account): array { return HostingProviderFactory::make()->restore($account); }
