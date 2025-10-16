<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Tests\Functional;

use MdavidDev\SymfonyCorrelationIdBundle\SymfonyCorrelationIdBundle;
use PHPUnit\Framework\TestCase;
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

        // Vérifie que le path existe
        $this->assertDirectoryExists($path);

        // Vérifie que le path contient bien le dossier src/
        $this->assertDirectoryExists($path . '/src');

        // Vérifie que le fichier SymfonyCorrelationIdBundle.php existe dans src/
        $this->assertFileExists($path . '/src/SymfonyCorrelationIdBundle.php');
    }
}

// Kernel de test minimaliste
class CorrelationIdTestKernel extends Kernel
{
    public function registerBundles(): array
    {
        return [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new SymfonyCorrelationIdBundle(),
        ];
    }

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