<?php

declare(strict_types=1);

namespace Cadenza\Source\Instagram;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class CadenzaSourceInstagramBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()
            ->defaults()->autowire()->autoconfigure()
            ->set(InstagramConnector::class)
                ->arg('$clientId', '%env(default::INSTAGRAM_CLIENT_ID)%')
                ->arg('$clientSecret', '%env(default::INSTAGRAM_CLIENT_SECRET)%');
    }
}
