<?php

declare(strict_types=1);

namespace App\Controller;

use App\Studio\VisitorAnswers;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The site asks the studio's model to answer a visitor from the site's own text (the "ask" block). The site sends the
 * question and its published text; visitors never talk to the studio themselves.
 */
#[AsController]
final class AnswerController
{
    public function __construct(private readonly VisitorAnswers $answers)
    {
    }

    #[Route('/api/answer', methods: ['POST'])]
    public function answer(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $question = \is_array($data) ? trim((string) ($data['question'] ?? '')) : '';
        $knowledge = \is_array($data) ? (string) ($data['knowledge'] ?? '') : '';
        if ('' === $question || mb_strlen($question) > 300 || '' === trim($knowledge)) {
            return new JsonResponse(['error' => 'A question (up to 300 characters) and the site\'s text are required.'], 422);
        }
        try {
            return new JsonResponse($this->answers->answer($question, mb_substr($knowledge, 0, 30000), (string) ($data['site'] ?? 'this site')));
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => mb_substr($e->getMessage(), 0, 200)], 503);
        }
    }
}
