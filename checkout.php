<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use VP3\Payments\PaymentProviderFactory;
$customer=vp3_customer();$planKey=vp3_input('plan','hosted')==='self-hosted'?'self-hosted-standard':'vp3-hosted-standard';$plan=null;
if(vp3_db_available()){$stmt=vp3_db()->prepare("SELECT pp.*,p.name product_name,p.id product_db_id FROM product_plans pp JOIN products p ON p.id=pp.product_id WHERE pp.plan_key=? AND pp.status='active' AND p.status='active' LIMIT 1");$stmt->execute([$planKey]);$plan=$stmt->fetch();}
if(empty($_SESSION['checkout_intent']))$_SESSION['checkout_intent']=vp3_secure_token(24);
if(vp3_method()==='POST'){
    vp3_verify_csrf();
    if(!$customer){vp3_flash('error','Create and verify an account before checkout.');vp3_redirect('signup.php');}
    if(!$plan||!vp3_db_available()){vp3_flash('error','The selected plan is not available.');vp3_redirect('pricing.php');}
    $intent=(string)($_POST['checkout_intent']??'');if($intent===''||!hash_equals((string)$_SESSION['checkout_intent'],$intent)){http_response_code(409);exit('Checkout request expired.');}
    $idempotency=hash('sha256',$intent);$providerName=(string)vp3_config('payments.provider','manual');
    try{
        $result=vp3_transaction(function(PDO $pdo)use($customer,$plan,$idempotency,$providerName):array{
            $existing=$pdo->prepare('SELECT id,order_number FROM orders WHERE idempotency_key=? LIMIT 1');$existing->execute([$idempotency]);$row=$existing->fetch();if(is_array($row))return ['id'=>(int)$row['id'],'order_number'=>$row['order_number']];
            $orderNumber='VP3-'.gmdate('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)),0,8));$amount=(int)$plan['price_cents'];
            $stmt=$pdo->prepare("INSERT INTO orders (order_number,customer_id,order_status,payment_status,subtotal_cents,tax_cents,total_cents,currency,payment_provider,idempotency_key,created_at,updated_at) VALUES (?,?,'pending','pending',?,0,?,'USD',?,?,NOW(),NOW())");$stmt->execute([$orderNumber,$customer['id'],$amount,$amount,$providerName,$idempotency]);$orderId=(int)$pdo->lastInsertId();
            $stmt=$pdo->prepare('INSERT INTO order_items (order_id,product_id,plan_id,item_name,quantity,unit_price_cents,total_cents,created_at) VALUES (?,?,?,?,1,?,?,NOW())');$stmt->execute([$orderId,$plan['product_db_id'],$plan['id'],$plan['product_name'].' · '.$plan['name'],$amount,$amount]);
            vp3_audit('customer',(int)$customer['id'],'order.created','order',$orderNumber,['plan_key'=>$plan['plan_key'],'payment_provider'=>$providerName]);
            return ['id'=>$orderId,'order_number'=>$orderNumber];
        });
        $_SESSION['last_order_id']=$result['id'];unset($_SESSION['checkout_intent']);
        $provider=PaymentProviderFactory::make($providerName);$payment=$provider->createCheckout(['id'=>$result['id'],'order_number'=>$result['order_number'],'total_cents'=>$plan['price_cents'],'currency'=>'USD'],$customer,[$plan]);
        if(!empty($payment['checkout_url'])){header('Location: '.(string)$payment['checkout_url'],true,303);exit;}
        vp3_redirect('order-complete.php');
    }catch(Throwable $e){vp3_log('error','Checkout initialization failed',['message'=>$e->getMessage(),'provider'=>$providerName]);vp3_flash('error','Checkout could not be initialized. The pending order remains available to VP3 support.');vp3_redirect('account-orders.php');}
}
$pageTitle='Checkout';require VP3_ROOT.'/includes/header.php';?>
<section class="form-shell"><span class="eyebrow">Checkout foundation</span><h1><?=vp3_e($plan['name']??'Launch plan')?></h1><p>Orders are provider-neutral. Live Stripe Checkout is not enabled until credentials, products, signed webhooks, and reconciliation tests are configured.</p><?php if($plan):?><div class="card"><h3><?=vp3_e($plan['product_name'])?></h3><p><?=vp3_e($plan['hosting_type']==='vp3_hosted'?'Hosted subdomain, connected license, installation job, version visibility, and managed support.':'Product license, authorized domain activation, eligible releases, and guided installation.')?></p><strong><?=((int)$plan['price_cents']>0)?vp3_e(vp3_money((int)$plan['price_cents'])):'Sales quote required'?></strong></div><?php endif;?><form method="post"><?=vp3_csrf_field()?><input type="hidden" name="plan" value="<?=vp3_e($planKey==='self-hosted-standard'?'self-hosted':'hosted')?>"><input type="hidden" name="checkout_intent" value="<?=vp3_e((string)$_SESSION['checkout_intent'])?>"><button class="button" type="submit"><?= $customer?'Create pending order':'Create account to continue' ?></button></form></section>
<?php require VP3_ROOT.'/includes/footer.php';?>
