<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Catalog;

/**
 * A kind of node the page tree may contain: its props (with a small schema), its slots and the template that renders it.
 *
 * Prop schema entries: ['type' => string|text|markdown|url|image|color|int|bool|enum|list, 'required' => bool,
 * 'values' => [...] for enum, 'default' => mixed, 'items' => [prop schema...] for list of objects or 'of' => type,
 * 'description' => string].
 */
final readonly class NodeType
{
    /**
     * @param array<string, array<string, mixed>> $props
     * @param list<string>                        $slots
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $description,
        public string $category,
        public array $props = [],
        public array $slots = [],
        public ?string $template = null,
        public string $source = 'core',
    ) {
    }

    public function template(): string
    {
        return $this->template ?? '@CadenzaRuntime/node/'.$this->name.'.html.twig';
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $defaults = [];
        foreach ($this->props as $name => $schema) {
            if (\array_key_exists('default', $schema)) {
                $defaults[$name] = $schema['default'];
            }
        }

        return $defaults;
    }

    /**
     * Compact description for the agent.
     *
     * @return array<string, mixed>
     */
    public function describe(): array
    {
        $props = [];
        foreach ($this->props as $name => $schema) {
            $props[$name] = self::describeProp($schema);
        }

        return array_filter([
            'type' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'props' => $props,
            'slots' => $this->slots ?: null,
        ]);
    }

    /**
     * @param array<string, mixed> $schema
     */
    private static function describeProp(array $schema): string
    {
        $type = $schema['type'];
        if ('enum' === $type) {
            $type = implode('|', array_map(static fn (mixed $v): string => var_export($v, true), $schema['values']));
        } elseif ('list' === $type) {
            $type = isset($schema['items'])
                ? 'list of {'.implode(', ', array_map(
                    static fn (string $k, array $s): string => $k.': '.self::describeProp($s),
                    array_keys($schema['items']),
                    $schema['items'],
                )).'}'
                : 'list of '.($schema['of'] ?? 'string');
        }

        $parts = [$type];
        if ($schema['required'] ?? false) {
            $parts[] = 'required';
        }
        if (\array_key_exists('default', $schema) && null !== $schema['default'] && [] !== $schema['default']) {
            $parts[] = 'default '.json_encode($schema['default'], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        }
        if (isset($schema['description'])) {
            $parts[] = '— '.$schema['description'];
        }

        return implode(', ', $parts);
    }
}
