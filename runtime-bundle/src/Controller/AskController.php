<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Controller;

use Cadenza\Runtime\Ask\SiteKnowledge;
use Cadenza\Runtime\Ask\VisitorQuestions;
use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Owner\OwnerResolver;
use Cadenza\Runtime\Site\Document;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A visitor asks the site a question in their own words. The answer comes only from what the published site says:
 * the model gets that text and the question, no tools, and nothing of the owner's. It works only while the owner
 * keeps an "ask" block on a published page.
 */
#[AsController]
final class AskController
{
    public function __construct(
        private readonly SiteManager $site,
        private readonly SiteKnowledge $knowledge,
        private readonly VisitorQuestions $questions,
        private readonly OwnerResolver $owner,
        private readonly HttpClientInterface $http,
        #[Autowire('%cadenza.harness_url%')]
        private readonly string $harnessUrl,
        #[Autowire('%cadenza.token%')]
        private readonly string $token,
        #[Autowire(service: 'limiter.cadenza_ask')]
        private readonly RateLimiterFactoryInterface $perVisitor,
        #[Autowire(service: 'limiter.cadenza_ask_site')]
        private readonly RateLimiterFactoryInterface $perDay,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[Route('/_cadenza/ask', name: 'cadenza_ask', methods: ['POST'])]
    public function ask(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $question = trim((string) preg_replace('~\s+~u', ' ', \is_array($data) ? (string) ($data['question'] ?? '') : ''));
        $path = \is_array($data) ? (string) ($data['path'] ?? '/') : '/';
        if (mb_strlen($question) < 3 || mb_strlen($question) > 300) {
            return new JsonResponse(['ok' => false, 'error' => 'length'], 422);
        }
        $document = $this->site->document();
        if (!self::enabled($document)) {
            return new JsonResponse(['ok' => false, 'error' => 'off'], 404);
        }
        $owner = $this->owner->isOwner($request);
        // The owner trying it out is neither limited nor counted among the visitors' questions.
        if (!$owner && (!$this->perVisitor->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted() || !$this->perDay->create('site')->consume()->isAccepted())) {
            return new JsonResponse(['ok' => false, 'error' => 'too_many'], 429);
        }

        try {
            $result = $this->http->request('POST', $this->harnessUrl.'/api/answer', [
                'headers' => ['X-Cadenza-Token' => $this->token],
                'json' => ['question' => $question, 'knowledge' => $this->knowledge->text(), 'site' => (string) ($document['name'] ?? '')],
                'timeout' => 45,
            ])->toArray();
        } catch (\Throwable $e) {
            $this->logger?->warning('The visitors\' assistant could not answer: {error}', ['error' => $e->getMessage()]);

            return new JsonResponse(['ok' => false, 'error' => 'busy'], 503);
        }
        $answered = (bool) ($result['answered'] ?? false);
        $answer = $answered ? mb_substr(trim((string) ($result['answer'] ?? '')), 0, 1500) : null;
        if (!$owner) {
            $this->questions->record($question, $answer, $answered, $path);
        }

        return new JsonResponse(['ok' => true, 'answered' => $answered, 'answer' => $answer]);
    }

    /**
     * @param array<string, mixed> $document
     */
    public static function enabled(array $document): bool
    {
        foreach (Document::walk($document) as $entry) {
            if ('ask' === ($entry['node']['type'] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
