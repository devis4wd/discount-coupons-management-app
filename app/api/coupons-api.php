<?php

//Session start
session_start();

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

/*
JSON RESPONSE STRUCTURES RETURNED TO JS

GET — CLIENT COUPON LIST - success:
{
    "success": true,
    "data": [
        {
            "id": int,
            "client_id": int,
            "discount_rule_id": int,
            "code": string,
            "status": int,
            "usage_cap": int|null,
            "exp_date": string,
            "created_at": string,
            "discount_perc": int,
            "service_category_id": int,
            "service_category": string,
            "visit_type_id": int,
            "visit_type": string,
            "coupon_service_type": string
        }
    ],
    "pagination": {
        "current_page": int,
        "rows_per_page": int,
        "total_results": int,
        "total_pages": int
    },
    "error": null
}

POST — CREATE COUPON - success:
{
    "success": true,
    "data": { "id": int },
    "message": string,
    "error": null
}

DELETE / PATCH — DELETE COUPON / TOGGLE COUPON STATUS - success:
{
    "success": true,
    "data": {
        "id": int,
        "client_id": int
    },
    "message": string,
    "error": null
}

Validation error:
{
    "success": false,
    "data": null,
    "input_errors": { "field_error_key": string, ... }
}

Global/request error:
{
    "success": false,
    "data": null,
    "error": string
}
*/

// ----------------------------------------
// API AUTHORIZATION
// Only authenticated users with an active account can access this API.
// ----------------------------------------
if (!isset($_SESSION['user_id'])) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => 'Authentication required.',
    ]);

    exit;
}

if ((int) ($_SESSION['user_status'] ?? 0) !== 1) {

    http_response_code(403);

    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => 'This account is inactive.',
    ]);

    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ----------------------------------------
// GET — CLIENT COUPON LIST
// Retrieves the selected client's paginated coupons, with optional active/inactive filtering and the related discount-rule data.
// ----------------------------------------

