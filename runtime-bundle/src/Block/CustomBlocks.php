<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Block;

use Cadenza\Runtime\Catalog\Catalog;
use Cadenza\Runtime\Catalog\NodeType;
use Cadenza\Runtime\Catalog\NodeTypeProviderInterface;
use Cadenza\Runtime\Entity\CustomBlock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Block types written by the agent for this site. They join the node catalog like built-in patterns; their templates
 * are checked with sample props before they are saved.
 */
#[AutoconfigureTag('cadenza.node_type_provider')]
final class CustomBlocks implements NodeTypeProviderInterface
{
    private const PROP_TYPES = ['string', 'text', 'markdown', 'url', 'image', 'int', 'bool', 'enum', 'list', 'date', 'color'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BlockRenderer $renderer,
        // The catalog lists this provider too; its providers are a lazy iterator, so there is no circle.
        private readonly Catalog $catalog,
        #[\Symfony\Component\DependencyInjection\Attribute\Target('cadenza.event_bus')]
        private readonly \Symfony\Component\Messenger\MessageBusInterface $events,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%cadenza.blocks_dir%')]
        private readonly string $sourceDir,
    ) {
    }

    public function nodeTypes(): iterable
    {
        try {
            $blocks = $this->em->getRepository(CustomBlock::class)->findBy([], ['name' => 'ASC']);
        } catch (\Throwable) {
            return; // no table yet (before migrations)
        }
        foreach ($blocks as $block) {
            yield new NodeType($block->getName(), $block->getLabel(), $block->getDescription().' (a block written for this site)', 'custom', self::normaliseProps($block->getProps())[0], [], null, 'custom');
        }
    }

    public function get(string $name): ?CustomBlock
    {
        return $this->em->getRepository(CustomBlock::class)->findOneBy(['name' => $name]);
    }

    /**
     * @return list<CustomBlock>
     */
    public function all(): array
    {
        return $this->em->getRepository(CustomBlock::class)->findBy([], ['name' => 'ASC']);
    }

    /**
     * Creates a block type, or a new version of one this site already has.
     *
     * @param array<string, mixed> $props prop name => schema (type, required, default, values, items, of, description)
     *
     * @return array{block: ?CustomBlock, errors: list<string>}
     */
    public function save(string $name, string $label, string $description, array $props, string $template): array
    {
        $errors = [];
        if (!preg_match('/^[a-z][a-z0-9-]{2,38}$/', $name)) {
            $errors[] = 'name: lowercase letters, digits and dashes, e.g. "recipe-card".';
        }
        $existing = $this->get($name);
        $catalog = $this->catalog;
        if (null === $existing && $catalog->has($name)) {
            $errors[] = \sprintf('name: "%s" is a built-in block; choose another name.', $name);
        }
        [$props, $propErrors] = self::normaliseProps($props);
        $errors = [...$errors, ...$propErrors];
        if ([] === $errors) {
            $errors = $this->renderer->check($template, self::sample($props));
        }
        if ([] !== $errors) {
            return ['block' => null, 'errors' => $errors];
        }
        if (null !== $existing) {
            $existing->revise($label, $description, $props, $template);
            $block = $existing;
        } else {
            $block = new CustomBlock($name, $label, $description, $props, $template);
            $this->em->persist($block);
        }
        $this->em->flush();
        $catalog->reset();
        // A copy Tailwind can read, so the block's classes end up in the stylesheet.
        if (!is_dir($this->sourceDir)) {
            mkdir($this->sourceDir, 0o775, true);
        }
        file_put_contents($this->sourceDir.'/'.$block->getName().'.html.twig', $block->getTemplate());
        $this->events->dispatch(new \Cadenza\Runtime\Message\BlockDefined($block->getName(), $block->getVersion()));

        return ['block' => $block, 'errors' => []];
    }

    /**
     * @param array<string, mixed> $props
     */
    public function render(string $name, array $props, ?\Closure $icon = null): string
    {
        $block = $this->get($name) ?? throw new \RuntimeException(\sprintf('Block type "%s" no longer exists.', $name));

        return $this->renderer->render($block->getName(), $block->getVersion(), $block->getTemplate(), $props, $icon);
    }

    /**
     * @param array<string, mixed> $props
     *
     * @return array{0: array<string, array<string, mixed>>, 1: list<string>}
     */
    public static function normaliseProps(array $props): array
    {
        $out = [];
        $errors = [];
        foreach ($props as $name => $schema) {
            if (\is_string($schema)) {
                $schema = ['type' => $schema];
            }
            if (!\is_string($name) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,30}$/', $name) || !\is_array($schema)) {
                $errors[] = \sprintf('props: "%s" needs a name like "title" and a schema like {"type": "string"}.', \is_string($name) ? $name : '#'.$name);
                continue;
            }
            $type = $schema['type'] ?? 'string';
            if (!\in_array($type, self::PROP_TYPES, true)) {
                $errors[] = \sprintf('props.%s: type is one of %s.', $name, implode(', ', self::PROP_TYPES));
                continue;
            }
            if ('enum' === $type && (!\is_array($schema['values'] ?? null) || [] === $schema['values'])) {
                $errors[] = \sprintf('props.%s: an enum needs "values".', $name);
                continue;
            }
            if ('list' === $type) {
                // Models write lists in a few dialects: "of" with an object of fields means "items"; "items" with a
                // single {"type": …} means "of"; nothing means a list of strings.
                if (\is_array($schema['of'] ?? null)) {
                    $schema['items'] ??= $schema['of'];
                    unset($schema['of']);
                }
                if (\is_array($schema['items'] ?? null) && \is_string($schema['items']['type'] ?? null) && !\is_array($schema['items']['type'])) {
                    $schema['of'] = $schema['items']['type'];
                    unset($schema['items']);
                }
                if (isset($schema['items'])) {
                    [$items, $itemErrors] = self::normaliseProps((array) $schema['items']);
                    $schema['items'] = $items;
                    array_push($errors, ...array_map(static fn (string $e): string => 'props.'.$name.'.items: '.$e, $itemErrors));
                } elseif (!\in_array($schema['of'] ?? 'string', ['string', 'text', 'int', 'image', 'url'], true)) {
                    $errors[] = \sprintf('props.%s: a list has "items" (an object of fields) or "of" (string, text, int, image, url).', $name);
                } else {
                    $schema['of'] ??= 'string';
                }
            }
            $out[$name] = array_intersect_key($schema, array_flip(['type', 'required', 'default', 'values', 'items', 'of', 'description']));
        }

        return [$out, $errors];
    }

    /**
     * Sample values for every prop, to try a template before it is saved.
     *
     * @param array<string, array<string, mixed>> $props
     *
     * @return array<string, mixed>
     */
    public static function sample(array $props): array
    {
        $sample = [];
        foreach ($props as $name => $schema) {
            $sample[$name] = match ($schema['type'] ?? 'string') {
                'int' => 3,
                'bool' => true,
                'enum' => $schema['values'][0],
                'image' => '/media/sample.jpg',
                'url' => '/',
                'date' => '2030-06-14',
                'color' => '#336699',
                'list' => isset($schema['items']) ? [self::sample($schema['items']), self::sample($schema['items'])] : ['First', 'Second'],
                default => 'Sample text',
            };
        }

        return $sample;
    }
}
