<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Controller;

use Cadenza\Runtime\Form\Forms;
use Cadenza\Runtime\Owner\OwnerResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Visitors send forms here; the owner downloads the answers.
 */
#[AsController]
final class FormController
{
    public function __construct(
        private readonly Forms $forms,
        private readonly OwnerResolver $owner,
        #[Autowire(service: 'limiter.cadenza_form')]
        private readonly RateLimiterFactoryInterface $limiter,
    ) {
    }

    #[Route('/_cadenza/form/{id}', name: 'cadenza_form_submit', requirements: ['id' => '[a-z][a-z0-9_-]{2,24}'], methods: ['POST'])]
    public function submit(Request $request, string $id): Response
    {
        $json = str_contains((string) $request->headers->get('Accept'), 'json');
        $back = $this->back($request);
        $form = $this->forms->published($id);
        if (null === $form) {
            return $json ? new JsonResponse(['ok' => false, 'error' => 'draft'], 404) : new RedirectResponse($back, 303);
        }
        // A hidden field only bots fill in: pretend it worked.
        if ('' !== (string) $request->request->get('cz_website', '')) {
            return $json ? new JsonResponse(['ok' => true]) : new RedirectResponse($back.'?cz_sent='.$id.'#'.$id, 303);
        }
        if (!$this->limiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
            return $json ? new JsonResponse(['ok' => false, 'error' => 'too_many'], 429) : new Response('Too many submissions, try again later.', 429);
        }
        if (!$this->forms->stats($form)['open']) {
            return $json ? new JsonResponse(['ok' => false, 'error' => 'closed'], 409) : new RedirectResponse($back.'#'.$id, 303);
        }
        $result = Forms::validate($form, (array) $request->request->all('f'));
        if ([] !== $result['errors']) {
            return $json ? new JsonResponse(['ok' => false, 'errors' => $result['errors']], 422) : new Response('Please fill in the form correctly.', 422);
        }
        $stats = $this->forms->stats($form);
        if (null !== $stats['left'] && $result['people'] > $stats['left']) {
            return $json ? new JsonResponse(['ok' => false, 'error' => 'not_enough', 'left' => $stats['left']], 409) : new RedirectResponse($back.'#'.$id, 303);
        }
        $this->forms->store($form, $result['answers'], $result['people']);

        return $json ? new JsonResponse(['ok' => true, 'left' => $this->forms->stats($form)['left']]) : new RedirectResponse($back.'?cz_sent='.$id.'#'.$id, 303);
    }

    #[Route('/_cadenza/form/{id}/answers.xlsx', name: 'cadenza_form_export', requirements: ['id' => '[a-z][a-z0-9_-]{2,24}'], methods: ['GET'])]
    public function export(Request $request, string $id): Response
    {
        if (!$this->owner->isOwner($request)) {
            throw new AccessDeniedHttpException();
        }
        $form = $this->forms->published($id) ?? throw new NotFoundHttpException();
        $this->forms->markSeen($id);
        $name = (new AsciiSlugger())->slug(Forms::title($form))->lower()->toString() ?: 'answers';

        return (new BinaryFileResponse($this->forms->export($form)))
            ->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $name.'-'.date('Y-m-d').'.xlsx')
            ->deleteFileAfterSend();
    }

    #[Route('/_cadenza/form/{id}/seen', name: 'cadenza_form_seen', requirements: ['id' => '[a-z][a-z0-9_-]{2,24}'], methods: ['POST'])]
    public function seen(Request $request, string $id): Response
    {
        if (!$this->owner->isOwner($request)) {
            throw new AccessDeniedHttpException();
        }
        $this->forms->markSeen($id);

        return new JsonResponse(['ok' => true]);
    }

    /**
     * The page the form was sent from, on this site only.
     */
    private function back(Request $request): string
    {
        $path = parse_url((string) $request->headers->get('Referer'), \PHP_URL_PATH);

        return \is_string($path) && str_starts_with($path, '/') && !str_starts_with($path, '//') ? $path : '/';
    }
}
