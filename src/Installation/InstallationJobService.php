<?php
declare(strict_types=1);
namespace VP3\Installation;

use PDO;
use RuntimeException;
use Throwable;
use VP3\Hosting\HostingProviderFactory;
use VP3\Hosting\HostingProviderInterface;
use VP3\Hosting\ManualHostingProvider;

final class InstallationJobService
{
    private const STEPS = [
        'validate_order','validate_hosting','validate_license','reserve_subdomain',
        'create_hosting_space','create_database','deploy_release','write_environment',
        'run_migrations','create_owner','create_activation','verify_health','complete','notify',
    ];

    public function __construct(private PDO $db) {}

    public function queue(int $hostingAccountId, ?string $version): string
    {
        $existing = $this->db->prepare("SELECT job_uuid FROM installation_jobs WHERE hosting_account_id=? AND job_status IN ('queued','running','waiting_manual') LIMIT 1");
        $existing->execute([$hostingAccountId]);
        $uuid = $existing->fetchColumn();
        if (is_string($uuid)) {
            return $uuid;
        }
        $uuid = \vp3_uuid();
        $stmt = $this->db->prepare("INSERT INTO installation_jobs(job_uuid,hosting_account_id,product_id,requested_version,job_status,current_step,progress_percent,created_at,updated_at) SELECT ?,h.id,h.product_id,?,'queued','validate_order',0,NOW(),NOW() FROM hosting_accounts h WHERE h.id=?");
        $stmt->execute([$uuid,$version,$hostingAccountId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Hosting account not found.');
        }
        $this->db->prepare("UPDATE hosting_accounts SET installation_status='queued',updated_at=NOW() WHERE id=?")->execute([$hostingAccountId]);
        \vp3_audit('system',null,'installation.queued','installation_job',$uuid,['hosting_account_id'=>$hostingAccountId,'version'=>$version]);
        return $uuid;
    }

    public function confirmManualStep(string $jobUuid, int $adminId): void
    {
        $job=$this->load($jobUuid);
        if($job['job_status']!=='waiting_manual')throw new RuntimeException('Job is not waiting for manual confirmation.');
        $this->event((int)$job['job_id'],(string)$job['current_step'],'completed','Confirmed by administrator.');
        $index=array_search((string)$job['current_step'],self::STEPS,true);
        $percent=$index===false?0:(int)floor((($index+1)/count(self::STEPS))*100);
        $next=$index===false?self::STEPS[0]:self::STEPS[min($index+1,count(self::STEPS)-1)];
        $this->db->prepare("UPDATE installation_jobs SET job_status='queued',current_step=?,progress_percent=?,failure_message=NULL,updated_at=NOW() WHERE id=?")->execute([$next,$percent,(int)$job['job_id']]);
        $this->db->prepare("UPDATE hosting_accounts SET installation_status='queued',updated_at=NOW() WHERE id=?")->execute([(int)$job['hosting_account_id']]);
        \vp3_audit('admin',$adminId,'installation.manual_step_confirmed','installation_job',$jobUuid,['step'=>$job['current_step']]);
    }

    public function run(string $jobUuid): array
    {
        $lockPath = VP3_ROOT . '/var/locks/install-' . preg_replace('/[^a-zA-Z0-9-]/','',$jobUuid) . '.lock';
        $handle = fopen($lockPath,'c+');
        if ($handle === false || !flock($handle,LOCK_EX|LOCK_NB)) {
            throw new RuntimeException('Installation job is already running.');
        }
        try {
            $job = $this->load($jobUuid);
            if ($job['job_status'] === 'completed') {
                return ['job_uuid'=>$jobUuid,'job_status'=>'completed','progress_percent'=>100];
            }
            if ($job['job_status'] === 'cancelled') {
                throw new RuntimeException('Installation job is cancelled.');
            }
            $this->db->prepare("UPDATE installation_jobs SET job_status='running',failure_message=NULL,started_at=COALESCE(started_at,NOW()),updated_at=NOW() WHERE id=?")->execute([(int)$job['job_id']]);
            $this->db->prepare("UPDATE hosting_accounts SET installation_status='running',hosting_status=IF(hosting_status='pending','provisioning',hosting_status),updated_at=NOW() WHERE id=?")->execute([(int)$job['hosting_account_id']]);
            $completed = $this->completedSteps((int)$job['job_id']);
            $provider = HostingProviderFactory::make((string)$job['provider']);
            $release = $this->release((int)$job['product_db_id'], $job['requested_version']);
            if (!$job['requested_version']) {
                $this->db->prepare('UPDATE installation_jobs SET requested_version=?,updated_at=NOW() WHERE id=?')->execute([$release['version'],(int)$job['job_id']]);
                $job['requested_version'] = $release['version'];
            }
            foreach (self::STEPS as $index=>$step) {
                if (isset($completed[$step])) {
                    continue;
                }
                $this->db->prepare('UPDATE installation_jobs SET current_step=?,updated_at=NOW() WHERE id=?')->execute([$step,(int)$job['job_id']]);
                $this->event((int)$job['job_id'],$step,'started');
                try {
                    $this->executeStep($step,$job,$release,$provider);
                    $percent=(int)floor((($index+1)/count(self::STEPS))*100);
                    $next=self::STEPS[min($index+1,count(self::STEPS)-1)];
                    $this->event((int)$job['job_id'],$step,'completed');
                    $this->db->prepare('UPDATE installation_jobs SET current_step=?,progress_percent=?,updated_at=NOW() WHERE id=?')->execute([$next,$percent,(int)$job['job_id']]);
                } catch (ManualActionRequired $e) {
                    $message=substr($e->getMessage(),0,500);
                    $this->event((int)$job['job_id'],$step,'waiting',$message);
                    $this->db->prepare("UPDATE installation_jobs SET job_status='waiting_manual',current_step=?,failure_message=?,updated_at=NOW() WHERE id=?")->execute([$step,$message,(int)$job['job_id']]);
                    $this->db->prepare("UPDATE hosting_accounts SET installation_status='waiting_manual',updated_at=NOW() WHERE id=?")->execute([(int)$job['hosting_account_id']]);
                    \vp3_audit('system',null,'installation.waiting_manual','installation_job',$jobUuid,['step'=>$step]);
                    return ['job_uuid'=>$jobUuid,'job_status'=>'waiting_manual','current_step'=>$step,'progress_percent'=>(int)$job['progress_percent']];
                } catch (Throwable $e) {
                    $message=substr($e->getMessage(),0,500);
                    $this->event((int)$job['job_id'],$step,'failed',$message);
                    $this->db->prepare("UPDATE installation_jobs SET job_status='failed',current_step=?,failure_message=?,updated_at=NOW() WHERE id=?")->execute([$step,$message,(int)$job['job_id']]);
                    $this->db->prepare("UPDATE hosting_accounts SET installation_status='failed',updated_at=NOW() WHERE id=?")->execute([(int)$job['hosting_account_id']]);
                    \vp3_audit('system',null,'installation.failed','installation_job',$jobUuid,['step'=>$step,'message'=>$message]);
                    throw $e;
                }
            }
            $this->db->prepare("UPDATE installation_jobs SET job_status='completed',current_step='complete',progress_percent=100,completed_at=NOW(),failure_message=NULL,updated_at=NOW() WHERE id=?")->execute([(int)$job['job_id']]);
            $this->db->prepare("UPDATE hosting_accounts SET hosting_status='active',installation_status='completed',installed_version=?,provisioned_at=COALESCE(provisioned_at,NOW()),updated_at=NOW() WHERE id=?")->execute([$release['version'],(int)$job['hosting_account_id']]);
            \vp3_audit('system',null,'installation.completed','installation_job',$jobUuid,['version'=>$release['version']]);
            return ['job_uuid'=>$jobUuid,'job_status'=>'completed','progress_percent'=>100,'installed_version'=>$release['version']];
        } finally {
            flock($handle,LOCK_UN);
            fclose($handle);
        }
    }

    private function load(string $jobUuid): array
    {
        $stmt=$this->db->prepare('SELECT ij.id job_id,ij.job_uuid,ij.requested_version,ij.job_status,ij.current_step,ij.progress_percent,h.id hosting_account_id,h.hosting_uuid,h.customer_id,h.product_id product_db_id,h.plan_id,h.license_id,h.order_id,h.subdomain,h.custom_domain,h.hosting_status,h.installation_status,h.installed_version,h.environment,h.provider,o.order_status,o.payment_status,l.status license_status,l.expires_at,p.product_id catalog_product_id FROM installation_jobs ij JOIN hosting_accounts h ON h.id=ij.hosting_account_id LEFT JOIN orders o ON o.id=h.order_id LEFT JOIN licenses l ON l.id=h.license_id JOIN products p ON p.id=h.product_id WHERE ij.job_uuid=? LIMIT 1');
        $stmt->execute([$jobUuid]);$job=$stmt->fetch();
        if(!is_array($job))throw new RuntimeException('Installation job not found.');
        return $job;
    }

    private function completedSteps(int $jobId): array
    {
        $stmt=$this->db->prepare("SELECT step FROM installation_job_events WHERE installation_job_id=? AND status='completed'");$stmt->execute([$jobId]);
        $done=[];foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $step)$done[(string)$step]=true;return $done;
    }

