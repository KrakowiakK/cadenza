<?php

declare(strict_types=1);

namespace App\Controller;

use App\Studio\SiteClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The site's timeline. Restoring never rewrites history: it publishes an old state as a new version.
 */
#[AsController]
final class HistoryController
{
    public function __construct(private readonly SiteClient $site)
    {
    }

    #[Route('/api/history', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse(['versions' => $this->site->history(100)]);
    }

    #[Route('/api/history/{version}/restore', requirements: ['version' => '\d+'], methods: ['POST'])]
    public function restore(int $version): JsonResponse
    {
        return $this->answer($this->site->restore($version));
    }

    /**
     * Undo the latest published change: bring back the version before it.
     */
    #[Route('/api/history/undo', methods: ['POST'])]
    public function undo(): JsonResponse
    {
        $versions = $this->site->history(2);
        if (\count($versions) < 2) {
            return new JsonResponse(['error' => 'There is nothing to undo.'], 409);
        }

        return $this->answer($this->site->restore((int) $versions[1]['version']));
    }

    /**
     * Start over from the blank canvas (version 1), keeping the history.
     */
    #[Route('/api/history/start-over', methods: ['POST'])]
    public function startOver(): JsonResponse
    {
        return $this->answer($this->site->restore(1));
    }

    /**
     * @param array<string, mixed> $result
     */
    private function answer(array $result): JsonResponse
    {
        return new JsonResponse($result, ($result['ok'] ?? false) ? 200 : 409);
    }
}
