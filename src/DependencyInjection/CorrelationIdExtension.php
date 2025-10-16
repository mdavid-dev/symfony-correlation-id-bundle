<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class CorrelationIdExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Enregistrer la configuration comme paramètres
        $container->setParameter('correlation_id.header_name', $config['header_name']);
        $container->setParameter('correlation_id.generator', $config['generator']);
        $container->setParameter('correlation_id.trust_header', $config['trust_header']);
        $container->setParameter('correlation_id.validation', $config['validation']);
        $container->setParameter('correlation_id.monolog', $config['monolog']);
        $container->setParameter('correlation_id.http_client', $config['http_client']);
        $container->setParameter('correlation_id.messenger', $config['messenger']);
        $container->setParameter('correlation_id.cli', $config['cli']);

        // Charger les services
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../config')
        );
        $loader->load('services.yaml');
    }

    public function getAlias(): string
    {
        return 'correlation_id';
    }
}