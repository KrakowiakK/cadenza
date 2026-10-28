<?php

declare(strict_types=1);

namespace App\Studio;

/**
 * Makes a widget the way a careful programmer would: writes it, saves it, tries it in a browser, and fixes what the
 * test shows once. Used by the chat tool and by the command.
 */
final class WidgetWorkshop
{
    public function __construct(
        private readonly SiteClient $site,
        private readonly WidgetProgrammer $programmer,
        private readonly WidgetTester $tester,
    ) {
    }

    /**
     * @param \Closure(string): void $status told each step: writing, checking, fixing, testing, looking, repairing
     *
     * @return array{ok: bool, errors: list<string>, version: int, shape: string, code: string, tested: bool, findings: ?string, notes: list<string>}
     */
    public function build(string $name, string $brief, string $label, ?string $shape, string $language, \Closure $status, ?WidgetProgrammer $programmer = null, ?int $draftId = null): array
    {
        $programmer ??= $this->programmer;
        // A change starts from what the owner sees: the version of the sketch being worked on, else the live one —
        // never from a newer version waiting in another sketch.
        $current = $this->site->widgetCode($name, $draftId);
        $existing = \is_string($current['code'] ?? null);
        // A change keeps the frame the widget was written for, unless a new one is asked for.
        $shape = isset(WidgetProgrammer::SHAPES[(string) $shape]) ? (string) $shape : (string) ($current['shape'] ?? 'wide');
        $libraries = $this->site->widgetLibraries();
        $status('writing');
        $code = $programmer->write($brief, $language, $libraries, $shape, $existing ? $current['code'] : null, $current['lastError'] ?? null, $status);
        $result = $this->site->defineWidget($name, $existing ? '' : $label, $existing ? '' : mb_substr($brief, 0, 500), $code, $shape);
        if (!($result['ok'] ?? false)) {
            return ['ok' => false, 'errors' => (array) ($result['errors'] ?? ['unknown error']), 'version' => 0, 'shape' => $shape, 'code' => $code, 'tested' => false, 'findings' => null, 'notes' => [], 'existing' => $existing];
        }
        $findings = null;
        $tested = $this->tester->available();
        if ($tested) {
            // The original brief is what the result is judged against, also after a change.
            $purpose = $existing ? ($current['description'] ?? $brief) : $brief;
            $findings = $this->test($name, (int) $result['version'], $shape, (string) $purpose, $language, $status);
            if (null !== $findings) {
                $status('repairing');
                try {
                    $fixed = $programmer->write("Testing it in a browser showed these problems — fix them all, keep everything else:\n".$findings, $language, $libraries, $shape, $code, null, $status);
                    $saved = $this->site->defineWidget($name, '', '', $fixed, $shape);
                    if ($saved['ok'] ?? false) {
                        [$code, $result] = [$fixed, $saved];
                        $findings = $this->test($name, (int) $result['version'], $shape, (string) $purpose, $language, $status);
                    }
                } catch (\Throwable) {
                    // the first version stays
                }
            }
        }

        return ['ok' => true, 'errors' => [], 'version' => (int) $result['version'], 'shape' => $shape, 'code' => $code, 'tested' => $tested, 'findings' => $findings, 'notes' => (array) ($result['notes'] ?? []), 'existing' => $existing];
    }

    /**
     * @return string|null what is wrong, or null when it works and looks right
     */
    private function test(string $name, int $version, string $shape, string $purpose, string $language, \Closure $status): ?string
    {
        $status('testing');
        $run = $this->tester->run($name, $version, $shape);
        $problems = array_map(static fn (string $e): string => '- Browser error: '.$e, $run['errors']);
        $status('looking');
        if (null !== $seen = $this->tester->review($purpose, $run['screenshots'], $run['played'], $shape, $language)) {
            $problems[] = $seen;
        }

        return [] === $problems ? null : implode("\n", $problems);
    }
}
