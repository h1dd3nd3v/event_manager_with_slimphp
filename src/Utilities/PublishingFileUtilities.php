<?php

declare(strict_types=1);

namespace App\Utilities;

use Cloudinary\Cloudinary;
use InvalidArgumentException;
use LengthException;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Throwable;

class PublishingFileUtilities
{
    public const MAX_FILE_SIZE_BYTES = 500 * 1024 * 1024;
    private const MAX_DISPLAY_IMAGE_SIZE_BYTES = 5 * 1024 * 1024;

    private const DOCUMENT_MIME_TYPES = [
        'pdf' => ['application/pdf'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/x-zip',
            'application/x-zip-compressed',
        ],
        'epub' => [
            'application/epub+zip',
            'application/zip',
            'application/x-zip',
        ],
        'mobi' => [
            'application/x-mobipocket-ebook',
            'application/octet-stream',
        ],
        'azw' => [
            'application/vnd.amazon.ebook',
            'application/octet-stream',
        ],
        'azw3' => [
            'application/vnd.amazon.ebook',
            'application/octet-stream',
        ],
    ];

    private const VIDEO_MIME_TYPES = [
        '3g2' => ['video/3gpp2'],
        '3gp' => ['video/3gpp'],
        'avi' => ['video/avi', 'video/x-msvideo'],
        'flv' => ['video/x-flv'],
        'm4v' => ['video/mp4', 'video/x-m4v'],
        'mkv' => ['video/x-matroska'],
        'mov' => ['video/quicktime'],
        'mp4' => ['video/mp4'],
        'mpeg' => ['video/mpeg'],
        'mpg' => ['video/mpeg'],
        'ogv' => ['video/ogg'],
        'webm' => ['video/webm'],
        'wmv' => ['video/x-ms-wmv', 'video/x-ms-asf'],
    ];

    private const DISPLAY_IMAGE_MIME_TYPES = [
        'gif' => ['image/gif'],
        'jpeg' => ['image/jpeg'],
        'jpg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];

    public function upload(UploadedFileInterface $file): array
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('The uploaded file could not be received.');
        }

        $size = $file->getSize();
        if ($size === null || $size <= 0) {
            throw new InvalidArgumentException('The uploaded file is empty.');
        }
        if ($size > self::MAX_FILE_SIZE_BYTES) {
            throw new LengthException('The maximum file size is 500 MB.');
        }

        $clientFilename = $file->getClientFilename() ?? '';
        $filename = basename(str_replace('\\', '/', $clientFilename));
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimeTypes = self::VIDEO_MIME_TYPES[$extension]
            ?? self::DOCUMENT_MIME_TYPES[$extension]
            ?? null;

        if ($mimeTypes === null) {
            throw new InvalidArgumentException(
                'Supported files are PDF, DOCX, EPUB, MOBI, AZW, AZW3, and video files.'
            );
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'publishing_');
        if ($tempPath === false) {
            throw new RuntimeException('Unable to create a temporary upload file.');
        }

