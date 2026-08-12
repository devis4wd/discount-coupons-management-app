<?php

//Session start
session_start();

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

/*
JSON RESPONSE STRUCTURES RETURNED TO JS

GET — SINGLE CLIENT INFO (?id=...) - success:
{
    "success": true,
    "data": {
        "name": string,
        "type_id": int,
        "client_type": string,
        "status": int,
        "client_status": string,
        "client_code": string,
        "city": string,
        "province": string
    },
    "error": null
}

GET — CLIENT LIST - success:
{
    "success": true,
    "data": [
        {
            "id": int,
            "name": string,
            "type_id": int,
            "client_type": string,
            "status": int,
            "client_code": string,
            "city": string,
            "province": string,
            "created_at": string,
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

POST — CREATE CLIENT - success:
{
    "success": true,
    "data": { "id": int },
    "message": string,
    "error": null
}

PATCH — TOGGLE CLIENT STATUS / EDIT CLIENT INFO - success:
{
    "success": true,
    "data": { "id": int },
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

$method = $_SERVER['REQUEST_METHOD'];

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


// ----------------------------------------
// GET — CLIENT INFO / CLIENT LIST
// Retrieves either one single client by ?id (CASE1) or the paginated client list used by the dashboard, including filters and related coupon data (CASE2).
// ----------------------------------------

if ($method === 'GET') {

    // GET — SINGLE CLIENT INFO (CASE1)
    // Retrieves the client identified by ?id for the client-details and client-edit pages.
    if (array_key_exists('id', $_GET)) {

        // Read the id parameter, make sure it is a valid integer and save it into the $client_id variable.
        $client_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        // Immediately Reject the request if the id is missing, invalid, or not a positive integer.
        if ($client_id === false || $client_id === null || $client_id <= 0) {

            http_response_code(400);

            echo json_encode([
                'success' => false,
                'data' => null,
                'error' => 'Invalid client ID',
            ]);

            exit;
        }

        // If the ID saved in $client_id is valid, proceed to retrieve the requested client from the database.
        $clients_info_sql = "SELECT 
        name, 
        type_id, 
        status, 
        client_code, 
        city, 
        province,

        ct.type AS client_type,

        -- I need to tell SQL how to interpret 1/0 for status since it's not handled by aspecific table
        CASE
            WHEN cl.status = 1 THEN 'Active'
            WHEN cl.status = 0 THEN 'Inactive'
        END AS client_status

        FROM clients as cl 

        INNER JOIN client_types AS ct
        ON ct.id = cl.type_id

        WHERE cl.id = ?
        ";

        $stmt = mysqli_prepare($db_connection, $clients_info_sql);

        if (!$stmt) {
            // If the SQL query could not be prepared (syntax error, invalid query, etc.)
            http_response_code(500);

            echo json_encode([
                'success' => false,
                'data' => null,
                'error' => 'Unable to prepare client info query',
            ]);

            exit;
        }

        mysqli_stmt_bind_param($stmt, 'i', $client_id);


        if (!mysqli_stmt_execute($stmt)) {
            //Here we manage server request error and define the Error Response Body of the JSON {
            http_response_code(500);

            echo json_encode([
                'success' => false,
                'data' => null,
                'error' => 'Unable to retrieve info for this client',
            ]);

            exit;
        }

        // Retrieve the rows returned by the executed SELECT query.
        $client_result = mysqli_stmt_get_result($stmt);

        if (!$client_result) {
            http_response_code(500);

            echo json_encode([
                'success' => false,
                'data' => null,
                'error' => 'Unable to read client info query result',
            ]);

            mysqli_stmt_close($stmt);
            exit;
        }

        $client_row = mysqli_fetch_assoc($client_result);

        if (!$client_row) {
            http_response_code(404);

            echo json_encode([
                'success' => false,
                'data' => null,
                'error' => 'Client not found',
            ]);

            mysqli_stmt_close($stmt);
            exit;
        }

        $client_info = [
            'name' => $client_row['name'],
            'type_id' => (int) $client_row['type_id'],
            'client_type' => $client_row['client_type'],
            'status' => (int) $client_row['status'],
            'client_status' => $client_row['client_status'],
            'client_code' => $client_row['client_code'],
            'city' => $client_row['city'],
            'province' => $client_row['province'],
        ];

        mysqli_stmt_close($stmt);

        echo json_encode([
            'success' => true,
            'data' => $client_info,
            'error' => null,
        ]);

        exit;
    } // end of CASE 1

    else {
        // GET — CLIENT LIST (CASE2)
        // Retrieves the paginated client list for the dashboard, applying the optional status and search filters.

        // The result will be a modular WHERE (for status filter) + AND (for search filter) condition to add to the GET SQL query below which gives the list of clients to 
        // show in dashboard woth all the relavant data linked to the clients db table. The SQL filter will look like this:
        // WHERE cl.status = 1
        // AND (
        // cl.name LIKE ?
        // OR cl.client_code LIKE ?
        // )

        //First, create an array of conditions that  will be used to create the modular SQL query filter $where_sql below
        $where_conditions = []; // array that stores the status and search conditions


        // Second, add status condition 
        $status_filter = $_GET['status'] ?? 'all'; //'all' is the default value if no more specific filters are selected in the dashboard dropdown filter menu

        //Define the only status accepted (the ones the user can choose on the frontend dashboard.php page)
        $allowed_statuses = ['all', 'active', 'inactive'];

        if (!in_array($status_filter, $allowed_statuses, true)) {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'data' => null,
                'error' => 'Invalid status filter selected on Dashboard',
            ]);

            exit;
        }

        //C-Based on the value of status_filter, this will return a condition to use in the modular SQL filter where_conditions

        if ($status_filter === 'active') {
            $where_conditions[] = 'cl.status = 1';
        } elseif ($status_filter === 'inactive') {
            $where_conditions[] = 'cl.status = 0';
        }


        //Let's now do the same for the value stored in ?search= query string
        $search_filter = trim($_GET['search'] ?? '');

        if ($search_filter !== '') {
            $where_conditions[] = '(
                cl.name LIKE ?
                OR cl.client_code LIKE ?
                OR cl.city LIKE ?
                OR cl.province LIKE ?
                OR CAST(cl.id AS CHAR) LIKE ?
            )';

            //Define search patter (how to pass the searched term to the sql query). 
            $search_pattern = '%' . $search_filter . '%'; // equals to something like: '%clientname%'
        }

        //And then use the $where_condition array of conditions we just created to create the modular sql condition to be added to the GET query below
        $where_sql = '';

        if ($where_conditions !== []) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_conditions); //take the two conditions and create'WHERE cond 1 AND cond 2'
        }


        //PAGINATION: read the requested page from the query string.
        //The number of rows per page is fixed by the backend (currently set on 10) so every dashboard request follows the same rule.
        $current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
        $current_page = ($current_page !== false && $current_page !== null && $current_page > 0)
            ? $current_page
            : 1; // fallback: show first page by defualt

        $rows_per_page = 10;


        //Before retrieving only one page of clients, count how many clients match the current status/search filters.
        //This total is needed to calculate how many pages the frontend pagination must show.
        $clients_count_sql = "
            SELECT COUNT(*) AS total_results
            FROM clients AS cl
            $where_sql
        ";

        $count_stmt = mysqli_prepare($db_connection, $clients_count_sql);

        if (!$count_stmt) {
            http_response_code(500);

            echo json_encode([
                'success' => false,
                'data' => null,
                'error' => 'Unable to prepare clients count query',
            ]);

            exit;
        }

        //The COUNT query uses the same search placeholders as the main query, so the same search pattern must be bound here too.
        if ($search_filter !== '') {
            mysqli_stmt_bind_param(
                $count_stmt,
                'sssss',
                $search_pattern,
                $search_pattern,
                $search_pattern,
                $search_pattern,
                $search_pattern
            );
        }

        if (!mysqli_stmt_execute($count_stmt)) {
            http_response_code(500);

            echo json_encode([
                'success' => false,
                'data' => null,
                'error' => 'Unable to count clients',
            ]);

            mysqli_stmt_close($count_stmt);
            exit;
        }

        $count_result = mysqli_stmt_get_result($count_stmt);
        $count_row = mysqli_fetch_assoc($count_result);

        $total_results = (int) $count_row['total_results'];
        //get the number of total pages required to show all he records found in db. Ceil() to round up the nummber (e.g. 34 records found, 10 per page = 4 pages total, not just 3)
        $total_pages = (int) ceil($total_results / $rows_per_page);

        mysqli_stmt_close($count_stmt);

        //If the current page no longer exists (for example after deleting the last row of the last page),
        //move back to the new last available page instead of returning an empty table.
        if ($total_pages > 0 && $current_page > $total_pages) {
            $current_page = $total_pages;
        }

        //OFFSET tells SQL how many matching rows it must skip before starting to return rows.
        //Example with 10 rows per page: page 1 skips 0, page 2 skips 10 (first 10 records already shown in page 1), page 3 skips 20, etc.
        $offset = ($current_page - 1) * $rows_per_page;


        //Now the SQL query to retrieve clients to show in dashbard table, with all the related info we need to show
        $clients_info_sql = "SELECT
                -- Basic client information - 'cl' is the alias used for the clients table
                cl.id,
                cl.name,
                cl.type_id,
                cl.status,
                cl.client_code,
                cl.city,
                cl.province,
                cl.created_at,
                -- Also retrive the type value (1 = PR, 2 = CO) from the client_types table (alias cl) and save the value as client_type (friendly name) 
                ct.type AS client_type,

                -- Also retrive the client's coupon service categories. CASE checks all valid coupons belonging to the client and returns a final value (Medical / Physiotherapy / All / none).
                CASE
                -- Case 1: the client has coupons for both service categories > returns 1 = Medical or All, 0 = every other category)
                -- MAX() is required cause one client may have multiple coupons associated to itself (so if at least one row contains 1, MAX() returns 1)
                    WHEN
                        MAX(
                            CASE
                                WHEN sc.name IN ('Medical', 'All services') THEN 1
                                ELSE 0
                            END
                        ) = 1

                        AND

                        -- second inner CASE performs the same check for Physiotherapy.
                        -- Category All is included in both checks because an All coupon applies to both Medical and Physiotherapy.
                        MAX(
                            CASE
                                WHEN sc.name IN ('Physiotherapy', 'All services') THEN 1
                                ELSE 0
                            END
                        ) = 1

                    THEN 'all'

                    -- Case 2: only Medical coupons were found
                    WHEN MAX(
                        CASE
                            WHEN sc.name = 'Medical' THEN 1
                            ELSE 0
                        END
                    ) = 1

                    THEN 'medical'
                    
                    -- Case 3: only Physiotherapy coupons were found
                    WHEN MAX(
                        CASE
                            WHEN sc.name = 'Physiotherapy' THEN 1
                            ELSE 0
                        END
                    ) = 1

                    THEN 'physiotherapy'

                    -- Case 4: no valid coupon was found. This applies when
                    -- - the client has no coupons;
                    -- - all coupons are inactive;
                    -- - all coupons are expired;
                    -- - no valid service category is associated with them.
                    ELSE 'none'
                END AS coupon_service_type

                FROM clients AS cl -- clients (cl) is the main table since every <table> row must show one client's info
                
                -- Join the client_types table (INNER join is fine cause every client has a valid client type_id)
                -- - clients.type_id (as client_types) contains the foreign key.
                -- - client_types.id contains the corresponding primary key.
                INNER JOIN client_types AS ct
                    ON ct.id = cl.type_id

                -- Join the coupons table (LEFT join cause this way the client will appear in the result even when it has no coupons.
                LEFT JOIN coupons AS c
                    ON c.client_id = cl.id
                    AND c.status = 1 -- only active coupons are considered
                    -- Only coupons that have not expired are considered.
                    AND (
                        c.exp_date IS NULL -- NULL means the coupon has no expiration
                        OR c.exp_date >= CURRENT_TIMESTAMP
                    )

                -- Join the discount_rules table (each coupon has a discount_rule_id which identifies the discount rule applied to the coupon)
                LEFT JOIN discount_rules AS dr
                    ON dr.id = c.discount_rule_id

                -- Join the service_categories table (each discount rule contains service_category_id, which, in the service_categories table, corresponds to a service name (Medical / Physiotherapy / All) that we need to retrive.
                LEFT JOIN service_categories AS sc
                    ON sc.id = dr.service_category_id

                -- Here we add the WHERE (AND) filter we created. It'll be relevant only if the user filtered the client list using the status dropdown menu and/or the searchbar
                -- Otherwise this vaiable will contain an empty sting and itll be ignored
                $where_sql


                -- Group all retrieved coupon rows by client (1 client can have multiple coupons but we don't need 1 row for every coupon here)
                -- GROUP BY combines all coupon rows belonging to the same client, and MAX() determines which service categories are present among those coupons.
                GROUP BY
                    cl.id,
                    cl.name,
                    cl.type_id,
                    cl.status,
                    cl.client_code,
                    cl.city,
                    cl.province,
                    cl.created_at,
                    ct.type
                -- The query must order clients from the most recently added one to the oldest
                ORDER BY cl.created_at DESC

                -- Pagination: return only the rows belonging to the requested page.
                LIMIT ? OFFSET ?";


        // Prepare the SQL query.
        // Unlike mysqli_query(), mysqli_prepare() does not execute the query yet.
        // It only prepares it and keeps the ? placeholders ready to receive values.
        $stmt = mysqli_prepare($db_connection, $clients_info_sql);

        if (!$stmt) {
            // If the SQL query could not be prepared (syntax error, invalid query, etc.)

            http_response_code(500);

            echo json_encode([
                'success' => false,
                'data' => null,
                'error' => 'Unable to prepare clients query',
            ]);

            exit;
        }

        //Bind the values used by the main query.
        //If a search term exists, the five LIKE placeholders come first; LIMIT and OFFSET are always the last two integer placeholders.
        if ($search_filter !== '') {
            mysqli_stmt_bind_param(
                $stmt,
                'sssssii',
                $search_pattern,   // cl.name LIKE ?
                $search_pattern,   // cl.client_code LIKE ?
                $search_pattern,   // cl.city LIKE ?
                $search_pattern,   // cl.province LIKE ?
                $search_pattern,   // CAST(cl.id AS CHAR) LIKE ?
                $rows_per_page,    // LIMIT ?
                $offset            // OFFSET ?
            );
        } else {
            mysqli_stmt_bind_param(
                $stmt,
                'ii',
                $rows_per_page,    // LIMIT ?
                $offset            // OFFSET ?
            );
        }

        if (!mysqli_stmt_execute($stmt)) {
            //Here we manage server request error and define the Error Response Body of the JSON {
            http_response_code(500);

            echo json_encode([
                'success' => false,
                'data' => null,
                'error' => 'Unable to retrieve clients',
            ]);

            exit;
        }

        // Retrieve the rows returned by the executed SELECT query.
        $clients_query_db = mysqli_stmt_get_result($stmt);

        if (!$clients_query_db) {
            http_response_code(500);

            echo json_encode([
                'success' => false,
                'data' => null,
                'error' => 'Unable to read clients query result',
            ]);

            mysqli_stmt_close($stmt);
            exit;
        }


        // Create the array that will contain every retrieved client and the additional related info we wanted to get.
        $clients = [];

        // Read one database row at a time and convert it into a PHP associative array
        while ($client_row = mysqli_fetch_assoc($clients_query_db)) {
            //Data structure of each client's retrieved information object from db
            $clients[] = [
                'id' => (int) $client_row['id'],
                'name' => $client_row['name'],
                'type_id' => (int) $client_row['type_id'],
                'client_type' => $client_row['client_type'],
                'status' => (int) $client_row['status'],
                'client_code' => $client_row['client_code'],
                'city' => $client_row['city'],
                'province' => $client_row['province'],
                'created_at' => $client_row['created_at'],
                'coupon_service_type' => $client_row['coupon_service_type'],
            ];
        }

        mysqli_stmt_close($stmt);

        //Update Error Response Body when query is successful
        echo json_encode([
            'success' => true,
            'data' => $clients,
            'pagination' => [ //this will be the object used by renderPagination() to create the 4 variables
                'current_page' => $current_page,
                'rows_per_page' => $rows_per_page,
                'total_results' => $total_results,
                'total_pages' => $total_pages,
            ],
            'error' => null,
        ]);

        /*
        Expected output of this GET request:
        {
          "success": true,
          "data": [
            {
              "id": 1,
              "name": "Mario Rossi",
              "type_id": 1,
              "client_type": "PR",
              "status": 1,
              "client_code": "CL001",
              "city": "Vicenza",
              "province": "VI",
              "created_at": "2026-07-20 10:30:00",
              "coupon_service_type": "medical"
            }
          ],
          "error": null
        }
        */
    } // end of CASE 2
}

