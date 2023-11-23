<?php

declare(strict_types=1);

namespace Spiral\Boot\BootloadManager\Checker;

use Spiral\Boot\Attribute\BootloaderRules;
use Spiral\Boot\Bootloader\BootloaderInterface;
use Spiral\Boot\EnvironmentInterface;

final class RulesChecker implements BootloaderCheckerInterface
{
    public function __construct(
        private readonly EnvironmentInterface $environment,
    ) {
    }

    public function canInitialize(BootloaderInterface|string $bootloader, ?BootloaderRules $rules = null): bool
    {
        if ($rules === null) {
            return true;
        }

        if (!$rules->enabled) {
            return false;
        }

        foreach ($rules->denyEnv as $env => $denyValues) {
            $value = $this->environment->get($env);
            if ($value !== null && \in_array($value, (array) $denyValues, true)) {
                return false;
            }
        }

        foreach ($rules->allowEnv as $env => $allowValues) {
            $value = $this->environment->get($env);
            if ($value === null || !\in_array($value, (array) $allowValues, true)) {
                return false;
            }
        }

        return true;
    }
}
