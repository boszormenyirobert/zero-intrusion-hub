<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\CredentialHub;

use App\Service\Instance\HUB\InstanceRegistrationService;
use App\Service\User\BackendForwardingService;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

final class CredentialHubEndpointE2ETest extends WebTestCase
{
    /**
     * @dataProvider bootstrapEndpointProvider
     */
    public function testBootstrapEndpointsForwardRequestFromHttpKernel(string $path, string $process, string $body, mixed $expectedProcessPayload): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->disableInitializationStateGuard();

        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with(self::callback(static function (array $payload) use ($process, $expectedProcessPayload): bool {
                return array_key_exists($process, $payload) && $payload[$process] === $expectedProcessPayload;
            }))
            ->willReturn(new JsonResponse(['status' => 'ok', 'process' => $process]));

        static::getContainer()->set(BackendForwardingService::class, $forwardingService);

        $client->request('POST', $path, server: ['CONTENT_TYPE' => 'application/json'], content: $body);

        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'ok', 'process' => $process], json_decode((string) $client->getResponse()->getContent(), true));
    }

    /**
    * @dataProvider followUpDenyProvider
     */
    public function testFollowUpEndpointsRequireExtensionAuth(string $path, string $body): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->disableInitializationStateGuard();

        $client->request('POST', $path, server: ['CONTENT_TYPE' => 'application/json'], content: $body);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'Missing X-Extension-Auth header!'], json_decode((string) $client->getResponse()->getContent(), true));
    }

    /** @return iterable<string, array{string, string}> */
    public function followUpDenyProvider(): iterable
    {
        yield 'one-touch identifier' => ['/api/credential-hub/one-touch/identifier', json_encode(['identifier' => 'id-123'], JSON_THROW_ON_ERROR)];
        yield 'shared new to encrypt' => ['/api/credential-hub/shared/registration/new/to-encrypt', json_encode(['payload' => 'encrypt'], JSON_THROW_ON_ERROR)];
        yield 'shared new' => ['/api/credential-hub/shared/registration/new', json_encode(['payload' => 'create'], JSON_THROW_ON_ERROR)];
        yield 'domain read credential decrypted' => ['/api/credential-hub/domain/read/credential/decrypted', json_encode(['credential' => 'encrypted'], JSON_THROW_ON_ERROR)];
        yield 'domain read credential' => ['/api/credential-hub/domain/read/credential', json_encode(['credential' => 'plain'], JSON_THROW_ON_ERROR)];
        yield 'domain delete credential' => ['/api/credential-hub/domain/delete/credential', json_encode(['confirm' => true], JSON_THROW_ON_ERROR)];
        yield 'vault read credential decrypted' => ['/api/credential-hub/vault/read/credential/decrypted', json_encode(['credential' => 'encrypted'], JSON_THROW_ON_ERROR)];
        yield 'vault read credential' => ['/api/credential-hub/vault/read/credential', json_encode(['credential' => 'plain'], JSON_THROW_ON_ERROR)];
        yield 'vault edit credential' => ['/api/credential-hub/vault/edit/credential', json_encode(['credential' => 'updated'], JSON_THROW_ON_ERROR)];
        yield 'vault delete credential' => ['/api/credential-hub/vault/delete/credential', json_encode(['credential' => 'delete'], JSON_THROW_ON_ERROR)];
    }

    /**
     * @dataProvider followUpEndpointProvider
     */
    public function testFollowUpEndpointsForwardRequestFromHttpKernel(string $path, string $process, string $body, mixed $expectedProcessPayload): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->disableInitializationStateGuard();

        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with(self::callback(static function (array $payload) use ($process, $expectedProcessPayload): bool {
                return ($payload[$process] ?? null) === $expectedProcessPayload
                    && ($payload['X-Extension-Auth'] ?? null) === 'extension-auth-header';
            }))
            ->willReturn(new JsonResponse(['status' => 'ok', 'process' => $process]));

        static::getContainer()->set(BackendForwardingService::class, $forwardingService);

        $client->request('POST', $path, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_EXTENSION_AUTH' => 'extension-auth-header',
        ], content: $body);

        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'ok', 'process' => $process], json_decode((string) $client->getResponse()->getContent(), true));
    }

    /** @return iterable<string, array{string, string, string, mixed}> */
    public function bootstrapEndpointProvider(): iterable
    {
        yield 'one-touch qr identity' => [
            '/api/credential-hub/one-touch/qr-identity',
            'one_touch_qr_identity',
            json_encode(['source' => 'extension', 'type' => 'secure'], JSON_THROW_ON_ERROR),
            ['source' => 'extension', 'type' => 'secure'],
        ];
        yield 'shared registration qr identity' => [
            '/api/credential-hub/shared/registration/qr-identity',
            'shared_registration_qr_identity',
            json_encode([
                'description' => 'Shared credential',
                'isNew' => 'true',
                'source' => 'extension',
                'type' => 'registration-application',
                'userName' => 'tester',
                'userPassword' => 'secret',
                'userPublicId' => 'userPublicIdValue',
                'application' => 'example-app',
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'description' => 'Shared credential',
                'isNew' => 'true',
                'source' => 'extension',
                'type' => 'registration-application',
                'userName' => 'tester',
                'userPassword' => 'secret',
                'userPublicId' => 'userPublicIdValue',
                'application' => 'example-app',
            ], JSON_THROW_ON_ERROR),
        ];
        yield 'domain read qr identity' => [
            '/api/credential-hub/domain/read/qr-identity',
            'domain_read_qr_identity',
            json_encode(['domain' => 'example.test', 'userPublicId' => 'userPublicIdValue'], JSON_THROW_ON_ERROR),
            ['domain' => 'example.test', 'userPublicId' => 'userPublicIdValue'],
        ];
        yield 'domain delete qr identity' => [
            '/api/credential-hub/domain/delete/qr-identity',
            'domain_delete_qr_identity',
            json_encode([
                'domain' => 'example.test',
                'source' => 'extension',
                'targetId' => 'QmFzZTY0VGFyZ2V0',
                'type' => 'delete-domain',
                'userPublicId' => 'userPublicIdValue',
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'domain' => 'example.test',
                'source' => 'extension',
                'targetId' => 'QmFzZTY0VGFyZ2V0',
                'type' => 'delete-domain',
                'userPublicId' => 'userPublicIdValue',
            ], JSON_THROW_ON_ERROR),
        ];
        yield 'vault read qr identity' => [
            '/api/credential-hub/vault/read/qr-identity',
            'vault_read_qr_identity',
            json_encode(['domain' => 'example.test', 'source' => 'extension', 'type' => 'applications', 'userPublicId' => 'userPublicIdValue'], JSON_THROW_ON_ERROR),
            ['domain' => 'example.test', 'source' => 'extension', 'type' => 'applications', 'userPublicId' => 'userPublicIdValue'],
        ];
        yield 'vault edit qr identity' => [
            '/api/credential-hub/vault/edit/qr-identity',
            'vault_edit_qr_identity',
            json_encode([
                'application' => 'example-app',
                'description' => 'Credential update',
                'source' => 'extension',
                'targetId' => 'QmFzZTY0VGFyZ2V0',
                'type' => 'update-applications',
                'userName' => 'tester',
                'userPassword' => 'secret',
                'userPublicId' => 'userPublicIdValue',
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'application' => 'example-app',
                'description' => 'Credential update',
                'source' => 'extension',
                'targetId' => 'QmFzZTY0VGFyZ2V0',
                'type' => 'update-applications',
                'userName' => 'tester',
                'userPassword' => 'secret',
                'userPublicId' => 'userPublicIdValue',
            ], JSON_THROW_ON_ERROR),
        ];
        yield 'vault delete qr identity' => [
            '/api/credential-hub/vault/delete/qr-identity',
            'vault_delete_qr_identity',
            json_encode(['source' => 'extension', 'targetId' => 'QmFzZTY0VGFyZ2V0', 'type' => 'delete-applications', 'userPublicId' => 'userPublicIdValue'], JSON_THROW_ON_ERROR),
            json_encode(['source' => 'extension', 'targetId' => 'QmFzZTY0VGFyZ2V0', 'type' => 'delete-applications', 'userPublicId' => 'userPublicIdValue'], JSON_THROW_ON_ERROR),
        ];
    }

    /** @return iterable<string, array{string, string, string, mixed}> */
    public function followUpEndpointProvider(): iterable
    {
        yield 'one-touch identifier' => ['/api/credential-hub/one-touch/identifier', 'one_touch_identifier', '{"identifier":"id-123"}', ['identifier' => 'id-123']];
        yield 'one-touch state' => ['/api/credential-hub/one-touch/state', 'one_touch_state', json_encode(['iv' => base64_encode(str_repeat('a', 16)), 'processId' => rtrim(base64_encode(str_repeat('b', 16)), '='), 'type' => 'extension'], JSON_THROW_ON_ERROR), json_encode(['iv' => base64_encode(str_repeat('a', 16)), 'processId' => rtrim(base64_encode(str_repeat('b', 16)), '='), 'type' => 'extension'], JSON_THROW_ON_ERROR)];
        yield 'shared new to encrypt' => ['/api/credential-hub/shared/registration/new/to-encrypt', 'shared_registration_new_to_encrypt', json_encode(['payload' => 'encrypt'], JSON_THROW_ON_ERROR), json_encode(['payload' => 'encrypt'], JSON_THROW_ON_ERROR)];
        yield 'shared new' => ['/api/credential-hub/shared/registration/new', 'shared_registration_new', json_encode(['payload' => 'create'], JSON_THROW_ON_ERROR), json_encode(['payload' => 'create'], JSON_THROW_ON_ERROR)];
        yield 'shared state' => ['/api/credential-hub/shared/registration/state', 'shared_registration_state', json_encode(['processId' => rtrim(base64_encode(str_repeat('c', 16)), '='), 'type' => 'extension'], JSON_THROW_ON_ERROR), json_encode(['processId' => rtrim(base64_encode(str_repeat('c', 16)), '='), 'type' => 'extension'], JSON_THROW_ON_ERROR)];
        yield 'domain read credential decrypted' => ['/api/credential-hub/domain/read/credential/decrypted', 'domain_read_credential_encrypted', '{"credential":"encrypted"}', ['credential' => 'encrypted']];
        yield 'domain read credential' => ['/api/credential-hub/domain/read/credential', 'domain_read_credential', '{"credential":"plain"}', ['credential' => 'plain']];
        yield 'domain read state' => ['/api/credential-hub/domain/read/state', 'domain_read_state', json_encode(['domain' => 'example.test', 'iv' => base64_encode(str_repeat('d', 16)), 'processId' => rtrim(base64_encode(str_repeat('e', 16)), '='), 'type' => 'extension'], JSON_THROW_ON_ERROR), json_encode(['domain' => 'example.test', 'iv' => base64_encode(str_repeat('d', 16)), 'processId' => rtrim(base64_encode(str_repeat('e', 16)), '='), 'type' => 'extension'], JSON_THROW_ON_ERROR)];
        yield 'domain delete credential' => ['/api/credential-hub/domain/delete/credential', 'domain_delete_credential', '{"confirm":true}', '{"confirm":true}'];
        yield 'domain delete state' => ['/api/credential-hub/domain/delete/state', 'domain_delete_state', json_encode(['processId' => rtrim(base64_encode(str_repeat('f', 16)), '='), 'type' => 'extension'], JSON_THROW_ON_ERROR), json_encode(['processId' => rtrim(base64_encode(str_repeat('f', 16)), '='), 'type' => 'extension'], JSON_THROW_ON_ERROR)];
        yield 'vault read credential decrypted' => ['/api/credential-hub/vault/read/credential/decrypted', 'vault_read_credential_encrypted', '{"credential":"encrypted"}', ['credential' => 'encrypted']];
        yield 'vault read credential' => ['/api/credential-hub/vault/read/credential', 'vault_read_credential', '{"credential":"plain"}', '{"credential":"plain"}'];
        yield 'vault read state' => ['/api/credential-hub/vault/read/state', 'vault_read_state', json_encode(['domain' => 'example.test', 'iv' => base64_encode(str_repeat('g', 16)), 'processId' => rtrim(base64_encode(str_repeat('h', 16)), '='), 'type' => 'extension'], JSON_THROW_ON_ERROR), json_encode(['domain' => 'example.test', 'iv' => base64_encode(str_repeat('g', 16)), 'processId' => rtrim(base64_encode(str_repeat('h', 16)), '='), 'type' => 'extension'], JSON_THROW_ON_ERROR)];
        yield 'vault edit credential' => ['/api/credential-hub/vault/edit/credential', 'vault_edit_credential', '{"credential":"updated"}', '{"credential":"updated"}'];
        yield 'vault edit state' => ['/api/credential-hub/vault/edit/state', 'vault_edit_state', json_encode(['processId' => rtrim(base64_encode(str_repeat('i', 16)), '='), 'type' => 'extension'], JSON_THROW_ON_ERROR), json_encode(['processId' => rtrim(base64_encode(str_repeat('i', 16)), '='), 'type' => 'extension'], JSON_THROW_ON_ERROR)];
        yield 'vault delete credential' => ['/api/credential-hub/vault/delete/credential', 'vault_delete_credential', '{"credential":"delete"}', '{"credential":"delete"}'];
        yield 'vault delete state' => ['/api/credential-hub/vault/delete/state', 'vault_delete_state', json_encode(['processId' => rtrim(base64_encode(str_repeat('j', 16)), '='), 'type' => 'extension'], JSON_THROW_ON_ERROR), json_encode(['processId' => rtrim(base64_encode(str_repeat('j', 16)), '='), 'type' => 'extension'], JSON_THROW_ON_ERROR)];
    }

    private function disableInitializationStateGuard(): void
    {
        $instanceRegistrationService = $this->createMock(InstanceRegistrationService::class);
        $instanceRegistrationService->method('getInitializationState')->willReturn(false);

        static::getContainer()->set(InstanceRegistrationService::class, $instanceRegistrationService);
    }
}
