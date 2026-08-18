<?php

declare(strict_types=1);

namespace App\Dto\Response;

use App\Entity\FriendRequest;
use App\Entity\User;

class FriendRequestDto
{
    public function __construct(
        public readonly int $id,
        public readonly UserListItemDto $otherUser,
        public readonly string $status,
        public readonly string $createdAt,
    ) {
    }

    public static function fromEntity(FriendRequest $request, User $viewingAs): self
    {
        $requester = $request->getRequester() ?? throw new \LogicException('FriendRequest must have a requester.');
        $addressee = $request->getAddressee() ?? throw new \LogicException('FriendRequest must have an addressee.');

        $otherUser = $viewingAs === $requester ? $addressee : $requester;

        return new self(
            id: $request->getId() ?? throw new \LogicException('FriendRequest must have an ID.'),
            otherUser: UserListItemDto::fromEntity($otherUser),
            status: $request->getStatus()->value,
            createdAt: $request->getCreatedAt()->format(DATE_ATOM),
        );
    }

    /**
     * @param FriendRequest[] $requests
     *
     * @return self[]
     */
    public static function fromEntities(array $requests, User $viewingAs): array
    {
        return array_map(
            fn (FriendRequest $request) => self::fromEntity($request, $viewingAs),
            $requests
        );
    }
}