if ($method === 'GET') {
    // Every GET request to this API must identify the client whose coupons must be retrieved.
    $client_id = filter_input(
        INPUT_GET,
        'client_id',
        FILTER_VALIDATE_INT
    );

    if ($client_id === false || $client_id === null || $client_id <= 0) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Invalid client ID.',
        ]);

        exit;
    }

    // Read the optional coupon status filter. "all" is used when no specific status has been selected.
    $status_filter = $_GET['status'] ?? 'all';

    $allowed_statuses = [
        'all',
        'active',
        'inactive',
    ];

    if (!in_array($status_filter, $allowed_statuses, true)) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Invalid coupon status filter.',
        ]);

        exit;
    }

    // The client condition is always required.
    $where_conditions = [
        'c.client_id = ?',
    ];

    if ($status_filter === 'active') {
        $where_conditions[] = 'c.status = 1';
    } elseif ($status_filter === 'inactive') {
        $where_conditions[] = 'c.status = 0';
    }

    $where_sql = 'WHERE ' . implode(
        ' AND ',
        $where_conditions
    );


    //PAGINATION: read the requested page from the query string.
    //This table is smaller than the main dashboard tables, so 5 coupon rows are shown per page.
    $current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
    $current_page = ($current_page !== false && $current_page !== null && $current_page > 0)
        ? $current_page
        : 1;

    $rows_per_page = 5;


    //Count how many coupons belong to this client and match the currently selected status filter.
    //The frontend needs this total to calculate how many pagination pages must be shown.
    $coupons_count_sql = "
        SELECT COUNT(*) AS total_results
        FROM coupons AS c
        $where_sql
    ";

    $count_stmt = mysqli_prepare($db_connection, $coupons_count_sql);

    if (!$count_stmt) {
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Unable to prepare coupons count query.',
        ]);

        exit;
    }

    //client_id is always part of the WHERE condition, so it must also be bound to the COUNT query.
    mysqli_stmt_bind_param(
        $count_stmt,
        'i',
        $client_id
    );

    if (!mysqli_stmt_execute($count_stmt)) {
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Unable to count coupons.',
        ]);

        mysqli_stmt_close($count_stmt);
        exit;
    }

    $count_result = mysqli_stmt_get_result($count_stmt);
    $count_row = mysqli_fetch_assoc($count_result);

    $total_results = (int) $count_row['total_results'];
    $total_pages = (int) ceil($total_results / $rows_per_page);

    mysqli_stmt_close($count_stmt);

    //If the requested page no longer exists (for example after deleting the only coupon on the last page),
    //move back to the new last available page instead of returning an empty table.
    if ($total_pages > 0 && $current_page > $total_pages) {
        $current_page = $total_pages;
    }

    //OFFSET tells SQL how many matching coupon rows must be skipped before returning the current page.
    //With 5 rows per page: page 1 skips 0 rows, page 2 skips 5, page 3 skips 10, and so on.
    $offset = ($current_page - 1) * $rows_per_page;


    $coupons_sql = "
        SELECT
            c.id,
            c.client_id,
            c.discount_rule_id,
            c.usage_cap,
            c.exp_date,
            c.code,
            c.status,
            c.created_at,

            dr.discount_perc,

            sc.id AS service_category_id,
            sc.name AS service_category,

            vt.id AS visit_type_id,
            vt.name AS visit_type

        FROM coupons AS c

        INNER JOIN discount_rules AS dr
            ON dr.id = c.discount_rule_id

        INNER JOIN service_categories AS sc
            ON sc.id = dr.service_category_id

        INNER JOIN visit_types AS vt
            ON vt.id = dr.visit_type_id

        $where_sql

        ORDER BY c.created_at DESC

        -- Return only the coupon rows belonging to the requested pagination page.
        LIMIT ? OFFSET ?
    ";

    $stmt = mysqli_prepare($db_connection, $coupons_sql);

    if (!$stmt) {
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Unable to prepare coupons query.',
        ]);

        exit;
    }

    mysqli_stmt_bind_param(
        $stmt,
        'iii',
        $client_id,
        $rows_per_page,
        $offset
    );

    if (!mysqli_stmt_execute($stmt)) {
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Unable to retrieve coupons.',
        ]);

        mysqli_stmt_close($stmt);
        exit;
    }

    $coupons_result = mysqli_stmt_get_result($stmt);

    if (!$coupons_result) {
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Unable to read coupons query result.',
        ]);

        mysqli_stmt_close($stmt);
        exit;
    }

    $coupons = [];

    while ($coupon_row = mysqli_fetch_assoc($coupons_result)) {

        // Convert the DB service-category value into the same frontend identifier already used for coupon SVG icons on the dashboard.
        switch ($coupon_row['service_category']) {
            case 'Medical':
                $coupon_service_type = 'medical';
                break;

            case 'Physiotherapy':
                $coupon_service_type = 'physiotherapy';
                break;

            case 'All services':
                $coupon_service_type = 'all';
                break;

            default:
                $coupon_service_type = 'none';
                break;
        }

        $coupons[] = [
            'id' => (int) $coupon_row['id'],
            'client_id' => (int) $coupon_row['client_id'],
            'discount_rule_id' => (int) $coupon_row['discount_rule_id'],

            'code' => $coupon_row['code'],
            'status' => (int) $coupon_row['status'],

            'usage_cap' => $coupon_row['usage_cap'] === null ? null : (int) $coupon_row['usage_cap'],

            'exp_date' => $coupon_row['exp_date'],
            'created_at' => $coupon_row['created_at'],

            'discount_perc' => (int) $coupon_row['discount_perc'],

            'service_category_id' => (int) $coupon_row['service_category_id'],
            'service_category' => $coupon_row['service_category'],

            'visit_type_id' => (int) $coupon_row['visit_type_id'],
            'visit_type' => $coupon_row['visit_type'],

            'coupon_service_type' => $coupon_service_type,
        ];
    }

    mysqli_stmt_close($stmt);

    echo json_encode([
        'success' => true,
        'data' => $coupons,
        'pagination' => [
            'current_page' => $current_page,
            'rows_per_page' => $rows_per_page,
            'total_results' => $total_results,
            'total_pages' => $total_pages,
        ],
        'error' => null,
    ]);

    exit;
} else if ($method === 'POST') {

    // ----------------------------------------
    // POST — CREATE COUPON
    // Validates the new-coupon form, generates the coupon code and creates the coupon for the selected client.
    // ----------------------------------------

    //store query string values received from JS call
    $new_coupon_client_id = filter_input(INPUT_POST, 'coupon_client_id', FILTER_VALIDATE_INT);
    $new_coupon_rule_id = filter_input(INPUT_POST, 'coupon_discount_rule_id', FILTER_VALIDATE_INT);
    $new_coupon_usage_cap = filter_input(INPUT_POST, 'coupon_usage_cap', FILTER_VALIDATE_INT);
    $new_coupon_exp_date = trim($_POST['coupon_exp_date'] ?? '');

    //collect all input fields errors here to print UI form error messages
    $input_errors = [];

    //Proceed with input data validation

    // coupon_client_id was already validated during the GET request used to load this page.
    // However, it must be validated again here because this POST request performs an INSERT,
    // and users may directly alter either the URL query string or the POST request body.
    //
    // No input field error will be returned for the client ID because the client is not selected
    // through an input field in the New Coupon form. An invalid or nonexistent client will therefore
    // be handled as a global request error.

    // Validate the client ID format before using it in the database query
    if ($new_coupon_client_id === false || $new_coupon_client_id === null || $new_coupon_client_id <= 0) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Invalid client ID.',
        ]);

        exit;
    }

    //As for $new_coupon_usage_cap, may not be required if the selected discount rule has
    // a visit_type_id different from 1 (1 = first visit only).
    // In that case, JS does not submit coupon_usage_cap and FILTER_VALIDATE_INT returns null.
    // PHP will then explicitly set it to null because that coupon does not require a usage cap.
    //
    // If the usage cap is required, its minimum value depends on the client type: 1 for private clients and 5 for companies.
    //
    // JS already uses the data-visit-type-id attribute to enable the field and set its default value, but the same condition must be validated 
    // again in the backend because frontend HTML and submitted values can be manually altered.

    //However, before validating $new_coupon_usage_cap, query the database to:
    // - verify that the client still exists;
    // - verify that the selected discount rule exists;
    // - retrieve the real visit_type_id associated with the rule;
    // - retrieve the real client type used to determine the minimum usage cap.
    // The backend must not trust data-visit-type-id or client type values coming from the frontend.


    // So, let's validate the discount rule ID format before using it in the database query
    if ($new_coupon_rule_id === false || $new_coupon_rule_id === null || $new_coupon_rule_id <= 0) {
        $input_errors['discount_rule_id_err'] = 'Invalid discount rule selected.';
    }

    // Stop here if the discount rule ID is already formally invalid
    if (!empty($input_errors)) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'data' => null,
            'input_errors' => $input_errors,
        ]);

        exit;
    }

    //Now proceed with query as described above, which qill also retrive some more data that won't be used here for validation 
    //but thaw we'll need to build the coupon code later (so it makes sense retrive everything here and spare some code lines):

    $validation_sql = "
     SELECT
        c.id AS client_id,
        c.type_id AS client_type_id,
        c.client_code,

        dr.id AS discount_rule_id,
        dr.discount_perc,
        dr.visit_type_id,

        sc.name AS service_category_name,
        vt.name AS visit_type_name

    FROM clients AS c

    LEFT JOIN discount_rules AS dr
        ON dr.id = ?

    LEFT JOIN service_categories AS sc
        ON sc.id = dr.service_category_id

    LEFT JOIN visit_types AS vt
        ON vt.id = dr.visit_type_id

    WHERE c.id = ?
    ";

    $stmt = mysqli_prepare($db_connection, $validation_sql);

    mysqli_stmt_bind_param(
        $stmt,
        'ii',
        $new_coupon_rule_id,
        $new_coupon_client_id
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    $validation_data = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    // If no row is returned, the client does not exist. 
    // This is handled as a global request error because client ID is not a field that the user can select inside the New Coupon form.
    if (!$validation_data) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Client not found.',
        ]);

        exit;
    }

    // If the client exists but discount_rule_id is null, the selected discount rule does not exist.
    // This error is associated with the discount rule select field in the New Coupon form.
    if ($validation_data['discount_rule_id'] === null) {
        $input_errors['discount_rule_id_err'] = 'Invalid discount rule selected.';
    }

    // If the selected discount rule is for first visits only, usage_cap is mandatory and its minimum value depends on the client type.
    else if ((int) $validation_data['visit_type_id'] === 1) {
        $client_type_id = (int) $validation_data['client_type_id'];

        // Client type 1 = PR: minimum usage cap 1
        // Client type 2 = CO: minimum usage cap 5
        if ($client_type_id === 1) {
            $minimum_usage_cap = 1;
        } else if ($client_type_id === 2) {
            $minimum_usage_cap = 5;
        } else {
            http_response_code(500);

            echo json_encode([
                'success' => false,
                'data' => null,
                'error' => 'Invalid client type.',
            ]);

            exit;
        }

        if ($new_coupon_usage_cap === false || $new_coupon_usage_cap === null || $new_coupon_usage_cap < $minimum_usage_cap) {
            $input_errors['coupon_usage_cap_err'] = "Usage cap for this client type must be at least {$minimum_usage_cap}.";
        }
    } else {
        // If the selected discount rule has a visit_type_id different from 1, any usage cap submitted by the frontend must be ignored and stored as null.
        $new_coupon_usage_cap = null;
    }

    //Validate coupon expiration date
    if ($new_coupon_exp_date === '') {

        $input_errors['exp_date_err'] = 'Expiration date is required.';
    } else {
        // Turn the submitted string into a DateTime object (which is the data type set in dp for the coupons.exp_date column)
        $date = DateTime::createFromFormat('Y-m-d', $new_coupon_exp_date);
        // Check that the submitted date is valid
        if (!$date || $date->format('Y-m-d') !== $new_coupon_exp_date) {
            $input_errors['exp_date_err'] = 'Invalid expiration date.';
        } elseif ($date <= new DateTime('today')) { //prevent user to set already expired date or today date > only future dates are good
            $input_errors['exp_date_err'] = 'Expiration date must be in the future.';
        } else {
            // Coupon expires at the end of the selected day
            $date->setTime(23, 59, 59);
            // Update $new_coupon_exp_date with the new MySQL-ready DATETIME format
            $new_coupon_exp_date = $date->format('Y-m-d H:i:s');
        }
    }

    //If errors occured during input data validation
    if (!empty($input_errors)) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'data' => null,
            'input_errors' => $input_errors, //for frontend input error messages only
        ]);

        exit;
    }


    //If no input errors occurred so far, we need to 
    //A) create the maps of values required to build a coupon code using the available data and build the coupon code according to this pattern: 
    //   [SERVICE_CATEGORY]-[VISIT_TYPE]-[DISCOUNT_PERC]-[CLIENT_CODE] -->  e.g. MEDI-ALL-15-FLUFFYNINA
    //B) procedd with the INSERT query which will upload the new coupon data in db and, contextually, will build the coupon code


    //A) Create maps of values and build the coupon code and then generate the coupn code


    // This mapping is intentionally kept inside this API because these abbreviations are only required when a coupon is generated. 
    // This avoids adding coupon-specific columns or logic to the existing lookup tables and APIs.
    // This mapping also relies on the 'name' value stored in service_categories and visity_types tables rather than id, cause id may change more frequently:
    $service_category_codes = [
        'Physiotherapy' => 'PHYS',
        'Medical' => 'MED',
        'All services' => 'ALL',
    ];

    $visit_type_codes = [
        'First visit only' => 'FIRST',
        'All visits' => 'ALL',
    ];

    //Save variables to build coupon code by using data I already retrieved earlier, during the validation of input fields
    $service_category_name = $validation_data['service_category_name'];
    $visit_type_name = $validation_data['visit_type_name'];

    $service_category_code = $service_category_codes[$service_category_name] ?? null;
    $visit_type_code = $visit_type_codes[$visit_type_name] ?? null;

    // If a database value has no corresponding abbreviation, stop the operation instead of generating an incomplete coupon code.
    if ($service_category_code === null || $visit_type_code === null) {
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Unable to generate coupon code.',
        ]);

        exit;
    }

    $discount_perc = (int) $validation_data['discount_perc'];
    $client_code = strtoupper($validation_data['client_code']);

    //Create coupon code structure based on the [SERVICE_CATEGORY]-[VISIT_TYPE]-[DISCOUNT_PERC]-[CLIENT_CODE] pattern
    $new_coupon_code = implode('-', [
        $service_category_code,
        $visit_type_code,
        $discount_perc,
        $client_code,
    ]);

    //B) Now proceed proceed with new coupon INSERT 
    //NOTE THAT:
    // - I set the coupons.client_id and the coupons.discount_rule_id columns as FOREIGN KEYS in db (no coupon can refer to nonexistent client or discount rule) 
    // - I also set a UNIQUE constraint UNIQUE (client_id, discount_rule_id). So bascially no client can have two coupons based on the same discount rule. 
    // - I'll also need to check what visit_type_id is associated to the selected discount_rule, cause only discount_rules.visit_type_id = 1 (first visit only) will require a usage cap.
    //With the two groups of constraints - UNIQUE and FOREIGN KEYS -  SQL will do the heavy job of cross-checking while executing the INSERT statement. 
    //Those constraints are also the reason why I need to use a atry/catch structure or the mysqli_execute: those constraints can create php exceptions that need to be managed.

    $insert_sql = "
        INSERT INTO 
        coupons (
        client_id, 
        discount_rule_id, 
        usage_cap,
        exp_date,
        code
        ) 
        VALUES (?,?,?,?,?)";

    if ($stmt = mysqli_prepare($db_connection, $insert_sql)) {
        mysqli_stmt_bind_param(
            $stmt,
            "iiiss",
            $new_coupon_client_id,
            $new_coupon_rule_id,
            $new_coupon_usage_cap,
            $new_coupon_exp_date,
            $new_coupon_code
        );

        try {
            mysqli_stmt_execute($stmt);

            http_response_code(201);

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => mysqli_insert_id($db_connection),
                ],
                'message' => 'Coupon created', //for global feedback message only
                'error' => null,
            ]);
        }

        //Iintercept exception errors 
        catch (mysqli_sql_exception $exception) {

            switch ($exception->getCode()) {

                //Violation of UNIQUE constraint: error 1062   
                case 1062:
                    http_response_code(409);

                    echo json_encode([
                        'success' => false,
                        'data' => null,
                        'error' => 'A coupon aleady uses this rule. If you need to set a different expiration date for the same kind of coupon, delete the old one first. ', //for global feedback message only
                    ]);
                    break;

                // Violation of FOREIGN KEY constraints (tells us if at least ONE of the id values do not exist in db)
                case 1452:
                    http_response_code(400);

                    echo json_encode([
                        'success' => false,
                        'data' => null,
                        'error' => 'Nonexistent discount rule or client.', //for global feedback message only
                    ]);
                    break;

                // Every other database error for failed INSERT
                default:
                    http_response_code(500);

                    echo json_encode([
                        'success' => false,
                        'data' => null,
                        'error' => 'Failed to add coupon.', //for global feedback message only
                    ]);
                    break;
            }
        }

        mysqli_stmt_close($stmt);
    }
} elseif ($method === 'DELETE') {

    // ----------------------------------------
    // DELETE — DELETE COUPON
    // Permanently deletes the requested coupon after verifying that it belongs to the client currently being viewed.
    // ----------------------------------------

    // Retrieve and validate both the coupon ID and the client ID.
    // The client ID ensures that the coupon actually belongs to the
    // client profile currently being displayed.
    $coupon_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    $client_id = filter_input(INPUT_GET, 'client_id', FILTER_VALIDATE_INT);

    if ($coupon_id === false || $coupon_id === null || $coupon_id <= 0) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Invalid coupon ID.',
        ]);

        exit;
    }

    if ($client_id === false || $client_id === null || $client_id <= 0) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Invalid client ID.',
        ]);

        exit;
    }

    $delete_sql = "
        DELETE FROM coupons
        WHERE id = ?
        AND client_id = ?
    ";

    $stmt = mysqli_prepare($db_connection, $delete_sql);

    if (!$stmt) {
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Unable to prepare coupon deletion.',
        ]);

        exit;
    }

    mysqli_stmt_bind_param(
        $stmt,
        'ii',
        $coupon_id,
        $client_id
    );

    if (!mysqli_stmt_execute($stmt)) {
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Unable to delete coupon.',
        ]);

        mysqli_stmt_close($stmt);
        exit;
    }

    // No affected row means that the coupon does not exist or it exists but does not belong to the submitted client.
    if (mysqli_stmt_affected_rows($stmt) === 0) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Coupon not found for this client.',
        ]);

        mysqli_stmt_close($stmt);
        exit;
    }

    mysqli_stmt_close($stmt);

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $coupon_id,
            'client_id' => $client_id,
        ],
        'message' => 'Coupon deleted.',
        'error' => null,
    ]);

    exit;
} elseif ($method === 'PATCH') { //for coupons soft delete by using toggle switch buttons

    // ----------------------------------------
    // PATCH — TOGGLE COUPON STATUS
    // Updates a coupon's active/inactive status while verifying that it belongs to the client currently being viewed.
    // ----------------------------------------

    // Retrieve and validate both client id and coupon id.client_id ensures that the coupon
    // belongs to the client profile currently being displayed.
    $coupon_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    $client_id = filter_input(INPUT_GET, 'client_id', FILTER_VALIDATE_INT);

    if (
        $coupon_id === false || $coupon_id === null || $coupon_id <= 0
    ) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Invalid coupon ID.',
        ]);

        exit;
    }

    if ($client_id === false || $client_id === null || $client_id <= 0) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Invalid client ID.',
        ]);

        exit;
    }

    $update_sql = "
        UPDATE coupons
        SET status = 1 - status
        WHERE id = ?
        AND client_id = ?
    ";

    $stmt = mysqli_prepare($db_connection, $update_sql);

    if (!$stmt) {
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Unable to prepare coupon status update.',
        ]);

        exit;
    }

    mysqli_stmt_bind_param(
        $stmt,
        'ii',
        $coupon_id,
        $client_id
    );

    if (!mysqli_stmt_execute($stmt)) {
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Unable to update coupon status.',
        ]);

        mysqli_stmt_close($stmt);
        exit;
    }

    // Zero affected rows means that no coupon matched both IDs.
    if (mysqli_stmt_affected_rows($stmt) === 0) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Coupon not found for this client.',
        ]);

        mysqli_stmt_close($stmt);
        exit;
    }

    mysqli_stmt_close($stmt);

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $coupon_id,
            'client_id' => $client_id,
        ],
        'message' => 'Coupon status updated.',
        'error' => null,
    ]);

    exit;
} else {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => 'Method not allowed.',
    ]);

    exit;
}
