<?php

declare(strict_types=1);

namespace Cadenza\Runtime\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The headers every public site should send, set once for every response: no MIME sniffing, no framing by other
 * sites (the studio's previews are frames of the site itself), a short referrer, and no PHP version on display.
 */
#[AsEventListener(KernelEvents::RESPONSE, priority: -100)]
final class SecurityHeadersListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $headers = $event->getResponse()->headers;
        // A widget's frame is sandboxed by the page that embeds it and has headers of its own.
        $widget = str_starts_with($event->getRequest()->getPathInfo(), '/_cadenza/widget');
        foreach ([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ] as $name => $value) {
            if (!$headers->has($name) && !($widget && 'X-Frame-Options' === $name)) {
                $headers->set($name, $value);
            }
        }
        if (\function_exists('header_remove') && !headers_sent()) {
            header_remove('X-Powered-By');
        }
    }
}
