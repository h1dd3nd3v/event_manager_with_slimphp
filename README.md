# MySankofa Event Manager API

A PHP API built with Slim 4. It provides user registration/login, event management, publishing file uploads to Cloudinary, and Stripe Checkout session creation and record lookup.

## Requirements

- PHP with the extensions required by Composer, including `pdo_mysql` when using MySQL and `fileinfo`/`zip` for publishing uploads
- Composer
- MySQL or another database supported by Illuminate Database
- Stripe account and API keys for Checkout
- Cloudinary account credentials for event image and publishing file uploads
- Apache with URL rewriting enabled, or an equivalent web-server configuration

## Setup

1. Install PHP dependencies:

   ```sh
   composer install
   ```

2. Create a `.env` file in the project root. Do not commit this file or put real credentials in documentation.

   ```dotenv
   AUTH_JWT_SECRET=replace-with-at-least-32-random-bytes

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
   STRIPE_EBOOK_PRICE_ID=price_replace_me
   STRIPE_HARDCOVER_PRICE_ID=price_replace_me
   STRIPE_WEBHOOK_SECRET=whsec_replace_me
   DOMAIN=http://localhost:5173
   APP_DEBUG=false
   ```

   `DOMAIN` is the frontend base URL used for Stripe success and cancellation redirects. `APP_DEBUG=true` includes Stripe exception details in Stripe error responses; keep it disabled in production.

3. Create the database and tables expected by the models:

   - `users`: `id`, `username`, `email`, `password`, `created_at`, and `updated_at`.
   - `events`: `id`, `event_name`, `start_date`, `end_date`, `location`, `event_registration_link`, `event_created_by`, `event_updated_by`, `title_image_link`, `created_at`, and `updated_at`.
   - `stripe_events`: use the Stripe event-log table definition for this project. It must include the fields used by `StripeEventsModel`, including a unique `stripe_event_id`, `checkout_session_id`, `payment_status`, `payload` (JSON), `stripe_created_at`, `processing_status`, `processed_at`, and `attempts`. The model disables Eloquent timestamps, so `created_at` and `updated_at` are not required for this table.
   - `orders`: execute [`database/orders.sql`](./database/orders.sql) once to create the book order table.
   - `publishing_entries`: execute [`database/publishing_entries.sql`](./database/publishing_entries.sql) once to create the publishing file catalog.

   Database schema creation is not automated by migrations. Ensure the `stripe_events`, `orders`, and `publishing_entries` tables exist before using their respective endpoints.
   For an existing `publishing_entries` table, execute [`database/publishing_display_image.sql`](./database/publishing_display_image.sql) once to add optional display-image fields.
   For an existing `publishing_entries` table, execute [`database/publishing_name.sql`](./database/publishing_name.sql) once to add and populate the required name field.

4. Configure the web server to route requests to `public/index.php`. The project includes a root `.htaccess` that forwards requests into `public/`; enable Apache rewrite support and confirm the hosting document-root configuration matches your deployment.

## Docker and Render

The Docker image uses PHP 8.4 with Apache and `pdo_mysql`. It serves the Slim app from `public/` and listens on Render's `PORT` (defaulting to `10000` for local runs).

To build and run locally, first create a `.env` file using the setup example above:

```sh
docker build -t event-manager-api .
docker run --rm --env-file .env -p 10000:10000 -e PORT=10000 event-manager-api
```

For Render, create a Blueprint from this repository using `render.yaml`. Set the prompted database, authentication, Stripe, Cloudinary, and `DOMAIN` values in Render's environment settings. Use live Stripe Price IDs for the book's ebook and hardcover prices and the live webhook signing secret for the configured webhook endpoint. The app expects a reachable MySQL database; Render's managed database offering is PostgreSQL, so provide an external MySQL-compatible database and create the tables described above before using the API. Update the CORS allow list in `public/index.php` to include the deployed frontend origin.

## Environment variables

| Variable | Used for |
|---|---|
| `DB_DRIVER`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USERNAME`, `DB_PASSWORD`, `DB_CHARSET`, `DB_COLLATION` | Database connection configured in `src/database.php` |
| `STRIPE_SECRET_KEY` | Stripe API requests |
| `STRIPE_PRICE_ID` | The Price used to create each Checkout session |
| `STRIPE_EBOOK_PRICE_ID`, `STRIPE_HARDCOVER_PRICE_ID` | Stripe Price IDs for the book's ebook and hardcover options |
| `STRIPE_WEBHOOK_SECRET` | Signing secret for the Stripe webhook endpoint; keep it private |
| `DOMAIN` | Checkout success and cancellation URL base |
| `APP_DEBUG` | When true, includes Stripe exception details in Stripe error responses |
| `CLOUDINARY_CLOUD_NAME`, `CLOUDINARY_API_KEY`, `CLOUDINARY_API_SECRET` | Event image upload configuration |
| `AUTH_JWT_SECRET` | HS256 signing key for 24-hour login tokens and authentication middleware; use at least 32 random bytes |

## API

All request and response bodies are JSON except event image and publishing file uploads, which use `multipart/form-data`.

### Users

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/users` | Register a user. The request expects `first_name`, `last_name`, `username`, `email`, and `password`. |
| `POST` | `/users/login` | Check a username and password; returns an `access_token`, `token_type` (`Bearer`), and `expires_in` (seconds). |

