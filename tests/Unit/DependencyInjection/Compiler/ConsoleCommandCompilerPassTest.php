<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Tests\Unit\DependencyInjection\Compiler;

use MdavidDev\SymfonyCorrelationIdBundle\Console\ApplicationDecorator;
use MdavidDev\SymfonyCorrelationIdBundle\DependencyInjection\Compiler\ConsoleCommandCompilerPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class ConsoleCommandCompilerPassTest extends TestCase
{
    private ConsoleCommandCompilerPass $compilerPass;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->compilerPass = new ConsoleCommandCompilerPass();
        $this->container = new ContainerBuilder();
    }

    public function testProcessDoesNothingIfCliOptionIsDisabled(): void
    {
        $this->container->setParameter('correlation_id.cli.allow_option', false);
        $this->container->register('console.application', 'Symfony\Component\Console\Application');

        $this->compilerPass->process($this->container);

        $this->assertFalse($this->container->hasDefinition(ApplicationDecorator::class));
    }

    public function testProcessDoesNothingIfCliOptionParameterIsMissing(): void
    {
        $this->container->register('console.application', 'Symfony\Component\Console\Application');

        $this->compilerPass->process($this->container);

        $this->assertFalse($this->container->hasDefinition(ApplicationDecorator::class));
    }

    public function testProcessDoesNothingIfConsoleApplicationDefinitionIsMissing(): void
    {
        $this->container->setParameter('correlation_id.cli.allow_option', true);

        $this->compilerPass->process($this->container);

        $this->assertFalse($this->container->hasDefinition(ApplicationDecorator::class));
    }

    public function testProcessRegistersDecorator(): void
    {
        $this->container->setParameter('correlation_id.cli.allow_option', true);
        $this->container->register('console.application', 'Symfony\Component\Console\Application');

        $this->compilerPass->process($this->container);

        $this->assertTrue($this->container->hasDefinition(ApplicationDecorator::class));
        $definition = $this->container->getDefinition(ApplicationDecorator::class);

        $this->assertSame('console.application', $definition->getDecoratedService()[0]);
        $this->assertSame('.inner', (string) $definition->getArgument(0));
        
        $methodCalls = $definition->getMethodCalls();
        $this->assertCount(1, $methodCalls);
        $this->assertSame('setDispatcher', $methodCalls[0][0]);
        $this->assertSame('event_dispatcher', (string) $methodCalls[0][1][0]);
    }

    public function testProcessDoesNotRegisterDecoratorIfAlreadyDefined(): void
    {
        $this->container->setParameter('correlation_id.cli.allow_option', true);
        $this->container->register('console.application', 'Symfony\Component\Console\Application');

        $manualDefinition = new Definition(ApplicationDecorator::class);
        $this->container->setDefinition(ApplicationDecorator::class, $manualDefinition);

        $this->compilerPass->process($this->container);

        $this->assertSame($manualDefinition, $this->container->getDefinition(ApplicationDecorator::class));
        $this->assertEmpty($manualDefinition->getMethodCalls());
    }
}
