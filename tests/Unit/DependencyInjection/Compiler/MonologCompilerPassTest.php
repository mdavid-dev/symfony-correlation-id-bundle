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

        $container->register(CorrelationIdStorage::class, CorrelationIdStorage::class);

        $container->setParameter('correlation_id.monolog', [
            'enabled' => true,
            'key' => 'correlation_id',
        ]);

        $pass = new MonologCompilerPass();
        $pass->process($container);

        $this->assertTrue($container->hasDefinition(CorrelationIdProcessor::class));

        $definition = $container->getDefinition(CorrelationIdProcessor::class);

        $this->assertCount(2, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('correlation_id', $definition->getArgument(1));

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
        ]);

        $pass = new MonologCompilerPass();
        $pass->process($container);

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

        $pass = new class extends MonologCompilerPass {
            protected function isMonologAvailable(): bool
            {
                return false;
            }
        };

        $pass->process($container);

        $this->assertFalse($container->hasDefinition(CorrelationIdProcessor::class));
    }

    /**
     * @throws ReflectionException
     */
    public function testIsMonologAvailableReturnsTrue(): void
    {
        $pass = new MonologCompilerPass();

        $reflection = new ReflectionClass($pass);
        $method = $reflection->getMethod('isMonologAvailable');
        $method->setAccessible(true);

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

        $logger1 = $container->register('monolog.logger.app');
        $logger1->setClass(Logger::class);

        $logger2 = $container->register('monolog.logger.security');
        $logger2->setClass(Logger::class);

        $container->register('some.other.service', stdClass::class);

        $pass = new MonologCompilerPass();
        $pass->process($container);

        $logger1Def = $container->getDefinition('monolog.logger.app');
        $logger2Def = $container->getDefinition('monolog.logger.security');

        $calls1 = $logger1Def->getMethodCalls();
        $calls2 = $logger2Def->getMethodCalls();

        $this->assertNotEmpty($calls1, 'Logger app should have method calls');
        $this->assertNotEmpty($calls2, 'Logger security should have method calls');

        $pushProcessorCalls1 = array_filter($calls1, fn($call) => $call[0] === 'pushProcessor');
        $pushProcessorCalls2 = array_filter($calls2, fn($call) => $call[0] === 'pushProcessor');

        $this->assertCount(1, $pushProcessorCalls1, 'Logger app should have one pushProcessor call');
        $this->assertCount(1, $pushProcessorCalls2, 'Logger security should have one pushProcessor call');

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

        $otherService = $container->register('app.logger');
        $otherService->setClass(Logger::class);

        $pass = new MonologCompilerPass();
        $pass->process($container);

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

        $notALogger = $container->register('monolog.logger.fake');
        $notALogger->setClass(stdClass::class);

        $pass = new MonologCompilerPass();
        $pass->process($container);

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

        $container->setParameter('monolog.logger.class', Logger::class);

        $logger = $container->register('monolog.logger.param');
        $logger->setClass('%monolog.logger.class%');

        $pass = new MonologCompilerPass();
        $pass->process($container);

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

        $logger = $container->register('monolog.logger.null_class');

        $pass = new MonologCompilerPass();
        $pass->process($container);

        $calls = $logger->getMethodCalls();
        $this->assertEmpty($calls);
    }
}
