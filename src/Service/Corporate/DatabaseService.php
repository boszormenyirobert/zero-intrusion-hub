<?php

namespace App\Service\Corporate;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\OwnClient;
use App\Repository\OwnClientRepository;

/**
 * Service for managing OwnClient entity persistence and updates.
 *
 * Provides methods to create and update OwnClient records in the database.
 */
class DatabaseService
{
    public function __construct(
        private OwnClientRepository $ownClientRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Creates a new OwnClient entity from authorized data and persists it.
     *
     * @param array $authorizedData Associative array with corporate_id, corporate_id_key, corporate_id_secret, ssl_public_key
     * @return void
     */
    public function createOwnClient($authorizedData): void{
        $ownClient = new OwnClient();
        $ownClient->setCorporateId($authorizedData['corporate_id']);
        $ownClient->setCorporateIdKey($authorizedData['corporate_id_key']);
        $ownClient->setCorporateIdSecret($authorizedData['corporate_id_secret']);
        $ownClient->setSslPublicKey($authorizedData['ssl_public_key']);

        $this->entityManager->persist($ownClient);
        $this->entityManager->flush();
    }

    /**
     * Updates the domain of the first OwnClient entity found in the database.
     *
     * @param array $userInputs Associative array with 'domain' key
     * @throws \RuntimeException If no OwnClient entity is found
     * @return void
     */
    public function updateOwnClient($userInputs): void{
        $ownClient = $this->ownClientRepository->findOneBy([], ['id' => 'ASC']); 
        if (!$ownClient) {
            throw new \RuntimeException('No OwnClient found to update.');
        }
        $ownClient->setDomain($userInputs['domain']);
        
        $this->entityManager->persist($ownClient);
        $this->entityManager->flush();
    }
}