    private function executeStep(string $step,array $job,array $release,HostingProviderInterface $provider): void
    {
        match($step){
            'validate_order'=>$this->assert($job['payment_status']==='paid'||$job['environment']==='development','Paid order required.'),
            'validate_hosting'=>$this->assert(in_array($job['hosting_status'],['pending','provisioning','active'],true),'Hosting account unavailable.'),
            'validate_license'=>$this->validateLicense($job),
            'reserve_subdomain'=>$this->providerResult($provider->subdomainCheck((string)$job['subdomain'],(string)\vp3_config('hosting.base_domain')),$step),
            'create_hosting_space'=>$this->providerResult($provider->provision($job),$step),
            'deploy_release'=>$this->providerResult($provider->install($job,$release),$step),
            'complete'=>$this->db->prepare("UPDATE hosting_accounts SET installed_version=?,installation_status='completed',updated_at=NOW() WHERE id=?")->execute([$release['version'],(int)$job['hosting_account_id']]),
            default=>$provider instanceof ManualHostingProvider ? throw new ManualActionRequired("Manual action required for {$step}.") : true,
        };
    }

    private function providerResult(array $result,string $step): bool
    {
        if (($result['status']??'') === 'manual_action_required') {
            throw new ManualActionRequired("Manual provider action required for {$step}.");
        }
        return $this->assert(($result['ok']??false)===true,"Provider operation failed for {$step}.");
    }

    private function validateLicense(array $job): bool
    {
        $this->assert(in_array($job['license_status'],['active','development'],true),'Active license required.');
        if ($job['expires_at'] !== null && (string)$job['expires_at'] < date('Y-m-d')) {
            throw new RuntimeException('License expired.');
        }
        return true;
    }

    private function release(int $productId,?string $version): array
    {
        $sql="SELECT * FROM product_releases WHERE product_id=? AND status='published'";$params=[$productId];
        if($version){$sql.=' AND version=?';$params[]=$version;}
        $sql.=' ORDER BY published_at DESC,id DESC LIMIT 1';$stmt=$this->db->prepare($sql);$stmt->execute($params);$release=$stmt->fetch();
        if(!is_array($release))throw new RuntimeException('Approved release not found.');return $release;
    }

    private function assert(bool $condition,string $message): bool
    {
        if(!$condition)throw new RuntimeException($message);return true;
    }

    private function event(int $jobId,string $step,string $status,?string $message=null): void
    {
        $this->db->prepare('INSERT INTO installation_job_events(installation_job_id,step,status,message,created_at) VALUES(?,?,?,?,NOW())')->execute([$jobId,$step,$status,$message]);
    }
}
