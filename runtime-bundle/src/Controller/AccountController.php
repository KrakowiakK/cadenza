<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Controller;

use Cadenza\Runtime\Account\Accounts;
use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Owner\OwnerResolver;
use Cadenza\Runtime\Twig\RenderState;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Twig\Environment;

/**
 * Signing in as the owner: the login page, the very first owner account, and changing the password.
 */
#[AsController]
final class AccountController
{
    public function __construct(
        private readonly Accounts $accounts,
        private readonly OwnerResolver $owner,
        private readonly Security $security,
        private readonly SiteManager $site,
        private readonly RenderState $state,
        private readonly Environment $twig,
        private readonly CsrfTokenManagerInterface $csrf,
        #[Autowire('%cadenza.token%')]
        private readonly string $token,
    ) {
    }

    #[Route('/_cadenza/login', name: 'cadenza_login', methods: ['GET', 'POST'])]
    public function login(Request $request, AuthenticationUtils $auth): Response
    {
        if (!$this->accounts->hasOwner()) {
            return new RedirectResponse('/_cadenza/setup');
        }
        if ($this->owner->isOwner($request)) {
            return new RedirectResponse('/');
        }

        return $this->render('@CadenzaRuntime/account/login.html.twig', [
            'email' => $auth->getLastUsername(),
            'error' => $auth->getLastAuthenticationError()?->getMessageKey(),
        ]);
    }

    #[Route('/_cadenza/logout', name: 'cadenza_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('The firewall signs the owner out before this runs.');
    }

    /**
     * The first owner account. Only while there is none, and only from the machine the site runs on — or with the
     * site's setup token, for a site already on a server.
     */
    #[Route('/_cadenza/setup', name: 'cadenza_setup', methods: ['GET', 'POST'])]
    public function setup(Request $request): Response
    {
        if ($this->accounts->hasOwner()) {
            return new RedirectResponse('/_cadenza/login');
        }
        if (!self::isLocal($request) && !hash_equals($this->token, (string) $request->query->get('token', ''))) {
            throw new NotFoundHttpException();
        }
        $error = null;
        $email = (string) $request->request->get('email', '');
        if ($request->isMethod('POST')) {
            if (!$this->csrf->isTokenValid(new CsrfToken('submit', (string) $request->request->get('_token')))) {
                $error = 'csrf';
            } elseif ($request->request->get('password') !== $request->request->get('repeat')) {
                $error = 'mismatch';
            } else {
                try {
                    $account = $this->accounts->createOwner($email, (string) $request->request->get('password'), trim((string) $request->request->get('name')) ?: null);
                    $this->security->login($account, 'form_login', 'main');

                    return new RedirectResponse('/');
                } catch (\InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return $this->render('@CadenzaRuntime/account/setup.html.twig', ['email' => $email, 'error' => $error], $error ? 422 : 200);
    }

    #[Route('/_cadenza/account/password', name: 'cadenza_password', methods: ['POST'])]
    public function password(Request $request): Response
    {
        $account = $this->owner->account();
        if (null === $account || !$this->owner->isOwner($request)) {
            throw new AccessDeniedHttpException();
        }
        $result = 'saved';
        if (!$this->csrf->isTokenValid(new CsrfToken('submit', (string) $request->request->get('_token')))) {
            $result = 'csrf';
        } elseif (!$this->accounts->isPasswordValid($account, (string) $request->request->get('current'))) {
            $result = 'wrong_current';
        } else {
            try {
                $this->accounts->changePassword($account, (string) $request->request->get('password'));
            } catch (\InvalidArgumentException $e) {
                $result = $e->getMessage();
            }
        }

        return new RedirectResponse('/_cadenza/dashboard?password='.$result.'#account', 303);
    }

    /**
     * A request from this very computer, not passed on by a proxy in front of the site.
     */
    private static function isLocal(Request $request): bool
    {
        return \in_array($request->server->get('REMOTE_ADDR'), ['127.0.0.1', '::1'], true)
            && !$request->headers->has('X-Forwarded-For') && !$request->headers->has('Forwarded')
            && \in_array($request->getHost(), ['127.0.0.1', 'localhost', '::1', '[::1]'], true);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context, int $status = 200): Response
    {
        $this->state->document = $this->site->document();
        $response = new Response($this->twig->render($template, $context + ['page' => null]), $status);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
