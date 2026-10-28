<?php

declare(strict_types=1);

namespace App\Controller;

use App\Studio\SiteClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class ImportController
{
    public function __construct(private readonly SiteClient $site)
    {
    }

    #[Route('/api/imports/{jobId}', requirements: ['jobId' => '\d+'], methods: ['GET'])]
    public function status(int $jobId): JsonResponse
    {
        return new JsonResponse($this->site->importStatus($jobId));
    }

    #[Route('/api/connections', methods: ['GET'])]
    public function connections(): JsonResponse
    {
        return new JsonResponse(['connectors' => $this->site->connectors()]);
    }
}
