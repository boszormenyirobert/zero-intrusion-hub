<?php

namespace App\Entity;

use App\Repository\OwnClientRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OwnClientRepository::class)]
class OwnClient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $corporateId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $corporateIdKey = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $corporateIdSecret = null;

    #[ORM\Column(length: 5000, nullable: true)]
    private ?string $sslPublicKey = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $domain = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCorporateId(): ?string
    {
        return $this->corporateId;
    }

    public function setCorporateId(string $corporateId): static
    {
        $this->corporateId = $corporateId;

        return $this;
    }

    public function getCorporateIdKey(): ?string
    {
        return $this->corporateIdKey;
    }

    public function setCorporateIdKey(string $corporateIdKey): static
    {
        $this->corporateIdKey = $corporateIdKey;

        return $this;
    }

    public function getCorporateIdSecret(): ?string
    {
        return $this->corporateIdSecret;
    }

    public function setCorporateIdSecret(string $corporateIdSecret): static
    {
        $this->corporateIdSecret = $corporateIdSecret;

        return $this;
    }

    public function getSslPublicKey(): ?string
    {
        return $this->sslPublicKey;
    }

    public function setSslPublicKey(string $sslPublicKey): static
    {
        $this->sslPublicKey = $sslPublicKey;

        return $this;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): static
    {
        $this->domain = $domain;

        return $this;
    }
}
