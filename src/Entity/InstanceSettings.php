<?php

namespace App\Entity;

use App\Repository\InstanceSettingsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InstanceSettingsRepository::class)]
class InstanceSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?bool $initialization = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isInitialization(): ?bool
    {
        return $this->initialization;
    }

    public function setInitialization(bool $initialization): static
    {
        $this->initialization = $initialization;

        return $this;
    }
}
