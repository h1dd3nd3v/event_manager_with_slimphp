# MySankofa Event Manager API

A PHP API built with Slim 4. It provides user registration/login, event management, image uploads to Cloudinary, and Stripe Checkout session creation and record lookup.

## Requirements

- PHP with the extensions required by Composer, including `pdo_mysql` when using MySQL
- Composer
- MySQL or another database supported by Illuminate Database
- Stripe account and API keys for Checkout
- Cloudinary account credentials for event image uploads
- Apache with URL rewriting enabled, or an equivalent web-server configuration

## Setup

1. Install PHP dependencies:

   ```sh
   composer install
   ```

2. Create a `.env` file in the project root. Do not commit this file or put real credentials in documentation.

   ```dotenv
   AUTH_JWT_SECRET=replace-with-a-long-random-secret
   AUTH_JWT_TTL=3600

   DB_DRIVER=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=mysankofa_api
   DB_USERNAME=your_database_user
   DB_PASSWORD=your_database_password
   DB_CHARSET=utf8mb4
   DB_COLLATION=utf8mb4_unicode_ci

   CLOUDINARY_CLOUD_NAME=your_cloud_name
   CLOUDINARY_API_KEY=your_cloudinary_api_key
   CLOUDINARY_API_SECRET=your_cloudinary_api_secret

   STRIPE_SECRET_KEY=sk_test_replace_me
   STRIPE_PRICE_ID=price_replace_me
   DOMAIN=http://localhost:5173
   APP_DEBUG=false
   ```

   `DOMAIN` is the frontend base URL used for Stripe success and cancellation redirects. `APP_DEBUG=true` includes Stripe exception details in Stripe error responses; keep it disabled in production.

3. Create the database and tables expected by the models:

   - `users`: `id`, `username`, `email`, `password`, `created_at`, and `updated_at`.
   - `events`: `id`, `event_name`, `start_date`, `end_date`, `location`, `event_registration_link`, `event_created_by`, `event_updated_by`, `title_image_link`, `created_at`, and `updated_at`.
   - `stripe_events`: use the Stripe event-log table definition for this project. It must include the fields used by `StripeEventsModel`, including a unique `stripe_event_id`, `checkout_session_id`, `payment_status`, `payload` (JSON), `stripe_created_at`, `processing_status`, `processed_at`, and `attempts`. The model disables Eloquent timestamps, so `created_at` and `updated_at` are not required for this table.

   Database schema creation is not currently automated by migrations. Ensure the `stripe_events` table exists before creating or reading Checkout sessions.

4. Configure the web server to route requests to `public/index.php`. The project includes a root `.htaccess` that forwards requests into `public/`; enable Apache rewrite support and confirm the hosting document-root configuration matches your deployment.

## Environment variables

| Variable | Used for |
|---|---|
| `DB_DRIVER`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USERNAME`, `DB_PASSWORD`, `DB_CHARSET`, `DB_COLLATION` | Database connection configured in `src/database.php` |
| `STRIPE_SECRET_KEY` | Stripe API requests |
| `STRIPE_PRICE_ID` | The Price used to create each Checkout session |
| `DOMAIN` | Checkout success and cancellation URL base |
| `APP_DEBUG` | When true, includes Stripe exception details in Stripe error responses |
| `CLOUDINARY_CLOUD_NAME`, `CLOUDINARY_API_KEY`, `CLOUDINARY_API_SECRET` | Event image upload configuration |
| `AUTH_JWT_SECRET`, `AUTH_JWT_TTL` | Authentication-related configuration; login currently does not issue a JWT |

## API

All request and response bodies are JSON except event image uploads, which use `multipart/form-data`.

### Users

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/users` | Register a user. The request expects `first_name`, `last_name`, `username`, `email`, and `password`. |
| `POST` | `/users/login` | Check a username and password. |

### Events

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/events` | Create an event with `event_name`, `start_date`, `end_date`, `location`, `event_registration_link`, and `event_created_by`. Dates use `YYYY-MM-DD`. An optional `title_image` file is uploaded to Cloudinary. |
| `GET` | `/events` | List events. |
| `GET` | `/events/{event_id}` | Retrieve an event. |
| `DELETE` | `/events/{event_id}` | Delete an event. |
| `POST` | `/events/{event_id}` | Update event details. |
| `POST` | `/events/image/{event_id}` | Update an event image. |

### Stripe Checkout

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/checkout/create-checkout-session` | Create a one-time Stripe Checkout session, save its snapshot in `stripe_events`, and return `{ "url": "..." }`. |
| `GET` | `/checkout/checkout-session?session_id=cs_test_...` | Read a saved Checkout Session by ID. Returns status, payment status, amount, and currency. Returns `404` if no saved record exists. |
| `GET` | `/checkout/checkout-events?page=1&per_page=20` | Return paginated Checkout records, excluding the raw Stripe payload and customer email. `per_page` defaults to 20 and is capped at 100. |

The checkout log uses an internal identifier in the form `checkout_session_<session_id>` for the unique `stripe_event_id` column. These records are snapshots created by this application; they are not Stripe webhook events. The Checkout Session lookup reads the database and does not refresh the data from Stripe.

## Runtime notes

- CORS origins are currently configured in `public/index.php`; update the allow list for the frontend’s deployed origin.
- The Stripe checkout read endpoints do not currently use authentication middleware. Restrict access at the application or web-server layer before exposing sensitive operational data.
- PHP errors and Stripe integration failures are written to the server error log.
- Route definitions are in `src/Routes/`; controllers are in `src/Controllers/`; Eloquent models are in `src/Models/`.
