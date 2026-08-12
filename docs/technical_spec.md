# Technical Specification --- Discount Coupons Management App

> Concise implementation reference for the current project. This
> document defines the technical structure and business rules that
> matter for understanding or maintaining the application; it
> intentionally does not document every page, CSS class or JavaScript
> helper.

## 1. Stack and architecture

The application uses:

-   **Frontend:** server-rendered PHP/HTML, Tailwind CSS, vanilla
    JavaScript.
-   **Backend:** PHP 8.x with MySQLi prepared statements.
-   **Database:** MySQL 8.4.
-   **Authentication:** PHP sessions and password hashing.
-   **Development environment:** Docker Compose.

The local Docker environment separates the PHP/Apache application, MySQL
database, Tailwind watcher and phpMyAdmin. The application code is
bind-mounted into the PHP and Tailwind containers, while MySQL data uses
a persistent Docker volume.

The application is not a SPA. Its main boundary is:

``` text
PHP pages        -> initial HTML and page structure
JavaScript       -> fetch calls, dynamic rendering and UI behaviour
PHP API files    -> validation, business logic and database operations
MySQL            -> persistence and relational integrity
```

## 2. Project structure

``` text
/app
├── api/
│   ├── clients-api.php
│   ├── coupons-api.php
│   ├── discount-rules-api.php
│   ├── dropdown-menus-api.php
│   └── users-api.php
├── assets/
│   ├── css/
│   └── js/
├── includes/
│   └── header.php
├── public/
│   ├── dashboard.php
│   ├── client-create.php
│   ├── client-details.php
│   ├── client-edit.php
│   ├── discount-rule-all.php
│   ├── discount-rule-create.php
│   └── user-create.php
├── config.php
├── index.php
├── login.php
└── logout.php
```

Frontend pages contain the document/UI structure. Data-changing and list
operations are normally performed asynchronously through the `/api`
endpoints.

Login is intentionally an exception: it uses a traditional server-side
POST because authentication is session-based and does not require an
asynchronous UI flow.

## 3. Authentication and authorization model

Users authenticate with company email and password.

On successful login:

-   the password is verified with `password_verify()`;
-   inactive accounts are rejected;
-   the PHP session ID is regenerated;
-   the session stores user ID, name, surname, role and active status.

Roles currently supported:

``` text
admin
user
```

User creation is restricted to active administrators. Internal
application pages/API operations are intended to require an
authenticated active user, with additional role checks where an
operation is privileged.

Passwords are stored only as hashes generated with
`password_hash(..., PASSWORD_DEFAULT)`.

## 4. Data model

### `users`

Purpose: application staff accounts.

Important fields:

``` text
id
name
surname
email
password_hash
created_at
role          ENUM-like domain: admin | user
status        1 = active, 0 = inactive
```

Integrity rule:

-   `email` is unique.

### `client_types`

Lookup table for client category.

Current application values:

``` text
1 -> PR (private person)
2 -> CO (company / employees)
```

### `clients`

Purpose: client master data.

Important fields:

``` text
id
name
type_id
client_code
city
province
status
created_at
```

Integrity rules:

-   `client_code` is unique;
-   `type_id` references `client_types.id`;
-   new clients default to active status.

`client_code` is normalized to uppercase when a client is created and is
intentionally not editable later.

### `service_categories`

Lookup values used by discount rules.

Current values:

``` text
Physiotherapy
Medical
All services
```

### `visit_types`

Lookup values used by discount rules.

Current values:

``` text
First visit only
All visits
```

### `discount_rules`

Purpose: reusable discount definitions.

``` text
id
service_category_id
visit_type_id
discount_perc
created_at
```

Integrity rules:

-   `service_category_id` references `service_categories.id`;
-   `visit_type_id` references `visit_types.id`;
-   the combination
    `(service_category_id, visit_type_id, discount_perc)` is unique;
-   `discount_perc` is validated in the application as an integer from 0
    to 100.

### `coupons`

Purpose: client-specific assignment of a discount rule.

``` text
id
client_id
discount_rule_id
usage_cap       nullable
exp_date
code
status
created_at
```

Integrity rules:

-   `client_id` references `clients.id`;
-   `discount_rule_id` references `discount_rules.id`;
-   `(client_id, discount_rule_id)` is unique;
-   `code` is unique;
-   new coupons default to active status.

### Relationships

``` text
client_types        1 ---- N clients
service_categories  1 ---- N discount_rules
visit_types         1 ---- N discount_rules
clients             1 ---- N coupons
discount_rules      1 ---- N coupons
```

## 5. Core business rules

### Clients

-   Client name: required, maximum 50 characters.
-   Client code: required, 5--15 characters, normalized to uppercase,
    unique and immutable after creation.
-   Client type: must reference an existing client type (`PR` or `CO`).
-   City: required, maximum 70 characters.
-   Province/region code: required, 2--3 characters.
-   Client status can be toggled between active and inactive.
-   Editable profile fields are name, client type, city and province;
    client code remains fixed.

### Discount rules

A rule is defined by:

``` text
service category + visit type + discount percentage
```

The same combination cannot be created twice. Existing rules are treated
as reusable definitions rather than editable records; if different
conditions are needed, a new rule is created.

### Coupons

A coupon always belongs to one client and one discount rule.

