<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

class MfaRateLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RateLimiterFactory $mfaLimiter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TwoFactorAuthenticationEvents::ATTEMPT => ['onMfaAttempt', 20],
            TwoFactorAuthenticationEvents::FAILURE => 'onMfaFailure',
        ];
    }

    public function onMfaAttempt(TwoFactorAuthenticationEvent $event): void
    {
        $request = $event->getRequest();
        $limiter = $this->mfaLimiter->create($request->getClientIp());
        if ($limiter->consume(0)->isAccepted() === false) {
            throw new TooManyLoginAttemptsAuthenticationException();
        }
    }

    public function onMfaFailure(TwoFactorAuthenticationEvent $event): void
    {
        $request = $event->getRequest();
        $limiter = $this->mfaLimiter->create($request->getClientIp());
        $limiter->consume(1);
    }
}
