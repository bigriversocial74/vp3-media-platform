<?php
declare(strict_types=1);
namespace VP3\Hosting;
interface HostingProviderInterface
{
    public function subdomainCheck(string $subdomain,string $baseDomain): array;
    public function subdomainCreate(array $account): array;
    public function provision(array $account): array;
    public function install(array $account,array $release): array;
    public function upgrade(array $account,array $release): array;
    public function suspend(array $account): array;
    public function restore(array $account): array;
}
