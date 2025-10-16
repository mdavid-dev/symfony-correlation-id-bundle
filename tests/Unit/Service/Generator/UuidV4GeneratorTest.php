<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Tests\Unit\Service\Generator;

use MdavidDev\SymfonyCorrelationIdBundle\Service\Generator\CorrelationIdGeneratorInterface;
use MdavidDev\SymfonyCorrelationIdBundle\Service\Generator\UuidV4Generator;
use PHPUnit\Framework\TestCase;

class UuidV4GeneratorTest extends TestCase
{
    private UuidV4Generator $generator;

    protected function setUp(): void
    {
        $this->generator = new UuidV4Generator();
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(CorrelationIdGeneratorInterface::class, $this->generator);
    }

    public function testGenerateReturnsString(): void
    {
        $id = $this->generator->generate();

        $this->assertIsString($id);
        $this->assertNotEmpty($id);
    }

    public function testGenerateReturnsValidUuidV4Format(): void
    {
        $id = $this->generator->generate();

        // Format UUID v4: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
        // où y est 8, 9, a, ou b
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        $this->assertMatchesRegularExpression($pattern, $id);
    }

    public function testGenerateReturnsUniqueIds(): void
    {
        $ids = [];

        // Génère 100 IDs
        for ($i = 0; $i < 100; $i++) {
            $ids[] = $this->generator->generate();
        }

        // Vérifie qu'ils sont tous uniques
        $uniqueIds = array_unique($ids);
        $this->assertCount(100, $uniqueIds);
    }

    public function testGenerateReturnsCorrectLength(): void
    {
        $id = $this->generator->generate();

        // UUID v4 format: 36 caractères (32 hexa + 4 tirets)
        $this->assertSame(36, strlen($id));
    }

    public function testGenerateIsIdempotent(): void
    {
        $id1 = $this->generator->generate();
        $id2 = $this->generator->generate();

        // Deux appels consécutifs doivent produire des IDs différents
        $this->assertNotSame($id1, $id2);
    }
}