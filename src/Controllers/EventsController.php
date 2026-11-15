<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\EventsModel;
use DateTime;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;
use App\Utilities\ImageUtilities;

class EventsController
{

    public function create_event(Request $request, Response $response)
    {
        $form_data = $request->getParsedBody() ?? [];
        $uploaded_files = $request->getUploadedFiles();

        // Retrieve and sanitize inputs
        $event_name = trim($form_data['event_name'] ?? '');
        $start_date_input = trim($form_data['start_date'] ?? '');
        $end_date_input = trim($form_data['end_date'] ?? '');

        $location = trim($form_data['location'] ?? '');
        $event_registration_link = trim($form_data['event_registration_link'] ?? '');
        $event_created_by = $form_data['event_created_by'] ?? '';

        // Initialize image link
        $title_image_link = null;

        // Helper function for JSON responses
        $json_response = function ($data, $status = 200) use ($response) {
            $response->getBody()->write(json_encode($data));

            return $response
                ->withHeader("Content-Type", "application/json")
                ->withStatus($status);
        };

        // Validate event name
        if (
            empty($event_name) ||
            !preg_match("/^[a-zA-Z0-9 @'&.,()\-:]+$/", $event_name)
        ) {
            return $json_response([
                "errors" => true,
                "message" => "Invalid event name."
            ], 400);
        }

        // Validate start date
        $start_date = DateTime::createFromFormat('!Y-m-d', $start_date_input);

        if (!$start_date || $start_date->format('Y-m-d') !== $start_date_input) {
            return $json_response([
                "errors" => true,
                "message" => "Invalid start date."
            ], 400);
        }

        // Validate end date
        $end_date = DateTime::createFromFormat('!Y-m-d', $end_date_input);

        if (!$end_date || $end_date->format('Y-m-d') !== $end_date_input) {
            return $json_response([
                "errors" => true,
                "message" => "Invalid end date."
            ], 400);
        }

        // Ensure end date is not before start date
        if ($end_date < $start_date) {
            return $json_response([
                "errors" => true,
                "message" => "End date cannot be earlier than start date."
            ], 400);
        }

        // Validate registration URL (if provided)
        if (
            !empty($event_registration_link) &&
            !filter_var($event_registration_link, FILTER_VALIDATE_URL)
        ) {
            return $json_response([
                "errors" => true,
                "message" => "Invalid event registration link."
            ], 400);
        }

        try {

            // Handle title image upload
            if (isset($uploaded_files['title_image'])) {

                $image_file = $uploaded_files['title_image'];

                // Check upload status
                if ($image_file->getError() !== UPLOAD_ERR_OK) {
                    return $json_response([
                        "errors" => true,
                        "message" => "Image upload failed."
                    ], 400);
                }

                // Validate image size (maximum 5MB)
                if ($image_file->getSize() > 5 * 1024 * 1024) {
                    return $json_response([
                        "errors" => true,
                        "message" => "Image size must not exceed 5MB."
                    ], 400);
                }

                // Validate MIME type
                $allowed_types = [
                    'image/jpeg',
                    'image/png',
                    'image/webp'
                ];

                if (!in_array($image_file->getClientMediaType(), $allowed_types, true)) {
                    return $json_response([
                        "errors" => true,
                        "message" => "Only JPG, PNG and WEBP images are allowed."
                    ], 400);
                }

                // Upload image to Cloudinary
                $image_utilities = new ImageUtilities;

                $title_image_link = $image_utilities->upload_image($image_file);

                if (!$title_image_link) {
                    return $json_response([
                        "errors" => true,
                        "message" => "Unable to upload event image."
                    ], 500);
                }
            }

            // Create event
            $event = EventsModel::create([
                "event_name" => $event_name,
                "start_date" => $start_date->format('Y-m-d'),
                "end_date" => $end_date->format('Y-m-d'),
                "location" => $location,
                "event_registration_link" => $event_registration_link,
                "event_created_by" => $event_created_by,
                "title_image_link" => $title_image_link
            ]);

            return $json_response([
                "success" => true,
                "message" => "Event created successfully.",
                "data" => [
                    "event_id" => $event->id,
                    "event_name" => $event->event_name,
                    "title_image_link" => $event->title_image_link
                ]
            ], 201);

        } catch (Exception $e) {

            // Log the actual exception instead of exposing it
            error_log("Event creation error: " . $e->getMessage());

            return $json_response([
                "errors" => true,
                "message" => "An error occurred while creating the event."
            ], 500);
        }
    }