        try {
            if (!unlink($tempPath)) {
                throw new RuntimeException('Unable to prepare a temporary upload file.');
            }

            $file->moveTo($tempPath);
            $detectedMimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($tempPath);

            if (!is_string($detectedMimeType) || !in_array($detectedMimeType, $mimeTypes, true)) {
                throw new InvalidArgumentException(
                    'The file contents do not match a supported file type.'
                );
            }
            if (!$this->hasValidFileStructure($tempPath, $extension)) {
                throw new InvalidArgumentException(
                    'The file contents do not match a supported file type.'
                );
            }

            $resourceType = isset(self::VIDEO_MIME_TYPES[$extension])
                ? 'video'
                : 'raw';
            try {
                $upload = $this->cloudinary()->uploadApi()->upload($tempPath, [
                    'resource_type' => $resourceType,
                    'folder' => 'publishing',
                    'filename' => $filename,
                    'use_filename' => true,
                    'unique_filename' => true,
                    'overwrite' => false,
                ]);
            } catch (Throwable $error) {
                throw new RuntimeException('Cloudinary file upload failed.', 0, $error);
            }

            if (
                !isset($upload['secure_url'], $upload['public_id'])
                || !is_string($upload['secure_url'])
                || !is_string($upload['public_id'])
                || $upload['public_id'] === ''
            ) {
                throw new RuntimeException('Cloudinary did not return the required file details.');
            }

            return [
                'filename' => $filename,
                'url' => $upload['secure_url'],
                'public_id' => $upload['public_id'],
                'resource_type' => $upload['resource_type'] ?? $resourceType,
                'format' => $upload['format'] ?? $extension,
                'bytes' => $upload['bytes'] ?? $size,
            ];
        } finally {
            if (is_file($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    public function delete(string $publicId, string $resourceType): bool
    {
        if (!in_array($resourceType, ['image', 'raw', 'video'], true)) {
            throw new InvalidArgumentException('Unsupported Cloudinary resource type.');
        }

        try {
            $result = $this->cloudinary()->uploadApi()->destroy($publicId, [
                'resource_type' => $resourceType,
                'invalidate' => true,
            ]);

            return in_array($result['result'] ?? null, ['ok', 'not found'], true);
        } catch (Throwable $error) {
            throw new RuntimeException('Cloudinary file deletion failed.', 0, $error);
        }
    }

    public function uploadDisplayImage(UploadedFileInterface $file): array
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('The display image could not be received.');
        }

        $size = $file->getSize();
        if ($size === null || $size <= 0) {
            throw new InvalidArgumentException('The display image is empty.');
        }
        if ($size > self::MAX_DISPLAY_IMAGE_SIZE_BYTES) {
            throw new LengthException('The maximum display image size is 5 MB.');
        }

        $clientFilename = $file->getClientFilename() ?? '';
        $filename = basename(str_replace('\\', '/', $clientFilename));
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimeTypes = self::DISPLAY_IMAGE_MIME_TYPES[$extension] ?? null;
        if ($mimeTypes === null) {
            throw new InvalidArgumentException(
                'Display images must be JPG, PNG, GIF, or WEBP files.'
            );
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'publishing_image_');
        if ($tempPath === false) {
            throw new RuntimeException('Unable to create a temporary image file.');
        }

        try {
            if (!unlink($tempPath)) {
                throw new RuntimeException('Unable to prepare a temporary image file.');
            }

            $file->moveTo($tempPath);
            $detectedMimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($tempPath);
            if (!is_string($detectedMimeType) || !in_array($detectedMimeType, $mimeTypes, true)) {
                throw new InvalidArgumentException(
                    'The display image contents do not match its file type.'
                );
            }

            try {
                $upload = $this->cloudinary()->uploadApi()->upload($tempPath, [
                    'resource_type' => 'image',
                    'folder' => 'publishing/display-images',
                    'filename' => $filename,
                    'use_filename' => true,
                    'unique_filename' => true,
                    'overwrite' => false,
                ]);
            } catch (Throwable $error) {
                throw new RuntimeException('Cloudinary display image upload failed.', 0, $error);
            }

            if (
                !isset($upload['secure_url'], $upload['public_id'])
                || !is_string($upload['secure_url'])
                || !is_string($upload['public_id'])
                || $upload['public_id'] === ''
            ) {
                throw new RuntimeException(
                    'Cloudinary did not return the required display image details.'
                );
            }

            return [
                'url' => $upload['secure_url'],
                'public_id' => $upload['public_id'],
            ];
        } finally {
            if (is_file($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    private function cloudinary(): Cloudinary
    {
        return new Cloudinary([
            'cloud' => [
                'cloud_name' => $_ENV['CLOUDINARY_CLOUD_NAME'] ?? '',
                'api_key' => $_ENV['CLOUDINARY_API_KEY'] ?? '',
                'api_secret' => $_ENV['CLOUDINARY_API_SECRET'] ?? '',
            ],
        ]);
    }

    private function hasValidFileStructure(string $path, string $extension): bool
    {
        if (in_array($extension, ['docx', 'epub'], true)) {
            $archive = new \ZipArchive();
            if ($archive->open($path, \ZipArchive::RDONLY) !== true) {
                return false;
            }

            try {
                if ($extension === 'docx') {
                    return $archive->locateName('[Content_Types].xml') !== false
                        && $archive->locateName('word/document.xml') !== false;
                }

                return $archive->getFromName('mimetype') === 'application/epub+zip'
                    && $archive->locateName('META-INF/container.xml') !== false;
            } finally {
                $archive->close();
            }
        }

        if (in_array($extension, ['mobi', 'azw', 'azw3'], true)) {
            $handle = fopen($path, 'rb');
            if ($handle === false) {
                return false;
            }

            try {
                return fseek($handle, 60) === 0 && fread($handle, 8) === 'BOOKMOBI';
            } finally {
                fclose($handle);
            }
        }

        return true;
    }
}
