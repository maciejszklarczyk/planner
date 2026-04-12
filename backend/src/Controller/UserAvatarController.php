<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[OA\Tag(name: 'User')]
class UserAvatarController extends AbstractController
{
    private const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2 MB
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const AVATAR_SIZE = 256;
    private const AVATARS_PATH = 'avatars';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FilesystemOperator $uploadsStorage,
        private readonly string $s3PublicUrl,
        private readonly string $s3Bucket,
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

        $userId = $user->getId() ?? throw new \LogicException('User must have an ID to upload avatar.');
        $filename = sprintf('%s/user_%d.webp', self::AVATARS_PATH, $userId);

        $webpContent = $this->resizeAndCropToWebp($file->getPathname(), $file->getMimeType());
        $this->uploadsStorage->write($filename, $webpContent);

        $avatarUrl = sprintf('%s/%s/%s', rtrim($this->s3PublicUrl, '/'), $this->s3Bucket, $filename);
        $user->setAvatar($avatarUrl);
        $this->entityManager->flush();

        return $this->json(['avatar' => $avatarUrl]);
    }

    #[Route('/user/avatar', name: 'delete_user_avatar', methods: ['DELETE'])]
    public function delete(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Missing credentials'], Response::HTTP_UNAUTHORIZED);
        }

        $currentAvatar = $user->getAvatar();
        if ($currentAvatar) {
            $path = parse_url($currentAvatar, PHP_URL_PATH);
            if (is_string($path)) {
                // Strip leading /{bucket}/ prefix to get the storage key
                $key = preg_replace('#^/[^/]+/#', '', $path);
                if ($key && $this->uploadsStorage->fileExists($key)) {
                    $this->uploadsStorage->delete($key);
                }
            }
        }

        $user->setAvatar(null);
        $this->entityManager->flush();

        return $this->json(['avatar' => null]);
    }

    private function resizeAndCropToWebp(string $sourcePath, string $mimeType): string
    {
        $src = match ($mimeType) {
            'image/png' => \imagecreatefrompng($sourcePath),
            'image/webp' => \imagecreatefromwebp($sourcePath),
            'image/gif' => \imagecreatefromgif($sourcePath),
            default => \imagecreatefromjpeg($sourcePath),
        };

        if (!$src instanceof \GdImage) {
            throw new \RuntimeException('Failed to create image resource from: '.$sourcePath);
        }

        $srcW = \imagesx($src);
        $srcH = \imagesy($src);
        $size = self::AVATAR_SIZE;

        if ($srcW > $srcH) {
            $cropX = (int) (($srcW - $srcH) / 2);
            $cropY = 0;
            $cropSize = $srcH;
        } else {
            $cropX = 0;
            $cropY = (int) (($srcH - $srcW) / 2);
            $cropSize = $srcW;
        }

        $dst = \imagecreatetruecolor($size, $size);
        if (!$dst instanceof \GdImage) {
            throw new \RuntimeException('Failed to create destination image resource.');
        }

        \imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, $size, $size, $cropSize, $cropSize);

        ob_start();
        \imagewebp($dst, null, 85);
        $content = ob_get_clean();

        \imagedestroy($src);
        \imagedestroy($dst);

        if (false === $content || '' === $content) {
            throw new \RuntimeException('Failed to encode image as WebP.');
        }

        return $content;
    }
}
