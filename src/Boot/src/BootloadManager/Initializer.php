<?php

declare(strict_types=1);

namespace Spiral\Boot\BootloadManager;

use Psr\Container\ContainerInterface;
use Spiral\Boot\Attribute\BootloaderRules;
use Spiral\Boot\Bootloader\BootloaderInterface;
use Spiral\Boot\Bootloader\DependedInterface;
use Spiral\Boot\BootloadManager\Checker\BootloaderChecker;
use Spiral\Boot\BootloadManager\Checker\BootloaderCheckerInterface;
use Spiral\Boot\BootloadManager\Checker\CanBootedChecker;
use Spiral\Boot\BootloadManager\Checker\CheckerRegistry;
use Spiral\Boot\BootloadManager\Checker\ClassExistsChecker;
use Spiral\Boot\BootloadManager\Checker\RulesChecker;
use Spiral\Boot\BootloadManagerInterface;
use Spiral\Core\BinderInterface;
use Spiral\Core\Container;
use Spiral\Core\ResolverInterface;

/**
 * @internal
 * @psalm-import-type TClass from BootloadManagerInterface
 * @psalm-import-type TFullBinding from BootloaderInterface
 */
class Initializer implements InitializerInterface, Container\SingletonInterface
{
    protected ?BootloaderCheckerInterface $checker = null;

    public function __construct(
        protected readonly ContainerInterface $container,
        protected readonly BinderInterface $binder,
        protected readonly ClassesRegistry $bootloaders = new ClassesRegistry(),
        ?BootloaderCheckerInterface $checker = null,
    ) {
    }

    /**
     * Instantiate bootloader objects and resolve dependencies
     *
     * @param TClass[]|array<TClass, array<string,mixed>> $classes
     */
    public function init(array $classes, bool $useRules = true): \Generator
    {
        $this->checker ??= $this->initDefaultChecker();

        foreach ($classes as $bootloader => $options) {
            // default bootload syntax as simple array
            if (\is_string($options) || $options instanceof BootloaderInterface) {
                $bootloader = $options;
                $options = [];
            }
            $options = $useRules ? $this->getBootloaderRules($bootloader, $options) : [];

            if (!$this->checker->canInitialize($bootloader, $useRules ? $options : null)) {
                continue;
            }

            $this->bootloaders->register($bootloader instanceof BootloaderInterface ? $bootloader::class : $bootloader);

            if (!$bootloader instanceof BootloaderInterface) {
                $bootloader = $this->container->get($bootloader);
            }

            /** @var BootloaderInterface $bootloader */
            yield from $this->initBootloader($bootloader);
            yield $bootloader::class => [
                'bootloader' => $bootloader,
                'options' => $options instanceof BootloaderRules ? $options->args : $options,
            ];
        }
    }

    public function getRegistry(): ClassesRegistry
    {
        return $this->bootloaders;
    }

    /**
     * Resolve all bootloader dependencies and init bindings
     */
    protected function initBootloader(BootloaderInterface $bootloader): iterable
    {
        if ($bootloader instanceof DependedInterface) {
            yield from $this->init($this->getDependencies($bootloader));
        }

        $this->initBindings(
            $bootloader->defineBindings(),
            $bootloader->defineSingletons()
        );
    }

    /**
     * Bind declared bindings.
     *
     * @param TFullBinding $bindings
     * @param TFullBinding $singletons
     */
    protected function initBindings(array $bindings, array $singletons): void
    {
        foreach ($bindings as $aliases => $resolver) {
            $this->binder->bind($aliases, $resolver);
        }

        foreach ($singletons as $aliases => $resolver) {
            $this->binder->bindSingleton($aliases, $resolver);
        }
    }

    protected function getDependencies(DependedInterface $bootloader): array
    {
        $deps = $bootloader->defineDependencies();

        $reflectionClass = new \ReflectionClass($bootloader);

        $methodsDeps = [];

        foreach (Methods::cases() as $method) {
            if ($reflectionClass->hasMethod($method->value)) {
                $methodsDeps[] = $this->findBootloaderClassesInMethod(
                    $reflectionClass->getMethod($method->value)
                );
            }
        }

        return \array_values(\array_unique(\array_merge($deps, ...$methodsDeps)));
    }

    protected function findBootloaderClassesInMethod(\ReflectionMethod $method): array
    {
        $args = [];
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType && $this->shouldBeBooted($type)) {
                $args[] = $type->getName();
            }
        }

        return $args;
    }

    protected function shouldBeBooted(\ReflectionNamedType $type): bool
    {
        /** @var TClass $class */
        $class = $type->getName();

        return $this->isBootloader($class)
            && !$this->bootloaders->isBooted($class);
    }

    /**
     * @psalm-pure
     * @psalm-assert-if-true TClass $class
     */
    protected function isBootloader(string|object $class): bool
    {
        return \is_subclass_of($class, BootloaderInterface::class);
    }

    protected function initDefaultChecker(): BootloaderCheckerInterface
    {
        $registry = new CheckerRegistry();
        $registry->register($this->container->get(RulesChecker::class));
        $registry->register(new ClassExistsChecker());
        $registry->register(new CanBootedChecker($this->bootloaders));

        return new BootloaderChecker($registry);
    }

    /**
     * Returns merged rules. Attribute rules have lower priority.
     *
     * @param class-string<BootloaderInterface>|BootloaderInterface $bootloader
     */
    private function getBootloaderRules(
        string|BootloaderInterface $bootloader,
        array|callable|BootloaderRules $rules
    ): BootloaderRules {
        if ($rules instanceof \Closure) {
            $rules = $this->container instanceof ResolverInterface
                ? $rules(...$this->container->resolveArguments(new \ReflectionFunction($rules)))
                : $rules();
        }
        $attr = $this->getBootloaderRulesAttribute($bootloader);

        $getArgument = static function (string $key, bool $override, mixed $default = []) use ($rules, $attr): mixed {
            return match (true) {
                $rules instanceof BootloaderRules && $override => $rules->{$key},
                $rules instanceof BootloaderRules && !$override && \is_array($default) =>
                    $rules->{$key} + ($attr->{$key} ?? []),
                $rules instanceof BootloaderRules && !$override && \is_bool($default) => $rules->{$key},
                \is_array($rules) && $rules !== [] && $key === 'args' => $rules,
                default => $attr->{$key} ?? $default,
            };
        };

        $override = $rules instanceof BootloaderRules ? $rules->override : true;

        return new BootloaderRules(
            args: $getArgument('args', $override),
            enabled: $getArgument('enabled', $override, true),
            allowEnv: $getArgument('allowEnv', $override),
            denyEnv: $getArgument('denyEnv', $override),
        );
    }

    /**
     * @param class-string<BootloaderInterface>|BootloaderInterface $bootloader
     */
    private function getBootloaderRulesAttribute(string|BootloaderInterface $bootloader): ?BootloaderRules
    {
        $attribute = null;
        if ($bootloader instanceof BootloaderInterface || \class_exists($bootloader)) {
            $ref = new \ReflectionClass($bootloader);
            $attribute = $ref->getAttributes(BootloaderRules::class)[0] ?? null;
        }

        if ($attribute === null) {
            return null;
        }

        return $attribute->newInstance();
    }
}
