<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PublishingModel;
use App\Utilities\PublishingFileUtilities;
use InvalidArgumentException;
use LengthException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

class PublishingController
{
    public function upload_file(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $name = $this->validatedName(
            is_array($body) ? ($body['name'] ?? null) : null,
            $response
        );
        if ($name instanceof Response) {
            return $name;
        }

        $file = $this->uploadedFile($request, $response);
        if ($file instanceof Response) {
            return $file;
        }

        $displayImageFile = $this->optionalDisplayImage($request, $response);
        if ($displayImageFile instanceof Response) {
            return $displayImageFile;
        }

        $utility = new PublishingFileUtilities();
        $upload = null;
        $displayImage = null;
        try {
            $upload = $utility->upload($file);
            if ($displayImageFile !== null) {
                $displayImage = $utility->uploadDisplayImage($displayImageFile);
            }
        } catch (LengthException $error) {
            $this->cleanupUploadedAsset($utility, $upload, 'raw');
            $this->cleanupUploadedAsset($utility, $displayImage, 'image');
            return $this->jsonResponse($response, [
                'error' => $error->getMessage(),
            ], 413);
        } catch (InvalidArgumentException $error) {
            $this->cleanupUploadedAsset($utility, $upload, 'raw');
            $this->cleanupUploadedAsset($utility, $displayImage, 'image');
            return $this->jsonResponse($response, [
                'error' => $error->getMessage(),
            ], 400);
        } catch (Throwable $error) {
            $this->cleanupUploadedAsset($utility, $upload, 'raw');
            $this->cleanupUploadedAsset($utility, $displayImage, 'image');
            return $this->unexpectedError($response, 'upload publishing file', $error);
        }

        $upload['display_image'] = $displayImage['url'] ?? null;
        $upload['display_image_public_id'] = $displayImage['public_id'] ?? null;
        $upload['name'] = $name;

        try {
            $entry = PublishingModel::create($upload);

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'File uploaded successfully.',
                'data' => $entry,
            ], 201);
        } catch (Throwable $error) {
            $this->cleanupUploadedAsset($utility, $upload, 'raw');
            $this->cleanupUploadedAsset($utility, $displayImage, 'image');
            return $this->unexpectedError($response, 'save publishing entry', $error);
        }
    }

    public function list_entries(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $page = $this->positiveInteger($query['page'] ?? null, 1);
        $perPage = min($this->positiveInteger($query['per_page'] ?? null, 20), 100);

        try {
            $entries = PublishingModel::query()
                ->orderByDesc('id')
                ->paginate($perPage, ['*'], 'page', $page);

            return $this->jsonResponse($response, [
                'data' => $entries->items(),
                'pagination' => [
                    'currentPage' => $entries->currentPage(),
                    'perPage' => $entries->perPage(),
                    'total' => $entries->total(),
                    'lastPage' => $entries->lastPage(),
                ],
            ]);
        } catch (Throwable $error) {
            return $this->unexpectedError($response, 'list publishing entries', $error);
        }
    }

    public function get_entry(Request $request, Response $response, array $args): Response
    {
        $entryId = $this->positiveInteger($args['publishing_id'] ?? null, 0);
        if ($entryId === 0) {
            return $this->jsonResponse($response, [
                'error' => 'A valid publishing entry ID is required.',
            ], 400);
        }

        try {
            $entry = PublishingModel::find($entryId);
            if ($entry === null) {
                return $this->jsonResponse($response, [
                    'error' => 'Publishing entry not found.',
                ], 404);
            }

            return $this->jsonResponse($response, ['data' => $entry]);
        } catch (Throwable $error) {
            return $this->unexpectedError($response, 'read publishing entry', $error);
        }
    }

    public function update_entry(Request $request, Response $response, array $args): Response
    {
        $entryId = $this->positiveInteger($args['publishing_id'] ?? null, 0);
        if ($entryId === 0) {
            return $this->jsonResponse($response, [
                'error' => 'A valid publishing entry ID is required.',
            ], 400);
        }

        try {
            $entry = PublishingModel::find($entryId);
        } catch (Throwable $error) {
            return $this->unexpectedError($response, 'read publishing entry for update', $error);
        }

        if ($entry === null) {
            return $this->jsonResponse($response, [
                'error' => 'Publishing entry not found.',
            ], 404);
        }

        $body = $request->getParsedBody();
        $name = $entry->name;
        if (is_array($body) && array_key_exists('name', $body)) {
            $name = $this->validatedName($body['name'], $response);
            if ($name instanceof Response) {
                return $name;
            }
        }

        $file = $this->uploadedFile($request, $response);
        if ($file instanceof Response) {
            return $file;
        }

        $displayImageFile = $this->optionalDisplayImage($request, $response);
        if ($displayImageFile instanceof Response) {
            return $displayImageFile;
        }

        $utility = new PublishingFileUtilities();
        $upload = null;
        $displayImage = null;
        try {
            $upload = $utility->upload($file);
            if ($displayImageFile !== null) {
                $displayImage = $utility->uploadDisplayImage($displayImageFile);
            }
        } catch (LengthException $error) {
            $this->cleanupUploadedAsset($utility, $upload ?? null, 'raw');
            $this->cleanupUploadedAsset($utility, $displayImage, 'image');
            return $this->jsonResponse($response, [
                'error' => $error->getMessage(),
            ], 413);
        } catch (InvalidArgumentException $error) {
            $this->cleanupUploadedAsset($utility, $upload ?? null, 'raw');
            $this->cleanupUploadedAsset($utility, $displayImage, 'image');
            return $this->jsonResponse($response, [
                'error' => $error->getMessage(),
            ], 400);
        } catch (Throwable $error) {
            $this->cleanupUploadedAsset($utility, $upload ?? null, 'raw');
            $this->cleanupUploadedAsset($utility, $displayImage, 'image');
            return $this->unexpectedError($response, 'upload replacement publishing file', $error);
        }

        $oldPublicId = $entry->public_id;
        $oldResourceType = $entry->resource_type;
        $oldDisplayImagePublicId = $entry->display_image_public_id;
        $entryAttributes = $upload;
        $entryAttributes['name'] = $name;
        if ($displayImage !== null) {
            $entryAttributes['display_image'] = $displayImage['url'];
            $entryAttributes['display_image_public_id'] = $displayImage['public_id'];
        }

        try {
            if (!$entry->fill($entryAttributes)->save()) {
                throw new \RuntimeException('The publishing entry was not saved.');
            }
        } catch (Throwable $error) {
            $this->cleanupUploadedAsset($utility, $upload, 'raw');
            $this->cleanupUploadedAsset($utility, $displayImage, 'image');
            return $this->unexpectedError($response, 'update publishing entry', $error);
        }

        $previousAssetDeleted = true;
        if (
            $oldPublicId !== $upload['public_id']
            || $oldResourceType !== $upload['resource_type']
        ) {
            try {
                $previousAssetDeleted = $utility->delete($oldPublicId, $oldResourceType);
            } catch (Throwable $error) {
                error_log(
                    'Failed to delete replaced Cloudinary publishing asset: '
                    . get_class($error) . ': ' . $error->getMessage()
                );
                $previousAssetDeleted = false;
            }
        }

        $previousDisplayImageDeleted = true;
        if ($displayImage !== null && is_string($oldDisplayImagePublicId) && $oldDisplayImagePublicId !== '') {
            try {
                $previousDisplayImageDeleted = $utility->delete(
                    $oldDisplayImagePublicId,
                    'image'
                );
            } catch (Throwable $error) {
                error_log(
                    'Failed to delete replaced Cloudinary publishing display image: '
                    . get_class($error) . ': ' . $error->getMessage()
                );
                $previousDisplayImageDeleted = false;
            }
        }

        $payload = [
            'success' => true,
            'message' => 'Publishing entry updated successfully.',
            'data' => $entry,
            'previousAssetDeleted' => $previousAssetDeleted,
            'previousDisplayImageDeleted' => $previousDisplayImageDeleted,
        ];
        if (!$previousAssetDeleted) {
            $payload['warning'] = 'The previous Cloudinary file could not be deleted.';
        }
        if (!$previousDisplayImageDeleted) {
            $payload['displayImageWarning'] = 'The previous display image could not be deleted.';
        }

        return $this->jsonResponse($response, $payload);
    }

    public function delete_entry(Request $request, Response $response, array $args): Response
    {
        $entryId = $this->positiveInteger($args['publishing_id'] ?? null, 0);
        if ($entryId === 0) {
            return $this->jsonResponse($response, [
                'error' => 'A valid publishing entry ID is required.',
            ], 400);
        }

        try {
            $entry = PublishingModel::find($entryId);
        } catch (Throwable $error) {
            return $this->unexpectedError($response, 'delete publishing entry', $error);
        }

        if ($entry === null) {
            return $this->jsonResponse($response, [
                'error' => 'Publishing entry not found.',
            ], 404);
        }

        $utility = new PublishingFileUtilities();
        $cloudinaryDeleted = false;
        try {
            $cloudinaryDeleted = $utility->delete(
                $entry->public_id,
                $entry->resource_type
            );
            if (!$cloudinaryDeleted) {
                error_log(
                    'Cloudinary did not confirm deletion of publishing asset '
                    . $entry->public_id . '.'
                );
            }
        } catch (Throwable $error) {
            error_log(
                'Unable to delete Cloudinary asset '
                . $entry->public_id . ' for publishing entry: '
                . get_class($error) . ': ' . $error->getMessage()
            );
        }

        $displayImageDeleted = true;
        if (
            is_string($entry->display_image_public_id)
            && $entry->display_image_public_id !== ''
        ) {
            try {
                $displayImageDeleted = $utility->delete(
                    $entry->display_image_public_id,
                    'image'
                );
                if (!$displayImageDeleted) {
                    error_log(
                        'Cloudinary did not confirm deletion of publishing display image '
                        . $entry->display_image_public_id . '.'
                    );
                }
            } catch (Throwable $error) {
                error_log(
                    'Unable to delete Cloudinary publishing display image '
                    . $entry->display_image_public_id . ': '
                    . get_class($error) . ': ' . $error->getMessage()
                );
                $displayImageDeleted = false;
            }
        }

        try {
            if (!$entry->delete()) {
                throw new \RuntimeException('The publishing entry was not deleted.');
            }
        } catch (Throwable $error) {
            error_log(
                'Cloudinary asset was deleted but the publishing database entry could not be deleted: '
                . get_class($error) . ': ' . $error->getMessage()
            );

            return $this->jsonResponse($response, [
                'error' => 'The Cloudinary file was deleted, but the publishing entry could not be removed.',
            ], 500);
        }

        $payload = [
            'success' => true,
            'message' => 'Publishing entry deleted successfully.',
            'id' => $entryId,
            'cloudinaryDeleted' => $cloudinaryDeleted,
            'displayImageDeleted' => $displayImageDeleted,
        ];
        if (!$cloudinaryDeleted) {
            $payload['warning'] = 'The publishing entry was deleted, but its Cloudinary file could not be removed.';
        }
        if (!$displayImageDeleted) {
            $payload['displayImageWarning'] = 'The publishing entry was deleted, but its display image could not be removed.';
        }

        return $this->jsonResponse($response, $payload);
    }

    private function uploadedFile(
        Request $request,
        Response $response
    ): UploadedFileInterface|Response {
        $uploadedFiles = $request->getUploadedFiles();
        $file = $uploadedFiles['file'] ?? null;

        if (!$file instanceof UploadedFileInterface) {
            $contentLength = $request->getHeaderLine('Content-Length');
            $status = ctype_digit($contentLength)
                && (int) $contentLength > PublishingFileUtilities::MAX_FILE_SIZE_BYTES
                ? 413
                : 400;
            $message = $status === 413
                ? 'The maximum file size is 500 MB.'
                : 'A file is required in the "file" field.';

            return $this->jsonResponse($response, ['error' => $message], $status);
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            $status = in_array($file->getError(), [
                UPLOAD_ERR_INI_SIZE,
                UPLOAD_ERR_FORM_SIZE,
            ], true) ? 413 : 400;

            return $this->jsonResponse($response, [
                'error' => $status === 413
                    ? 'The maximum file size is 500 MB.'
                    : 'The uploaded file could not be received.',
            ], $status);
        }

        return $file;
    }

    private function optionalDisplayImage(
        Request $request,
        Response $response
    ): UploadedFileInterface|Response|null {
        $uploadedFiles = $request->getUploadedFiles();
        $image = $uploadedFiles['display_image'] ?? null;
        if ($image === null) {
            return null;
        }
        if (!$image instanceof UploadedFileInterface) {
            return $this->jsonResponse($response, [
                'error' => 'The display_image field must contain an image file.',
            ], 400);
        }
        if ($image->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($image->getError() !== UPLOAD_ERR_OK) {
            $status = in_array($image->getError(), [
                UPLOAD_ERR_INI_SIZE,
                UPLOAD_ERR_FORM_SIZE,
            ], true) ? 413 : 400;

            return $this->jsonResponse($response, [
                'error' => $status === 413
                    ? 'The maximum display image size is 5 MB.'
                    : 'The display image could not be received.',
            ], $status);
        }

        return $image;
    }

    private function validatedName($value, Response $response): string|Response
    {
        if (
            !is_string($value)
            || ($name = trim($value)) === ''
            || preg_match('/\A.{1,255}\z/us', $name) !== 1
        ) {
            return $this->jsonResponse($response, [
                'error' => 'name is required and must be between 1 and 255 characters.',
            ], 400);
        }

        return $name;
    }

    private function cleanupUploadedAsset(
        PublishingFileUtilities $utility,
        ?array $upload,
        string $resourceType
    ): void {
        if ($upload === null || !isset($upload['public_id'])) {
            return;
        }

        try {
            $resourceType = $upload['resource_type'] ?? $resourceType;
            if (!$utility->delete($upload['public_id'], $resourceType)) {
                error_log('Unable to clean up an unused Cloudinary publishing asset.');
            }
        } catch (Throwable $error) {
            error_log(
                'Unable to clean up an unused Cloudinary publishing asset: '
                . get_class($error) . ': ' . $error->getMessage()
            );
        }
    }

    private function positiveInteger($value, int $default): int
    {
        if (!is_string($value) && !is_int($value)) {
            return $default;
        }

        $validated = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return $validated === false ? $default : $validated;
    }

    private function unexpectedError(
        Response $response,
        string $operation,
        Throwable $error
    ): Response {
        error_log(
            'Failed to ' . $operation . ': '
            . get_class($error) . ': ' . $error->getMessage()
        );

        return $this->jsonResponse($response, [
            'error' => 'Unable to ' . $operation . '.',
        ], 500);
    }

    private function jsonResponse(
        Response $response,
        array $payload,
        int $status = 200
    ): Response {
        $response->getBody()->write((string) json_encode($payload));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
