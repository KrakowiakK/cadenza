<?php

declare(strict_types=1);

namespace App\Agent\Schema;

use Symfony\AI\Agent\Toolbox\ToolFactoryInterface;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Every "array" in a tool's parameters gets an "items" schema. A PHP `array` parameter is described as {"type":
 * "array"} alone, and the chat template of gpt-oss in LM Studio fails on that (it reads `param_spec['items']` and gets
 * the dictionary's method) — the whole request is refused with HTTP 400, so local gpt-oss could not run Cadenza.
 */
#[AsDecorator('ai.tool_factory')]
final class ListItemsToolFactory implements ToolFactoryInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly ToolFactoryInterface $inner,
    ) {
    }

    public function getTool(object|string $reference): iterable
    {
        foreach ($this->inner->getTool($reference) as $tool) {
            $parameters = $tool->getParameters();
            yield null === $parameters ? $tool : new Tool($tool->getReference(), $tool->getName(), $tool->getDescription(), self::withItems($parameters), $tool->getMetadata());
        }
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    public static function withItems(array $schema): array
    {
        if ('array' === ($schema['type'] ?? null) || (\is_array($schema['type'] ?? null) && \in_array('array', $schema['type'], true))) {
            $schema['items'] ??= new \stdClass();
        }
        foreach ($schema as $key => $value) {
            if (\is_array($value) && !array_is_list($value)) {
                $schema[$key] = self::withItems($value);
            } elseif (\is_array($value) && \in_array($key, ['anyOf', 'oneOf', 'allOf'], true)) {
                $schema[$key] = array_map(static fn (mixed $s): mixed => \is_array($s) ? self::withItems($s) : $s, $value);
            }
        }

        return $schema;
    }
}
