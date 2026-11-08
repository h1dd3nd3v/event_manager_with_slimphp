<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UsersModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class UsersController
{
    public function create_user(Request $request, Response $response)
    {
        // todo: update to only allow admins to create accounts
        // future feature: might want to add user roles and permissions too

        $form_data = $request->getParsedBody();

        // I need to write everything I need out and set it to null if it doesn't exist
        // to prevent the preg_match from going off

        $first_name = $form_data['first_name'] ?? "";
        $last_name = $form_data['last_name'] ?? "";
        $username = $form_data['username'] ?? "";
        $email = $form_data['email'] ?? "";
        $password = $form_data['password'] ?? "";

        if (
            !preg_match('/^[a-zA-Z0-9_]{3,20}$/', $first_name)
            || !preg_match('/^[a-zA-Z0-9_]{3,20}$/', $last_name)
        ) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Username should have "
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{2,19}$/', $username)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Invalid username, it should be at least 2 characters with no spaces"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Password should be at least 8 characters and, have an uppercase, lowercase, number, and special character"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Email is invalid e.g. example@gmail.com"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        try {
            // write a username checker, and email checker
            $username_check = $this->check_username($username);

            if ($username_check == true) {
                throw new Exception("Username is unavailable");
            }

            UsersModel::create([
                'username' => $username,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT)
            ]);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'User account created successfully'
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

    public function login_user(Request $request, Response $response)
    {
        $form_data = $request->getParsedBody();

        $username = $form_data['username'] ?? "";
        $password = $form_data['password'] ?? "";

        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{2,19}$/', $username)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Invalid username, it should be at least 2 characters with no spaces"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Password should be at least 8 characters and, have an uppercase, lowercase, number, and special character"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        try {
            $username_check = $this->check_username($username);

            if ($username_check == false) {
                throw new Exception("Username does not exist, contact administrator for assistance");
            }

            // password checking logic
            $returned_password = UsersModel::select('password')->where('username', '=', $username)->first();

            if (!password_verify($password, $returned_password['password'])) {
                throw new Exception("Incorrect password. Contact administrator for assistance");
            }

            $response->getBody()->write(json_encode([
                "success" => true,
                "message" => "Log in successful"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(200);

            // I need to include the jwt tokens and everything later
        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => $e->getMessage()
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }
    }

    public function update_password (Request $request, Response $response) {
        
    }

    private function check_username(string $username)
    {
        // check the database for the username
        try {
            $user_data = UsersModel::select('username')->where('username', '=', $username)->first();

            if ($user_data == null) {
                return false;
            }

            return true;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}