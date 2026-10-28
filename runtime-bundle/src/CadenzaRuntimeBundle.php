<?php

declare(strict_types=1);

namespace Cadenza\Runtime;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class CadenzaRuntimeBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('owner_mode')->defaultValue('token')->info('"always" for local development, "token" otherwise')->end()
                ->scalarNode('token')->isRequired()->cannotBeEmpty()->info('Shared secret with the Cadenza harness; also the owner login token')->end()
                ->scalarNode('harness_url')->defaultValue('http://127.0.0.1:8011')->end()
            ->end();
    }

    /**
     * @param array{owner_mode: string, token: string, harness_url: string} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            ->set('cadenza.owner_mode', $config['owner_mode'])
            ->set('cadenza.token', $config['token'])
            ->set('cadenza.harness_url', rtrim($config['harness_url'], '/'));

        $container->services()
            ->defaults()->autowire()->autoconfigure()
            ->load('Cadenza\\Runtime\\', '../src/')
            ->exclude(['../src/Entity/', '../src/CadenzaRuntimeBundle.php', '../src/Operation/ApplyResult.php', '../src/Operation/OperationException.php', '../src/Draft/DraftResult.php', '../src/Site/Diff.php', '../src/Site/Document.php', '../src/Site/Outline.php', '../src/Catalog/NodeType.php', '../src/Theme/']);
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('doctrine', [
            'orm' => ['mappings' => ['CadenzaRuntime' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => $this->getPath().'/src/Entity',
                'prefix' => 'Cadenza\\Runtime\\Entity',
                'alias' => 'Cadenza',
            ]]],
        ]);
        $builder->prependExtensionConfig('framework', [
            'asset_mapper' => ['paths' => [$this->getPath().'/assets' => '@cadenza/runtime']],
        ]);
    }
}
