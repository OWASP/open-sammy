<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Twig\Environment;

class PasswordResetRateLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RateLimiterFactory $passwordResetLimiter,
        private readonly Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest()) {
            return;
        }

        $route = $request->attributes->get('_route');
        if ($route !== 'app_login_reset_password_request' || !$request->isMethod('POST')) {
            return;
        }

        $limiter = $this->passwordResetLimiter->create($request->getClientIp());
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter()->getTimestamp() - time();
            $event->setResponse(new Response(
                $this->twig->render('application/auth/rate_limited.html.twig', [
                    'retry_after' => $retryAfter,
                ]),
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => $retryAfter]
            ));
        }
    }
}
