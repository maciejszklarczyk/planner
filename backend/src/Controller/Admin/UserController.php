<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Dto\Response\UserListItemDto;
use App\Dto\User\UserInviteDto;
use App\Entity\Enum\UserStatusEnum;
use App\Entity\User;
use App\Entity\UserInvitationToken;
use App\Exception\UserAlreadyCompletedRegistrationException;
use App\Exception\UserAlreadyExistsException;
use App\Repository\UserInvitationTokenRepository;
use App\Repository\UserRepository;
use App\Service\InvitationMailer;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: 'Admin')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserInvitationTokenRepository $invitationTokenRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly InvitationMailer $invitationMailer,
    ) {
    }

    #[Route('/admin/users', name: 'admin_users_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 50)));
        $search = $request->query->get('search');
        $excludeGroupId = $request->query->get('excludeGroupId')
            ? (int) $request->query->get('excludeGroupId')
            : null;

        $users = $this->userRepository->findWithPagination(
            page: $page,
            limit: $limit,
            search: $search,
            excludeGroupId: $excludeGroupId
        );

        $total = $this->userRepository->countWithFilters(
            search: $search,
            excludeGroupId: $excludeGroupId
        );

        return $this->json([
            'data' => UserListItemDto::fromEntities($users),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => $total > 0 ? (int) ceil($total / $limit) : 0,
            ],
        ]);
    }

    /**
     * @throws RandomException
     */
    #[Route('/admin/user-invite', name: 'send_user_invite', methods: ['POST'])]
    public function sendUserInvite(#[MapRequestPayload] UserInviteDto $dto, #[CurrentUser] User $currentUser): JsonResponse
    {
        $existingUser = $this->userRepository->findOneBy(['email' => $dto->email]);
        if ($existingUser) {
            throw new UserAlreadyExistsException('User already exists.');
        }

        $newUser = new User();
        $newUser->setEmail($dto->email);
        $newUser->setRoles(['ROLE_USER']);
        $newUser->setAddedBy($currentUser);
        $this->entityManager->persist($newUser);

        [$rawToken] = $this->createToken($dto->email);
        $this->entityManager->flush();

        $this->invitationMailer->sendInvitation($dto->email, $rawToken);

        return $this->json(['status' => 'ok', 'email' => $dto->email]);
    }

    /**
     * @throws RandomException
     */
    #[Route('/admin/user-invite/resend', name: 'resend_user_invite', methods: ['POST'])]
    public function resendUserInvite(#[MapRequestPayload] UserInviteDto $dto): JsonResponse
    {
        $user = $this->userRepository->findOneBy(['email' => $dto->email]);
        if (!$user) {
            throw new NotFoundHttpException('User not found.');
        }

        if (UserStatusEnum::NEW !== $user->getStatus()) {
            throw new UserAlreadyCompletedRegistrationException('User already completed registration.');
        }

        foreach ($this->invitationTokenRepository->findActiveByEmail($dto->email) as $oldToken) {
            $oldToken->setUsedAt(new \DateTimeImmutable());
        }

        [$rawToken] = $this->createToken($dto->email);
        $this->entityManager->flush();

        $this->invitationMailer->sendInvitation($dto->email, $rawToken);

        return $this->json(['status' => 'ok', 'email' => $dto->email]);
    }

    /**
     * @return array{string, UserInvitationToken} [rawToken, persistedEntity]
     *
     * @throws RandomException
     */
    private function createToken(string $email): array
    {
        $rawToken = bin2hex(random_bytes(32));
        $token = new UserInvitationToken(
            token: hash('sha256', $rawToken),
            email: $email,
        );
        $this->entityManager->persist($token);

        return [$rawToken, $token];
    }
}
