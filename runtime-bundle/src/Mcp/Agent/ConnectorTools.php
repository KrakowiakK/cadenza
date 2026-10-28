<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Mcp\Agent;

use Cadenza\Runtime\Connector\Connectors;
use Cadenza\Runtime\Media\MediaLibrary;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Bringing the owner's content in from other services. Tokens never leave the application.
 */
final class ConnectorTools
{
    public function __construct(
        private readonly Connectors $connectors,
        private readonly MediaLibrary $media,
        private readonly HttpClientInterface $http,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[McpTool(name: 'connectors_list', description: 'Installed connectors (Instagram…): what they import, how to connect (oauth sign-in / file export) and whether an account is connected.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function list(): array
    {
        return $this->connectors->describe();
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'import_start', description: 'Start importing from a connected account (method "account") or from an export file the owner uploaded (method "file" with fileUrl). Runs in the background; returns a job id.')]
    public function start(
        string $connector,
        #[Schema(enum: ['account', 'file'])]
        string $method,
        #[Schema(description: 'For method "file": http(s) URL of the uploaded export archive')]
        ?string $fileUrl = null,
    ): array {
        try {
            $file = null;
            if ('file' === $method) {
                if (null === $fileUrl || !preg_match('~^https?://~', $fileUrl)) {
                    return ['ok' => false, 'error' => 'fileUrl is required for method "file".'];
                }
                $file = tempnam(sys_get_temp_dir(), 'cadenza-import-');
                $handle = fopen($file, 'w');
                foreach ($this->http->stream($this->http->request('GET', $fileUrl, ['timeout' => 300])) as $chunk) {
                    fwrite($handle, $chunk->getContent());
                }
                fclose($handle);
            } elseif (null === $this->connectors->connection($connector)) {
                return ['ok' => false, 'error' => 'No account is connected; ask the owner to connect it (or to upload an export file).'];
            }
            $job = $this->connectors->startImport($connector, $method, $file);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true] + $job->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'import_status', description: 'Progress of an import job.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function status(int $jobId): array
    {
        return $this->connectors->job($jobId)?->toArray() ?? ['error' => 'Unknown job.'];
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'media_stats', description: 'What is in the media library: totals per source, most used hashtags/tags, years. Use it to plan galleries and albums.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function stats(): array
    {
        return $this->media->stats();
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'connector_disconnect', description: 'Forget a connected account (the imported images stay).')]
    public function disconnect(string $connector): array
    {
        $this->connectors->disconnect($connector);

        return ['ok' => true];
    }
}
