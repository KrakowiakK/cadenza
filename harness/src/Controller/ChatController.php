<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Conversation;
use App\Repository\ConversationRepository;
use App\Repository\ProposalRepository;
use App\Studio\Conductor;
use App\Studio\Turn;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class ChatController
{
    private readonly string $modelLabel;

    public function __construct(
        private readonly ConversationRepository $conversations,
        private readonly ProposalRepository $proposals,
        private readonly Conductor $conductor,
        private readonly Turn $turn,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(CADENZA_LLM_MODEL)%')]
        private readonly string $model,
        #[Autowire('%env(CADENZA_LLM_BASE_URL)%')]
        private readonly string $baseUrl,
        #[Autowire('%env(default::CADENZA_LLM_LABEL)%')]
        private readonly ?string $label = null,
    ) {
        $host = parse_url($this->baseUrl, \PHP_URL_HOST) ?: $this->baseUrl;
        $this->modelLabel = $this->label ?: strtok($this->model, '?').' @ '.$host;
    }

    /**
     * One message of the owner; the answer streams back as server-sent events:
     * conversation, status, tool (trace steps), ui (browser commands), proposal, message, error, done.
     */
    #[Route('/api/chat', methods: ['POST'])]
    public function chat(Request $request): StreamedResponse
    {
        $payload = $request->toArray();
        $conversation = null;
        if (\is_string($payload['conversationId'] ?? null)) {
            $conversation = $this->conversations->find($payload['conversationId']);
        }
        if (null === $conversation) {
            $conversation = new Conversation();
            $this->em->persist($conversation);
            $this->em->flush();
        }
        $message = mb_substr(trim((string) ($payload['message'] ?? '')), 0, 8000);
        $context = \is_array($payload['context'] ?? null) ? $payload['context'] : [];
        $attachments = array_values(array_filter(\is_array($payload['attachments'] ?? null) ? $payload['attachments'] : [], static fn (mixed $a): bool => \is_array($a) && \is_string($a['id'] ?? null)));

        $response = new StreamedResponse(function () use ($conversation, $message, $context, $attachments): void {
            set_time_limit(0);
            ignore_user_abort(false);
            @ini_set('zlib.output_compression', '0');
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            $send = static function (string $event, array $data): void {
                echo 'event: '.$event."\n".'data: '.json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_INVALID_UTF8_SUBSTITUTE)."\n\n";
                flush();
            };
            $this->turn->start($conversation, \Closure::fromCallable($send));
            $send('conversation', ['id' => $conversation->getId(), 'model' => $this->modelLabel]);
            try {
                $text = $this->conductor->answer($conversation, $message, $context, $attachments);
                $send('message', ['text' => $text]);
            } catch (\Throwable $e) {
                $this->logger->error('Cadenza turn failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
                $send('error', ['message' => $e->getMessage()]);
            }
            $send('done', []);
        });
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    #[Route('/api/info', methods: ['GET'])]
    public function info(): JsonResponse
    {
        return new JsonResponse(['model' => $this->modelLabel]);
    }

    #[Route('/api/conversations/{id}', methods: ['GET'])]
    public function conversation(string $id): JsonResponse
    {
        $conversation = $this->conversations->find($id);
        if (null === $conversation) {
            return new JsonResponse(['turns' => []]);
        }
        $turns = $conversation->getTurns();
        $proposals = $this->proposals->byIds(array_merge(...array_map(static fn (array $t): array => $t['proposals'] ?? [], $turns ?: [[]])));
        foreach ($turns as $i => $turn) {
            $turns[$i]['proposals'] = array_values(array_filter(array_map(static fn (string $id): ?array => isset($proposals[$id]) ? $proposals[$id]->toArray() : null, $turn['proposals'] ?? [])));
        }

        return new JsonResponse(['id' => $conversation->getId(), 'turns' => $turns]);
    }
}
