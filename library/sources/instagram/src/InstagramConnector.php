<?php

declare(strict_types=1);

namespace Cadenza\Source\Instagram;

use Cadenza\Runtime\Connector\ConnectorInterface;
use Cadenza\Runtime\Connector\ImportContext;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Instagram for Cadenza.
 *
 * - Sign-in: "Instagram API with Instagram Login" (Business and Creator accounts; personal accounts were dropped by
 *   Meta in Dec 2024). Needs a Meta app (INSTAGRAM_CLIENT_ID / INSTAGRAM_CLIENT_SECRET); in development mode the owner
 *   adds themselves as a tester, no app review needed. Long-lived tokens (60 days) are refreshed by the daily sync.
 * - Export: "Download your information" ZIP in JSON format — works for every account, no app needed.
 */
final class InstagramConnector implements ConnectorInterface
{
    private const GRAPH = 'https://graph.instagram.com';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly ?string $clientId = null,
        private readonly ?string $clientSecret = null,
    ) {
    }

    public function name(): string
    {
        return 'instagram';
    }

    public function label(): string
    {
        return 'Instagram';
    }

    public function description(): string
    {
        return 'Your Instagram posts — photos (also from carousels), captions, dates and hashtags. Sign in with a Business/Creator account to keep the site in sync every day, or upload the "Download your information" export (ZIP, JSON format) from any account.';
    }

    public function methods(): array
    {
        return ['oauth', 'file'];
    }

    public function oauthAvailable(): bool
    {
        return '' !== (string) $this->clientId && '' !== (string) $this->clientSecret;
    }

    public function authorizationUrl(string $redirectUri, string $state): string
    {
        return 'https://www.instagram.com/oauth/authorize?'.http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'instagram_business_basic',
            'state' => $state,
        ]);
    }

    public function finishAuthorization(string $code, string $redirectUri): array
    {
        $short = $this->http->request('POST', 'https://api.instagram.com/oauth/access_token', ['body' => [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
            'code' => preg_replace('/#_$/', '', $code),
        ]])->toArray();

        $long = $this->http->request('GET', self::GRAPH.'/access_token', ['query' => [
            'grant_type' => 'ig_exchange_token',
            'client_secret' => $this->clientSecret,
            'access_token' => $short['access_token'],
        ]])->toArray();

        $me = $this->http->request('GET', self::GRAPH.'/me', ['query' => ['fields' => 'user_id,username', 'access_token' => $long['access_token']]])->toArray();

        return [
            'credentials' => ['access_token' => $long['access_token'], 'user_id' => $me['user_id'] ?? $short['user_id'] ?? null],
            'account' => '@'.($me['username'] ?? 'instagram'),
            'expiresAt' => new \DateTimeImmutable('+'.(int) ($long['expires_in'] ?? 5184000).' seconds'),
        ];
    }

    public function refresh(array $credentials): ?array
    {
        $data = $this->http->request('GET', self::GRAPH.'/refresh_access_token', ['query' => ['grant_type' => 'ig_refresh_token', 'access_token' => $credentials['access_token']]])->toArray();

        return ['credentials' => ['access_token' => $data['access_token']] + $credentials, 'expiresAt' => new \DateTimeImmutable('+'.(int) ($data['expires_in'] ?? 5184000).' seconds')];
    }

    public function importFromAccount(array $credentials, ImportContext $context, callable $progress): array
    {
        $imported = $skipped = $done = 0;
        $errors = [];
        $url = self::GRAPH.'/me/media';
        $query = ['fields' => 'id,caption,media_type,media_url,permalink,timestamp,username,children{id,media_type,media_url}', 'limit' => 50, 'access_token' => $credentials['access_token']];

        while (null !== $url) {
            $page = $this->http->request('GET', $url, ['query' => $query])->toArray();
            $query = [];
            foreach ($page['data'] ?? [] as $post) {
                $items = 'CAROUSEL_ALBUM' === ($post['media_type'] ?? '') ? ($post['children']['data'] ?? []) : [$post];
                foreach ($items as $i => $item) {
                    $externalId = (string) $item['id'];
                    if ('IMAGE' !== ($item['media_type'] ?? '') || $context->has($externalId)) {
                        ++$skipped;
                        continue;
                    }
                    try {
                        $bytes = $this->http->request('GET', $item['media_url'], ['timeout' => 60])->getContent();
                        $caption = $post['caption'] ?? null;
                        $context->addImage($bytes, $externalId, $caption, isset($post['timestamp']) ? new \DateTimeImmutable($post['timestamp']) : null, ImportContext::hashtags($caption), $post['permalink'] ?? null, isset($post['username']) ? '@'.$post['username'] : null);
                        ++$imported;
                    } catch (\Throwable $e) {
                        $errors[] = \sprintf('%s: %s', $externalId, $e->getMessage());
                    }
                }
                $progress(++$done, null);
            }
            $url = $page['paging']['next'] ?? null;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    public function importFromFile(string $path, ImportContext $context, callable $progress): array
    {
        $zip = new \ZipArchive();
        if (true !== $zip->open($path)) {
            throw new \RuntimeException('This is not a ZIP archive. Upload the export file exactly as Instagram sent it.');
        }

        $posts = $this->postsFromExport($zip);
        if ([] === $posts) {
            $html = false;
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $html = $html || str_ends_with((string) $zip->getNameIndex($i), 'posts_1.html');
            }
            throw new \RuntimeException($html
                ? 'This export is in HTML format; request it again choosing the JSON format.'
                : 'No posts found in this archive. Is it the Instagram "Download your information" export?');
        }

        $imported = $skipped = 0;
        $errors = [];
        $total = array_sum(array_map(static fn (array $p): int => \count($p['media'] ?? []), $posts));
        $done = 0;
        foreach ($posts as $post) {
            $postCaption = $this->text($post['title'] ?? null);
            foreach ($post['media'] ?? [] as $media) {
                $uri = (string) ($media['uri'] ?? '');
                $externalId = 'export:'.basename($uri);
                $progress(++$done, $total);
                if (!preg_match('/\.(jpe?g|png|webp|heic)$/i', $uri) || $context->has($externalId)) {
                    ++$skipped;
                    continue;
                }
                $bytes = $zip->getFromName($uri);
                if (false === $bytes) {
                    $bytes = $this->findByBasename($zip, basename($uri));
                }
                if (false === $bytes) {
                    $errors[] = 'Missing file '.$uri;
                    continue;
                }
                $caption = $this->text($media['title'] ?? null) ?: $postCaption;
                $timestamp = $media['creation_timestamp'] ?? $post['creation_timestamp'] ?? null;
                try {
                    $context->addImage($bytes, $externalId, $caption ?: null, null !== $timestamp ? (new \DateTimeImmutable())->setTimestamp((int) $timestamp) : null, ImportContext::hashtags($caption));
                    ++$imported;
                } catch (\Throwable $e) {
                    $errors[] = basename($uri).': '.$e->getMessage();
                }
            }
        }
        $zip->close();

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Posts from any posts_*.json of the export (its location changed between export versions).
     *
     * @return list<array<string, mixed>>
     */
    private function postsFromExport(\ZipArchive $zip): array
    {
        $posts = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $name = (string) $zip->getNameIndex($i);
            if (!preg_match('~(^|/)posts_\d+\.json$~', $name)) {
                continue;
            }
            $data = json_decode((string) $zip->getFromIndex($i), true);
            if (\is_array($data)) {
                foreach (array_is_list($data) ? $data : ($data['ig_posts'] ?? []) as $post) {
                    if (\is_array($post)) {
                        $posts[] = $post;
                    }
                }
            }
        }

        return $posts;
    }

    private function findByBasename(\ZipArchive $zip, string $basename): string|false
    {
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            if (basename((string) $zip->getNameIndex($i)) === $basename) {
                return $zip->getFromIndex($i);
            }
        }

        return false;
    }

    /**
     * Instagram exports encode UTF-8 text as Latin-1 escaped bytes ("zdjÄ\u0099cie"); undo that.
     */
    private function text(mixed $value): string
    {
        if (!\is_string($value) || '' === $value) {
            return '';
        }
        $fixed = @mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');

        return \is_string($fixed) && mb_check_encoding($fixed, 'UTF-8') ? $fixed : $value;
    }
}
