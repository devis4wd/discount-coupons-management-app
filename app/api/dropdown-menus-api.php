<?php

//Session start
session_start();

//This API serves only one purpose: dynamically generate the <options> in filters/form dropdown menus based on the existing db records.
//This way, all the <select> input fields will always show updated selectable options 
//This API do the job for all the frontend <select> tags, regardless of the type of data (whether they show service categories, type of visits, discount rules etc.).
//There are only TWO EXCEPTIONS: 
// ONE: filter dropdowns for ACTIVE/INACTIVE statues, which will remain static since there's no dedicated db table to manage the BOOLEAN values 0=inactive and 1=active 
// TWO: the User/admin dropdown menu in the user-create.php page which only admins can access when they need ot create new users (employees) for this app

// This API will be called from different JS files (this projects has a JS file for each page that requires API calls).

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

/*
JSON RESPONSE STRUCTURES RETURNED TO JS

GET — DROPDOWN OPTIONS - success:
{
    "success": true,
    "data": [
        {
            "id": int,
            "visit_type_id": int|null,
            "discount_perc": int|null,
            "service_category_name": string|null,
            "visit_type_name": string|null,
            "name": string
        }
    ],
    "error": null
}

For service_categories and visit_types, the discount-rule-specific fields are null.
For discount_rules, those fields contain the additional data retrieved by the JOIN query.

Error:
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
// GET — DROPDOWN OPTIONS
// Retrieves the database-backed options requested by ?dataType for service-category, visit-type or discount-rule <select> elements.
// ----------------------------------------
if ($method === 'GET') {

    //The frontend attribute data-dropdown-type passed by JS is no the exact name of the table we'll retrive the data from.
    //That said, I added a static whitelist of queries that will do the following:
    //- check that only specific frontend values can act as key to trigger the db connection though this api
    //- prevent users from getting access to different tables through this api by editing the data-dropdown-type attribute
    //- give me flexibility to change the keys in case I prefer using them in in frontend instead of real table names.
    //- help me solving the problem described below (wasn0t for that, the whitlist would be made of 'frontend_keys' => 'table_names' only
    //  instead of 'frontend_key' => query)

    //A PROBLEM I HAD TO SOLVE: while the service_categories and the visit_types table has the same exact structure (id, name, created_at)
    //that's not the case for the discount_rule table: it has several columns, non of which uses the 'name' name, and the dropdown menu I 
    //need to create with data retrieved from that table is a combo of of values coming from 3 differet columns of that table (so "val 1 - val2 - val3).
    //At the same time, I needed to keep this API structure as standardized as possible for every dropdown menu and keep using 'name' to pass the retrieved text
    //to the JS for rendering the <option> tags.
    //SOLUTION:

    $dataDropdownType = trim($_GET['dataType'] ?? ''); //this will be service_categories, visit_types, etc

    //Whitelist of queries to access allowed tables (the ones relevant to create <option> input tags)
    $allowedQueriesAndTables = [
        //frontend attribute value > query with allowd db table name. 
        'service_categories' => "
        SELECT id, name
        FROM service_categories
        ORDER BY id ASC
    ",

        'visit_types' => "
        SELECT id, name
        FROM visit_types
        ORDER BY id ASC
    ",
        //this query will solve the problem of dropdown menus built from discount_rules table (which has no 'name' column)
        //Also, it'll retrive some additional info that I'll use to add html attributes to the <option> dor the servcie category dropdown menu
        //which will mainly be used in the client-detail page, where all the coupons management and creation happens.
        'discount_rules' => "
        SELECT
        dr.id,
        dr.visit_type_id,
        dr.discount_perc,

        sc.name AS service_category_name,
        vt.name AS visit_type_name,

        CONCAT(
            sc.name,
            ' - ',
            vt.name,
            ' - ',
            dr.discount_perc,
            '%'
        ) AS name
            FROM discount_rules AS dr
            INNER JOIN service_categories AS sc
                ON sc.id = dr.service_category_id
            INNER JOIN visit_types AS vt
                ON vt.id = dr.visit_type_id
            ORDER BY dr.id ASC
        ",
    ];

    if ($dataDropdownType === '' || !array_key_exists($dataDropdownType, $allowedQueriesAndTables)) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'selectedTag data-dropdown-type attribute is empty or has an invalid value',
        ]);

        exit;
    }

    //If JS passed a valid value that allows to identify an allowed query and table, then proceed with storing the right query
    //and connect ot db to retrieve the data for building <option> tags
    $dropdown_options_sql = $allowedQueriesAndTables[$dataDropdownType];

    //I won't need a prepare statment in this case cause I'm not using values (like id, name) but an identifier (the db table name)

    $result = mysqli_query($db_connection, $dropdown_options_sql);

    if (!$result) {
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Unable to retrieve dropdown options',
        ]);

        exit;
    }

    //If query is successful, save retrived data using inside an object for each table row
    //and store those objects inside a single array that will be shipped back to JS with all the data
    $dropdownOptions = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $dropdownOptions[] = [
            'id' => (int)$row['id'],
            //Use coalescent operator so this won't throw an error is the executed query was not the one for discount_rules table
            'visit_type_id' => isset($row['visit_type_id']) ? (int) $row['visit_type_id'] : null,
            'discount_perc' => isset($row['discount_perc']) ? (int) $row['discount_perc']  : null,
            'service_category_name' => $row['service_category_name'] ?? null,
            'visit_type_name' => $row['visit_type_name'] ?? null,
            'name' => $row['name'],
        ];
    }

    //If everything is successful, send a success 200 containing the retrieved data
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $dropdownOptions,
        'error' => null,
    ]);

    /*
    This will send data in this <form action="{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Medical"
        },
        {
            "id": 2,
            "name": "Physiotherapy"
        }
    ],
    "error": null
    }
    */
}
//Only GET calls are accepted by this API
else {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => 'Method not allowed',
    ]);

    exit;
}
