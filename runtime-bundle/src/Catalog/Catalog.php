<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Catalog;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class Catalog
{
    /** @var array<string, NodeType>|null */
    private ?array $types = null;

    /**
     * @param iterable<NodeTypeProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator('cadenza.node_type_provider')]
        private readonly iterable $providers,
    ) {
    }

    public function has(string $name): bool
    {
        return isset($this->all()[$name]);
    }

    public function get(string $name): NodeType
    {
        return $this->all()[$name] ?? throw new \InvalidArgumentException(\sprintf('Unknown node type "%s". Known types: %s.', $name, implode(', ', array_keys($this->all()))));
    }

    /**
     * @return array<string, NodeType>
     */
    public function all(): array
    {
        if (null === $this->types) {
            $this->types = [];
            foreach ($this->providers as $provider) {
                foreach ($provider->nodeTypes() as $type) {
                    $this->types[$type->name] = $type;
                }
            }
        }

        return $this->types;
    }
}
