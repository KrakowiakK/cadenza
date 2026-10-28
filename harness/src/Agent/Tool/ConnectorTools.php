<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Media\UploadStore;
use App\Studio\SiteClient;
use App\Studio\Turn;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsTool('list_connectors', 'Services the owner can bring content from (Instagram…), how to connect each and whether an account is already connected.', method: 'list')]
#[AsTool('connect_account', 'Show the owner a card to connect a service: sign in (when available) or upload its export file. The owner acts in the panel.', method: 'connect')]
#[AsTool('import_content', 'Start importing the owner\'s content: method "account" (connected account) or "file" (an export archive the owner attached, give its uploadId). Runs in the background; the panel shows progress and tells you when it is done.', method: 'import')]
#[AsTool('media_overview', 'What is in the media library: totals per source, top hashtags/tags, years — to plan galleries and albums after an import.', method: 'overview')]
final class ConnectorTools
{
    public function __construct(
        private readonly SiteClient $site,
        private readonly UploadStore $uploads,
        private readonly Turn $turn,
        #[Autowire('%env(CADENZA_APP_URL)%')]
        private readonly string $appUrl,
    ) {
    }

    public function list(): string
    {
        $lines = [];
        foreach ($this->site->connectors() as $c) {
            $lines[] = \sprintf('- %s (%s): %s Ways: %s. %s', $c['connector'], $c['label'], $c['description'], implode(', ', $c['methods']) ?: 'none', $c['connection'] ? 'Connected as '.$c['connection']['account'].'.' : 'Not connected.');
        }

        return $lines ? implode("\n", $lines) : 'No connectors are installed.';
    }

    /**
     * @param string $connector Connector name, e.g. "instagram"
     */
    public function connect(string $connector): string
    {
        foreach ($this->site->connectors() as $c) {
            if ($c['connector'] === $connector) {
                $this->turn->emit('connect', [
                    'connector' => $connector,
                    'label' => $c['label'],
                    'description' => $c['description'],
                    'methods' => $c['methods'],
                    'connection' => $c['connection'],
                    'signInUrl' => \in_array('oauth', $c['methods'], true) ? rtrim($this->appUrl, '/').'/_cadenza/connect/'.$connector : null,
                ]);

                return \sprintf('Showed the %s card (%s). Tell the owner briefly what to do; wait for them.', $c['label'], implode(' or ', $c['methods']));
            }
        }

        return 'Unknown connector; call list_connectors.';
    }

    /**
     * @param string      $connector Connector name, e.g. "instagram"
     * @param string      $method    "account" or "file"
     * @param string|null $uploadId  For "file": id of the attached export archive (e.g. "01j….zip")
     */
    public function import(string $connector, string $method, ?string $uploadId = null): string
    {
        $fileUrl = null;
        if ('file' === $method) {
            if (null === $uploadId || null === $this->uploads->path($uploadId)) {
                return 'Attach the export archive first (the owner uploads it in the panel), then pass its uploadId.';
            }
            $fileUrl = $this->uploads->url($uploadId);
        }
        $result = $this->site->importStart($connector, $method, $fileUrl);
        if (!($result['ok'] ?? false)) {
            return 'Could not start the import: '.($result['error'] ?? 'unknown error');
        }
        $this->turn->emit('import', ['jobId' => $result['jobId'], 'connector' => $connector]);

        return \sprintf('Import job %d started in the background; the panel shows progress and you will get a message when it is done. Tell the owner it is importing; do not build pages yet.', $result['jobId']);
    }

    public function overview(): string
    {
        return json_encode($this->site->mediaStats(), \JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
