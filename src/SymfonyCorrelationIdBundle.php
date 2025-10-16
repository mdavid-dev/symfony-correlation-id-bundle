<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class SymfonyCorrelationIdBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}