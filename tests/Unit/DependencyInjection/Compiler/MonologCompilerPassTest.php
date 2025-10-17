<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Tests\Unit\DependencyInjection\Compiler;

use MdavidDev\SymfonyCorrelationIdBundle\DependencyInjection\Compiler\MonologCompilerPass;
use MdavidDev\SymfonyCorrelationIdBundle\Monolog\CorrelationIdProcessor;
use MdavidDev\SymfonyCorrelationIdBundle\Service\CorrelationIdStorage;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class MonologCompilerPassTest extends TestCase
{
    public function testRegistersProcessorWhenMonologIsAvailableAndEnabled(): void
    {
        $container = new ContainerBuilder();

        // Simuler la présence du service CorrelationIdStorage
        $container->register(CorrelationIdStorage::class, CorrelationIdStorage::class);

        // Simuler la configuration
        $container->setParameter('correlation_id.monolog', [
            'enabled' => true,
            'key' => 'correlation_id',
        ]);

        $pass = new MonologCompilerPass();
        $pass->process($container);

        // Vérifier que le processor est enregistré
        $this->assertTrue($container->hasDefinition(CorrelationIdProcessor::class));

        $definition = $container->getDefinition(CorrelationIdProcessor::class);

        // Vérifier les arguments
        $this->assertCount(2, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('correlation_id', $definition->getArgument(1));

        // Vérifier le tag
        $this->assertTrue($definition->hasTag('monolog.processor'));
    }

    public function testDoesNotRegisterProcessorWhenDisabled(): void
    {
        $container = new ContainerBuilder();

        $container->setParameter('correlation_id.monolog', [
            'enabled' => false,
            'key' => 'correlation_id',
        ]);

        $pass = new MonologCompilerPass();
        $pass->process($container);

        $this->assertFalse($container->hasDefinition(CorrelationIdProcessor::class));
    }

    public function testDoesNotRegisterProcessorWhenParameterMissing(): void
    {
        $container = new ContainerBuilder();

        $pass = new MonologCompilerPass();
        $pass->process($container);

        $this->assertFalse($container->hasDefinition(CorrelationIdProcessor::class));
    }

    public function testUsesCustomKey(): void
    {
        $container = new ContainerBuilder();

        $container->register(CorrelationIdStorage::class, CorrelationIdStorage::class);

        $container->setParameter('correlation_id.monolog', [
            'enabled' => true,
            'key' => 'custom_request_id',
        ]);

        $pass = new MonologCompilerPass();
        $pass->process($container);

        $this->assertTrue($container->hasDefinition(CorrelationIdProcessor::class));

        $definition = $container->getDefinition(CorrelationIdProcessor::class);
        $this->assertSame('custom_request_id', $definition->getArgument(1));
    }

    public function testHandlesInvalidMonologParameter(): void
    {
        $container = new ContainerBuilder();

        // Paramètre invalide (pas un tableau)
        $container->setParameter('correlation_id.monolog', 'invalid');

        $pass = new MonologCompilerPass();
        $pass->process($container);

        $this->assertFalse($container->hasDefinition(CorrelationIdProcessor::class));
    }

    public function testHandlesMonologConfigWithoutEnabledKey(): void
    {
        $container = new ContainerBuilder();

        $container->setParameter('correlation_id.monolog', [
            'key' => 'correlation_id',
            // 'enabled' manquant
        ]);

        $pass = new MonologCompilerPass();
        $pass->process($container);

        // Sans 'enabled' ou avec 'enabled' = false, ne doit pas enregistrer
        $this->assertFalse($container->hasDefinition(CorrelationIdProcessor::class));
    }

    public function testDoesNotRegisterProcessorWhenMonologNotAvailable(): void
    {
        $container = new ContainerBuilder();

        $container->register(CorrelationIdStorage::class, CorrelationIdStorage::class);
        $container->setParameter('correlation_id.monolog', [
            'enabled' => true,
            'key' => 'correlation_id',
        ]);

        // Créer un mock du CompilerPass qui simule l'absence de Monolog
        $pass = new class extends MonologCompilerPass {
            protected function isMonologAvailable(): bool
            {
                return false; // Simuler l'absence de Monolog
            }
        };

        $pass->process($container);

        // Ne doit pas enregistrer le processor si Monolog n'est pas disponible
        $this->assertFalse($container->hasDefinition(CorrelationIdProcessor::class));
    }

    /**
     * @throws ReflectionException
     */
    public function testIsMonologAvailableReturnsTrue(): void
    {
        $pass = new MonologCompilerPass();

        // Utiliser la réflexion pour tester la méthode protégée
        $reflection = new ReflectionClass($pass);
        $method = $reflection->getMethod('isMonologAvailable');
        $method->setAccessible(true);

        // Monolog est disponible dans les tests
        $this->assertTrue($method->invoke($pass));
    }
}