    public function show_events(Request $request, Response $response)
    {
        $query = $request->getQueryParams();
        $page = $this->positiveInteger($query['page'] ?? null, 1);
        $per_page = min($this->positiveInteger($query['per_page'] ?? null, 20), 100);

        try {
            $events = EventsModel::select([
                    "id",
                    "event_name",
                    "start_date",
                    "end_date",
                    "location",
                    "event_registration_link",
                    "event_created_by",
                    "event_updated_by",
                    "title_image_link"
                ])
                ->orderByDesc("id")
                ->paginate($per_page, ["*"], "page", $page);

            $response->getBody()->write(json_encode([
                "success" => true,
                "message" => "Events retrieved successfully",
                "data" => $events->items(),
                "pagination" => [
                    "current_page" => $events->currentPage(),
                    "per_page" => $events->perPage(),
                    "total" => $events->total(),
                    "last_page" => $events->lastPage()
                ]
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => $e->getMessage()
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }
    }

    private function positiveInteger($value, int $default): int
    {
        if (!is_string($value) && !is_int($value)) {
            return $default;
        }

        $validated = filter_var($value, FILTER_VALIDATE_INT, [
            "options" => ["min_range" => 1]
        ]);

        return $validated === false ? $default : $validated;
    }

    public function get_event(Request $request, Response $response, array $args)
    {
        $event_id = $args['event_id'] ?? '';

        try {
            $event_data = EventsModel::select([
                "event_name",
                "start_date",
                "end_date",
                "location",
                "event_registration_link",
                "event_created_by",
                "event_updated_by",
                "title_image_link"
            ])->where("id", '=', $event_id)
                ->first();

            $response->getBody()->write(json_encode([
                "success" => true,
                "message" => "Events retrieved successfully",
                "data" => $event_data
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => $e->getMessage()
            ]));
            return $response->withHeader("Content-Type", "application/json");
        }
    }

    public function delete_event(Request $request, Response $response, array $args)
    {

        $event_id = $args['event_id'] ?? '';

        if (!filter_var($event_id, FILTER_VALIDATE_INT) || $event_id <= 0) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Invalid event ID."
            ]));

            return $response->withHeader("Content-Type", "application/json")
                ->withStatus(400);
        }

        try {

            $event = EventsModel::find($event_id);

            if (!$event) {
                throw new Exception("Event not found.");
            }

            $event->delete();

            $response->getBody()->write(json_encode([
                "success" => true,
                "message" => "Event deleted successfully."
            ]));

            return $response->withHeader("Content-Type", "application/json")
                ->withStatus(200);

        } catch (Exception $e) {

            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => $e->getMessage()
            ]));

            return $response->withHeader("Content-Type", "application/json")
                ->withStatus(500);
        }
    }
    public function update_event_details(Request $request, Response $response, array $args)
    {
        $form_data = $request->getParsedBody();

        $event_id = $args['event_id'] ?? '';

        if (!filter_var($event_id, FILTER_VALIDATE_INT) || $event_id <= 0) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Invalid event ID."
            ]));

            return $response->withHeader("Content-Type", "application/json")
                ->withStatus(400);
        }

        $event_name = trim($form_data['event_name'] ?? '');
        $start_date_input = $form_data['start_date'] ?? '';
        $end_date_input = $form_data['end_date'] ?? '';

        $location = trim($form_data['location'] ?? '');
        $event_registration_link = trim($form_data['event_registration_link'] ?? '');
        $event_created_by = $form_data['event_created_by'] ?? '';

        // Validate event name
        if (!preg_match("/^[a-zA-Z0-9 @'-]+$/", $event_name)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Invalid event name."
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        // Validate start date
        $start_date = DateTime::createFromFormat('!Y-m-d', $start_date_input);

        if (!$start_date || $start_date->format('Y-m-d') !== $start_date_input) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Invalid start date."
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        // Validate end date
        $end_date = DateTime::createFromFormat('!Y-m-d', $end_date_input);

        if (!$end_date || $end_date->format('Y-m-d') !== $end_date_input) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Invalid end date."
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        // Ensure end date is not before start date
        if ($end_date < $start_date) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "End date cannot be earlier than start date."
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        try {
            $event = EventsModel::find($event_id);

            if (!$event) {
                throw new Exception("Event not found");
            }

            EventsModel::where("id", "=", $event_id)
                ->update([
                    "event_name" => $event_name,
                    "start_date" => $start_date,
                    "end_date" => $end_date,
                    "location" => $location,
                    "event_registration_link" => $event_registration_link,
                    "event_created_by" => $event_created_by,
                    "title_image_link" => "https://www.google.com"
                ]);

            $response->getBody()->write(json_encode([
                "success" => true,
                "message" => "Event updated successfully"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(200);

        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => $e->getMessage()
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }
    }

    public function update_event_image(Request $request, Response $response, array $args)
    {
        $form_data = $request->getParsedBody();

        $event_id = $args['event_id'] ?? '';

        if (!filter_var($event_id, FILTER_VALIDATE_INT) || $event_id <= 0) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Invalid event ID."
            ]));

            return $response->withHeader("Content-Type", "application/json")
                ->withStatus(400);
        }

        $uploaded_file = $request->getUploadedFiles();

        $image_utilities = new ImageUtilities;

        if (isset($uploaded_file['title_image'])) {
            $title_image_link = $image_utilities->upload_image($uploaded_file['title_image']);
        }

        try {
            $event = EventsModel::find($event_id);

            if (!$event) {
                throw new Exception("Event not found");
            }

            EventsModel::where("id", "=", $event_id)
                ->update([
                    "title_image_link" => $title_image_link
                ]);

            $response->getBody()->write(json_encode([
                "success" => true,
                "message" => "Event updated successfully"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => $e->getMessage()
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }
    }
}