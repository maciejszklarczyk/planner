<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\Response\FriendRequestDto;
use App\Entity\FriendRequest;
use App\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FriendRequestDto::class)]
final class FriendRequestDtoTest extends TestCase
{
    /**
     * After an EntityManager reset (FriendshipService retry) the viewing user and the request's
     * requester are the same user loaded as two different objects. otherUser must still be the
     * other side of the request, not the viewer.
     */
    public function testViewerAsRequesterLoadedAsDifferentObjectSeesTheAddressee(): void
    {
        $request = $this->request(requester: $this->user(1, 'a@example.com'), addressee: $this->user(2, 'b@example.com'));

        $dto = FriendRequestDto::fromEntity($request, $this->user(1, 'a@example.com'));

        self::assertSame('b@example.com', $dto->otherUser->email);
    }

    public function testViewerAsAddresseeLoadedAsDifferentObjectSeesTheRequester(): void
    {
        $request = $this->request(requester: $this->user(1, 'a@example.com'), addressee: $this->user(2, 'b@example.com'));

        $dto = FriendRequestDto::fromEntity($request, $this->user(2, 'b@example.com'));

        self::assertSame('a@example.com', $dto->otherUser->email);
    }

    private function user(int $id, string $email): User
    {
        $user = new User();
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);
        $user->setEmail($email);

        return $user;
    }

    private function request(User $requester, User $addressee): FriendRequest
    {
        $request = new FriendRequest();
        (new \ReflectionProperty(FriendRequest::class, 'id'))->setValue($request, 10);
        $request->setRequester($requester);
        $request->setAddressee($addressee);

        return $request;
    }
}