// ----------------------------------------
// POST — CREATE CLIENT
// Validates the submitted form data and creates a new client, returning field-specific validation errors or a global result message.
// ----------------------------------------
elseif ($method === 'POST') {

    //Store query strings values received fron JS (the ?? '' just avoid reduntant php warnings for empty fields since there's already a validation process in this file)
    $new_client_name = trim($_POST['name'] ?? '');
    $new_client_code = mb_strtoupper(trim($_POST['client_code'] ?? ''), 'UTF-8'); //tim + upper case applied
    $new_client_type_id = filter_input(INPUT_POST, 'type_id', FILTER_VALIDATE_INT);
    $new_client_city = trim($_POST['city'] ?? '');
    $new_client_province = trim($_POST['province'] ?? '');
    $new_client_status_id = filter_input(INPUT_POST, 'status', FILTER_VALIDATE_INT);

    //Array to collect errors during input values validation
    $input_errors = [];

    /*this array will contain data in this form:
    {
    client_name_err: 'Invalid cleint name. Please choose a name.',
    client_code_err: 'Please type a client code.'
    }
    */


    //Validate input field values
    //Validate client name text input field value
    if (empty($new_client_name) || strlen($new_client_name) > 50) {
        $input_errors['client_name_err'] = 'Invalid name. Please type a name (max 50 characters)';
    }
    //Validate client code text input field value
    if (empty($new_client_code) || strlen($new_client_code) > 15 || strlen($new_client_code) < 5) {
        $input_errors['client_code_err'] = 'Invalid client code. Choose a descriptive client code between 5-15 charaters';
    }
    //Validate client type radio input field value
    //This check is mainly to print error messages cause a foreign key for the clients.type_id column has already been created 
    if ($new_client_type_id === false || $new_client_type_id < 1 || $new_client_type_id > 2) {
        $input_errors['client_type_err'] = 'Invalid client type. Please choose a client type';
    }
    //Validate client city text input field value
    if (empty($new_client_city) || strlen($new_client_city) > 70) {
        $input_errors['client_city_err'] = 'Invalid city. Type a city name (max 70 characters)';
    }
    //Validate client province text input field value
    if (empty($new_client_province) || strlen($new_client_province) < 2 || strlen($new_client_province) > 3) {
        $input_errors['client_province_err'] = 'Invalid province or region code. Type a valid one (max 3 characters)';
    }

    //If errors occur during input data validation, send error message to JS and stop here
    if (!empty($input_errors)) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'data' => null,
            'input_errors' => $input_errors, //for frontend input error messages only
        ]);

        exit;
    }


    //If no input errors occured, proceed with the INSERT to add the the new client to db
    // During the process we'll check:
    // - the UNICITY of the client code
    // - the existance of the selcted id in the client_types table (a FOREIGN KEY has been created for clients.type_id)
    // Also, no need to insert a client.status = 1 (active) cause that is the default value automatically set in db when new records are created
    if (empty($input_errors)) {
        // Create INSERT query
        $insert_sql = "
            INSERT INTO clients (
            name,
            type_id,
            client_code,
            city,
            province
        )
        VALUES (?, ?, ?, ?, ?)
        ";


        if ($stmt = mysqli_prepare($db_connection, $insert_sql)) {
            mysqli_stmt_bind_param(
                $stmt,
                'sisss',
                $new_client_name,
                $new_client_type_id,
                $new_client_code,
                $new_client_city,
                $new_client_province
            );

            //try-catch structure cause UNIQUE constraint set for client_code will tigger a mysqli_exception that
            //would prevent the json_encode blocs to be executed. So we need to manage these exceptions through this structure. 
            try {
                mysqli_stmt_execute($stmt);

                http_response_code(201);

                echo json_encode([
                    //success true/false wil also help managing the frontend feedbaack message
                    'success' => true,
                    'data' => [
                        'id' => mysqli_insert_id($db_connection),
                    ],
                    'message' => 'New client created successfully', //for global feedback message only
                    'error' => null,
                ]);
            }
            //The catch will intercept possible excpetions and will allow me to read the different response_code
            // that will return an error message to be used as global message only
            catch (mysqli_sql_exception $exception) {

                switch ($exception->getCode()) {
                    //If UNIQUE db costraint is violated: error 1062   
                    case 1062:
                        http_response_code(409);

                        echo json_encode([
                            'success' => false,
                            'data' => null,
                            'error' => 'A client with this client code already exists. Please choose a unique client code', //for global feedback message only
                        ]);
                        break;

                    // Violation of FOREIGN KEY constraints
                    case 1452:
                        http_response_code(400);

                        echo json_encode([
                            'success' => false,
                            'data' => null,
                            'error' => 'The selected client type is no longer valid.', //for global feedback message only
                        ]);
                        break;

                    // Every other database error for failed INSERT
                    default:
                        http_response_code(500);

                        echo json_encode([
                            'success' => false,
                            'data' => null,
                            'error' => 'Failed to create new client.', //for global feedback message only
                        ]);
                        break;
                }
            }

            mysqli_stmt_close($stmt);
        }
    }
}