The backend validates the real client and rule records before creation
rather than trusting frontend metadata.

Rules for `usage_cap`:

``` text
First visit only + PR -> required, minimum 1
First visit only + CO -> required, minimum 5
All visits             -> stored as NULL
```

Expiration date is mandatory and must be later than the current date.
The selected day is stored with time `23:59:59`.

A client cannot receive a second coupon based on the same discount rule
because of `UNIQUE (client_id, discount_rule_id)`.

## 6. Coupon-code generation

Coupon codes are generated in `coupons-api.php`, not accepted as
authoritative input from JavaScript.

Format:

``` text
[SERVICE_CATEGORY]-[VISIT_TYPE]-[DISCOUNT_PERC]-[CLIENT_CODE]
```

Backend mappings:

``` text
Physiotherapy     -> PHYS
Medical           -> MED
All services      -> ALL
First visit only  -> FIRST
All visits        -> ALL
```

Example:

``` text
MED-ALL-15-NINAFLUFFY
```

The frontend can build a preview for immediate feedback, but the backend
independently retrieves the database values and generates the persisted
code.

## 7. API overview

All dedicated API files return JSON. Successful responses generally
expose `success`, `data` and, when relevant, `message`; failures use
either field-level `input_errors` or a global `error` message.

HTTP status codes are used alongside the JSON response (`200/201`,
`400`, `403`, `404`, `405`, `409`, `500` as appropriate).

### `clients-api.php`

``` text
GET    retrieve one client or a paginated client list
POST   create a client
PATCH  toggle status or edit client information
```

List features:

-   status filter: all / active / inactive;
-   backend search by ID, name, client code, city or province;
-   10 rows per page;
-   active-coupon category summary for dashboard rendering.

PATCH uses an `action` query parameter to distinguish status toggling
from profile editing.

### `discount-rules-api.php`

``` text
GET    retrieve paginated discount rules
POST   create a discount rule
```

GET supports service-category and visit-type filters and returns 10 rows
per page.

POST relies on both backend validation and database FK/UNIQUE
constraints.

### `coupons-api.php`

``` text
GET     retrieve a client's paginated coupons
POST    validate and create a coupon
PATCH   toggle coupon status
DELETE  permanently delete a coupon
```

GET supports active/inactive filtering and returns 5 rows per page.

POST performs the cross-check required for client type, rule type,
`usage_cap`, expiration date and coupon-code generation before
insertion.

DELETE requires both coupon ID and client ID so the deletion is scoped
to the expected client.

### `dropdown-menus-api.php`

``` text
GET    retrieve options for database-backed select elements
```

Accepted `dataType` values are whitelisted:

``` text
service_categories
visit_types
discount_rules
```

For discount rules, the endpoint joins lookup tables and creates a
readable option label from service category, visit type and percentage.
Arbitrary table names cannot be supplied by the frontend.

### `users-api.php`

``` text
POST   create a new application user (admin only)
```

Validates name, surname, company email, password/confirmation and role.
Email uniqueness is checked for user feedback and also protected by a
database UNIQUE constraint.

Password requirements currently include:

-   minimum 8 characters;
-   at least one uppercase character;
-   at least one special character.

## 8. Validation and data integrity

The project uses layered validation:

1.  **Frontend/UI** provides required fields, min/max values,
    conditional controls and immediate feedback where useful.
2.  **PHP backend** validates all values that affect persistence or
    business logic.
3.  **MySQL constraints** provide the final integrity guarantee for
    foreign keys and uniqueness.

Prepared statements are used whenever request data is bound into SQL
queries.

Typical database constraint errors are translated into controlled API
responses rather than exposing raw MySQL errors. In particular:

``` text
1062 -> uniqueness conflict
1452 -> invalid foreign-key reference
```

Field validation errors are returned together so the frontend can mark
all invalid form fields after one request.

## 9. Main application workflows

### Login

``` text
email + password
    -> server-side validation
    -> users lookup
    -> password verification
    -> active-account check
    -> PHP session
    -> dashboard
```

### Create a client

``` text
client form
    -> POST clients-api.php
    -> backend validation
    -> DB FK/UNIQUE checks
    -> client created active by default
```

### Create a discount rule

``` text
service category + visit type + percentage
    -> POST discount-rules-api.php
    -> backend validation
    -> DB FK/UNIQUE checks
    -> reusable rule created
```

### Create a coupon

``` text
client detail page
    -> select discount rule
    -> conditional usage cap + expiration date
    -> POST coupons-api.php
    -> re-read client/rule data from DB
    -> validate business rules
    -> generate coupon code in backend
    -> INSERT coupon
```

### List/search/filter data

Client, coupon and discount-rule tables are populated through GET API
calls. Filtering/searching resets pagination to the first page, and the
backend returns pagination metadata used by the shared pagination logic.

## 10. Deliberate technical boundaries

-   No React/Vue/Node layer: the project remains a conventional PHP
    application enhanced with JavaScript.
-   No ORM: SQL and relational constraints remain explicit.
-   No external booking, billing or coupon-redemption integration.
-   No rule-editing subsystem: rules are reusable definitions and new
    conditions produce new rules.
-   No client-side authority over persisted coupon codes or relational
    IDs.
-   No custom error-code framework beyond HTTP status, field errors and
    global messages.

These boundaries are intentional scope choices rather than missing
framework features.
