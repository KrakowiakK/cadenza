<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Catalog;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contributes node types to the catalog. The core provides the UI primitives; every installed block adds its views.
 */
#[AutoconfigureTag('cadenza.node_type_provider')]
interface NodeTypeProviderInterface
{
    /**
     * @return iterable<NodeType>
     */
    public function nodeTypes(): iterable;
}
