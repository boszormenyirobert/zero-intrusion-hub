<?php

declare(strict_types=1);

namespace App\Tests\Service\Security;

use App\Service\Security\ApiClientAuthGuard;
use App\Service\Security\ClientAuthRequestResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class ClientAuthRequestResolverTest extends TestCase
{
    public function testResolveOrDenyReturnsUnauthorizedResponseWhenClientAuthIsMissing(): void
    {
        $resolver = new ClientAuthRequestResolver(new ApiClientAuthGuard());
        $request = Request::create('/api/user-registration', 'POST', content: '{}');

        $result = $resolver->resolveOrDeny($request);

        self::assertInstanceOf(JsonResponse::class, $result);
        self::assertSame(401, $result->getStatusCode());
        self::assertSame(['error' => 'Missing X-Client-Auth header!'], json_decode((string) $result->getContent(), true));
    }

    public function testResolveOrDenyReturnsValidatedRequestAttributeWhenAvailable(): void
    {
        $resolver = new ClientAuthRequestResolver(new ApiClientAuthGuard());
        $request = Request::create('/api/user-registration', 'POST', content: '{}');
        $request->attributes->set(ApiClientAuthGuard::REQUEST_ATTRIBUTE, 'validated-upstream-header');

        $result = $resolver->resolveOrDeny($request);

        self::assertSame('validated-upstream-header', $result);
    }
}