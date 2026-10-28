<?php

declare(strict_types=1);

namespace App\Controller;

use App\Media\ImageOffers;
use App\Media\ImagePlacer;
use App\Media\UploadStore;
use App\Repository\ConversationRepository;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class MediaController
{
    public function __construct(
        private readonly UploadStore $uploads,
        private readonly ImageOffers $offers,
        private readonly ImagePlacer $placer,
        private readonly ConversationRepository $conversations,
    ) {
    }

    /**
     * Photos, pasted screenshots and drawn sketches from the panel: multipart "file", or JSON {dataUrl, name}.
     */
    #[Route('/api/uploads', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        try {
            $file = $request->files->get('file');
            if (null !== $file) {
                $isZip = (bool) preg_match('/\.(zip|xlsx|xls|ods|csv)$/i', $file->getClientOriginalName()) || 'application/zip' === $file->getMimeType();
                if ($file->getSize() > ($isZip ? 500 : 25) * 1024 * 1024) {
                    return new JsonResponse(['error' => 'The file is too large.'], 413);
                }
                $saved = $isZip
                    ? $this->uploads->saveFile($file->getPathname(), $file->getClientOriginalName())
                    : $this->uploads->save((string) file_get_contents($file->getPathname()), $file->getClientOriginalName());
            } else {
                $payload = $request->toArray();
                if (!preg_match('~^data:image/(png|jpeg|webp);base64,(.+)$~', (string) ($payload['dataUrl'] ?? ''), $m)) {
                    return new JsonResponse(['error' => 'Send a file or an image data URL.'], 400);
                }
                $saved = $this->uploads->save((string) base64_decode($m[2], true), (string) ($payload['name'] ?? 'sketch'));
            }
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 415);
        }

        return new JsonResponse($saved);
    }

    #[Route('/uploads/{id}', methods: ['GET'])]
    public function file(string $id): Response
    {
        $path = $this->uploads->path($id);
        if (null === $path) {
            return new Response('', 404);
        }
        $response = new BinaryFileResponse($path);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->setMaxAge(86400);

        return $response;
    }

    /**
     * The owner picked a photo from a grid offered by the agent: import it and put it into the sketch.
     */
    #[Route('/api/offers/{id}/pick', methods: ['POST'])]
    public function pick(string $id, Request $request): JsonResponse
    {
        $offer = $this->offers->offer($id);
        if (null === $offer) {
            return new JsonResponse(['error' => 'This choice expired.'], 404);
        }
        $candidateId = (string) ($request->toArray()['candidateId'] ?? '');
        $candidate = null;
        foreach ($offer['candidates'] as $c) {
            if ($c['id'] === $candidateId) {
                $candidate = $c;
            }
        }
        $conversation = $this->conversations->find((string) $offer['conversationId']);
        if (null === $candidate || null === $conversation) {
            return new JsonResponse(['error' => 'Unknown photo.'], 404);
        }

        $media = $this->placer->import($candidate['id'], $candidate['title']);
        if (!($media['ok'] ?? false)) {
            return new JsonResponse(['error' => $media['error'] ?? 'Import failed.'], 502);
        }
        $result = $this->placer->place($conversation, $offer['draftId'] ?? null, $offer['nodeId'], $offer['prop'], $media, $offer['label']);
        if (null === $result['proposal']) {
            return new JsonResponse(['error' => implode(' ', $result['errors'])], 409);
        }
        $offer['draftId'] = $result['proposal']->getDraftId();
        $offer['picked'] = $candidate['id'];
        $this->offers->update($offer);

        return new JsonResponse(['ok' => true, 'proposal' => $result['proposal']->toArray(), 'media' => $media]);
    }
}
