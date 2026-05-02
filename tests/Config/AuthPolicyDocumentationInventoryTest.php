<?php

declare(strict_types=1);

namespace App\Tests\Config;

use App\Attribute\ClientAuthRequired;
use App\Attribute\CsrfProtectedRoute;
use App\Attribute\ExtensionAuthRequired;
use App\Attribute\InitializationOnlyRoute;
use App\Attribute\InitializationOrJwtRoute;
use App\Attribute\JwtRequired;
use App\Attribute\PublicRoute;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Annotation\Route;

final class AuthPolicyDocumentationInventoryTest extends TestCase
{
    /** @return array<class-string, list<string>> */
    private const POLICY_ATTRIBUTE_MAP = [
        PublicRoute::class => [
            '3.3 Explicit public HUB/browser routes',
            '3.4 Explicit public Credential Hub bootstrap routes',
            '4. Additional public API routes',
        ],
        JwtRequired::class => ['3.1 JWT-protected HUB routes'],
        ClientAuthRequired::class => ['3.2 Client-auth-protected machine/API routes'],
        ExtensionAuthRequired::class => ['3.8 Extension-auth-protected Credential Hub follow-up routes'],
        InitializationOnlyRoute::class => ['3.5 Initialization-only HUB routes'],
        InitializationOrJwtRoute::class => ['3.6 Initialization-or-JWT HUB routes'],
        CsrfProtectedRoute::class => ['3.7 Explicit CSRF-protected routes'],
    ];

    public function testEveryDocumentedPolicyRouteExistsInControllerInventory(): void
    {
        $documentedRoutes = $this->extractDocumentedRoutes();
        $inventoryRoutes = array_values(array_unique(array_map(
            static fn (array $route): string => $route['path'],
            $this->routeInventory()
        )));

        $missing = array_values(array_diff($documentedRoutes, $inventoryRoutes));

        sort($missing);

        self::assertSame([], $missing, "Documented routes missing from controller inventory:\n- " . implode("\n- ", $missing));
    }

    public function testCredentialHubDocumentationMatchesBootstrapAndFollowUpPolicies(): void
    {
        $bootstrapRoutes = [];
        $followUpRoutes = [];

        foreach ($this->routeInventory() as $route) {
            if (!str_contains($route['class'], 'App\\Controller\\CredentialHub\\')) {
                continue;
            }

            if (in_array(PublicRoute::class, $route['attributes'], true)) {
                $bootstrapRoutes[] = $route['path'];
            }

            if (in_array(ExtensionAuthRequired::class, $route['attributes'], true)) {
                $followUpRoutes[] = $route['path'];
            }
        }

        sort($bootstrapRoutes);
        sort($followUpRoutes);

        $documentedBootstrap = $this->extractRoutesFromSection('3.4 Explicit public Credential Hub bootstrap routes');
        $documentedFollowUps = $this->extractRoutesFromSection('3.8 Extension-auth-protected Credential Hub follow-up routes');

        sort($documentedBootstrap);
        sort($documentedFollowUps);

        self::assertSame($bootstrapRoutes, $documentedBootstrap);
        self::assertSame($followUpRoutes, $documentedFollowUps);
    }

    /**
     * @return list<array{class: class-string, method: string, path: string, attributes: list<class-string>}>
     */
    private function routeInventory(): array
    {
        $inventory = [];

        foreach ($this->controllerClasses() as $className) {
            $reflectionClass = new \ReflectionClass($className);

            if ($reflectionClass->isAbstract()) {
                continue;
            }

            $classRoutePrefix = $this->normalizeRoutePath($this->extractRoutePath($reflectionClass->getAttributes(Route::class)[0] ?? null));

            foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->class !== $reflectionClass->getName()) {
                    continue;
                }

                $routeAttributes = $method->getAttributes(Route::class);

                if ($routeAttributes === []) {
                    continue;
                }

                $methodPath = $this->normalizeRoutePath($this->extractRoutePath($routeAttributes[0]));
                $inventory[] = [
                    'class' => $className,
                    'method' => $method->getName(),
                    'path' => $this->joinRoutePath($classRoutePrefix, $methodPath),
                    'attributes' => array_values(array_filter([
                        PublicRoute::class,
                        JwtRequired::class,
                        ClientAuthRequired::class,
                        ExtensionAuthRequired::class,
                        InitializationOnlyRoute::class,
                        InitializationOrJwtRoute::class,
                        CsrfProtectedRoute::class,
                    ], static fn (string $attributeClass): bool => $method->getAttributes($attributeClass) !== [])),
                ];
            }
        }

        return $inventory;
    }

    /** @return list<string> */
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

            if (preg_match('/^namespace\s+([^;]+);/m', $contents, $namespaceMatches) !== 1) {
                continue;
            }

            if (preg_match('/^(?:final\s+|abstract\s+)?class\s+(\w+)/m', $contents, $classMatches) !== 1) {
                continue;
            }

            $classes[] = trim($namespaceMatches[1]) . '\\' . trim($classMatches[1]);
        }

        sort($classes);

        return $classes;
    }

    /** @return list<string> */
    private function extractDocumentedRoutes(): array
    {
        preg_match_all('/^\s*-\s+`([^`]+)`/m', (string) file_get_contents($this->authPolicyPath()), $matches);

        $routes = array_values(array_unique(array_filter(
            array_map(static fn (string $route): string => trim($route), $matches[1] ?? []),
            static fn (string $route): bool => str_starts_with($route, '/')
        )));
        sort($routes);

        return $routes;
    }

    /** @return list<string> */
    private function extractRoutesFromSection(string $sectionHeading): array
    {
        $contents = (string) file_get_contents($this->authPolicyPath());
        $pattern = '/###\s+' . preg_quote($sectionHeading, '/') . '\R(.*?)(?:\R##+\s+|\z)/s';

        if (preg_match($pattern, $contents, $matches) !== 1) {
            return [];
        }

        preg_match_all('/^\s*-\s+`([^`]+)`/m', $matches[1], $routeMatches);

        return array_values(array_filter(
            array_map(static fn (string $route): string => trim($route), $routeMatches[1] ?? []),
            static fn (string $route): bool => str_starts_with($route, '/')
        ));
    }

    private function extractRoutePath(?\ReflectionAttribute $routeAttribute): string
    {
        if ($routeAttribute === null) {
            return '';
        }

        $route = $routeAttribute->newInstance();

        return is_string($route->getPath()) ? $route->getPath() : '';
    }

    private function joinRoutePath(string $prefix, string $path): string
    {
        $fullPath = rtrim($prefix, '/') . '/' . ltrim($path, '/');
        $normalized = preg_replace('#/+#', '/', $fullPath);

        return $normalized === '' ? '/' : (string) $normalized;
    }

    private function normalizeRoutePath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        return '/' . ltrim($path, '/');
    }

    private function authPolicyPath(): string
    {
        return dirname(__DIR__, 2) . '/Readme/AUTH_POLICY.md';
    }
}
