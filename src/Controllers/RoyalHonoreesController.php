<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\RoyalHonoreesModel;
use App\Utilities\ImageUtilities;
use JsonException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

class RoyalHonoreesController
{
    public function create_honoree(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];

        $name = $this->textField($body['name'] ?? null, 'name', 255, $response);
        if ($name instanceof Response) {
            return $name;
        }

        $titles = $this->titlesField($body['titles'] ?? null, $response);
        if ($titles instanceof Response) {
            return $titles;
        }

        $bio = $this->textField($body['bio'] ?? null, 'bio', 5000, $response);
        if ($bio instanceof Response) {
            return $bio;
        }

        $socialLinks = $this->socialLinksField($body['social_links'] ?? null, $response);
        if ($socialLinks instanceof Response) {
            return $socialLinks;
        }

        $profileImage = $this->profileImage($request, $response);
        if ($profileImage instanceof Response) {
            return $profileImage;
        }

        try {
            $honoree = RoyalHonoreesModel::create([
                'name' => $name,
                'titles' => $titles,
                'bio' => $bio,
                'profile_image_url' => $profileImage,
                'social_links' => $socialLinks,
            ]);

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Royal honoree created successfully.',
                'data' => $honoree,
            ], 201);
        } catch (Throwable $error) {
            return $this->unexpectedError($response, 'create royal honoree', $error);
        }
    }

    public function list_honorees(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $page = $this->positiveInteger($query['page'] ?? null, 1);
        $perPage = min($this->positiveInteger($query['per_page'] ?? null, 20), 100);

        try {
            $honorees = RoyalHonoreesModel::query()
                ->orderBy('name')
                ->paginate($perPage, ['*'], 'page', $page);

            return $this->jsonResponse($response, [
                'data' => $honorees->items(),
                'pagination' => [
                    'currentPage' => $honorees->currentPage(),
                    'perPage' => $honorees->perPage(),
                    'total' => $honorees->total(),
                    'lastPage' => $honorees->lastPage(),
                ],
            ]);
        } catch (Throwable $error) {
            return $this->unexpectedError($response, 'list royal honorees', $error);
        }
    }

    public function get_honoree(Request $request, Response $response, array $args): Response
    {
        $honoreeId = $this->positiveInteger($args['honoree_id'] ?? null, 0);
        if ($honoreeId === 0) {
            return $this->jsonResponse($response, [
                'error' => 'A valid royal honoree ID is required.',
            ], 400);
        }

        try {
            $honoree = RoyalHonoreesModel::find($honoreeId);
            if ($honoree === null) {
                return $this->jsonResponse($response, [
                    'error' => 'Royal honoree not found.',
                ], 404);
            }

            return $this->jsonResponse($response, ['data' => $honoree]);
        } catch (Throwable $error) {
            return $this->unexpectedError($response, 'read royal honoree', $error);
        }
    }

    public function update_honoree(Request $request, Response $response, array $args): Response
    {
        $honoreeId = $this->positiveInteger($args['honoree_id'] ?? null, 0);
        if ($honoreeId === 0) {
            return $this->jsonResponse($response, [
                'error' => 'A valid royal honoree ID is required.',
            ], 400);
        }

        try {
            $honoree = RoyalHonoreesModel::find($honoreeId);
        } catch (Throwable $error) {
            return $this->unexpectedError($response, 'read royal honoree for update', $error);
        }

        if ($honoree === null) {
            return $this->jsonResponse($response, [
                'error' => 'Royal honoree not found.',
            ], 404);
        }

        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $updates = [];

        if (array_key_exists('name', $body)) {
            $name = $this->textField($body['name'], 'name', 255, $response);
            if ($name instanceof Response) {
                return $name;
            }
            $updates['name'] = $name;
        }

        if (array_key_exists('titles', $body)) {
            $titles = $this->titlesField($body['titles'], $response);
            if ($titles instanceof Response) {
                return $titles;
            }
            $updates['titles'] = $titles;
        }

        if (array_key_exists('bio', $body)) {
            $bio = $this->textField($body['bio'], 'bio', 5000, $response);
            if ($bio instanceof Response) {
                return $bio;
            }
            $updates['bio'] = $bio;
        }

        if (array_key_exists('social_links', $body)) {
            $socialLinks = $this->socialLinksField($body['social_links'], $response);
            if ($socialLinks instanceof Response) {
                return $socialLinks;
            }
            $updates['social_links'] = $socialLinks;
        }

        $profileImage = $this->profileImage($request, $response);
        if ($profileImage instanceof Response) {
            return $profileImage;
        }
        if ($profileImage !== null) {
            $updates['profile_image_url'] = $profileImage;
        }

        if ($updates === []) {
            return $this->jsonResponse($response, [
                'error' => 'Provide at least one honoree field to update.',
            ], 400);
        }

        try {
            if (!$honoree->fill($updates)->save()) {
                throw new \RuntimeException('The royal honoree was not saved.');
            }

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Royal honoree updated successfully.',
                'data' => $honoree,
            ]);
        } catch (Throwable $error) {
            return $this->unexpectedError($response, 'update royal honoree', $error);
        }
    }

    public function delete_honoree(Request $request, Response $response, array $args): Response
    {
        $honoreeId = $this->positiveInteger($args['honoree_id'] ?? null, 0);
        if ($honoreeId === 0) {
            return $this->jsonResponse($response, [
                'error' => 'A valid royal honoree ID is required.',
            ], 400);
        }

        try {
            $honoree = RoyalHonoreesModel::find($honoreeId);
            if ($honoree === null) {
                return $this->jsonResponse($response, [
                    'error' => 'Royal honoree not found.',
                ], 404);
            }

            if (!$honoree->delete()) {
                throw new \RuntimeException('The royal honoree was not deleted.');
            }

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Royal honoree deleted successfully.',
                'id' => $honoreeId,
            ]);
        } catch (Throwable $error) {
            return $this->unexpectedError($response, 'delete royal honoree', $error);
        }
    }

    private function profileImage(
        Request $request,
        Response $response
    ): string|null|Response {
        $uploadedFiles = $request->getUploadedFiles();
        $image = $uploadedFiles['profile_image'] ?? null;
        if ($image === null || (
            $image instanceof UploadedFileInterface
            && $image->getError() === UPLOAD_ERR_NO_FILE
        )) {
            return null;
        }

        if (!$image instanceof UploadedFileInterface) {
            return $this->jsonResponse($response, [
                'error' => 'profile_image must contain an image file.',
            ], 400);
        }

        if ($image->getError() !== UPLOAD_ERR_OK) {
            $status = in_array($image->getError(), [
                UPLOAD_ERR_INI_SIZE,
                UPLOAD_ERR_FORM_SIZE,
            ], true) ? 413 : 400;

            return $this->jsonResponse($response, [
                'error' => $status === 413
                    ? 'Profile images must not exceed 5 MB.'
                    : 'The profile image could not be received.',
            ], $status);
        }

        $size = $image->getSize();
        if ($size === null || $size <= 0) {
            return $this->jsonResponse($response, [
                'error' => 'The profile image is empty.',
            ], 400);
        }
        if ($size > 5 * 1024 * 1024) {
            return $this->jsonResponse($response, [
                'error' => 'Profile images must not exceed 5 MB.',
            ], 413);
        }

        try {
            $imageUrl = (new ImageUtilities())->upload_image($image);
            if (!is_string($imageUrl) || $imageUrl === '') {
                $message = is_array($imageUrl)
                    ? ($imageUrl['errorMessage'] ?? 'Unknown image upload error')
                    : 'The image utility returned an invalid URL.';
                error_log('Royal honoree profile image upload failed: ' . $message);

                return $this->jsonResponse($response, [
                    'error' => 'Unable to upload the profile image.',
                ], 500);
            }

            return $imageUrl;
        } catch (Throwable $error) {
            error_log(
                'Royal honoree profile image upload failed: '
                . get_class($error) . ': ' . $error->getMessage()
            );

            return $this->jsonResponse($response, [
                'error' => 'Unable to upload the profile image.',
            ], 500);
        }
    }

    private function textField(
        $value,
        string $field,
        int $maxLength,
        Response $response
    ): string|Response {
        if (
            !is_string($value)
            || ($text = trim($value)) === ''
            || preg_match('/\A.{1,' . $maxLength . '}\z/us', $text) !== 1
        ) {
            return $this->jsonResponse($response, [
                'error' => $field . ' is required and must be at most '
                    . $maxLength . ' characters.',
            ], 400);
        }

        return $text;
    }

    private function titlesField($value, Response $response): array|Response
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return $this->jsonResponse($response, [
                    'error' => 'titles must be a JSON array of strings.',
                ], 400);
            }
        }

        if (!is_array($value) || $value === [] || array_values($value) !== $value) {
            return $this->jsonResponse($response, [
                'error' => 'titles must be a non-empty JSON array of strings.',
            ], 400);
        }

        $titles = [];
        foreach ($value as $title) {
            if (!is_string($title) || ($title = trim($title)) === '' || strlen($title) > 200) {
                return $this->jsonResponse($response, [
                    'error' => 'Each title must be a non-empty string of at most 200 characters.',
                ], 400);
            }
            $titles[] = $title;
        }

        return $titles;
    }

    private function socialLinksField($value, Response $response): array|null|Response
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return $this->jsonResponse($response, [
                    'error' => 'social_links must be a JSON object mapping platforms to URLs.',
                ], 400);
            }
        }
        if (!is_array($value)) {
            return $this->jsonResponse($response, [
                'error' => 'social_links must be a JSON object mapping platforms to URLs.',
            ], 400);
        }

        $socialLinks = [];
        foreach ($value as $platform => $url) {
            if (
                !is_string($platform)
                || preg_match('/\A[a-zA-Z][a-zA-Z0-9_-]{0,49}\z/', $platform) !== 1
                || !is_string($url)
                || strlen($url) > 2048
                || filter_var($url, FILTER_VALIDATE_URL) === false
                || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            ) {
                return $this->jsonResponse($response, [
                    'error' => 'Social links must map platform names to valid HTTP or HTTPS URLs.',
                ], 400);
            }

            $socialLinks[$platform] = $url;
        }

        return $socialLinks;
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
