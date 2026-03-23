<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

class LoginRateLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RateLimiterFactory $loginLimiter,
        private readonly RouterInterface $router,
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
        if ($route !== 'app_login_login' || !$request->isMethod('POST')) {
            return;
        }

        $limiter = $this->loginLimiter->create($request->getClientIp());
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
