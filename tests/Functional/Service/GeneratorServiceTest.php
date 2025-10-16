<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Tests\Functional\Service;

use Exception;
use MdavidDev\SymfonyCorrelationIdBundle\Service\Generator\CorrelationIdGeneratorInterface;
use MdavidDev\SymfonyCorrelationIdBundle\Service\Generator\UuidV4Generator;
use MdavidDev\SymfonyCorrelationIdBundle\SymfonyCorrelationIdBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

class GeneratorServiceTest extends TestCase
{
    public function testGeneratorServiceIsAvailableInContainer(): void
    {
        $kernel = new GeneratorTestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();

        $this->assertTrue($container->has(CorrelationIdGeneratorInterface::class));

        $generator = $container->get(CorrelationIdGeneratorInterface::class);
        $this->assertInstanceOf(UuidV4Generator::class, $generator);

        $kernel->shutdown();
    }

    public function testGeneratorServiceCanGenerateId(): void
    {
        $kernel = new GeneratorTestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $generator = $container->get(CorrelationIdGeneratorInterface::class);

        $id = $generator->generate();

        $this->assertIsString($id);
        $this->assertNotEmpty($id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id
        );

        $kernel->shutdown();
    }
}

class GeneratorTestKernel extends Kernel
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

        // Ajouter un CompilerPass pour rendre les services publics pour les tests
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                // Rendre le service public pour les tests
                foreach ($container->getDefinitions() as $id => $definition) {
                    if (str_starts_with($id, 'MdavidDev\\SymfonyCorrelationIdBundle\\')) {
                        $definition->setPublic(true);
                    }
                }

                // Rendre aussi les alias publics
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