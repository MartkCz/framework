<?php

declare(strict_types=1);

namespace Spiral\Tests\Boot\Fixtures;

use Spiral\Boot\Attribute\BootloaderRules;

#[BootloaderRules(allowEnv: [
    'APP_ENV' => 'prod',
    'APP_DEBUG' => false,
    'RR_MODE' => ['http']
])]
class BootloaderH extends AbstractBootloader
{
}
