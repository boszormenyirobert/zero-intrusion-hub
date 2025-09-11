<?php

namespace App\Service\Corporate;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\OwnClient;
use App\Repository\OwnClientRepository;

class DatabaseService
{

    public function __construct(
        private OwnClientRepository $ownClientRepository,
        private EntityManagerInterface $entityManager
    ) {}

    public function createOwnClient($authorizedData){
        $ownClient = new OwnClient();
        $ownClient->setCorporateId($authorizedData['corporate_id']);
        $ownClient->setCorporateIdKey($authorizedData['corporate_id_key']);
        $ownClient->setCorporateIdSecret($authorizedData['corporate_id_secret']);
        $ownClient->setSslPublicKey($authorizedData['ssl_public_key']);

        $this->entityManager->persist($ownClient);
        $this->entityManager->flush();
    }

    public function updateOwnClient($userInputs){
        $ownClient = $this->ownClientRepository->findOneBy([], ['id' => 'ASC']); 
        $ownClient->setDomain($userInputs['domain']);
        
        $this->entityManager->persist($ownClient);
        $this->entityManager->flush();
    }
}