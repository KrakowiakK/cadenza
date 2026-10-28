<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Controller;

use Cadenza\Runtime\Account\Members;
use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Owner\OwnerResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The members' area: the owner invites people and hands out their links; a member who lost the link asks for a new
 * one by e-mail.
 */
#[AsController]
final class MemberController
{
    public function __construct(
        private readonly Members $members,
        private readonly OwnerResolver $owner,
        private readonly SiteManager $site,
        private readonly CsrfTokenManagerInterface $csrf,
        #[Autowire(service: 'limiter.cadenza_form')]
        private readonly RateLimiterFactoryInterface $limiter,
        #[Autowire('%cadenza.notify_email%')]
        private readonly ?string $from = null,
        private readonly ?MailerInterface $mailer = null,
    ) {
    }

    #[Route('/_cadenza/members/check', name: 'cadenza_member_check', methods: ['GET'])]
    public function check(): never
    {
        throw new \LogicException('The firewall signs the member in before this runs.');
    }

    /**
     * "Send me my link": the answer is the same whether the address is on the list or not.
     */
    #[Route('/_cadenza/members/link', name: 'cadenza_member_request', methods: ['POST'])]
    public function request(Request $request): Response
    {
        $target = (string) $request->request->get('target', '/');
        $target = str_starts_with($target, '/') && !str_starts_with($target, '//') ? $target : '/';
        if ('' === (string) $request->request->get('cz_website', '')
            && $this->limiter->create('link-'.($request->getClientIp() ?? 'unknown'))->consume()->isAccepted()
            && null !== ($member = $this->members->byEmail((string) $request->request->get('email')))
            && null !== $this->mailer) {
            $site = $this->site->document()['name'] ?? $request->getHost();
            $this->mailer->send((new Email())
                ->from($this->from ?? 'no-reply@'.$request->getHost())
                ->to($member->getEmail())
                ->subject($site)
                ->text($site."\n\n".$this->members->link($member, Members::REQUEST_LIFETIME, $target, $request)."\n"));
        }

        return new RedirectResponse($target.(str_contains($target, '?') ? '&' : '?').'cz_link=sent', 303);
    }

    #[Route('/_cadenza/members', name: 'cadenza_member_add', methods: ['POST'])]
    public function add(Request $request): Response
    {
        $this->guard($request);
        $result = 'added';
        try {
            $this->members->add((string) $request->request->get('email'), (string) $request->request->get('name'));
        } catch (\InvalidArgumentException $e) {
            $result = $e->getMessage();
        }

        return new RedirectResponse('/_cadenza/dashboard?members='.$result.'#members', 303);
    }

    #[Route('/_cadenza/members/{id}/link', name: 'cadenza_member_link', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function link(Request $request, int $id): JsonResponse
    {
        $this->guard($request);
        $member = $this->members->get($id) ?? throw new NotFoundHttpException();

        return new JsonResponse(['url' => $this->members->link($member, Members::INVITE_LIFETIME, null, $request)]);
    }

    #[Route('/_cadenza/members/{id}/remove', name: 'cadenza_member_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function remove(Request $request, int $id): Response
    {
        $this->guard($request);
        $this->members->remove($this->members->get($id) ?? throw new NotFoundHttpException());

        return new RedirectResponse('/_cadenza/dashboard?members=removed#members', 303);
    }

    private function guard(Request $request): void
    {
        if (!$this->owner->isOwner($request) || !$this->csrf->isTokenValid(new CsrfToken('submit', (string) $request->request->get('_token')))) {
            throw new AccessDeniedHttpException();
        }
    }
}
