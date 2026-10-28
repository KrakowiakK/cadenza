<?php

declare(strict_types=1);

namespace App\Agent;

use Symfony\AI\Agent\Toolbox\ToolCallArgumentResolverInterface;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Local and smaller models often send "9" for an int, "true" for a bool or a JSON string for a list.
 * Coerce such values to the declared PHP type before the strict resolver sees them, instead of failing the call.
 */
#[AsDecorator('ai.tool_call_argument_resolver')]
final class LenientArgumentResolver implements ToolCallArgumentResolverInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly ToolCallArgumentResolverInterface $inner,
    ) {
    }

    public function resolveArguments(Tool $metadata, ToolCall $toolCall): array
    {
        $reference = $metadata->getReference();
        $method = new \ReflectionMethod($reference->getClass(), $reference->getMethod());
        $arguments = $toolCall->getArguments();
        $changed = false;

        foreach ($method->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (!\array_key_exists($name, $arguments)) {
                continue;
            }
            $value = $arguments[$name];
            $types = $this->typeNames($parameter);
            $coerced = $value;

            if (null === $value || '' === $value || 'null' === $value) {
                $coerced = $parameter->allowsNull() ? null : $value;
            } elseif (\is_string($value) && \in_array('int', $types, true) && !\in_array('string', $types, true) && preg_match('/^-?\d+$/', trim($value))) {
                $coerced = (int) trim($value);
            } elseif (\is_string($value) && \in_array('float', $types, true) && !\in_array('string', $types, true) && is_numeric(trim($value))) {
                $coerced = (float) trim($value);
            } elseif (\is_string($value) && \in_array('bool', $types, true) && !\in_array('string', $types, true)) {
                $coerced = \in_array(strtolower(trim($value)), ['1', 'true', 'yes'], true);
            } elseif (\is_string($value) && \in_array('array', $types, true) && !\in_array('string', $types, true)) {
                $decoded = json_decode(trim($value), true);
                $coerced = \is_array($decoded) ? $decoded : array_values(array_filter(array_map('trim', explode(',', $value)), 'strlen'));
            } elseif (\is_int($value) && \in_array('string', $types, true) && !\in_array('int', $types, true)) {
                $coerced = (string) $value;
            }

            if ($coerced !== $value) {
                $arguments[$name] = $coerced;
                $changed = true;
            }
        }

        if ($changed) {
            $toolCall = new ToolCall($toolCall->getId(), $toolCall->getName(), $arguments, $toolCall->getSignature());
        }

        return $this->inner->resolveArguments($metadata, $toolCall);
    }

    /**
     * @return list<string>
     */
    private function typeNames(\ReflectionParameter $parameter): array
    {
        $type = $parameter->getType();
        if ($type instanceof \ReflectionNamedType) {
            return [$type->getName()];
        }
        if ($type instanceof \ReflectionUnionType) {
            return array_map(static fn (\ReflectionNamedType $t): string => $t->getName(), array_filter($type->getTypes(), static fn ($t): bool => $t instanceof \ReflectionNamedType));
        }

        return [];
    }
}
