<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

final class ApplicationDecorator extends BaseApplication
{

    public function __construct(BaseApplication $application)
    {
        parent::__construct($application->getName(), $application->getVersion());

        $this->setCatchExceptions($application->areExceptionsCaught());
        $this->setAutoExit($application->isAutoExitEnabled());
    }

    protected function getDefaultInputDefinition(): InputDefinition
    {
        $definition = parent::getDefaultInputDefinition();

        $definition->addOption(new InputOption(
            'correlation-id',
            null,
            InputOption::VALUE_REQUIRED,
            'Correlation ID for this command execution'
        ));

        return $definition;
    }
}