### Events

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/events` | Create an event with `event_name`, `start_date`, `end_date`, `location`, `event_registration_link`, and `event_created_by`. Dates use `YYYY-MM-DD`. An optional `title_image` file is uploaded to Cloudinary. |
| `GET` | `/events?page=1&per_page=20` | List events. `page` defaults to 1; `per_page` defaults to 20 and is capped at 100. The response includes pagination metadata (`current_page`, `per_page`, `total`, and `last_page`). |
| `GET` | `/events/{event_id}` | Retrieve an event. |
| `DELETE` | `/events/{event_id}` | Delete an event. |
| `POST` | `/events/{event_id}` | Update event details. |
| `POST` | `/events/image/{event_id}` | Update an event image. |

### Publishing

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/publishing` | Upload one `file` (up to 500 MB), provide a `name`, and optionally upload a `display_image` (JPG, PNG, GIF, or WEBP up to 5 MB) using `multipart/form-data`; save their details and return the new entry. Requires a login token. |
| `GET` | `/publishing?page=1&per_page=20` | Publicly list publishing entries with pagination. `per_page` defaults to 20 and is capped at 100. |
| `GET` | `/publishing/{publishing_id}` | Publicly retrieve a publishing entry by its numeric ID. |
| `POST` | `/publishing/{publishing_id}` | Replace an entry's file using the `file` field in `multipart/form-data`. Optionally change its `name` and/or replace its display image with the `display_image` field; omitting either keeps its current value. Requires a login token. |
| `DELETE` | `/publishing/{publishing_id}` | Delete an entry and attempt to remove its Cloudinary asset. The response indicates whether Cloudinary cleanup succeeded. Requires a login token. |

Documents and e-books are stored as Cloudinary `raw` assets; videos are stored as Cloudinary `video` assets; display images are stored as Cloudinary `image` assets. Supported video extensions are 3G2, 3GP, AVI, FLV, M4V, MKV, MOV, MP4, MPEG, MPG, OGV, WebM, and WMV. Upload endpoints validate the file extension and detected MIME type, and check DOCX, EPUB, and MOBI-family file structure before upload.

The Docker image configures PHP to accept 500 MB uploads. For non-Docker deployments, configure `upload_max_filesize=500M`, `post_max_size=510M`, `max_execution_time=600`, and `max_input_time=600` in PHP.

### Stripe Checkout

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/checkout/create-checkout-session` | Create a one-time Stripe Checkout session, save its snapshot in `stripe_events`, and return `{ "url": "..." }`. |
| `GET` | `/checkout/checkout-session?session_id=cs_test_...` | Read a saved Checkout Session by ID. Returns status, payment status, amount, and currency. Returns `404` if no saved record exists. |
| `GET` | `/checkout/checkout-events?page=1&per_page=20` | Return paginated Checkout records, including customer email and timestamps but excluding the raw Stripe payload. `per_page` defaults to 20 and is capped at 100. |
| `POST` | `/checkout/webhook` | Receive and verify Stripe Checkout payment events. Configure Stripe to send `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`, and `checkout.session.expired` to this endpoint. |

### Book orders

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/orders` | Create a guest order and Stripe Checkout session. JSON body: `{ "format": "ebook" }` or `{ "format": "hardcover" }`. Returns `orderId`, `sessionId`, and the Checkout `url`. Hardcover checkout collects a shipping address using the existing allowed-country list; shipping is included in the configured Stripe Price. |
| `GET` | `/orders?page=1&per_page=20` | List orders. Requires a login token. |
| `GET` | `/orders/{order_id}` | Retrieve an order. Requires a login token. |
| `PATCH` | `/orders/{order_id}/fulfillment` | Set `{ "fulfillment_status": "processing" }`, `"shipped"`, or `"fulfilled"` after payment. Requires a login token. |

Order payment status is set to `paid` only after a verified Stripe webhook confirms payment. E-book fulfillment is manual and can be marked `fulfilled`; hardcover orders can be marked `processing`, `shipped`, then `fulfilled`.

The current order-management routes use the existing JWT middleware, so **any authenticated account can list and manage all orders**. This includes customer email and shipping-address data. Restrict these routes to administrators before production use.

In Stripe Dashboard, create a live-mode webhook endpoint at `https://event-manager-with-slimphp.onrender.com/checkout/webhook`, select the four events above, and set its signing secret as `STRIPE_WEBHOOK_SECRET` in Render. Do not use the webhook signing secret in the browser or commit it to the repository.

The checkout log uses an internal identifier in the form `checkout_session_<session_id>` for the unique `stripe_event_id` column. These records are snapshots created by this application; they are not Stripe webhook events. The Checkout Session lookup reads the database and does not refresh the data from Stripe.

## Runtime notes

- CORS origins are currently configured in `public/index.php`; update the allow list for the frontend’s deployed origin.
- Send login tokens in the `Authorization: Bearer <token>` header. `AuthMiddleware` verifies the token and attaches the matching user model to the request as the `user` attribute; routes are not protected by it until it is explicitly added to their route groups.
- The Stripe checkout read endpoints do not currently use authentication middleware. Restrict access at the application or web-server layer before exposing sensitive operational data.
- PHP errors and Stripe integration failures are written to the server error log.
- Route definitions are in `src/Routes/`; controllers are in `src/Controllers/`; Eloquent models are in `src/Models/`.
