<?php

declare(strict_types=1);

namespace App\Tests\_support;

use App\Service\MailingService;

class StubMailingService extends MailingService
{
    protected function sendMail(
        string $to,
        string $toName,
        string $subject,
        string $message,
        ?string $attachmentFile = null,
        ?string $from = null,
        string $fromName = 'SAMMY Mailing System'
    ): bool {
        return true;
    }
}
