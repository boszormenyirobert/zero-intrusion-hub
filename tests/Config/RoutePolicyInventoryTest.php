<?php

declare(strict_types=1);

namespace App\Tests\Config;

use App\Attribute\ClientAuthRequired;
use App\Attribute\ExtensionAuthRequired;
use App\Attribute\InitializationOnlyRoute;
use App\Attribute\InitializationOrJwtRoute;
use App\Attribute\JwtRequired;
use App\Attribute\PublicRoute;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Annotation\Route;

final class RoutePolicyInventoryTest extends TestCase
{
    private const EXPECTED_ROUTE_COUNT = 50;

    /** @var array<class-string, string> */
    private const PRIMARY_MARKERS = [
        PublicRoute::class => 'PublicRoute',
        JwtRequired::class => 'JwtRequired',
        ClientAuthRequired::class => 'ClientAuthRequired',
        ExtensionAuthRequired::class => 'ExtensionAuthRequired',
        InitializationOnlyRoute::class => 'InitializationOnlyRoute',
        InitializationOrJwtRoute::class => 'InitializationOrJwtRoute',
    ];

    public function testEveryRouteHasExactlyOnePrimaryPolicyMarker(): void
    {
        $inventory = [];
        $violations = [];

        foreach ($this->controllerClasses() as $className) {
            $reflectionClass = new \ReflectionClass($className);

            if ($reflectionClass->isAbstract()) {
                continue;
            }

            foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->class !== $reflectionClass->getName()) {
                    continue;
                }

                $routeAttributes = $method->getAttributes(Route::class);

                if ($routeAttributes === []) {
                    continue;
                }

                $inventory[] = $method->class . '::' . $method->getName();

                $markerNames = [];

                foreach (self::PRIMARY_MARKERS as $attributeClass => $label) {
                    if ($method->getAttributes($attributeClass) !== []) {
                        $markerNames[] = $label;
                    }
                }

                $route = $routeAttributes[0]->newInstance();
                $path = is_string($route->getPath()) ? $route->getPath() : '(unknown)';
                $identifier = sprintf('%s::%s [%s]', $method->class, $method->getName(), $path);

                if (count($markerNames) !== 1) {
                    $violations[] = sprintf(
                        '%s must declare exactly one primary route policy marker, found: %s',
                        $identifier,
                        $markerNames === [] ? 'none' : implode(', ', $markerNames)
                    );

                    continue;
                }

                if (str_contains($method->class, 'App\\Controller\\CredentialHub\\')) {
                    $expectedMarker = $path === '/qr-identity' ? 'PublicRoute' : 'ExtensionAuthRequired';

                    if ($markerNames[0] !== $expectedMarker) {
                        $violations[] = sprintf(
                            '%s must use %s, found %s',
                            $identifier,
                            $expectedMarker,
                            $markerNames[0]
                        );
                    }
                }
            }
        }

        sort($inventory);
        sort($violations);

        self::assertCount(
            self::EXPECTED_ROUTE_COUNT,
            $inventory,
            sprintf('Route inventory drift detected. Expected %d route methods, found %d.', self::EXPECTED_ROUTE_COUNT, count($inventory))
        );

        self::assertSame([], $violations, "Route policy inventory violations:\n- " . implode("\n- ", $violations));
    }

    /** @return list<class-string> */
    private function controllerClasses(): array
    {
        $classes = [];
        $controllerDirectory = dirname(__DIR__, 2) . '/src/Controller';

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($controllerDirectory));

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if (!is_string($contents)) {
                continue;
            }

            $namespace = $this->extractNamespace($contents);
            $className = $this->extractClassName($contents);

            if ($namespace === null || $className === null) {
                continue;
            }

            $classes[] = $namespace . '\\' . $className;
        }

        sort($classes);

        return $classes;
    }

    private function extractNamespace(string $contents): ?string
    {
        if (preg_match('/^namespace\s+([^;]+);/m', $contents, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }

    private function extractClassName(string $contents): ?string
    {
        if (preg_match('/^(?:final\s+|abstract\s+)?class\s+(\w+)/m', $contents, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }
}