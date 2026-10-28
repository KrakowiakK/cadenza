<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Controller;

use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Owner\OwnerResolver;
use Cadenza\Runtime\Theme\Theme;
use Cadenza\Runtime\Widget\WidgetLibraries;
use Cadenza\Runtime\Widget\Widgets;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Widgets run as documents of their own, in a sandbox: an opaque origin (no cookies, no storage, no access to the
 * page around them), no network except the site's libraries and photos, nothing from other servers. The sandbox is
 * in the response itself, so it holds even when someone opens the address directly.
 */
#[AsController]
final class WidgetController
{
    private const NAME = '[a-z][a-z0-9-]{2,38}';

    public function __construct(
        private readonly Widgets $widgets,
        private readonly WidgetLibraries $libraries,
        private readonly SiteManager $site,
        private readonly OwnerResolver $owner,
        #[Autowire('%kernel.project_dir%/public/media')]
        private readonly string $mediaDir,
        #[Autowire(service: 'limiter.cadenza_form')]
        private readonly \Symfony\Component\RateLimiter\RateLimiterFactoryInterface $limiter,
    ) {
    }

    #[Route('/_cadenza/widget/{name}', name: 'cadenza_widget', requirements: ['name' => self::NAME], methods: ['GET'])]
    public function show(Request $request, string $name): Response
    {
        $widget = $this->widgets->get($name) ?? throw new NotFoundHttpException();
        $origin = $request->getSchemeAndHttpHost();
        $imports = [];
        $versions = $this->libraries->all();
        foreach ($this->libraries->specifiers() as $specifier => $library) {
            $imports[$specifier] = $origin.'/_cadenza/widget-lib/'.$library.'?v='.$versions[$library];
        }
        $theme = $this->site->document()['theme'] ?? [];
        $vars = '';
        foreach (Theme::cssVariables($theme) as $var => $value) {
            $vars .= $var.':'.$value.';';
        }
        // Photos of the site come through an address that lets the sandbox use them (e.g. as 3D textures).
        $code = (string) preg_replace('~(["\'`(]\s*)/media/~', '$1'.$origin.'/_cadenza/widget-media/', $this->widgets->code($widget, $request->query->has('v') ? $request->query->getInt('v') : null));
        $html = strtr(self::SHELL, [
            '{lang}' => htmlspecialchars((string) ($this->site->document()['language'] ?? 'pl')),
            '{title}' => htmlspecialchars($widget->getLabel()),
            '{fonts}' => htmlspecialchars(Theme::googleFontsUrl($theme)),
            '{vars}' => str_replace('<', '', $vars),
            '{auto}' => $request->query->getBoolean('auto') ? 'auto' : '100%',
            '{importmap}' => json_encode(['imports' => $imports], \JSON_UNESCAPED_SLASHES | \JSON_HEX_TAG),
            '{name}' => json_encode($widget->getName()),
            '{code}' => $code,
        ]);

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Security-Policy' => implode('; ', [
                'sandbox allow-scripts allow-pointer-lock',
                'default-src \'none\'',
                'script-src \'unsafe-inline\' '.$origin.'/_cadenza/widget-lib/',
                'style-src \'unsafe-inline\' https://fonts.googleapis.com',
                'font-src https://fonts.gstatic.com data:',
                'img-src '.$origin.'/_cadenza/widget-media/ data: blob:',
                'media-src '.$origin.'/_cadenza/widget-media/ data: blob:',
                'connect-src '.$origin.'/_cadenza/widget-media/',
                'worker-src blob:',
                'frame-ancestors \'self\'',
                'base-uri \'none\'',
                'form-action \'none\'',
            ]),
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'public, max-age=60',
        ]);
    }

    #[Route('/_cadenza/widget-lib/{library}', name: 'cadenza_widget_library', requirements: ['library' => '[@a-z0-9][A-Za-z0-9@._/-]{0,120}'], methods: ['GET'])]
    public function library(string $library): Response
    {
        $source = $this->libraries->source($library) ?? throw new NotFoundHttpException();

        return new Response($source, 200, [
            'Content-Type' => 'text/javascript; charset=utf-8',
            // A sandboxed document has no origin of its own: its module imports are cross-origin requests.
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    #[Route('/_cadenza/widget-media/{file}', name: 'cadenza_widget_media', requirements: ['file' => '[A-Za-z0-9][A-Za-z0-9._/-]{0,200}'], methods: ['GET'])]
    public function media(string $file): Response
    {
        $path = realpath($this->mediaDir.'/'.$file);
        $root = realpath($this->mediaDir);
        if (false === $path || false === $root || !str_starts_with($path, $root.'/') || !is_file($path)
            || !preg_match('/\.(jpe?g|png|webp|gif|avif|svg|mp3|ogg|wav|mp4|webm|glb|gltf|json)$/i', $path)) {
            throw new NotFoundHttpException();
        }
        $response = new BinaryFileResponse($path, 200, ['Access-Control-Allow-Origin' => '*', 'Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
        if (str_ends_with($path, '.svg')) {
            $response->headers->set('Content-Security-Policy', 'default-src \'none\'; style-src \'unsafe-inline\'; sandbox');
        }

        return $response;
    }

    /**
     * What went wrong in a widget while the owner was looking at it — kept for the agent to read and fix.
     */
    #[Route('/_cadenza/widget/{name}/error', name: 'cadenza_widget_error', requirements: ['name' => self::NAME], methods: ['POST'])]
    public function error(Request $request, string $name): JsonResponse
    {
        if (!$this->owner->isOwner($request)) {
            throw new NotFoundHttpException();
        }
        $data = json_decode($request->getContent(), true);
        $error = \is_array($data) && \is_string($data['error'] ?? null) ? trim($data['error']) : '';
        if ('' === $error) {
            return new JsonResponse(['ok' => false], 422);
        }

        return new JsonResponse(['ok' => $this->widgets->reportError($name, (int) ($data['version'] ?? 0), $error)]);
    }

    /**
     * The shared leaderboard of a game. The sandbox cannot reach the network: the page around it asks for it.
     */
    #[Route('/_cadenza/widget/{name}/scores', name: 'cadenza_widget_scores', requirements: ['name' => self::NAME], methods: ['GET', 'POST'])]
    public function scores(Request $request, string $name): JsonResponse
    {
        if (null === $this->widgets->get($name)) {
            throw new NotFoundHttpException();
        }
        if ($request->isMethod('POST')) {
            if (!$this->limiter->create('score-'.$request->getClientIp())->consume()->isAccepted()) {
                return new JsonResponse(['ok' => false, 'error' => 'too_many'], 429);
            }
            $data = json_decode($request->getContent(), true);
            $ok = \is_array($data) && \is_string($data['player'] ?? null) && \is_int($data['score'] ?? null)
                && $this->widgets->addScore($name, $data['player'], $data['score']);
            if (!$ok) {
                return new JsonResponse(['ok' => false, 'error' => 'invalid'], 422);
            }
        }

        return new JsonResponse(['ok' => true, 'scores' => $this->widgets->topScores($name)], 200, ['Cache-Control' => 'no-store']);
    }

    private const SHELL = <<<'HTML'
        <!doctype html>
        <html lang="{lang}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{title}</title>
        <link rel="stylesheet" href="{fonts}">
        <style>:root{{vars}}*,*::before,*::after{box-sizing:border-box}html,body{margin:0;height:{auto};background:transparent;color:var(--foreground);font-family:var(--cz-font-body);-webkit-font-smoothing:antialiased}body{overflow:hidden}canvas,svg{display:block}h1,h2,h3{font-family:var(--cz-font-heading)}</style>
        <script type="importmap">{importmap}</script>
        <script>
        (() => {
            const name = {name}, send = (m) => parent.postMessage(Object.assign({cadenzaWidget: name}, m), '*');
            // Storage of its own, kept by the page around it (a sandbox has none): high scores survive a visit.
            let data = {};
            try { data = JSON.parse(decodeURIComponent(location.hash.slice(1)) || '{}') || {}; } catch (e) {}
            const save = () => send({store: data});
            const store = {
                getItem: (k) => (k in data ? String(data[k]) : null),
                setItem: (k, v) => { data[k] = String(v); save(); },
                removeItem: (k) => { delete data[k]; save(); },
                clear: () => { data = {}; save(); },
                key: (i) => Object.keys(data)[i] ?? null,
                get length() { return Object.keys(data).length; },
            };
            for (const n of ['localStorage', 'sessionStorage']) { try { Object.defineProperty(window, n, {value: store, configurable: true}); } catch (e) {} }
            // The site's colours as hex, for canvas and three.js (the CSS variables are oklch()).
            const css = getComputedStyle(document.documentElement), paint = document.createElement('canvas').getContext('2d', {willReadFrequently: true});
            const hex = (v) => { paint.clearRect(0, 0, 1, 1); paint.fillStyle = '#000'; paint.fillStyle = v || '#000'; paint.fillRect(0, 0, 1, 1); const [r, g, b] = paint.getImageData(0, 0, 1, 1).data; return '#' + [r, g, b].map((n) => n.toString(16).padStart(2, '0')).join(''); };
            const colors = {};
            for (const t of ['primary', 'primary-foreground', 'background', 'foreground', 'muted', 'muted-foreground', 'accent', 'accent-foreground', 'secondary', 'border', 'card']) colors[t.replace(/-(\w)/g, (m, c) => c.toUpperCase())] = hex(css.getPropertyValue('--' + t).trim());
            window.cadenza = {colors, language: document.documentElement.lang, reducedMotion: matchMedia('(prefers-reduced-motion: reduce)').matches, scroll: 0.5, pointer: null, visible: true};
            // From the page around it: scroll position, pointer over the frame (also when it ignores pointer events).
            addEventListener('message', (e) => {
                const v = e.source === parent && e.data && e.data.cadenzaView;
                if (!v || typeof v.scroll !== 'number') return;
                window.cadenza.scroll = v.scroll;
                window.cadenza.visible = !!v.visible;
                if (v.pointer && typeof v.pointer.x === 'number' && typeof v.pointer.y === 'number') window.cadenza.pointer = {x: v.pointer.x, y: v.pointer.y};
                dispatchEvent(new CustomEvent('cadenza:view', {detail: window.cadenza}));
            });
            // A shared leaderboard, kept by the site: await cadenza.scores.top(), await cadenza.scores.submit(name, score).
            let asked = 0;
            const waiting = {};
            addEventListener('message', (e) => {
                const r = e.source === parent && e.data && e.data.cadenzaScores;
                if (r && waiting[r.id]) { waiting[r.id](Array.isArray(r.scores) ? r.scores : []); delete waiting[r.id]; }
            });
            const ask = (m) => new Promise((resolve) => { const id = ++asked; waiting[id] = resolve; send(Object.assign({scores: true, id}, m)); setTimeout(() => { if (waiting[id]) { waiting[id]([]); delete waiting[id]; } }, 8000); });
            window.cadenza.scores = {top: () => ask({}), submit: (player, score) => ask({player: String(player).slice(0, 24), score: Math.max(0, Math.floor(Number(score) || 0))})};
            const report = (text) => send({error: String(text).slice(0, 500)});
            addEventListener('error', (e) => {
                if (e.target && e.target.tagName === 'SCRIPT') { report('A script did not load: ' + (e.target.src || 'an import of an inline module')); return; }
                report((e.message || 'Error') + (e.lineno ? ' (line ' + e.lineno + ')' : ''));
            }, true);
            addEventListener('unhandledrejection', (e) => report('Unhandled: ' + ((e.reason && e.reason.message) || e.reason)));
            // Keys a game uses must not scroll the page around it.
            addEventListener('keydown', (e) => {
                if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', ' ', 'PageUp', 'PageDown'].includes(e.key) && !/^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) e.preventDefault();
            }, {passive: false});
            if ('{auto}' === 'auto') addEventListener('load', () => new ResizeObserver(() => send({height: Math.ceil(document.documentElement.scrollHeight)})).observe(document.body));
        })();
        </script>
        </head><body>
        {code}
        </body></html>
        HTML;
}
