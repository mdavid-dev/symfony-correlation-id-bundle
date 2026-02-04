<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Tests\Functional\Service;

use Exception;
use MdavidDev\SymfonyCorrelationIdBundle\Service\CorrelationIdStorage;
use MdavidDev\SymfonyCorrelationIdBundle\SymfonyCorrelationIdBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

class CorrelationIdStorageTest extends TestCase
{
    public function testStorageServiceIsAvailableInContainer(): void
    {
        $kernel = new StorageTestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();

        $this->assertTrue($container->has(CorrelationIdStorage::class));

        $storage = $container->get(CorrelationIdStorage::class);
        $this->assertInstanceOf(CorrelationIdStorage::class, $storage);

        $kernel->shutdown();
    }

    public function testStorageServiceCanStoreAndRetrieveId(): void
    {
        $kernel = new StorageTestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $storage = $container->get(CorrelationIdStorage::class);

        $this->assertFalse($storage->has());
        $this->assertNull($storage->get());

        $correlationId = 'test-functional-id-789';
        $storage->set($correlationId);

        $this->assertTrue($storage->has());
        $this->assertSame($correlationId, $storage->get());

        $storage->clear();
        $this->assertFalse($storage->has());
        $this->assertNull($storage->get());

        $kernel->shutdown();
    }
}

class StorageTestKernel extends Kernel
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

                foreach ($container->getAliases() as $id => $alias) {
                    if (str_starts_with($id, 'MdavidDev\\SymfonyCorrelationIdBundle\\')) {
                        $alias->setPublic(true);
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
