<?php

//Session start. Authentication data is stored in the PHP session by login.php.
session_start();

$pageTitle = 'Create new user';


// ----------------------------------------
// PAGE AUTHORIZATION
// Only active ADMIN users can access the Create User page.
// ----------------------------------------

/*
There are three requirements to access this page:
- the user must be logged in;
- the logged user must have the "admin" role;
- the logged user account must still be active.

IMPORTANT: the same authorization control is repeated inside users-api.php. This is because protecting this page alone
would NOT be enough, because users could otherwise try to call the API endpoint directly without opening this frontend page.
*/
if (!isset($_SESSION['user_id']) ||  ($_SESSION['user_role'] ?? null) !== 'admin' ||  (int) ($_SESSION['user_status'] ?? 0) !== 1) {
    header('Location: ../index.php');
    exit;
}


require_once __DIR__ . '/../includes/header.php';

?>


<main id="main-content" class="bg-light-grey">
    <div class="w-full h-full max-w-7xl mx-auto py-8 px-3 rounded-2xl">
        <section id="new-user-main-panel" class="bg-white rounded-2xl p-3 border-2 border-mid-blue">
            <div class="flex flex-col mb-6 items-start md:px-3">
                <h1 class="text-2xl py-3"> Create new User </h1>
                <!--   Global success / API error feedback is rendered dynamically by user-create.js.-->
                <p id="feedback-msg" role="status" aria-live="polite" aria-atomic="true" class="min-h-[36px] inline-block text-xs text-pretty opacity-0 transition-opacity duration-200 my-3"></p>
            </div>

            <div id="new-user-form-box" class="py-5 md:px-3 relative sm:static">
                <!--  This form is managed by user-create.js -->
                <form id="new-user-form" class="w-full flex flex-col gap-6 justify-between lg:max-w-3/5">
                    <!-- User Name -->
                    <div class="w-full flex flex-col gap-2">
                        <div class="flex flex-col items-left gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <label for="user-name" class="font-bold"> User name <span class="text-primary-orange ml-1">*</span> </label>
                            <input type="text" name="user_name" id="user-name" aria-describedby="name-error" placeholder="E.g. Toshi" aria-invalid="false" class="w-full sm:w-1/2 input-normal-style">
                        </div>
                        <span id="name-error" class="block min-h-[25px] w-full text-xs text-left sm:text-right text-primary-orange"></span>
                    </div>
                    <!-- User Surname -->
                    <div class="w-full flex flex-col gap-2">
                        <div class="flex flex-col items-left gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <label for="user-surname" class="font-bold"> User surname <span class="text-primary-orange ml-1">*</span> </label>
                            <input type="text" name="user_surname" id="user-surname" aria-describedby="surname-error" placeholder="E.g. Yoshida" aria-invalid="false" class="w-full sm:w-1/2 input-normal-style">
                        </div>
                        <span id="surname-error" class="block min-h-[25px] w-full text-xs text-left sm:text-right text-primary-orange"></span>
                    </div>
                    <!--User  Email -->
                    <div class="w-full flex flex-col gap-2">
                        <div class="flex flex-col items-left gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <label for="user-email" class="font-bold"> Email <span class="text-primary-orange ml-1">*</span></label>
                            <input type="email" name="user_email" id="user-email" aria-describedby="email-error" autocomplete="email" placeholder="toshi.yoshida@example.com" aria-invalid="false" class="w-full sm:w-1/2 input-normal-style">
                        </div>
                        <span id="email-error" class="block min-h-[25px] w-full text-xs text-left sm:text-right text-primary-orange"></span>
                    </div>
                    <!--User  Password -->
                    <div class="w-full flex flex-col gap-2">
                        <div class="flex flex-col items-left gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <label for="user-password" class="font-bold"> Password <span class="text-primary-orange ml-1">*</span> </label>
                            <input type="password" name="user_password" id="user-password" aria-describedby="password-error" autocomplete="new-password" placeholder="Min 8 characters" aria-invalid="false" class="w-full sm:w-1/2 input-normal-style">
                        </div>
                        <span id="password-error" class="block min-h-[25px] w-full text-xs text-left sm:text-right text-primary-orange"></span>
                    </div>
                    <!--User  Confirm Password -->
                    <div class="w-full flex flex-col gap-2">
                        <div class="flex flex-col items-left gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <label for="confirm-password" class="font-bold"> Confirm password <span class="text-primary-orange ml-1">*</span>
                            </label>
                            <input type="password" name="confirm_password" id="confirm-password" aria-describedby="confirm-password-error" autocomplete="new-password" placeholder="Repeat password" aria-invalid="false" class="w-full sm:w-1/2 input-normal-style">
                        </div>
                        <span id="confirm-password-error" class="block min-h-[25px] w-full text-xs text-left sm:text-right text-primary-orange"></span>
                    </div>
                    <!-- User Role -->
                    <div class="w-full flex flex-col gap-2">
                        <div class="flex flex-col items-left gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <label for="user-role" class="font-bold"> Role <span class="text-primary-orange ml-1">*</span> </label>
                            <!-- This select dropdown menu is NOT generated dynamically and it's one of the only two exceptions to the dynamic genenration performed via dropdown-menus-api.php -->
                            <select name="user_role" id="user-role" aria-describedby="user-role-error" aria-invalid="false" class="input-normal-style">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <span id="user-role-error" class="block min-h-[25px] w-full text-xs text-left sm:text-right text-primary-orange"></span>
                    </div>
                    <!-- Buttons -->
                    <div id="new-user-btns-box" class="flex gap-8 mt-7 mx-auto sm:mx-0">
                        <a href="./dashboard.php" class="btn-custom btn-empty-orange min-w-[85px]"> Go back </a>

                        <button id="new-user-submit-btn" type="submit" class="btn-custom btn-filled-blue-submit min-w-[85px]"> Create user </button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</main>


<script defer src="../assets/js/user-create.js"></script>
