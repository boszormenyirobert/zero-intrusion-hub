<?php

namespace App\Entity;

use App\Repository\ProcessRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProcessRepository::class)]
class Process
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $processId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $authId = null;

    #[ORM\Column(nullable: true)]
    private ?bool $allowed = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProcessId(): ?string
    {
        return $this->processId;
    }

    public function setProcessId(?string $processId): static
    {
        $this->processId = $processId;

        return $this;
    }

    public function getAuthId(): ?string
    {
        return $this->authId;
    }

    public function setAuthId(?string $authId): static
    {
        $this->authId = $authId;

        return $this;
    }

    public function isAllowed(): ?bool
    {
        return $this->allowed;
    }

    public function setAllowed(?bool $allowed): static
    {
        $this->allowed = $allowed;

        return $this;
    }
}
