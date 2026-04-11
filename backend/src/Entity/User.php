<?php

declare(strict_types=1);

namespace App\Entity;

use App\Dto\User\EditUserDto;
use App\Entity\Enum\UserStatusEnum;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use SoftDeleteableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    /**
     * @var Collection<int, UserHasGroup>
     */
    #[ORM\OneToMany(targetEntity: UserHasGroup::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $userHasGroups;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    private ?self $addedBy = null;

    #[ORM\Column(enumType: UserStatusEnum::class, options: ['default' => UserStatusEnum::NEW])]
    private ?UserStatusEnum $status = UserStatusEnum::NEW;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $avatar = null;

    public function __construct()
    {
        $this->userHasGroups = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    /**
     * @return Collection<int, UserHasGroup>
     */
    public function getUserHasGroups(): Collection
    {
        return $this->userHasGroups;
    }

    public function isMemberOf(Group $group): bool
    {
        return $this->userHasGroups->exists(
            fn (int $key, UserHasGroup $uhg) => $uhg->getGroup() === $group
        );
    }

    public function addUserHasGroup(UserHasGroup $userHasGroup): static
    {
        if (!$this->userHasGroups->contains($userHasGroup)) {
            $this->userHasGroups->add($userHasGroup);
            $userHasGroup->setUsers($this);
        }

        return $this;
    }

    public function removeUserHasGroup(UserHasGroup $userHasGroup): static
    {
        if ($this->userHasGroups->removeElement($userHasGroup)) {
            // set the owning side to null (unless already changed)
            if ($userHasGroup->getUsers() === $this) {
                $userHasGroup->setUsers(null);
            }
        }

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function updateFromDto(EditUserDto $dto): static
    {
        $this->email = $dto->email;
        $this->name = $dto->name;

        return $this;
    }

    public function getAddedBy(): ?self
    {
        return $this->addedBy;
    }

    public function setAddedBy(?self $addedBy): static
    {
        $this->addedBy = $addedBy;

        return $this;
    }

    public function getStatus(): ?UserStatusEnum
    {
        return $this->status;
    }

    public function setStatus(UserStatusEnum $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }
}
