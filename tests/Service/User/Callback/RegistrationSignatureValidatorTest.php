<?php

declare(strict_types=1);

namespace App\Tests\Service\User\Callback;

use App\DTO\RegistrationProcessDTO;
use App\Entity\OwnClient;
use App\Repository\OwnClientRepository;
use App\Service\User\Callback\RegistrationSignatureValidator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class RegistrationSignatureValidatorTest extends TestCase
{
    public function testIsValidReturnsFalseWhenSignatureIsNotBase64(): void
    {
        $repository = $this->createMock(OwnClientRepository::class);
        $repository->expects(self::never())->method('findOneBy');

        $validator = new RegistrationSignatureValidator(
            $this->createMock(LoggerInterface::class),
            $repository
        );

        self::assertFalse($validator->isValid($this->createDto('not-base64!')));
    }

    public function testIsValidReturnsFalseWhenOwnClientIsMissing(): void
    {
        $repository = $this->createMock(OwnClientRepository::class);
        $repository->expects(self::once())->method('findOneBy')->willReturn(null);

        $validator = new RegistrationSignatureValidator(
            $this->createMock(LoggerInterface::class),
            $repository
        );

        self::assertFalse($validator->isValid($this->createDto(base64_encode('signature'))));
    }

    public function testIsValidReturnsFalseWhenPublicKeyCannotBeLoaded(): void
    {
        $ownClient = (new OwnClient())->setSslPublicKey('invalid-public-key');
        $repository = $this->createMock(OwnClientRepository::class);
        $repository->method('findOneBy')->willReturn($ownClient);

        $validator = new RegistrationSignatureValidator(
            $this->createMock(LoggerInterface::class),
            $repository
        );

        self::assertFalse($validator->isValid($this->createDto(base64_encode('signature'))));
    }

    public function testIsValidReturnsTrueForMatchingSignature(): void
    {
        $signature = '';
        $identity = json_encode([
            'publicId' => 'public-id',
            'email' => 'user@example.test',
        ], JSON_THROW_ON_ERROR);

        $privateKey = openssl_pkey_get_private($this->getPrivateKeyPem());

        self::assertNotFalse($privateKey);
        self::assertTrue(openssl_sign($identity, $signature, $privateKey, OPENSSL_ALGO_SHA256));

        $ownClient = (new OwnClient())->setSslPublicKey($this->getPublicKeyPem());
        $repository = $this->createMock(OwnClientRepository::class);
        $repository->method('findOneBy')->willReturn($ownClient);

        $validator = new RegistrationSignatureValidator(
            $this->createMock(LoggerInterface::class),
            $repository
        );

        self::assertTrue($validator->isValid($this->createDto(base64_encode($signature))));
    }

    public function testIsValidReturnsFalseForMismatchedSignature(): void
    {
        $signature = '';
        $identity = json_encode([
            'publicId' => 'public-id',
            'email' => 'user@example.test',
        ], JSON_THROW_ON_ERROR);

        $otherPrivateKey = openssl_pkey_get_private($this->getPrivateKeyPem());

        self::assertNotFalse($otherPrivateKey);
        self::assertTrue(openssl_sign($identity.'-mismatch', $signature, $otherPrivateKey, OPENSSL_ALGO_SHA256));

        $ownClient = (new OwnClient())->setSslPublicKey($this->getPublicKeyPem());
        $repository = $this->createMock(OwnClientRepository::class);
        $repository->method('findOneBy')->willReturn($ownClient);

        $validator = new RegistrationSignatureValidator(
            $this->createMock(LoggerInterface::class),
            $repository
        );

        self::assertFalse($validator->isValid($this->createDto(base64_encode($signature))));
    }

    public function testIsValidReturnsFalseWhenIdentityCannotBeEncoded(): void
    {
        $repository = $this->createMock(OwnClientRepository::class);
        $repository->expects(self::never())->method('findOneBy');

        $validator = new RegistrationSignatureValidator(
            $this->createMock(LoggerInterface::class),
            $repository
        );

        self::assertFalse($validator->isValid($this->createDtoWithEmail(base64_encode('signature'), "\xB1\x31")));
    }

    private function createDto(string $signature): RegistrationProcessDTO
    {
        return RegistrationProcessDTO::mapFromArrayLogin([
            'signature' => $signature,
            'publicId' => 'public-id',
            'email' => 'user@example.test',
            'processId' => 'process-123',
        ]);
    }

    private function createDtoWithEmail(string $signature, string $email): RegistrationProcessDTO
    {
        return RegistrationProcessDTO::mapFromArrayLogin([
            'signature' => $signature,
            'publicId' => 'public-id',
            'email' => $email,
            'processId' => 'process-123',
        ]);
    }

    private function getPrivateKeyPem(): string
    {
        $content = file_get_contents(dirname(__DIR__, 4).'/config/jwt/private.pem');

        self::assertIsString($content);

        return $content;
    }

    private function getPublicKeyPem(): string
    {
        $content = file_get_contents(dirname(__DIR__, 4).'/config/jwt/public.pem');

        self::assertIsString($content);

        return $content;
    }
}
