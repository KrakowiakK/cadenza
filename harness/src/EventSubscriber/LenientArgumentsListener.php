<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Agent\Tool\SiteTools;
use App\Agent\Tool\UiTools;
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Smaller models sometimes pass a list as a JSON string ("operations": "[{…}]"). Instead of failing the call,
 * decode it and run the tool with the real list.
 */
final class LenientArgumentsListener
{
    public function __construct(
        private readonly SiteTools $siteTools,
        private readonly UiTools $uiTools,
        private readonly ToolTraceSubscriber $trace,
    ) {
    }

    #[AsEventListener(priority: -10)]
    public function __invoke(ToolCallRequested $event): void
    {
        $call = $event->getToolCall();
        $args = $call->getArguments();
        $listArgument = match ($call->getName()) {
            'propose_changes' => 'operations',
            'point_at' => 'nodeIds',
            default => null,
        };
        if (null === $listArgument || !\is_string($args[$listArgument] ?? null)) {
            return;
        }
        $raw = trim($args[$listArgument]);
        $decoded = json_decode($raw, true);
        if (!\is_array($decoded)) {
            // Common damage: Markdown code fences, or the whole JSON escaped once more.
            $raw = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $raw) ?? $raw;
            $decoded = json_decode($raw, true) ?? json_decode(stripcslashes($raw), true);
            if (\is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }
        }
        if (\is_array($decoded) && !array_is_list($decoded)) {
            $decoded = [$decoded];
        }
        if (!\is_array($decoded)) {
            $event->deny(\sprintf('"%s" must be a JSON array, not a string. Call the tool again passing the array itself.', $listArgument));
            $this->trace->finish($call->getName(), false);

            return;
        }

        $result = match ($call->getName()) {
            'propose_changes' => $this->siteTools->propose($decoded, (string) ($args['summary'] ?? ''), isset($args['reviseDraftId']) ? (int) $args['reviseDraftId'] : null),
            'point_at' => $this->uiTools->highlight(array_map('strval', $decoded)),
        };
        $event->setResult(new ToolResult($call, $result));
        $this->trace->finish($call->getName(), !str_starts_with($result, 'The site rejected'));
    }
}
