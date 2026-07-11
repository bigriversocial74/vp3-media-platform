<?php
declare(strict_types=1);
namespace VP3\Hosting;

use PDO;
use RuntimeException;

final class HostingAccountService
{
    public function __construct(private PDO $db) {}

    public function create(array $data): int
    {
        $subdomain=strtolower(trim((string)($data['subdomain']??'')));
        $environment=(string)($data['environment']??'production');
        $providerName=(string)($data['provider']??'manual');
        if(!preg_match('/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/',$subdomain))throw new RuntimeException('invalid_subdomain');
        if(!in_array($environment,['production','staging','development'],true))throw new RuntimeException('invalid_environment');
        $customerId=(int)($data['customer_id']??0);$productId=(int)($data['product_id']??0);$planId=(int)($data['plan_id']??0);$licenseId=(int)($data['license_id']??0);$orderId=(int)($data['order_id']??0);
        return \vp3_transaction(function(PDO $db)use($data,$subdomain,$environment,$providerName,$customerId,$productId,$planId,$licenseId,$orderId):int{
            $s=$db->prepare("SELECT id FROM customers WHERE id=? AND status='active'");$s->execute([$customerId]);if(!$s->fetchColumn())throw new RuntimeException('active_customer_required');
            $s=$db->prepare("SELECT id FROM products WHERE id=? AND status='active' AND hosted_enabled=1");$s->execute([$productId]);if(!$s->fetchColumn())throw new RuntimeException('hosted_product_required');
            if($planId){$s=$db->prepare("SELECT id FROM product_plans WHERE id=? AND product_id=? AND status='active' AND hosting_type IN ('vp3_hosted','enterprise')");$s->execute([$planId,$productId]);if(!$s->fetchColumn())throw new RuntimeException('hosting_plan_mismatch');}
            if($licenseId){$s=$db->prepare("SELECT id FROM licenses WHERE id=? AND customer_id=? AND product_id=? AND status IN ('active','development')");$s->execute([$licenseId,$customerId,$productId]);if(!$s->fetchColumn())throw new RuntimeException('hosting_license_mismatch');}elseif($environment!=='development'){throw new RuntimeException('active_license_required');}
            if($orderId){$s=$db->prepare("SELECT id FROM orders WHERE id=? AND customer_id=? AND payment_status='paid'");$s->execute([$orderId,$customerId]);if(!$s->fetchColumn())throw new RuntimeException('paid_order_mismatch');}elseif($environment!=='development'){throw new RuntimeException('paid_order_required');}
            $provider=HostingProviderFactory::make($providerName);$check=$provider->subdomainCheck($subdomain,(string)\vp3_config('hosting.base_domain'));if(!($check['ok']??false)||(($check['context']['available']??true)===false))throw new RuntimeException('subdomain_unavailable');
            $custom=trim((string)($data['custom_domain']??''));$custom=$custom!==''?\vp3_normalize_domain($custom):null;
            $stmt=$db->prepare("INSERT INTO hosting_accounts(hosting_uuid,customer_id,product_id,plan_id,license_id,order_id,subdomain,custom_domain,hosting_status,installation_status,environment,provider,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,'pending','not_started',?,?,NOW(),NOW())");
            $stmt->execute([\vp3_uuid(),$customerId,$productId,$planId?:null,$licenseId?:null,$orderId?:null,$subdomain,$custom,$environment,$providerName]);
            $id=(int)$db->lastInsertId();\vp3_audit('admin',isset($_SESSION['admin_id'])?(int)$_SESSION['admin_id']:null,'hosting.created','hosting_account',(string)$id,['subdomain'=>$subdomain,'customer_id'=>$customerId,'product_id'=>$productId]);return $id;
        });
    }
}
