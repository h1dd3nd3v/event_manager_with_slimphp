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
        $form_data = $request->getParsedBody();

        $uploaded_file = $request->getUploadedFiles();

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

        // if (isset($uploadedFiles['image'])) {
        //     $userDetails['photo_url'] = $utilityFunctions->uploadImage($uploadedFiles['image']);
        // }

        $image_utilities = new ImageUtilities;

        if (isset($uploaded_file['title_image'])) {
            $title_image_link = $image_utilities->upload_image($uploaded_file['title_image']);
        }

        try {
            // create the event
            // write a function to check an image and then upload the image to cloudinary
            EventsModel::create([
                "event_name" => $event_name,
                "start_date" => $start_date,
                "end_date" => $end_date,
                "location" => $location,
                "event_registrati on_link" => $event_registration_link,
                "event_created_by" => $event_created_by,
                "title_image_link" => $title_image_link
            ]);

            $response->getBody()->write(json_encode([
                "success" => true,
                "message" => "Event created successfully"
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

    public function show_events(Request $request, Response $response)
    {
        try {
            $events_data = EventsModel::select([
                "event_name",
                "start_date",
                "end_date",
                "location",
                "event_registration_link",
                "event_created_by",
                "event_updated_by",
                "title_image_link"
            ])->get();

            $response->getBody()->write(json_encode([
                "success" => true,
                "message" => "Events retrieved successfully",
                "data" => $events_data
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

    // write a function to update events and event images
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
}