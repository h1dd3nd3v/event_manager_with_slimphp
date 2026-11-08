<?php

namespace App\Utilities;

use Exception;
use Cloudinary\Cloudinary;

class ImageUtilities
{
    private $cloud_name;
    private $api_key;
    private $api_secret;

    public function __construct()
    {
        $this->cloud_name = $_ENV['CLOUDINARY_CLOUD_NAME'];
        $this->api_key = $_ENV['CLOUDINARY_API_KEY'];
        $this->api_secret = $_ENV['CLOUDINARY_API_SECRET'];
    }

    public function upload_image($image)
    {
        $tempPath = null;

        try {
            if ($image->getError() !== UPLOAD_ERR_OK) {
                throw new Exception("Image upload error: " . $image->getError());
            }

            if ($image->getSize() <= 0) {
                throw new Exception("The uploaded image is empty.");
            }

            // Validate the contents instead of trusting the client-provided filename or MIME type.
            $tempPath = tempnam(sys_get_temp_dir(), 'image_');
            if ($tempPath === false) {
                throw new Exception("Unable to create a temporary file.");
            }

            unlink($tempPath);
            $image->moveTo($tempPath);

            $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $fileInfo->file($tempPath);
            $imageMimeTypes = [
                'image/avif',
                'image/bmp',
                'image/gif',
                'image/jpeg',
                'image/png',
                'image/tiff',
                'image/webp'
            ];

            if (!in_array($mimeType, $imageMimeTypes, true)) {
                throw new Exception("The uploaded file is not a supported image type.");
            }

            $cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => $this->cloud_name,
                    'api_key' => $this->api_key,
                    'api_secret' => $this->api_secret
                ]
            ]);

            $upload = $cloudinary->uploadApi()->upload($tempPath);

            return $upload['secure_url'];
        } catch (Exception $e) {
            return [
                "success" => false,
                "errorMessage" => $e->getMessage()
            ];
        } finally {
            if ($tempPath !== null && is_file($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    public function upload_video($video)
    {
        $tempPath = null;

        try {
            if ($video->getError() !== UPLOAD_ERR_OK) {
                throw new Exception("Video upload error: " . $video->getError());
            }

            if ($video->getSize() <= 0) {
                throw new Exception("The uploaded video is empty.");
            }

            // Detect the actual file type from its contents; client-provided MIME types are untrusted.
            $tempPath = tempnam(sys_get_temp_dir(), 'video_');
            if ($tempPath === false) {
                throw new Exception("Unable to create a temporary file.");
            }

            // moveTo expects to move the uploaded file to the destination.
            unlink($tempPath);
            $video->moveTo($tempPath);

            $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $fileInfo->file($tempPath);
            $videoMimeTypes = [
                'video/3gpp',
                'video/3gpp2',
                'video/avi',
                'video/mp2t',
                'video/mp4',
                'video/mpeg',
                'video/ogg',
                'video/quicktime',
                'video/webm',
                'video/x-flv',
                'video/x-matroska',
                'video/x-ms-asf',
                'video/x-ms-wmv',
                'video/x-msvideo'
            ];

            if (!in_array($mimeType, $videoMimeTypes, true)) {
                throw new Exception("The uploaded file is not a supported video type.");
            }

            $cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => $this->cloud_name,
                    'api_key' => $this->api_key,
                    'api_secret' => $this->api_secret
                ]
            ]);

            $upload = $cloudinary->uploadApi()->upload($tempPath, [
                'resource_type' => 'video'
            ]);

            return $upload['secure_url'];
        } catch (Exception $e) {
            return [
                "success" => false,
                "errorMessage" => $e->getMessage()
            ];
        } finally {
            if ($tempPath !== null && is_file($tempPath)) {
                unlink($tempPath);
            }
        }
    }
}