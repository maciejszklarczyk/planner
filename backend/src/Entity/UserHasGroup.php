<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\UserGroupRoleEnum;
use App\Repository\UserHasGroupRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

#[ORM\Entity(repositoryClass: UserHasGroupRepository::class)]
class UserHasGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ManyToOne(targetEntity: User::class, inversedBy: 'userHasGroups')]
    #[JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ManyToOne(targetEntity: Group::class, inversedBy: 'userHasGroups')]
    #[JoinColumn(nullable: false)]
    private ?Group $group = null;

    #[ORM\Column(enumType: UserGroupRoleEnum::class)]
    private UserGroupRoleEnum $role = UserGroupRoleEnum::MEMBER;

    #[ManyToOne(targetEntity: User::class)]
    private ?User $addedBy = null;

    public function __construct()
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getGroup(): ?Group
    {
        return $this->group;
    }

    public function setGroup(?Group $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function getRole(): UserGroupRoleEnum
    {
        return $this->role;
    }

    public function setRole(UserGroupRoleEnum $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getAddedBy(): ?User
    {
        return $this->addedBy;
    }

    public function setAddedBy(?User $addedBy): static
    {
        $this->addedBy = $addedBy;

        return $this;
    }
}
