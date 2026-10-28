<?php

declare(strict_types=1);

namespace App\Diagnostics;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

/**
 * Looks inside the user's application with Symfony AI Mate (logs, profiler, container). Mate reads files on disk,
 * so it answers even when the application itself is broken.
 */
final class Mate
{
    public function __construct(
        #[Autowire('%env(default:cadenza_app_path:CADENZA_APP_PATH)%')]
        private readonly string $appPath,
    ) {
    }

    public function isAvailable(): bool
    {
        return is_file($this->appPath.'/vendor/bin/mate');
    }

    /**
     * @param array<string, scalar> $options
     *
     * @return array<string, mixed>
     */
    public function call(string $tool, array $options = []): array
    {
        $command = [\PHP_BINARY, 'vendor/bin/mate', 'tools:call', $tool, '--format=json'];
        foreach ($options as $name => $value) {
            $command[] = \is_bool($value) ? ($value ? '--'.$name : '') : '--'.$name.'='.$value;
        }
        $process = new Process(array_values(array_filter($command)), $this->appPath, null, null, 60);
        $process->run();
        if (!$process->isSuccessful()) {
            return ['error' => trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'Mate failed.'];
        }
        $data = json_decode($process->getOutput(), true);

        return \is_array($data) ? ($data['untrusted_data'] ?? $data) : ['raw' => mb_substr($process->getOutput(), 0, 4000)];
    }
}
