<?php
declare(strict_types=1);
namespace VP3\Network;

use PDO;

final class PublicLicenseService
{
    public function __construct(private PDO $db) {}

    public function verify(string $verificationId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT ppl.listing_uuid,ppl.display_name,ppl.public_domain,ppl.hosting_type,ppl.verification_id,ppl.verification_status,
                    ppl.launched_at,p.name AS product_name,l.edition,l.status AS license_status
             FROM public_platform_listings ppl
             JOIN licenses l ON l.id=ppl.license_id
             JOIN products p ON p.id=ppl.product_id
             WHERE ppl.verification_id=? AND ppl.listing_status='published' LIMIT 1"
        );
        $stmt->execute([strtoupper(trim($verificationId))]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }
}
