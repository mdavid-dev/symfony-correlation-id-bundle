<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\DependencyInjection;

use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class CorrelationIdExtension extends Extension
{
    /**
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('correlation_id.header_name', $config['header_name']);
        $container->setParameter('correlation_id.generator', $config['generator']);
        $container->setParameter('correlation_id.trust_header', $config['trust_header']);

        $container->setParameter('correlation_id.validation.enabled', $config['validation']['enabled']);
        $container->setParameter('correlation_id.validation.max_length', $config['validation']['max_length']);
        $container->setParameter('correlation_id.validation.pattern', $config['validation']['pattern']);

        $container->setParameter('correlation_id.monolog', $config['monolog']);
        $container->setParameter('correlation_id.http_client', $config['http_client']);
        $container->setParameter('correlation_id.messenger', $config['messenger']);
        $container->setParameter('correlation_id.cli', $config['cli']);
        $container->setParameter('correlation_id.cli.enabled', $config['cli']['enabled']);
        $container->setParameter('correlation_id.cli.prefix', $config['cli']['prefix']);
        $container->setParameter('correlation_id.cli.allow_option', $config['cli']['allow_option']);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../config')
        );
        $loader->load('services.yaml');

        if (!$config['cli']['enabled']) {
            $container->removeDefinition('MdavidDev\SymfonyCorrelationIdBundle\EventListener\ConsoleListener');
        }
    }

    public function getAlias(): string
    {
        return 'correlation_id';
    }
}