// ----------------------------------------
// PATCH — TOGGLE CLIENT STATUS / EDIT CLIENT INFO
// Routes PATCH requests by ?action to either update only the active/inactive status or save the editable client profile fields.
// ----------------------------------------
elseif ($method === 'PATCH') {

    // There are two PATCH operations handled by this endpoint:
    // - toggle-status --> when you only need to activate/deactivate a client immediately (dashboard page and client-edit page)
    // - edit-info     --> when you need to update the editable fields in the client edit form (client-edit page)

    // Here I just decided to add an 'action' query string parameter in the api call url to keep the two PATCH call semantically separated.
    //This action attribute can have to values:
    // - &action=toggle-status (used for the api call on dashboard page and client-edit page) 
    // - &action=edit-info     (used for the api call on client-edit) 

    //Just an attempt to make the code more explicit rather than optimization of code lines number

    $patch_action = $_GET['action'] ?? 'toggle-status';

    $allowed_patch_actions = [
        'toggle-status',
        'edit-info',
    ];

    if (!in_array($patch_action, $allowed_patch_actions, true)) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Invalid PATCH action',
        ]);

        exit;
    }


    // Both PATCH operations require a valid client ID.
    $client_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($client_id === false || $client_id === null || $client_id <= 0) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Invalid client ID',
        ]);

        exit;
    }


    // ---------------------------------------------
    // PATCH CASE 1 - TOGGLE CLIENT STATUS ONLY
    // ---------------------------------------------

    // PATCH — TOGGLE CLIENT STATUS
    // Updates only the client's active/inactive status for the dashboard and client-edit page.
    if ($patch_action === 'toggle-status') {

        // status is stored as 1/0, so subtracting its current value from 1
        // switches active -> inactive and inactive -> active.
        $stmt = mysqli_prepare(
            $db_connection,
            "UPDATE clients
             SET status = 1 - status
             WHERE id = ?"
        );

        if (!$stmt) {

            http_response_code(500);

            echo json_encode([
                'success' => false,
                'data' => null,
                'error' => 'Unable to prepare client status update',
            ]);

            exit;
        }

        mysqli_stmt_bind_param(
            $stmt,
            'i',
            $client_id
        );

        if (!mysqli_stmt_execute($stmt)) {

            http_response_code(500);

            echo json_encode([
                'success' => false,
                'data' => null,
                'error' => 'Unable to update client status',
            ]);

            mysqli_stmt_close($stmt);
            exit;
        }

        if (mysqli_stmt_affected_rows($stmt) === 0) {

            http_response_code(404);

            echo json_encode([
                'success' => false,
                'data' => null,
                'error' => 'Client not found',
            ]);

            mysqli_stmt_close($stmt);
            exit;
        }

        mysqli_stmt_close($stmt);

        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $client_id,
            ],
            'message' => 'Client status updated successfully',
            'error' => null,
        ]);

        exit;
    }


    // ---------------------------------------------
    // PATCH CASE 2 - EDIT CLIENT INFO
    // ---------------------------------------------

    // PATCH — EDIT CLIENT INFO
    // Validates and updates the editable client profile fields submitted by the client-edit form.
    if ($patch_action === 'edit-info') {

        // PHP doesn't automatically populate $_POST for PATCH requests.
        // Read and parse the urlencoded request body manually instead.
        $patch_data = [];

        parse_str(
            file_get_contents('php://input'),
            $patch_data
        );


        // Store editable values received from the edit-client form. client_code is intentionally excluded because it becomes immutable
        // after client creation and may already be used inside coupon codes.
        $edited_client_name = trim(
            $patch_data['name'] ?? ''
        );

        $edited_client_type_id = filter_var(
            $patch_data['type_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $edited_client_city = trim(
            $patch_data['city'] ?? ''
        );

        $edited_client_province = trim(
            $patch_data['province'] ?? ''
        );


        // Collect validation errors using the same keys already adopted  by the create-client form whenever possible.
        $input_errors = [];


        // Validate client name.
        if (
            empty($edited_client_name) ||
            strlen($edited_client_name) > 50
        ) {

            $input_errors['client_name_err'] = 'Invalid name. Please type a name (max 50 characters)';
        }


        // Validate client type( clients.type_id is also protected by a foreign key in the database)
        if ($edited_client_type_id === false || $edited_client_type_id === null || $edited_client_type_id < 1 || $edited_client_type_id > 2) {

            $input_errors['client_type_err'] = 'Invalid client type. Please choose a client type';
        }


        // Validate city.
        if (empty($edited_client_city) || strlen($edited_client_city) > 70) {

            $input_errors['client_city_err'] = 'Invalid city. Type a city name (max 70 characters)';
        }


        // Validate province / region code.
        if (
            empty($edited_client_province) || strlen($edited_client_province) < 2 || strlen($edited_client_province) > 3
        ) {

            $input_errors['client_province_err'] = 'Invalid province or region code. Type a valid one (max 3 characters)';
        }


        // Stop before querying the database if one or more submitted
        // editable fields failed validation.
        if (!empty($input_errors)) {

            http_response_code(400);

            echo json_encode([
                'success' => false,
                'data' => null,
                'input_errors' => $input_errors,
            ]);

            exit;
        }

        // Check that the target client still exists before the UPDATE. This also lets us distinguish a nonexistent client from a valid
        // UPDATE where the submitted values are identical to the current ones.
        $check_stmt = mysqli_prepare(
            $db_connection,
            "SELECT id
             FROM clients
             WHERE id = ?"
        );

        if (!$check_stmt) {

            http_response_code(500);

            echo json_encode([
                'success' => false,
                'data' => null,
                'error' => 'Unable to prepare client validation query',
            ]);

            exit;
        }

        mysqli_stmt_bind_param(
            $check_stmt,
            'i',
            $client_id
        );

        mysqli_stmt_execute($check_stmt);

        $check_result =
            mysqli_stmt_get_result($check_stmt);

        $existing_client =
            mysqli_fetch_assoc($check_result);

        mysqli_stmt_close($check_stmt);


        if (!$existing_client) {

            http_response_code(404);

            echo json_encode([
                'success' => false,
                'data' => null,
                'error' => 'Client not found',
            ]);

            exit;
        }

        // Client exists and submitted data is valid: update only the fields that are editable from this page.
        $update_sql = "
            UPDATE clients
            SET
                name = ?,
                type_id = ?,
                city = ?,
                province = ?
            WHERE id = ?
        ";

        $stmt = mysqli_prepare(
            $db_connection,
            $update_sql
        );

        if (!$stmt) {

            http_response_code(500);

            echo json_encode([
                'success' => false,
                'data' => null,
                'error' => 'Unable to prepare client info update',
            ]);

            exit;
        }

        mysqli_stmt_bind_param(
            $stmt,
            'sissi',
            $edited_client_name,
            $edited_client_type_id,
            $edited_client_city,
            $edited_client_province,
            $client_id
        );

        try {

            mysqli_stmt_execute($stmt);

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $client_id,
                ],
                'message' => 'Client information updated successfully',
                'error' => null,
            ]);
        } catch (mysqli_sql_exception $exception) {

            switch ($exception->getCode()) {

                // clients.type_id is a foreign key, so reject the request
                // if the referenced client type no longer exists.
                case 1452:

                    http_response_code(400);

                    echo json_encode([
                        'success' => false,
                        'data' => null,
                        'error' => 'The selected client type is no longer valid.',
                    ]);

                    break;


                // Every other database error generated by the UPDATE.
                default:

                    http_response_code(500);

                    echo json_encode([
                        'success' => false,
                        'data' => null,
                        'error' => 'Failed to update client information.',
                    ]);

                    break;
            }
        }

        mysqli_stmt_close($stmt);

        exit;
    }
} else {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => 'Method not allowed',
    ]);

    exit;
}
