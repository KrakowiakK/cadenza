<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Controller;

use Cadenza\Runtime\Entity\Media;
use Cadenza\Runtime\Media\AlbumBuilder;
use Cadenza\Runtime\Media\MediaLibrary;
use Cadenza\Runtime\Message\MediaImported;
use Cadenza\Runtime\Owner\OwnerResolver;
use Cadenza\Runtime\Phone\PhoneSessions;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * "Photos from your phone": the panel shows a QR code, the phone opens a tiny page, picks photos from the camera roll
 * and sends them. No account, no app, no keys. EXIF dates and places turn them into albums.
 */
#[AsController]
final class PhoneController
{
    public function __construct(
        private readonly PhoneSessions $sessions,
        private readonly OwnerResolver $owner,
        private readonly MediaLibrary $media,
        private readonly AlbumBuilder $albums,
        private readonly EntityManagerInterface $em,
        #[Target('cadenza.event_bus')]
        private readonly MessageBusInterface $events,
        private readonly Environment $twig,
        #[Autowire('%env(default::CADENZA_LAN_URL)%')]
        private readonly ?string $lanUrl = null,
    ) {
    }

    #[Route('/_cadenza/phone', name: 'cadenza_phone_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if (!$this->owner->isOwner($request)) {
            return new JsonResponse(['error' => 'Only the owner can receive photos.'], 403);
        }
        $locale = str_starts_with((string) $request->getPreferredLanguage(['pl', 'en']), 'pl') ? 'pl' : 'en';
        $session = $this->sessions->create($locale);
        $url = $this->baseUrl($request).'/_cadenza/phone/'.$session['token'];
        $qr = (new SvgWriter())->write(new QrCode(data: $url, size: 240, margin: 8))->getString();

        return new JsonResponse(['token' => $session['token'], 'url' => $url, 'qr' => $qr]);
    }

    #[Route('/_cadenza/phone/{token}', name: 'cadenza_phone_page', requirements: ['token' => '[a-f0-9]{32}'], methods: ['GET'])]
    public function page(string $token): Response
    {
        $session = $this->sessions->get($token);
        if (null === $session) {
            return new Response($this->twig->render('@CadenzaRuntime/phone/expired.html.twig'), 410);
        }
        if ('waiting' === $session['status']) {
            $session['status'] = 'open';
            $this->sessions->save($session);
        }

        return new Response($this->twig->render('@CadenzaRuntime/phone/upload.html.twig', ['token' => $token, 'locale' => $session['locale']]));
    }

    #[Route('/_cadenza/phone/{token}/upload', name: 'cadenza_phone_upload', requirements: ['token' => '[a-f0-9]{32}'], methods: ['POST'])]
    public function upload(Request $request, string $token): JsonResponse
    {
        $session = $this->sessions->get($token);
        $file = $request->files->get('photo');
        if (null === $session || 'done' === $session['status'] || null === $file || !$file->isValid()) {
            return new JsonResponse(['error' => 'Upload not accepted.'], 400);
        }
        try {
            $media = $this->media->store((string) file_get_contents($file->getPathname()), ['source' => 'phone', 'alt' => '']);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 415);
        }
        $session = $this->sessions->get($token) ?? $session;
        $session['status'] = 'receiving';
        $session['media'][] = (int) $media->getId();
        $this->sessions->save($session);
        $this->events->dispatch(new MediaImported((int) $media->getId(), 'phone', 'phone:'.$media->getId(), 0));

        return new JsonResponse(['ok' => true, 'path' => $media->getPath()]);
    }

    #[Route('/_cadenza/phone/{token}/finish', name: 'cadenza_phone_finish', requirements: ['token' => '[a-f0-9]{32}'], methods: ['POST'])]
    public function finish(string $token): JsonResponse
    {
        $session = $this->sessions->get($token);
        if (null === $session) {
            return new JsonResponse(['error' => 'Expired.'], 410);
        }
        $photos = $session['media'] ? $this->em->getRepository(Media::class)->findBy(['id' => $session['media']]) : [];
        $session['albums'] = $this->albums->build($photos, $session['locale']);
        $session['status'] = 'done';
        $this->sessions->save($session);

        return new JsonResponse(['ok' => true, 'albums' => $session['albums']]);
    }

    #[Route('/_cadenza/phone/{token}/status', name: 'cadenza_phone_status', requirements: ['token' => '[a-f0-9]{32}'], methods: ['GET'])]
    public function status(Request $request, string $token): JsonResponse
    {
        if (!$this->owner->isOwner($request)) {
            return new JsonResponse(['error' => 'Forbidden.'], 403);
        }
        $session = $this->sessions->get($token);
        if (null === $session) {
            return new JsonResponse(['status' => 'expired']);
        }
        $recent = \array_slice($session['media'], -8);
        $thumbs = $recent ? array_map(static fn (Media $m): string => $m->getPath(), $this->em->getRepository(Media::class)->findBy(['id' => $recent])) : [];

        return new JsonResponse(['status' => $session['status'], 'count' => \count($session['media']), 'thumbs' => $thumbs, 'albums' => $session['albums']]);
    }

    /**
     * The phone cannot open 127.0.0.1: use the configured LAN URL, or this computer's address in the local network.
     */
    private function baseUrl(Request $request): string
    {
        if ($this->lanUrl) {
            return rtrim($this->lanUrl, '/');
        }
        $host = $request->getHost();
        if (\in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            $socket = @stream_socket_client('udp://8.8.8.8:53');
            if (false !== $socket) {
                $host = explode(':', (string) stream_socket_get_name($socket, false))[0] ?: $host;
                fclose($socket);
            }
        }

        return $request->getScheme().'://'.$host.(\in_array($request->getPort(), [80, 443], true) ? '' : ':'.$request->getPort());
    }
}
