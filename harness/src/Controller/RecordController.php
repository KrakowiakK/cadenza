<?php

declare(strict_types=1);

namespace App\Controller;

use App\Studio\SiteClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The owner publishes or rejects entries the agent wrote (posts, prices, events) from the card in the panel.
 */
#[AsController]
final class RecordController
{
    public function __construct(private readonly SiteClient $site)
    {
    }

    #[Route('/api/records/decide', methods: ['POST'])]
    public function decide(Request $request): JsonResponse
    {
        $body = $request->toArray();
        $slugs = isset($body['slugs']) && \is_array($body['slugs']) ? array_values(array_map('strval', $body['slugs'])) : null;

        return new JsonResponse($this->site->decideRecords((string) ($body['collection'] ?? ''), (bool) ($body['publish'] ?? false), $slugs));
    }
}
