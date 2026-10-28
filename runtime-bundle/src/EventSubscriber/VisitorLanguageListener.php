<?php

declare(strict_types=1);

namespace Cadenza\Runtime\EventSubscriber;

use Cadenza\Runtime\Twig\RenderState;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * A brand-new site has no language yet: its own screens (first run, sign-in) speak the visitor's.
 */
#[AsEventListener(KernelEvents::REQUEST)]
final class VisitorLanguageListener
{
    public function __construct(private readonly RenderState $state)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->state->visitorLanguage = $event->getRequest()->getPreferredLanguage(['en', 'pl']) ?? 'en';
        }
    }
}
