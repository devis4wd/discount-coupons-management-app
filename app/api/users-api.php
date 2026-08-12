<?php

//The API needs access to the current authenticated user's session
//because only active ADMIN users are allowed to create new application users.
session_start();

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

/*
JSON RESPONSE STRUCTURES RETURNED TO JS

POST — CREATE USER - success:
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
    "input_errors": {
        "name_err": string,
        "surname_err": string,
        "email_err": string,
        "password_err": string,
        "confirm_password_err": string,
        "role_err": string
    }
}

Authorization / global / request error:
{
    "success": false,
    "data": null,
    "error": string
}
*/

$method = $_SERVER['REQUEST_METHOD'];

// ----------------------------------------
// API AUTHORIZATION
// Creating application users is an ADMIN-only operation.
// ----------------------------------------


//This control MUST also exist here even though user-create.php already protects the frontend page.
//A user could manually call this API endpoint without ever visiting user-create.php, so authorization must always be enforced by
//the backend endpoint actually performing the protected database operation.
//In the specific case of user-creation (which is this api file scope), the user must be authenticated, active and with an ADMIN role.

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? null) !== 'admin' || (int) ($_SESSION['user_status'] ?? 0) !== 1) {

    http_response_code(403);

    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => 'You are not authorized to create users.',
    ]);

    exit;
}

