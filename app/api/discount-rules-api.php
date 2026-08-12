<?php

//Session start
session_start();

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

/*
JSON RESPONSE STRUCTURES RETURNED TO JS

GET — DISCOUNT RULE LIST - success:
{
    "success": true,
    "data": [
        {
            "id": int,
            "service_category_id": int,
            "visit_type_id": int,
            "discount_perc": string|int,
            "created_at": string,
            "service_category": string,
            "visit_type": string
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

POST — CREATE DISCOUNT RULE - success:
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

//Define arrays with existing (allowed) values for checks that will be performed in multiple if/else if cblocks below
//The lists are defined since categories and visit types are quite few. In a more complex project these lists should be retrived/updated dynamically

//Only id existing in db are allowed
$allowed_service_categories = ['', '1', '2', '3'];
$allowed_visit_types = ['', '1', '2'];

// ----------------------------------------
// GET — DISCOUNT RULE LIST
// Retrieves the paginated discount-rule list, applying the optional service-category and visit-type filters.
// ----------------------------------------
if ($method === 'GET') {

    $where_conditions = [];

    //if filter value is '', normalize the empty sting as no filter applied
    $service_cat_filter = $_GET['servicesFilter'] ?? '';


    if (!in_array($service_cat_filter, $allowed_service_categories, true)) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Invalid service category filter selected',
        ]);

        exit;
    }

    //set sql condition based on attribute value of the selected <option>
    //The '' option (defualt) doesn't need to be managed here cause it simoly won't trigger any WHERE condition
    if ($service_cat_filter === '1') {
        $where_conditions[] = 'dr.service_category_id = 1'; //physio
    } elseif ($service_cat_filter === '2') {
        $where_conditions[] = 'dr.service_category_id = 2'; //medical
    } elseif ($service_cat_filter === '3') {
        $where_conditions[] = 'dr.service_category_id = 3'; //all services
    }

    $visit_type_filter = $_GET['visitsFilter'] ?? '';

    if (!in_array($visit_type_filter, $allowed_visit_types, true)) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Invalid type of visit filter selected',
        ]);

        exit;
    }

    if ($visit_type_filter === '1') {
        $where_conditions[] = 'dr.visit_type_id = 1'; //first visit only
    } elseif ($visit_type_filter === '2') {
        $where_conditions[] = 'dr.visit_type_id = 2'; //all visits
    }

    $where_sql = '';

    if ($where_conditions !== []) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
    }


    //PAGINATION: read the requested page from the query string and use 10 rows per page.
    $current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
    $current_page = ($current_page !== false && $current_page !== null && $current_page > 0)
        ? $current_page
        : 1;

    $rows_per_page = 10;


    //Count how many discount rules match the currently selected filters.
    //The frontend needs this number to know how many pagination buttons must be shown.
    $discount_rules_count_sql = "
        SELECT COUNT(*) AS total_results
        FROM discount_rules AS dr
        $where_sql
    ";

    $count_stmt = mysqli_prepare($db_connection, $discount_rules_count_sql);

    if (!$count_stmt || !mysqli_stmt_execute($count_stmt)) {
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Unable to count discount rules',
        ]);

        exit;
    }

    $count_result = mysqli_stmt_get_result($count_stmt);
    $count_row = mysqli_fetch_assoc($count_result);

    $total_results = (int) $count_row['total_results'];
    $total_pages = (int) ceil($total_results / $rows_per_page);

    mysqli_stmt_close($count_stmt);

    if ($total_pages > 0 && $current_page > $total_pages) {
        $current_page = $total_pages;
    }

    //OFFSET is the number of matching rows SQL must skip before returning this page.
    $offset = ($current_page - 1) * $rows_per_page;


    $discount_rules_info_sql = "SELECT
        dr.id,
        dr.service_category_id,
        dr.visit_type_id,
        dr.discount_perc,
        dr.created_at,

        sc.name AS service_category,
        vt.name AS visit_type

        FROM discount_rules as dr

        INNER JOIN service_categories AS sc
            ON sc.id = dr.service_category_id

        INNER JOIN visit_types AS vt
            ON vt.id = dr.visit_type_id

        $where_sql

        ORDER BY dr.created_at DESC

        LIMIT ? OFFSET ?";

    //Prepare sql query
    $stmt = mysqli_prepare($db_connection, $discount_rules_info_sql);

    if (!$stmt) {
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Unable to prepare discount rules query',
        ]);

        exit;
    }

    //LIMIT and OFFSET are always integers and are the only placeholders used by this query.
    mysqli_stmt_bind_param(
        $stmt,
        'ii',
        $rows_per_page,
        $offset
    );

    if (!mysqli_stmt_execute($stmt)) {
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Unable to retrieve discount rules',
        ]);

        exit;
    }

    //Retrieve data for each frontend row returened by the sql query
    $discount_rules_query_db = mysqli_stmt_get_result($stmt);

    if (!$discount_rules_query_db) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Unable to read discount rules query results'
        ]);

        mysqli_stmt_close($stmt);
        exit;
    }

    //array to store retrieved data
    $discount_rules = [];

    while ($discount_rule_row = mysqli_fetch_assoc($discount_rules_query_db)) {
        $discount_rules[] = [
            'id' => (int) $discount_rule_row['id'],
            'service_category_id' => (int) $discount_rule_row['service_category_id'],
            'visit_type_id' => (int) $discount_rule_row['visit_type_id'],
            'discount_perc' => $discount_rule_row['discount_perc'],
            'created_at' => $discount_rule_row['created_at'],
            'service_category' => $discount_rule_row['service_category'],
            'visit_type' => $discount_rule_row['visit_type'],
        ];
    }

    mysqli_stmt_close($stmt);

    echo json_encode([
        'success' => true,
        'data' => $discount_rules,
        'pagination' => [
            'current_page' => $current_page,
            'rows_per_page' => $rows_per_page,
            'total_results' => $total_results,
            'total_pages' => $total_pages,
        ],
        'error' => null,
    ]);
} else if ($method === 'POST') {

    // ----------------------------------------
    // POST — CREATE DISCOUNT RULE
    // Validates the submitted form data and creates a new discount rule, returning field-specific validation errors or a global result message.
    // ----------------------------------------

    //Store query strings id values reeived from JS and validate them as integer values
    $new_dr_category_id = filter_input(INPUT_POST, 'service_category_id', FILTER_VALIDATE_INT);
    $new_dr_visit_id = filter_input(INPUT_POST, 'visit_type_id', FILTER_VALIDATE_INT);
    $new_dr_percentage = filter_input(INPUT_POST, 'discount_perc', FILTER_VALIDATE_INT);

    //Array to collect ALL errors relate to inpunt fields validation. This will be used by JS code to generate forntend 
    //form input errors through the showInputError() function and the JS map I created for data association error > input field > message 
    $input_errors = [];

    /*this array will contain data in this form:
    {
    service_category_id_err: 'Invalid service category selected',
    discount_percentage_err: 'Please set a discount percentage within 0 and 100'
    }
    */

    //Check if input id values received from JS are not "" and are higher than 0 (id in db starts from 1, not 0)
    //This check is mainly to print error messages cause a foreign key for the discount_rules.service_category_id column has already been created
    if ($new_dr_category_id === false || $new_dr_category_id < 1) {
        $input_errors['service_category_id_err'] = 'Invalid service category selected';
    }
    //Check if input id values received from JS are not "" and are higher than 0 (id in db starts from 1, not 0)
    //This check is mainly to print error messages cause a foreign key for the discount_rules.visit_type_id column has already been created
    if ($new_dr_visit_id === false || $new_dr_visit_id < 1) {
        $input_errors['visit_type_id_err'] = 'Invalid visit type selected';
    }
    //Check if input discount percentage is not "" and is a valuew between 0 and 100 (%)
    if ($new_dr_percentage === false || $new_dr_percentage < 0 || $new_dr_percentage > 100) {
        $input_errors['discount_percentage_err'] = 'Please set a discount percentage within 0 and 100';
    }

    //If errors occured during input data validation, send error message to JS and don't go further
    if (!empty($input_errors)) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'data' => null,
            'input_errors' => $input_errors, //for frontend input error messages only
        ]);

        exit;
    }

    //If no input errors so far, preceed to add new discount rule with the INSERT query
    // NOTE that:
    // - We need to check whether the input id that will be saved in the discount_rules.service_category_id and discount_rules.visit_type id columns  
    //   actually match existing id in visit_types.id and service_categories.id columns. This check will be perfomed at DB level since I set the
    //   the discount_rules.service_category_id and discount_rules.visit_type as FOREIGN KEYS that will throw an error during the INSERT in case the ones
    //   we're trying to attach to the new discount rules don't exist in the other two tables that define the corresponding values
    // - No new discount rule can be added if it has the same combo of service_category_id, visit_type_id, discount_perc values of an existing rule 
    //   Again, this check is performed ad DB LEVEL through the UNIQUE constraint set directly in DB, so a 1062  error will be returned in these cases and the new rule won't be inserted in db
    if (empty($input_errors)) {

        $insert_sql = "
            INSERT INTO discount_rules (
            service_category_id,
            visit_type_id,
            discount_perc
        )
        VALUES (?, ?, ?)
        ";

        if ($stmt = mysqli_prepare($db_connection, $insert_sql)) {
            mysqli_stmt_bind_param(
                $stmt,
                "iii",
                $new_dr_category_id,
                $new_dr_visit_id,
                $new_dr_percentage
            );

            //Now, since I'll check the query against the UNIQUE constraint and this - in case we're actually trying to 
            //insert a rule with unnique condition which already exists - may trigger non just the json_encode error I defined but
            //also a myqli_exception (this is a standard behavior since PHPH 8.1) that would prevent the code to get to the classic else condition contianing the error json encode, 
            //I had to opt for this try - catch structure since I also need to use errors in json encode to manage the frontend feedback message and the
            //automatic mysqli_exception would prevend my js code from using those messages
            try {
                mysqli_stmt_execute($stmt);

                http_response_code(201);

                echo json_encode([
                    //success true/false wil also help managing the frontend feedbaack message
                    'success' => true,
                    'data' => [
                        'id' => mysqli_insert_id($db_connection),
                    ],
                    'message' => 'New discount rule created successfully', //for global feedback message only
                    'error' => null,
                ]);
            }
            //The catch will intercept possible excpetions and will allow me to read the response_code defined within correctly
            catch (mysqli_sql_exception $exception) {

                switch ($exception->getCode()) {
                    //If UNIQUE db costraint is violated: error 1062   
                    case 1062:
                        http_response_code(409);

                        echo json_encode([
                            'success' => false,
                            'data' => null,
                            'error' => 'A discount rule with these conditions already exists.', //for global feedback message only
                        ]);
                        break;

                    // Violation of FOREIGN KEY constraints (tells us if at least ONE of the id values do not exist in db)
                    case 1452:
                        http_response_code(400);

                        echo json_encode([
                            'success' => false,
                            'data' => null,
                            'error' => 'The selected service category or visit type is no longer valid.', //for global feedback message only
                        ]);
                        break;

                    // Every other database error for failed INSERT
                    default:
                        http_response_code(500);

                        echo json_encode([
                            'success' => false,
                            'data' => null,
                            'error' => 'Failed to create new discount rule.', //for global feedback message only
                        ]);
                        break;
                }
            }

            mysqli_stmt_close($stmt);
        }
    }
}
