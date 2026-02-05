<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Tests\Functional\Integration;

use Exception;
use MdavidDev\SymfonyCorrelationIdBundle\Service\CorrelationIdStorage;
use MdavidDev\SymfonyCorrelationIdBundle\SymfonyCorrelationIdBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ConsoleIntegrationTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testConsoleCommandGeneratesId(): void
    {
        $kernel = new ConsoleTestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        /** @var CorrelationIdStorage $storage */
        $storage = $container->get(CorrelationIdStorage::class);

        $application = new Application();
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $container->get('event_dispatcher');
        $application->setDispatcher($dispatcher);
        $application->setAutoExit(false);

        $command = new class($storage) extends Command {
            public function __construct(private readonly CorrelationIdStorage $storage)
            {
                parent::__construct('test:command');
            }

            protected function execute($input, $output): int
            {
                $output->writeln('ID: ' . ($this->storage->get() ?? 'NULL'));
                return Command::SUCCESS;
            }
        };
        $application->addCommands([$command]);

        $this->assertFalse($storage->has());

        $input = new ArrayInput(['command' => 'test:command']);
        $output = new NullOutput();
        $application->run($input, $output);

        $this->assertFalse($storage->has());

        $kernel->shutdown();
    }

    /**
     * @throws Exception
     */
    public function testConsoleCommandUsesProvidedEnvVar(): void
    {
        $_SERVER['CORRELATION_ID'] = 'manual-id-123';
        
        $kernel = new ConsoleTestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        /** @var CorrelationIdStorage $storage */
        $storage = $container->get(CorrelationIdStorage::class);

        $application = new Application();
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $container->get('event_dispatcher');
        $application->setDispatcher($dispatcher);
        $application->setAutoExit(false);

        $capturedId = null;
        $command = new class($storage, $capturedId) extends Command {
            public ?string $capturedId = null;
            public function __construct(private readonly CorrelationIdStorage $storage, &$capturedId)
            {
                parent::__construct('test:envvar');
                $this->capturedId = &$capturedId;
            }

            protected function execute($input, $output): int
            {
                $this->capturedId = $this->storage->get();
                return Command::SUCCESS;
            }
        };
        $application->addCommands([$command]);

        $input = new ArrayInput(['command' => 'test:envvar']);
        $application->run($input, new NullOutput());

        $this->assertSame('manual-id-123', $capturedId);
        $this->assertFalse($storage->has());

        unset($_SERVER['CORRELATION_ID']);
        $kernel->shutdown();
    }
}

class ConsoleTestKernel extends Kernel
{
    public function registerBundles(): array
    {
        return [
            new FrameworkBundle(),
            new SymfonyCorrelationIdBundle(),
        ];
    }

    /**
     * @throws Exception
     */
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'secret' => 'test-secret',
                'test' => true,
            ]);

            if ($container->hasAlias(CorrelationIdStorage::class)) {
                $container->getAlias(CorrelationIdStorage::class)->setPublic(true);
            }

            if ($container->hasAlias('event_dispatcher')) {
                $container->getAlias('event_dispatcher')->setPublic(true);
            }
        });
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach ($container->getDefinitions() as $id => $definition) {
                    if (str_starts_with($id, 'MdavidDev\\SymfonyCorrelationIdBundle\\')) {
                        $definition->setPublic(true);
                    }
                }
            }
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/symfony-correlation-id-bundle/cache/' . spl_object_hash($this);
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/symfony-correlation-id-bundle/logs';
    }
}
