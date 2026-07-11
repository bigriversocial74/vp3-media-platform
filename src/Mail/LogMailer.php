<?php
declare(strict_types=1);
namespace VP3\Mail;
final class LogMailer implements MailerInterface
{
    public function send(string $to,string $subject,string $body): void
    {
        $record=json_encode(['time'=>gmdate('c'),'to'=>$to,'subject'=>$subject,'body'=>$body],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
        file_put_contents(VP3_ROOT.'/var/logs/mail-'.gmdate('Y-m-d').'.log',$record,FILE_APPEND|LOCK_EX);
    }
}
