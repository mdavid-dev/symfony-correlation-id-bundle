<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Tests\Unit\DependencyInjection\Compiler;

use MdavidDev\SymfonyCorrelationIdBundle\DependencyInjection\Compiler\MonologCompilerPass;
use MdavidDev\SymfonyCorrelationIdBundle\Monolog\CorrelationIdProcessor;
use MdavidDev\SymfonyCorrelationIdBundle\Service\CorrelationIdStorage;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use stdClass;
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

    public function testAddsProcessorToAllTaggedLoggers(): void
    {
        $container = new ContainerBuilder();

        $container->register(CorrelationIdStorage::class, CorrelationIdStorage::class);

        $container->setParameter('correlation_id.monolog', [
            'enabled' => true,
            'key' => 'correlation_id',
        ]);

        // Simuler plusieurs loggers Monolog avec des IDs qui commencent par "monolog.logger"
        // ET avec la classe Monolog\Logger explicitement définie
        $logger1 = $container->register('monolog.logger.app');
        $logger1->setClass(Logger::class);

        $logger2 = $container->register('monolog.logger.security');
        $logger2->setClass(Logger::class);

        // Ajouter aussi un service qui ne doit PAS recevoir le processor
        $container->register('some.other.service', stdClass::class);

        $pass = new MonologCompilerPass();
        $pass->process($container);

        // Vérifier que le processor a été ajouté aux deux loggers
        $logger1Def = $container->getDefinition('monolog.logger.app');
        $logger2Def = $container->getDefinition('monolog.logger.security');

        $calls1 = $logger1Def->getMethodCalls();
        $calls2 = $logger2Def->getMethodCalls();

        $this->assertNotEmpty($calls1, 'Logger app should have method calls');
        $this->assertNotEmpty($calls2, 'Logger security should have method calls');

        // Vérifier qu'il y a un appel à pushProcessor
        $pushProcessorCalls1 = array_filter($calls1, fn($call) => $call[0] === 'pushProcessor');
        $pushProcessorCalls2 = array_filter($calls2, fn($call) => $call[0] === 'pushProcessor');

        $this->assertCount(1, $pushProcessorCalls1, 'Logger app should have one pushProcessor call');
        $this->assertCount(1, $pushProcessorCalls2, 'Logger security should have one pushProcessor call');

        // Vérifier que l'autre service n'a PAS de pushProcessor
        $otherServiceDef = $container->getDefinition('some.other.service');
        $otherCalls = $otherServiceDef->getMethodCalls();
        $this->assertEmpty($otherCalls, 'Other service should not have method calls');
    }

    public function testSkipsServicesWithoutMonologLoggerPrefix(): void
    {
        $container = new ContainerBuilder();

        $container->register(CorrelationIdStorage::class, CorrelationIdStorage::class);

        $container->setParameter('correlation_id.monolog', [
            'enabled' => true,
            'key' => 'correlation_id',
        ]);

        // Service qui ne commence pas par "monolog.logger"
        $otherService = $container->register('app.logger');
        $otherService->setClass(Logger::class);

        $pass = new MonologCompilerPass();
        $pass->process($container);

        // Ce service ne doit PAS avoir le processor
        $calls = $otherService->getMethodCalls();
        $this->assertEmpty($calls);
    }

    public function testSkipsServicesWithNonMonologClass(): void
    {
        $container = new ContainerBuilder();

        $container->register(CorrelationIdStorage::class, CorrelationIdStorage::class);

        $container->setParameter('correlation_id.monolog', [
            'enabled' => true,
            'key' => 'correlation_id',
        ]);

        // Service avec le bon ID mais pas la bonne classe
        $notALogger = $container->register('monolog.logger.fake');
        $notALogger->setClass(stdClass::class);

        $pass = new MonologCompilerPass();
        $pass->process($container);

        // Ce service ne doit PAS avoir le processor (ce n'est pas un Monolog\Logger)
        $calls = $notALogger->getMethodCalls();
        $this->assertEmpty($calls);
    }

    public function testHandlesLoggerWithParameterClass(): void
    {
        $container = new ContainerBuilder();

        $container->register(CorrelationIdStorage::class, CorrelationIdStorage::class);

        $container->setParameter('correlation_id.monolog', [
            'enabled' => true,
            'key' => 'correlation_id',
        ]);

        // Définir le paramètre correctement avec clé et valeur
        $container->setParameter('monolog.logger.class', Logger::class);

        // Logger dont la classe est définie par un paramètre
        $logger = $container->register('monolog.logger.param');
        $logger->setClass('%monolog.logger.class%');

        $pass = new MonologCompilerPass();
        $pass->process($container);

        // Ce logger DOIT avoir le processor
        $calls = $logger->getMethodCalls();
        $this->assertNotEmpty($calls);

        $pushProcessorCalls = array_filter($calls, fn($call) => $call[0] === 'pushProcessor');
        $this->assertCount(1, $pushProcessorCalls);
    }

    public function testSkipsLoggerWithNullClassAndNonMonologId(): void
    {
        $container = new ContainerBuilder();

        $container->register(CorrelationIdStorage::class, CorrelationIdStorage::class);

        $container->setParameter('correlation_id.monolog', [
            'enabled' => true,
            'key' => 'correlation_id',
        ]);

        // Service avec le bon préfixe mais classe null et ID qui n'est pas Monolog\Logger
        $logger = $container->register('monolog.logger.null_class');
        // Ne pas définir de classe (getClass() retournera null)
        // L'ID sera utilisé comme fallback, mais ce n'est pas une classe valide

        $pass = new MonologCompilerPass();
        $pass->process($container);

        // Ce service ne doit PAS avoir le processor car l'ID n'est pas une classe Monolog
        $calls = $logger->getMethodCalls();
        $this->assertEmpty($calls);
    }
}