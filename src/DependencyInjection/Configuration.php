<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('correlation_id');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('header_name')
                    ->info('HTTP header name for correlation ID')
                    ->defaultValue('X-Correlation-ID')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('generator')
                    ->info('ID generator type: uuid_v4, uuid_v7, ulid, or service ID')
                    ->defaultValue('uuid_v4')
                    ->cannotBeEmpty()
                ->end()
                ->booleanNode('trust_header')
                    ->info('Trust incoming correlation ID from header')
                    ->defaultTrue()
                ->end()
                ->arrayNode('validation')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Enable validation of incoming correlation IDs')
                            ->defaultTrue()
                        ->end()
                        ->integerNode('max_length')
                            ->info('Maximum length for correlation ID')
                            ->defaultValue(255)
                            ->min(1)
                        ->end()
                        ->scalarNode('pattern')
                            ->info('Regex pattern to validate correlation ID format (security: alphanumeric, dashes, underscores only)')
                            ->defaultValue('/^[a-zA-Z0-9-_]+$/')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('monolog')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Enable Monolog integration')
                            ->defaultTrue()
                        ->end()
                        ->scalarNode('key')
                            ->info('Log context key for correlation ID')
                            ->defaultValue('correlation_id')
                            ->cannotBeEmpty()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('http_client')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Enable HttpClient integration')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('messenger')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Enable Messenger integration')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('cli')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Enable CLI integration')
                            ->defaultTrue()
                        ->end()
                        ->scalarNode('prefix')
                            ->info('Prefix for CLI-generated correlation IDs')
                            ->defaultValue('CLI-')
                        ->end()
                        ->booleanNode('allow_env_var')
                            ->info('Allow CORRELATION_ID environment variable')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
