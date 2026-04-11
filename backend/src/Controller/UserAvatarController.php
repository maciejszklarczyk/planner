<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use function imagecopyresampled;
use function imagecreatefrompng;
use function imagecreatefromwebp;
use function imagecreatefromgif;
use function imagecreatefromjpeg;
use function imagecreatetruecolor;
use function imagedestroy;
use function imagesx;
use function imagesy;
use function imagewebp;

#[OA\Tag(name: 'User')]
class UserAvatarController extends AbstractController
{
    private const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2 MB
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const AVATAR_SIZE = 256;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $avatarsDir,
        private readonly string $avatarsPublicPath,
    ) {
    }

    #[Route('/user/avatar', name: 'upload_user_avatar', methods: ['POST'])]
    public function upload(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Missing credentials'], Response::HTTP_UNAUTHORIZED);
        }

        $file = $request->files->get('avatar');

        if (!$file) {
            return $this->json(['message' => 'No file uploaded'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return $this->json(['message' => 'File too large (max 2 MB)'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            return $this->json(['message' => 'Invalid file type. Allowed: JPEG, PNG, WebP, GIF'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $filename = sprintf('user_%d.webp', $user->getId());
        $sourcePath = $file->getPathname();

        $this->resizeAndCrop($sourcePath, $this->avatarsDir . '/' . $filename, $file->getMimeType());

        $avatarUrl = $this->avatarsPublicPath . '/' . $filename;
        $user->setAvatar($avatarUrl);
        $this->entityManager->flush();

        return $this->json(['avatar' => $avatarUrl]);
    }

    private function resizeAndCrop(string $sourcePath, string $destPath, string $mimeType): void
    {
        $src = match ($mimeType) {
            'image/png'  => imagecreatefrompng($sourcePath),
            'image/webp' => imagecreatefromwebp($sourcePath),
            'image/gif'  => imagecreatefromgif($sourcePath),
            default      => imagecreatefromjpeg($sourcePath),
        };

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $size = self::AVATAR_SIZE;

        // Center crop to square
        if ($srcW > $srcH) {
            $cropX = (int) (($srcW - $srcH) / 2);
            $cropY = 0;
            $cropSize = $srcH;
        } else {
            $cropX = 0;
            $cropY = (int) (($srcH - $srcW) / 2);
            $cropSize = $srcW;
        }

        $dst = imagecreatetruecolor($size, $size);
        imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, $size, $size, $cropSize, $cropSize);

        imagewebp($dst, $destPath, 85);

        imagedestroy($src);
        imagedestroy($dst);
    }

    #[Route('/user/avatar', name: 'delete_user_avatar', methods: ['DELETE'])]
    public function delete(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Missing credentials'], Response::HTTP_UNAUTHORIZED);
        }

        $currentAvatar = $user->getAvatar();
        if ($currentAvatar) {
            $filePath = $this->avatarsDir . '/' . basename(parse_url($currentAvatar, PHP_URL_PATH));
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $user->setAvatar(null);
        $this->entityManager->flush();

        return $this->json(['avatar' => null]);
    }
}