// ----------------------------------------
// POST — CREATE USER
// Validates the submitted user data and inserts a new application user into the users table.
// ----------------------------------------
if ($method === 'POST') {

    //Initialize the array that will contain field-specific validation errors.
    //The keys match the ones expected by user-create.js.
    $input_errors = [];

    // NAME VALIDATION
    // ----------------------------------------
    $input_name = trim($_POST['user_name'] ?? '');

    if ($input_name === '') {
        $input_errors['name_err'] = 'Please enter a name for this user.';
    }

    //Unicode letters are allowed, together with apostrophes and hyphens
    //(e.g. Ludwig, Jean-Pierre, D'Angelo).
    elseif (!preg_match('/^[\p{L}\'-]+$/u', $input_name)) {
        $input_errors['name_err'] = "User names can contain only letters, apostrophes and hyphens.";
    }

    // SURNAME VALIDATION
    // ----------------------------------------
    $input_surname = trim($_POST['user_surname'] ?? '');

    if ($input_surname === '') {
        $input_errors['surname_err'] = 'Please enter a surname for this user.';
    } elseif (!preg_match('/^[\p{L}\'-]+$/u', $input_surname)) {
        $input_errors['surname_err'] = "User surnames can contain only letters, apostrophes and hyphens.";
    }

    // EMAIL VALIDATION
    // ----------------------------------------

    /*
    Some context explanation abour the naming convention for company email addresses I chose:

    - default structure:
      name.surname@companydomain.com

    - if homonyms exist:
      first user  -> name.surname@companydomain.com
      second user -> name.surname1@companydomain.com
      third user  -> name.surname2@companydomain.com
      etc.

    - numbers used for homonyms must always be placed immediately  after the surname and before the @.

    - double names/surnames can be concatenated:
      Sarah Lucio Cornelio Silla -> luciocornelio.silla@companydomain.com

    The API validates this naming convention but does NOT automatically generate the next progressive address: the administrator
    still enters the intended email.
    */

    $input_email = trim($_POST['user_email'] ?? '');

    if ($input_email === '') {

        $input_errors['email_err'] =
            'Please enter a valid company email address.';
    }

    //First make sure it is structurally a valid email address.
    elseif (!filter_var($input_email, FILTER_VALIDATE_EMAIL)) {

        $input_errors['email_err'] =
            'Please enter a valid email address.';
    }

    //Then enforce the company-specific naming convention.
    //Compared with the previous user-create.php regex, [0-9]* is intentionally allowed after the surname so that 
    //the documented homonym convention (name.surname1, name.surname2, etc.) actually works.

    elseif (
        !preg_match(
            '/^[a-zA-Z]+\.[a-zA-Z]+[0-9]*@' .
                preg_quote(COMPANY_DOMAIN, '/') .
                '$/',
            $input_email
        )
    ) {

        $input_errors['email_err'] =
            'Company email must have the format name.surname@' .
            COMPANY_DOMAIN .
            ' or name.surname1@' .
            COMPANY_DOMAIN .
            ' for homonyms.';
    } else {
        //Email is used as the login credential, therefore an exact email address can belong to one user only.
        //This PHP check exists mainly to return a clear validation message to the admin.
        //Keep in mind that a UNIQUE constraint ALSO exists on users.email at database level and that is the final integrity
        //guarantee and also protects against simultaneous  requests that could otherwise pass this SELECT before either INSERT is executed.

        $check_email_sql = "
            SELECT id
            FROM users
            WHERE email = ?
            LIMIT 1
        ";

        if ($check_email_stmt = mysqli_prepare($db_connection, $check_email_sql)) {

            mysqli_stmt_bind_param(
                $check_email_stmt,
                's',
                $input_email
            );

            if (mysqli_stmt_execute($check_email_stmt)) {

                mysqli_stmt_store_result($check_email_stmt);

                if (mysqli_stmt_num_rows($check_email_stmt) > 0) {

                    $input_errors['email_err'] =
                        'This specific email is already registered. Please use a progressive number for users with the same name and surname.';
                }
            } else {

                mysqli_stmt_close($check_email_stmt);

                http_response_code(500);

                echo json_encode([
                    'success' => false,
                    'data' => null,
                    'error' => 'System error during email validation.',
                ]);

                exit;
            }

            mysqli_stmt_close($check_email_stmt);
        } else {

            http_response_code(500);

            echo json_encode([
                'success' => false,
                'data' => null,
                'error' => 'Unable to prepare email validation.',
            ]);

            exit;
        }
    }


    // PASSWORD VALIDATION
    // ----------------------------------------

    //Password must NOT be trimmed.
    //Empty can be can legitimately and intentionally be part of a password, therefore both creation and login 
    //must hash/verify exactly the string entered by the user.

    $input_password = $_POST['user_password'] ?? '';

    if ($input_password === '') {

        $input_errors['password_err'] =
            'Please enter a valid password.';
    } else {
        //Current project password requirements:
        //- at least 8 characters
        //- at least one uppercase letter
        //- at least one special character

        $has_upper = preg_match('/[A-Z]/', $input_password);

        $has_special = preg_match(
            '/[!@#$%^&*()_\-+=\[\]{};:\'",.<>\/?\\\\|`~]/',
            $input_password
        );

        $is_long_enough = strlen($input_password) >= 8;

        if (
            !$is_long_enough ||
            !$has_upper ||
            !$has_special
        ) {

            $input_errors['password_err'] =
                'Use at least 8 characters, 1 uppercase, 1 special character.';
        }
    }

    // CONFIRM PASSWORD VALIDATION
    // ----------------------------------------

    $input_confirm_password = $_POST['confirm_password'] ?? '';

    if ($input_confirm_password === '') {

        $input_errors['confirm_password_err'] =
            'Please confirm the new password.';
    } elseif ($input_password !== $input_confirm_password) {

        $input_errors['confirm_password_err'] =
            'The passwords do not match. Please enter the same password.';
    }


    // ROLE VALIDATION
    // ----------------------------------------

    $input_role = trim($_POST['user_role'] ?? '');

    //Set whitelist with the only role values currently allowed by the users.role ENUM column.
    $allowed_roles = [
        'admin',
        'user',
    ];

    if ($input_role === '') {

        $input_errors['role_err'] =
            'Please select a role.';
    } elseif (!in_array($input_role, $allowed_roles, true)) {

        $input_errors['role_err'] =
            'Invalid role selected.';
    }


    // RETURN ALL VALIDATION ERRORS TO JS
    // ----------------------------------------

    //Do not query INSERT if at least one submitted field is invalid.
    // I decided to return all input errors together also lets the frontend render  every invalid field after a single request 
    //instead of forcing one request for every validation problem.

    if (!empty($input_errors)) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'data' => null,
            'input_errors' => $input_errors,
        ]);

        exit;
    }

    // If no input errors are returned, proceed with the INSERT query for the new user.

    // INSERT NEW USER
    // ----------------------------------------

    $insert_user_sql = "
        INSERT INTO users (
            name,
            surname,
            email,
            password_hash,
            role
        )
        VALUES (?, ?, ?, ?, ?)
    ";


    if ($insert_user_stmt = mysqli_prepare($db_connection, $insert_user_sql)) {

        //Hash the validated plain-text password immediately before storing it.
        //The plain-text password itself is never written to the database for secusrity reasons
        $password_hash = password_hash(
            $input_password,
            PASSWORD_DEFAULT
        );

        mysqli_stmt_bind_param(
            $insert_user_stmt,
            'sssss',
            $input_name,
            $input_surname,
            $input_email,
            $password_hash,
            $input_role
        );


        if (mysqli_stmt_execute($insert_user_stmt)) {

            $new_user_id = mysqli_insert_id($db_connection);

            mysqli_stmt_close($insert_user_stmt);
            mysqli_close($db_connection);

            http_response_code(201);

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $new_user_id,
                ],
                'message' => 'New user successfully added.',
                'error' => null,
            ]);

            exit;
        }


        /*SQL error 1062 means a UNIQUE constraint was violated.
        Normally the previous SELECT already catches an existing email, but users.email should also be UNIQUE at DB level.
        Keeping this check handles the unlikely race condition where two simultaneous requests try to insert the same email.
        */
        if (mysqli_stmt_errno($insert_user_stmt) === 1062) {

            mysqli_stmt_close($insert_user_stmt);

            http_response_code(409);

            echo json_encode([
                'success' => false,
                'data' => null,
                'input_errors' => [
                    'email_err' => 'This email is already registered.',
                ],
            ]);

            exit;
        }


        mysqli_stmt_close($insert_user_stmt);

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => 'Something went wrong. The new user was not added to the database.',
        ]);

        exit;
    }


    http_response_code(500);

    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => 'Unable to prepare the request to create the new user.',
    ]);

    exit;
}

// ----------------------------------------
// UNSUPPORTED HTTP METHOD
// ----------------------------------------

http_response_code(405);

header('Allow: POST');

echo json_encode([
    'success' => false,
    'data' => null,
    'error' => 'Method not allowed.',
]);

exit;
