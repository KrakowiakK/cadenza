<?php

declare(strict_types=1);

namespace App\Controller;

use App\Connection\Connections;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The panel's "Connections": what the studio uses, what this computer already has, and connecting a service with a
 * key that is checked first and then kept in the vault. Key values go in, never out.
 */
#[AsController]
final class ConnectionsController
{
    public function __construct(private readonly Connections $connections)
    {
    }

    #[Route('/api/services', methods: ['GET'])]
    public function status(Request $request): JsonResponse
    {
        return new JsonResponse($this->connections->status('0' !== $request->query->get('discover')));
    }

    #[Route('/api/services/test', methods: ['POST'])]
    public function test(Request $request): JsonResponse
    {
        $body = $request->toArray();

        return new JsonResponse($this->connections->test((string) ($body['provider'] ?? ''), self::text($body['key'] ?? null), self::text($body['baseUrl'] ?? null)));
    }

    #[Route('/api/services/model', methods: ['POST'])]
    public function model(Request $request): JsonResponse
    {
        $body = $request->toArray();
        $result = $this->connections->useProvider((string) ($body['provider'] ?? ''), self::text($body['key'] ?? null), self::text($body['env'] ?? null), self::text($body['model'] ?? null), self::text($body['baseUrl'] ?? null));

        return new JsonResponse($result + ['status' => $this->connections->status(false)], $result['ok'] ? 200 : 422);
    }

    #[Route('/api/services/feature', methods: ['POST'])]
    public function service(Request $request): JsonResponse
    {
        $body = $request->toArray();
        $id = (string) ($body['service'] ?? '');
        if (true === ($body['disconnect'] ?? false)) {
            $this->connections->disconnectService($id);
            $result = ['ok' => true];
        } else {
            $result = $this->connections->useService($id, self::text($body['key'] ?? null), self::text($body['env'] ?? null));
        }

        return new JsonResponse($result + ['status' => $this->connections->status(false)], $result['ok'] ? 200 : 422);
    }

    #[Route('/api/services/code', methods: ['POST'])]
    public function code(Request $request): JsonResponse
    {
        $result = $this->connections->useForCode(self::text($request->toArray()['model'] ?? null));

        return new JsonResponse($result + ['status' => $this->connections->status(false)], $result['ok'] ? 200 : 422);
    }

    private static function text(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
