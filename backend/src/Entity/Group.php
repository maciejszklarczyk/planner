<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\UserGroupRoleEnum;
use App\Repository\GroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: GroupRepository::class)]
#[ORM\Table(name: '`group`')]
#[UniqueEntity('name')]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt')]
class Group
{
    use SoftDeleteableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<int, UserHasGroup>
     */
    #[ORM\OneToMany(targetEntity: UserHasGroup::class, mappedBy: 'group', orphanRemoval: true)]
    private Collection $userHasGroups;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    public function __construct()
    {
        $this->userHasGroups = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, UserHasGroup>
     */
    public function getUserHasGroups(): Collection
    {
        return $this->userHasGroups;
    }

    public function addUserHasGroup(UserHasGroup $userHasGroup): static
    {
        if (!$this->userHasGroups->contains($userHasGroup)) {
            $this->userHasGroups->add($userHasGroup);
            $userHasGroup->setGroups($this);
        }

        return $this;
    }

    public function removeUserHasGroup(UserHasGroup $userHasGroup): static
    {
        if ($this->userHasGroups->removeElement($userHasGroup)) {
            // set the owning side to null (unless already changed)
            if ($userHasGroup->getGroups() === $this) {
                $userHasGroup->setGroups(null);
            }
        }

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getGroupOwnerUser(): ?User
    {
        foreach ($this->userHasGroups as $userHasGroup) {
            if (UserGroupRoleEnum::OWNER === $userHasGroup->getRole()) {
                return $userHasGroup->getUser();
            }
        }

        return null;
    }
}
