<?php

namespace App\Demo;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/** En mode démo, toute écriture est refusée sauf le chat (qui ne modifie rien). */
#[AsEventListener(event: RequestEvent::class)]
final readonly class DemoReadOnlyListener
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(
        #[Autowire('%app.demo_mode%')]
        private bool $demoMode,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->demoMode || !$event->isMainRequest()
            || \in_array($request->getMethod(), self::SAFE_METHODS, true)
            || '/api/chat' === $request->getPathInfo()) {
            return;
        }

        $event->setResponse(new JsonResponse(['error' => 'Démo en lecture seule : modification désactivée.'], 403));
    }
}
