<?php
//Decomment for debugging:
//ini_set('display_errors', 1);
//error_reporting(E_ALL);

//Start session
session_start();


// INITIAL NOTES
//Unlike the other parts of this web app I created, I decided that no external API/fetch call is required for login.
//Authentication is handled through a traditional server-side POST. So, this same PHP file validates credentials, creates the session 
//and redirects the authenticated user.
//This choice was dictated by two simple considerations: 
//- I was too lazy to refactor it after creating the other files (this one was the first one I created);
//- Login can use a traditional server-side POST because authentication is session-based and doesn't require asynchronous UI updates.


//Check if user is already logged in and active.
//If so, there's no reason to show the login page again.
if (isset($_SESSION['user_id']) && isset($_SESSION['user_status']) && (int) $_SESSION['user_status'] === 1) {
    header('Location: public/dashboard.php');
    exit;
}

//Connect to DB
require_once __DIR__ . '/config.php';

//Set variables used for user login.
//Password is never printed back into the form after a failed attempt.
$email = $password = '';
$email_err = $password_err = $login_err = '';

//Set form_submitted variable to be used as second condition for frontend warning messages
//and avoid their appearance at the first page load.
$form_submitted = ($_SERVER['REQUEST_METHOD'] === 'POST');


// ----------------------------------------
// POST — USER LOGIN
// Validates the submitted credentials, checks the matching DB user and creates the authenticated session.
// ----------------------------------------
if ($form_submitted) {

    //Validate email input.
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $email_err = 'Please enter a valid company email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_err = 'Please enter a valid email address';
    }

    //Validate password input.
    //Unlike names/emails, passwords are NOT trimmed:
    //spaces can legitimately be part of a password and must therefore be checked exactly as entered.
    $password = $_POST['password'] ?? '';

    if ($password === '') {
        $password_err = 'Please enter a valid password';
    }

    //If the basic input validation is successful, look for the user in DB.
    if (empty($email_err) && empty($password_err)) {

        $check_user_sql = "
            SELECT
                id,
                name,
                surname,
                email,
                password_hash,
                role,
                status
            FROM users
            WHERE email = ?
            LIMIT 1
        ";

        if ($check_user_stmt = mysqli_prepare($db_connection, $check_user_sql)) {

            mysqli_stmt_bind_param(
                $check_user_stmt,
                's',
                $email
            );

            if (mysqli_stmt_execute($check_user_stmt)) {

                $result = mysqli_stmt_get_result($check_user_stmt);
                $user = mysqli_fetch_assoc($result);

                //I'm not gonna use a different frontend error messages for non-existing email and wrong password.
                //This is for security reasons: returning the same message in both cases avoids telling an unauthenticated user whether a specific company email
                //actually exists in the users table.

                if (!$user || !password_verify($password, $user['password_hash'])) {
                    $login_err = 'Invalid email or password. Please try again.';
                }

                //Credentials are correct, but inactive users must not be allowed to log in.
                elseif ((int) $user['status'] !== 1) {
                    $login_err = 'This account is inactive. Please contact the administrator.';
                }

                //Valid credentials + active account: create the authenticated session.
                else {
                    // Regenerate the session ID after a successful authentication. This keeps the current session data
                    // while assigning a new session ID   and helps prevent session fixation attacks.

                    session_regenerate_id(true);

                    $_SESSION['user_id'] = (int) $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_surname'] = $user['surname'];
                    $_SESSION['user_role'] = $user['role'];

                    //Only active users reach this point, therefore session status is always 1.
                    $_SESSION['user_status'] = 1;

                    header('Location: index.php');
                    exit;
                }
            } else {
                //Do not expose raw MySQL errors in the frontend.
                $login_err = 'Something went wrong during login. Please try again.';
            }

            mysqli_stmt_close($check_user_stmt);
        } else {
            //Do not expose raw MySQL errors in the frontend.
            $login_err = 'Something went wrong during login. Please try again.';
        }
    }
}

mysqli_close($db_connection);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!--login.php is in the project root-->
    <script defer src="./assets/js/script.js"></script>
    <link rel="stylesheet" href="./assets/css/output.css">

    <title>Login Page</title>
</head>

<body>

    <!--For WCAG/EAA accessibility standards-->
    <a href="#main-content" class="sr-only focus:not-sr-only"> Skip to main content </a>

    <main id="main-content">
        <section aria-labelledby="login-title" class="min-h-screen flex flex-col justify-center items-center">
            <div class="w-full max-w-80 mx-auto min-h-80 flex flex-col justify-center px-3">
                <h1 id="login-title" class="w-full text-4xl text-primary-black font-bold font-inter-sans text-center mb-3"> Login </h1>
                <form id="login-form" method="post" aria-describedby="login-desc" class="w-full login-form min-h-80 py-8 flex flex-col gap-4 justify-between">
                    <p id="login-desc" class="text-center mb-5 text-xs text-pretty">
                        Insert your credentials to access the reserved area.
                    </p>
                    <!-- Email -->
                    <div id="email-login-field" class="flex flex-col gap-2 text-sm">
                        <label for="email">Email</label>
                        <input id="email" type="email" aria-describedby="email-error" name="email" autocomplete="email" value="<?= htmlspecialchars($email); ?>" aria-invalid="<?= !empty($email_err) ? 'true' : 'false'; ?>" placeholder="name.surname@company.com" class="<?= ($form_submitted && !empty($email_err)) ? 'input-error' : 'input-normal-style'; ?>">
                        <span id="email-error" class="text-xs pt-1 <?= ($form_submitted && !empty($email_err))   ? 'text-primary-orange'         : 'hidden'; ?>">
                            <?= htmlspecialchars($email_err); ?>
                        </span>
                    </div>
                    <!-- Password -->
                    <div id="password-login-field" class="flex flex-col gap-2 text-sm">
                        <label for="password">Password</label>
                        <!--  Password is intentionally never restored as an input value  after a failed login attempt for security reasons.     -->
                        <input id="password" type="password" aria-describedby="password-error" name="password" autocomplete="current-password" aria-invalid="<?= !empty($password_err) ? 'true' : 'false'; ?>" placeholder="Enter your password..." class="<?= ($form_submitted && !empty($password_err)) ? 'input-error' : 'input-normal-style'; ?>">
                        <span id="password-error" class="text-xs pt-1 <?= ($form_submitted && !empty($password_err)) ? 'text-primary-orange'  : 'hidden'; ?>">
                            <?= htmlspecialchars($password_err); ?>
                        </span>
                    </div>
                    <!-- General login error -->
                    <div id="login-error" role="alert" cass="text-center text-sm mx-auto py-3 <?= ($form_submitted && !empty($login_err))    ? 'text-primary-orange'  : 'hidden'; ?>">
                        <?= htmlspecialchars($login_err); ?>
                    </div>
                    <!--Submit-->
                    <button id="login-button" type="submit" class="mx-auto btn-custom btn-filled-blue-submit">
                        Enter
                    </button>
                </form>

                <p id="login-assistance-message" class="w-full text-xs text-center">
                    Please <a href="#" class="text-blue-600 hover:underline"> contact the system admin </a> if you don't have an account or you forgot your current password.
                </p>
            </div>
        </section>
    </main>
</body>

</html>
