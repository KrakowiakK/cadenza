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
                ->scalarNode('token')->isRequired()->cannotBeEmpty()->info('Shared secret with the Cadenza harness (MCP and studio API); also allows creating the first owner account from another machine')->end()
                ->scalarNode('harness_url')->defaultValue('http://127.0.0.1:8011')->end()
                ->scalarNode('notify_email')->defaultNull()->info('Where to send a note when a visitor sends a form (needs MAILER_DSN)')->end()
            ->end();
    }

    /**
     * @param array{token: string, harness_url: string, notify_email: ?string} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            ->set('cadenza.token', $config['token'])
            ->set('cadenza.harness_url', rtrim($config['harness_url'], '/'))
            ->set('cadenza.notify_email', $config['notify_email'] ?: null);

        $container->services()
            ->defaults()->autowire()->autoconfigure()
            ->load('Cadenza\\Runtime\\', '../src/')
            ->exclude(['../src/Entity/', '../src/CadenzaRuntimeBundle.php', '../src/Operation/ApplyResult.php', '../src/Operation/OperationException.php', '../src/Draft/DraftResult.php', '../src/Site/Diff.php', '../src/Site/Document.php', '../src/Site/Outline.php', '../src/Catalog/NodeType.php', '../src/Theme/', '../src/Message/', '../src/Connector/ImportContext.php']);
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
            'messenger' => [
                'buses' => [
                    'messenger.bus.default' => [],
                    // Events (MediaImported…): blocks may or may not listen, so no handler is fine.
                    'cadenza.event_bus' => ['default_middleware' => ['enabled' => true, 'allow_no_handlers' => true]],
                ],
                'routing' => [
                    'Cadenza\\Runtime\\Message\\RunImport' => 'async',
                    'Cadenza\\Runtime\\Message\\SyncConnections' => 'async',
                    'Cadenza\\Runtime\\Message\\FormSubmitted' => 'async',
                ],
            ],
            // Visitors' forms: a few answers per address in a while is plenty for a person, too few for a bot.
            'rate_limiter' => ['cadenza_form' => ['policy' => 'sliding_window', 'limit' => 6, 'interval' => '10 minutes']],
        ]);
    }
}
