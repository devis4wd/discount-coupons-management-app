<?php
$loggedUserFullName = trim(
    ($_SESSION['user_name'] ?? '') . ' ' .
        ($_SESSION['user_surname'] ?? '')
);

$currentPage = basename($_SERVER['PHP_SELF']);

//Check the role stored in the authenticated user's session. This value is set during login and is used here only to decide 
//whether ADMIN-only navigation links (i.e. 'create user' page) should be rendered in the navbar or not. 
$isAdmin = ($_SESSION['user_role'] ?? null) === 'admin';

//list of pages that will make the 'All clients and coupons' appear as current page / active in navbar)
$clientsPages = [
    'dashboard.php',
    'client-create.php',
    'client-details.php',
    'client-edit.php'
];

//list of pages that will make the 'All discount rules' appear as current page / active in navbar)
$discountRulesPages = [
    'discount-rule-all.php',
    'discount-rule-create.php'
];

//ADMIN-only Users section. At the moment this section contains only the page used to create new users. 
$usersPages = ['user-create.php'];

$isClientsSection = in_array($currentPage, $clientsPages, true);
$isDiscountRulesSection = in_array($currentPage, $discountRulesPages, true);
$isUsersSection = in_array($currentPage, $usersPages, true);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script defer src="../assets/js/script.js"></script>
    <link rel="stylesheet" href="../assets/css/output.css">
    <title><?= $pageTitle ?? 'My website' ?></title>
</head>

<body class="bg-light-grey">
    <!--For WCAGG/EAA accessibility standards-->
    <a href="#main-content" class="sr-only focus:not-sr-only">
        Skip to main content
    </a>

    <div>
        <header id="header">
            <div id="up-header">
                <div id="upheader-logo-level" class="bg-white border-b-2 border-mid-grey">
                    <div class="w-full max-w-7xl mx-auto px-3 flex justify-between items-center py-3">
                        <div id="logo-container" class="max-w-[60px]">
                            <a href="../public/dashboard.php" aria-label="Homepage" title="App Logo - Homepage">
                                <img src="../assets/img/app_logo.png" alt="Coupon App Logo">
                            </a>
                        </div>
                        <div id="logged-user">
                            <p><b>Logged staff</b>: <?= htmlspecialchars($loggedUserFullName, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>
                </div>
                <div id="upheader-nav-level" class="bg-white border-b-2 border-mid-grey">
                    <div
                        class="w-full max-w-7xl mx-auto px-3 flex flex-col md:flex-row justify-between items-start md:items-center py-4">

                        <!-- BURGER -->
                        <button id="burger-btn" aria-label="Open main navigation menu" aria-controls="main-nav"
                            aria-expanded="false" class="md:hidden cursor-pointer">
                            <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg" class="text-primary-black">
                                <path d="M3 6H21M3 12H21M3 18H21" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>

                        <!-- NAV - absolute relative to its parent div-->
                        <nav id="main-nav"
                            class="hidden absolute left-0 w-screen h-[calc(100dvh-var(--header-height))] bg-white top-[var(--header-height)] pt-8 px-3 z-50 md:static md:block md:w-full md:z-auto md:h-auto md:mt-0 md:p-0">
                            <ul class="w-full h-full gap-4 flex flex-col md:flex-row md:h-auto md:gap-7">
                                <li>
                                    <a href="../public/dashboard.php" class="underline-effect-navbar-link <?= $isClientsSection ? 'current-page' : '' ?>" <?= $isClientsSection ? 'aria-current="true"' : '' ?>>
                                        All clients and coupons
                                    </a>
                                </li>
                                <li>
                                    <a href="../public/discount-rule-all.php" class="underline-effect-navbar-link <?= $isDiscountRulesSection ? 'current-page' : '' ?>" <?= $isDiscountRulesSection ? 'aria-current="true"' : '' ?>>
                                        All discount rules
                                    </a>
                                </li>
                                <!-- User creation is an ADMIN-only feature. The link is not rendered at all for normal users, it's not only hidden through CSS/JavaScript. 
                                 IMPORTANT: this is only a frontend/navigation convenience. user-create.php and users-api.php will still perform their own backend authorization checks. -->
                                <?php
                                if ($isAdmin): ?>
                                    <li>
                                        <a href="../public/user-create.php" class="underline-effect-navbar-link <?= $isUsersSection ? 'current-page' : '' ?>" <?= $isUsersSection ? 'aria-current="true"' : '' ?>>
                                            Create user
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <li id="logout-link"
                                    class="mt-auto pb-6 mx-auto w-1/2 md:pb-0 md:mt-0 md:mx-0 md:ml-auto md:w-auto">
                                    <a href="/logout.php"
                                        class="block mx-auto p-3 bg-primary-blue text-white rounded-lg text-center md:inline md:mx-0 md:p-0 md:bg-transparent md:rounded-none md:text-primary-blue hover:underline">Logout</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>

            </div>
        </header>