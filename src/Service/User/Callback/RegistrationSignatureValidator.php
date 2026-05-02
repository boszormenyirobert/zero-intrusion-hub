<?php

namespace App\Service\User\Callback;

use App\DTO\RegistrationProcessDTO;
use App\Repository\OwnClientRepository;
use Psr\Log\LoggerInterface;

class RegistrationSignatureValidator
{
    public function __construct(
        private LoggerInterface $logger,
        private OwnClientRepository $ownClientRepository
    ) {
    }

    public function isValid(RegistrationProcessDTO $process): bool
    {
        $receivedSignature = $this->decodeSignature($process->getSignature());

        if ($receivedSignature === null) {
            return false;
        }

        $userIdentity = $this->buildUserIdentity($process);

        if ($userIdentity === null) {
            return false;
        }

        $publicKey = $this->loadPublicKey();

        if ($publicKey === null) {
            return false;
        }

        return openssl_verify($userIdentity, $receivedSignature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    private function decodeSignature(string $signature): ?string
    {
        $decodedSignature = base64_decode($signature, true);

        if ($decodedSignature === false) {
            $this->logger->error('Failed to decode registration signature from base64.');

            return null;
        }

        return $decodedSignature;
    }

    private function buildUserIdentity(RegistrationProcessDTO $process): ?string
    {
        try {
            return json_encode([
                'publicId' => $process->getPublicId(),
                'email' => $process->getEmail(),
            ], JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->logger->error('Failed to encode registration identity for signature validation.', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function loadPublicKey(): ?\OpenSSLAsymmetricKey
    {
        $ownClient = $this->ownClientRepository->findOneBy([], ['id' => 'ASC']);

        if ($ownClient === null) {
            $this->logger->error('Signature validation failed because no own client configuration was found.');

            return null;
        }

        $publicKey = openssl_pkey_get_public($ownClient->getSslPublicKey());

        if ($publicKey === false) {
            $this->logger->error('Failed to load public key for signature validation.', [
                'openssl_error' => openssl_error_string(),
            ]);

            return null;
        }

        return $publicKey;
    }
}
