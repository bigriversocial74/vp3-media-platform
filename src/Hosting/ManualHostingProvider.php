<?php
declare(strict_types=1);
namespace VP3\Hosting;
final class ManualHostingProvider implements HostingProviderInterface
{
    private function result(string $operation,array $context=[]): array{return ['ok'=>true,'provider'=>'manual','operation'=>$operation,'status'=>'manual_action_required','context'=>$context];}
    public function subdomainCheck(string $subdomain,string $baseDomain): array{return $this->result('subdomain_check',['hostname'=>"{$subdomain}.{$baseDomain}"]);}
    public function subdomainCreate(array $account): array{return $this->result('subdomain_create',['hosting_uuid'=>$account['hosting_uuid']??null]);}
    public function provision(array $account): array{return $this->result('hosting_provision',['hosting_uuid'=>$account['hosting_uuid']??null]);}
    public function install(array $account,array $release): array{return $this->result('product_install',['version'=>$release['version']??null]);}
    public function upgrade(array $account,array $release): array{return $this->result('product_upgrade',['version'=>$release['version']??null]);}
    public function suspend(array $account): array{return $this->result('hosting_suspend');}
    public function restore(array $account): array{return $this->result('hosting_restore');}
}
