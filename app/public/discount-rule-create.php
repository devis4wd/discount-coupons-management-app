<?php

//Session start
session_start();

$pageTitle = 'Create New Discount Rule';

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
        <section id="new-rules-main-panel" class="bg-white rounded-2xl p-3 border-2 border-mid-blue">
            <div class="flex-col w-full items-center justify-start text-sm md:max-w-1/2 lg:max-w-1/3">
                <h1 class="text-2xl pt-3">Create new discount rule</h1>
                <p id="feedback-msg" role="status" aria-live="polite" aria-atomic="true" class="min-h-[36px] inline-block text-xs text-pretty opacity-0 transition-opacity duration-200 my-3">
                    <!-- Message content will be injected dynamically -->
                </p>
            </div>

            <div id="new-rules-form-box" class="py-5 md:px-3">
                <!-- Form with novalidate attribute cause input validation is managed by backend only -->
                <form novalidate id="new-rules-form" action="../api/discount-rules-api.php" method="post" class="w-full flex flex-col gap-6 justify-between md:max-w-2/3 lg:max-w-3/5">
                     <div class="w-full flex flex-col gap-2">
                        <div class="flex flex-col items-left gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <label for="new-rule-serv-cat" class="font-bold">Service category<span class="text-primary-orange ml-1">*</span></label>
                            <select name="new-rule-serv-cat" id="new-rule-serv-cat" aria-describedby="new-rule-serv-cat-error" data-dropdown-type="service_categories" aria-label="Select service category" required class="w-full sm:w-1/2 input-normal-style">
                                <!-- <option> tags will be injected dynamically-->
                            </select>
                        </div>
                        <!-- <Error message text will be injected dynamically:-->
                        <span id="new-rule-serv-cat-error" class="block min-h-[25px] w-full text-xs text-left sm:text-right text-primary-orange"></span>
                    </div>
                     <div class="w-full flex flex-col gap-2">
                        <div class="flex flex-col items-left gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <label for="new-rule-type-visit" class="font-bold">Type of visit<span class="text-primary-orange ml-1">*</span></label>
                            <select name="new-rule-type-visit" id="new-rule-type-visit" aria-describedby="new-rule-type-visit-error" data-dropdown-type="visit_types" aria-label="Choose type of visit" required class="w-full sm:w-1/2 input-normal-style">
                                <!-- <option> tags will be injected dynamically-->
                            </select>
                        </div>
                        <!-- <Error message text will be injected dynamically:-->
                        <span id="new-rule-type-visit-error" class="block min-h-[25px] w-full text-xs text-left sm:text-right text-primary-orange"></span>
                    </div>

                     <div class="w-full flex flex-col gap-2">
                        <div class="flex flex-col items-left gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <label for="new-rule-perc-discount" class="font-bold">Discount percentage<span class="text-primary-orange ml-1">*</span></label>
                            <input type="number" name="new-rule-perc-discount" id="new-rule-perc-discount" aria-describedby="new-rule-perc-discount-error" required aria-label="Set discount percentage" min="0" max="100" placeholder="%  " class="w-1/3 sm:w-1/4 input-normal-style">
                        </div>
                        <!-- <Error message text will be injected dynamically:-->
                        <span id="new-rule-perc-discount-error" class="block min-h-[25px] w-full text-xs text-left sm:text-right text-primary-orange"></span>
                    </div>

                    <div id="new-rules-btns-box" class="flex gap-8 mt-7 mx-auto sm:mx-0">
                        <a href="./discount-rule-all.php" class="btn-custom btn-empty-orange min-w-16 min-w-[85px]">Go back</a>
                        <button id="new-rule-submit-btn" type="submit" form="new-rules-form" class="btn-custom btn-filled-blue-submit min-w-[85px]">Create</button>
                    </div>
                </form>
            </div>
        </section>
    </div>

</main>

</div>
<footer></footer>

<script src="../assets/js/discount-rule-create-new.js"></script>
</body>

</html>