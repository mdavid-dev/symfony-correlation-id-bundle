<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Tests\Functional;

use Exception;
use MdavidDev\SymfonyCorrelationIdBundle\SymfonyCorrelationIdBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

class BundleInitializationTest extends TestCase
{
    public function testBundleInitialization(): void
    {
        $kernel = new CorrelationIdTestKernel('test', true);
        $kernel->boot();

        $bundles = $kernel->getBundles();

        $this->assertArrayHasKey('SymfonyCorrelationIdBundle', $bundles);
        $this->assertInstanceOf(SymfonyCorrelationIdBundle::class, $bundles['SymfonyCorrelationIdBundle']);

        $kernel->shutdown();
    }

    public function testBundlePath(): void
    {
        $bundle = new SymfonyCorrelationIdBundle();
        $path = $bundle->getPath();

        $this->assertDirectoryExists($path);

        $this->assertDirectoryExists($path . '/src');

        $this->assertFileExists($path . '/src/SymfonyCorrelationIdBundle.php');
    }
}

class CorrelationIdTestKernel extends Kernel
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
        $loader->load(function ($container) {
            $container->loadFromExtension('framework', [
                'secret' => 'test-secret',
                'test' => true,
            ]);
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
