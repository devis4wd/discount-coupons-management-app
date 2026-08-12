<?php

//Session start
session_start();

$pageTitle = 'Edit Client Profile';

//Access this page only if the user is logged in and has an active account
if (
    !isset($_SESSION['user_id']) ||
    (int) ($_SESSION['user_status'] ?? 0) !== 1
) {
    header('Location: ../index.php');
    exit;
}

require_once '../includes/header.php';
?>


<main id="main-content" class="bg-light-grey">
    <div class="w-full h-full max-w-7xl mx-auto py-8 px-3 rounded-2xl">
        <section id="edit-client-main-panel" class="bg-white rounded-2xl p-3 border-2 border-mid-blue">
            <div class="flex-col w-full items-center justify-start text-sm md:max-w-1/2 md:px-3 lg:max-w-1/3">
                <!-- Title dynamically updated by JS -->
                <h1 id="edit-client-page-title" class="text-2xl pt-3"></h1>
                <p id="feedback-msg" role="status" aria-live="polite" aria-atomic="true" class="min-h-[36px] inline-block text-xs text-pretty opacity-0 transition-opacity duration-200 my-3">
                    <!-- Message content will be injected dynamically -->
                </p>
            </div>

            <div id="edit-client-form-box" class="py-5 md:px-3">
                <!-- Actually both the api file path and the method are entorely managed and set in backend, so here I left them just as referencce but they're useless now -->
                <form novalidate id="edit-client-form" action="../api/clients-api.php" method="patch" class="w-full flex flex-col gap-8 justify-between lg:max-w-3/5">
                    <div class="w-full">
                        <div class="w-full flex gap-2  justify-between mb-[30px]">
                            <label for="client-status-change" class="font-bold">Client status<span class="text-primary-orange ml-1">*</span></label>
                            <div class="client-toggle-status status-toggle flex justify-center items-center">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="client-status-change" id="client-status-change" value="active" class="sr-only toggle-checkbox regular-size-toggle-checkbox">
                                    <div class="toggle-track relative w-9 h-5 rounded-full transition-colors duration-300">
                                        <span class="toggle-knob absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white transition-transform duration-300 transform">
                                        </span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="w-full flex flex-col gap-2">
                        <div class="flex flex-col items-left gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <label for="client-name-edit" class="font-bold">Client name<span class="text-primary-orange ml-1">*</span></label>
                            <input type="text" name="client-name-edit" id="client-name-edit" aria-label="Edit client name" aria-describedby="edit-client-name-error" required class="w-full sm:w-1/2 input-normal-style" placeholder="Name"></input>
                        </div>
                        <!-- <Error message text will be injected dynamically:-->
                        <span id="edit-client-name-error" class="block min-h-[25px] w-full text-xs text-left sm:text-right text-primary-orange"></span>
                    </div>
                    <div class="w-full flex flex-col gap-2">
                        <div class="flex flex-col items-left gap-2 sm:flex-row sm:items-start sm:justify-between mb-[30px]">
                            <label for="client-code-edit" class="font-bold">Client code</label>
                            <input disabled type="text" name="client-code-edit" id="client-code-edit" aria-label="Edit client code" class="w-full sm:w-1/2 py-1 px-2 text-primary-grey focus:outline-none focus:border-primary-blue" placeholder="Code"></input>
                        </div>
                    </div>

                    <div class="w-full flex flex-col gap-2">
                        <div class="flex flex-col items-left gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <span id="client-type-label" class="font-bold">Client category<span class="text-primary-orange ml-1">*</span></span>
                            <div id="client-type-group" role="radiogroup" aria-labelledby="client-type-label" aria-describedby="edit-client-type-error" aria-required="true" class="flex flex-col items-items-start gap-2 sm:flex-row sm:items-center sm:justify-between md:gap-3 lg:gap-8">
                                <!-- Checked attribute will be udpated dynamically-->
                                <div>
                                    <input class="mr-2" type="radio" name="client-type-input" id="client-type-pr-input" value="1" />
                                    <label for="client-type-pr-input">PR (single person)</label>
                                </div>
                                <div>
                                    <input class="mr-2" type="radio" name="client-type-input" id="client-type-co-input" value="2" />
                                    <label for="client-type-co-input">CO (company)</label>
                                </div>
                            </div>
                        </div>
                        <!-- <Error message text will be injected dynamically and printed:-->
                        <span id="edit-client-type-error" class="block min-h-[25px] w-full text-xs text-left sm:text-right text-primary-orange"></span>
                    </div>

                    <div class="w-full flex flex-col gap-2">
                        <div class="flex flex-col items-left gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <label for="client-city-edit" class="font-bold">City<span class="text-primary-orange ml-1">*</span></label>
                            <input type="text" name="client-city-edit" id="client-city-edit" aria-label="Edit client city" aria-describedby="edit-client-city-error" required class="w-full sm:w-1/2 input-normal-style" placeholder="City"></input>
                        </div>
                        <!-- <Error message text will be injected dynamically:-->
                        <span id="edit-client-city-error" class="block min-h-[25px] w-full text-xs text-left sm:text-right text-primary-orange"></span>
                    </div>

                    <div class="w-full flex flex-col gap-2">
                        <div class="flex flex-col items-left gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <label for="client-province-edit" class="font-bold">Province / Region<span class="text-primary-orange ml-1">*</span></label>
                            <input type="text" name="client-province-edit" id="client-province-edit" aria-label="Edit client province" aria-describedby="edit-client-province-error" required class="1/3 input-normal-style" placeholder="Province"></input>
                        </div>
                        <!-- <Error message text will be injected dynamically:-->
                        <span id="edit-client-province-error" class="block min-h-[25px] w-full text-xs text-left sm:text-right text-primary-orange"></span>
                    </div>


                    <div id="edit-client-btns-box" class="flex gap-8 mt-7 mx-auto sm:mx-0">
                        <a id="edit-client-back-link" href="./client-details.php" class="btn-custom btn-empty-orange min-w-16 min-w-[85px]">Go back</a>
                        <button id="edit-client-submit-btn" type="submit" form="edit-client-form" class="btn-custom btn-filled-blue-submit min-w-[85px]">Save</button>
                    </div>
                </form>
            </div>
        </section>
    </div>

</main>

</div>
<footer></footer>

<script src="../assets/js/client-edit.js"></script>
</body>

</html>