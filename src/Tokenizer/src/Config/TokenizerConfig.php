<?php

declare(strict_types=1);

namespace Spiral\Tokenizer\Config;

use Spiral\Core\InjectableConfig;

/**
 * Tokenizer component configuration.
 */
final class TokenizerConfig extends InjectableConfig
{
    public const CONFIG = 'tokenizer';

    /** @var array<non-empty-string, array<int, non-empty-string>> */
    protected array $config = [
        'debug' => false,
        'directories' => [],
        'exclude' => [],
        'scopes' => [],
    ];

    public function isDebug(): bool
    {
        return (bool) ($this->config['debug'] ?? false);
    }

    public function getDirectories(): array
    {
        return $this->config['directories'] ?? [\getcwd()];
    }

    public function getExcludes(): array
    {
        return $this->config['exclude'] ?? ['vendor', 'tests'];
    }

    /**
     * @return array{directories: array<string>, exclude: array<string>}
     */
    public function getScope(string $scope): array
    {
        $directories = $this->config['scopes'][$scope]['directories'] ?? $this->getDirectories();
        $excludes = $this->config['scopes'][$scope]['exclude'] ?? $this->getExcludes();

        return [
            'directories' => $directories,
            'exclude' => $excludes,
        ];
    }
}
