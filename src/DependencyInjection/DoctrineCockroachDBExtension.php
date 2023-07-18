<?php

declare(strict_types=1);

namespace DoctrineCockroachDB\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class DoctrineCockroachDBExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $locator = new FileLocator(dirname(__DIR__) . '/../Resources/config');
        $loader = new YamlFileLoader($container, $locator);

        $loader->load('services.yaml');
    }
}
