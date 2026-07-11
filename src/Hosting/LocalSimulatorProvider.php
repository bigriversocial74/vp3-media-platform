<?php
declare(strict_types=1);
namespace VP3\Hosting;
final class LocalSimulatorProvider implements HostingProviderInterface
{
    private function ok(string $operation,array $context=[]): array{return ['ok'=>true,'provider'=>'local_simulator','operation'=>$operation,'status'=>'simulated','context'=>$context,'receipt'=>hash('sha256',$operation.'|'.json_encode($context))];}
    public function subdomainCheck(string $subdomain,string $baseDomain): array{return $this->ok('subdomain_check',['available'=>preg_match('/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/',$subdomain)===1,'hostname'=>"{$subdomain}.{$baseDomain}"]);}
    public function subdomainCreate(array $account): array{return $this->ok('subdomain_create',['hosting_uuid'=>$account['hosting_uuid']??null]);}
    public function provision(array $account): array{return $this->ok('hosting_provision',['hosting_uuid'=>$account['hosting_uuid']??null]);}
    public function install(array $account,array $release): array{return $this->ok('product_install',['hosting_uuid'=>$account['hosting_uuid']??null,'version'=>$release['version']??null]);}
    public function upgrade(array $account,array $release): array{return $this->ok('product_upgrade',['version'=>$release['version']??null]);}
    public function suspend(array $account): array{return $this->ok('hosting_suspend');}
    public function restore(array $account): array{return $this->ok('hosting_restore');}
}
