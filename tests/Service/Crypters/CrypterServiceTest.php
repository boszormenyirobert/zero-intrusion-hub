<?php

declare(strict_types=1);

namespace App\Tests\Service\Crypters;

use App\Service\Crypters\CrypterService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class CrypterServiceTest extends TestCase
{
    public function testEncryptAndDecryptRoundTripForArrayDtoPayload(): void
    {
        $params = $this->createMock(ContainerBagInterface::class);
        $params->method('get')->with('DATA_HASH_SECRET')->willReturn('12345678901234567890123456789012');

        $payload = ['publicId' => 'public-1', 'secret' => 'secret-1'];
        $encrypted = (new CrypterService($payload, $params))->encryptData();

        self::assertIsString($encrypted);
        self::assertNotSame('', $encrypted);

        $decrypted = (new CrypterService($encrypted, $params))->decryptData(true);

        self::assertSame($payload, $decrypted);
    }

    public function testDecryptReturnsJsonStringWhenDtoFlagIsFalse(): void
    {
        $params = $this->createMock(ContainerBagInterface::class);
        $params->method('get')->with('DATA_HASH_SECRET')->willReturn('12345678901234567890123456789012');

        $encrypted = (new CrypterService(['status' => 'ok'], $params))->encryptData();
        $decrypted = (new CrypterService($encrypted, $params))->decryptData(false);

        self::assertSame('{"status":"ok"}', $decrypted);
    }

    public function testDecryptDataThrowsForInvalidCiphertext(): void
    {
        $params = $this->createMock(ContainerBagInterface::class);
        $params->method('get')->with('DATA_HASH_SECRET')->willReturn('12345678901234567890123456789012');

        $service = new CrypterService(base64_encode('short-invalid-payload'), $params);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');

        $service->decryptData();
    }

    public function testPrivateHelpersCanBeExercisedViaReflection(): void
    {
        $params = $this->createMock(ContainerBagInterface::class);
        $params->method('get')->with('DATA_HASH_SECRET')->willReturn('12345678901234567890123456789012');

        $service = new CrypterService(['status' => 'ok'], $params);
        $reflection = new \ReflectionClass($service);

        $setIv = $reflection->getMethod('setIv');
        $setIv->setAccessible(true);
        $ivProperty = $reflection->getProperty('iv');
        $ivProperty->setAccessible(true);

        $setIv->invoke($service, true, '');
        self::assertSame(16, strlen($ivProperty->getValue($service)));

        $setIv->invoke($service, false, '1234567890abcdefpayload');
        self::assertSame('1234567890abcdef', $ivProperty->getValue($service));

        $decodeJson = $reflection->getMethod('decodeJson');
        $decodeJson->setAccessible(true);
        self::assertSame(['status' => 'ok'], $decodeJson->invoke($service, '{"status":"ok"}'));

        $this->expectException(\JsonException::class);
        $decodeJson->invoke($service, '{invalid');
    }
}
