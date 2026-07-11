<?php
declare(strict_types=1);
namespace VP3\Mail;
use RuntimeException;
final class MailerFactory
{
    public static function make(): MailerInterface
    {
        return match((string)\vp3_config('mail.transport','log')){'log'=>new LogMailer(),default=>throw new RuntimeException('Unsupported mail transport.')};
    }
}
