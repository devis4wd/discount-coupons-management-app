<?php

//Session start
session_start();

$pageTitle = 'Create New Client';

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
        <section id="new-client-main-panel" class="bg-white rounded-2xl p-3 border-2 border-mid-blue">
            <div class="flex-col w-full items-center justify-start text-sm md:max-w-1/2 md:px-3 lg:max-w-1/3">
                <h1 class="text-2xl pt-3">Create new client</h1>
                <p id="feedback-msg" role="status" aria-live="polite" aria-atomic="true" class="min-h-[36px] inline-block text-xs text-pretty opacity-0 transition-opacity duration-200 my-3">
                    <!-- Message content will be injected dynamically -->
                </p>

            </div>

            <div id="new-client-form-box" class="py-5 md:px-3 relative sm:static">
                <!-- Form with novalidate attribute cause input validation is managed by backend only -->
                <form novalidate id="new-client-form" action="../api/clients.php" method="post" class="w-full flex flex-col gap-4 justify-between md:max-w-2/3">
                    <div class="w-full flex flex-col gap-2">
                        <div class="flex flex-col items-left gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <label for="client-name-create-input" class="font-bold">Client full name<span class="text-primary-orange ml-1">*</span></label>
                            <!-- Value attribute to be injected with PHP based on the API and current info-->
                            <input type="text" name="client-name-create-input" id="client-name-create-input" aria-label="Create client name" aria-describedby="new-client-name-error" placeholder="e.g. Hans Richter or Pharma & Co."
                                required class="w-full sm:w-1/2 input-normal-style" placeholder="Name"></input>
                        </div>
                        <!-- <Error message text will be injected dynamically:-->
                        <span id="new-client-name-error" class="block min-h-[25px] w-full text-xs text-left sm:text-right text-primary-orange"></span>
                    </div>
                    <div class="w-full flex flex-col gap-2">
                        <div class="flex flex-col items-left gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex">
                                <label for="client-code-create-input" class="font-bold">Client code<span class="text-primary-orange ml-1 mr-2">*</span></label>

                                <!--Help icon-button - #help-box becomes relative only from sm screens and up-->
                                <div id="help-box" class="flex items-start justify-center mr-auto sm:relative">
                                    <button type="button" id="help-btn" aria-label="Help about client codes" aria-controls="help-message" aria-expanded="false" class="cursor-pointer">
                                        <svg aria-hidden="true" focusable="false" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-help-grey">
                                            <path d="M12.838 17.638C13.0793 17.396 13.2 17.1 13.2 16.75C13.2 16.4 13.0793 16.104 12.838 15.862C12.5967 15.62 12.3007 15.4993 11.95 15.5C11.5993 15.5007 11.3037 15.6217 11.063 15.863C10.8223 16.1043 10.7013 16.4 10.7 16.75C10.6987 17.1 10.8197 17.396 11.063 17.638C11.3063 17.88 11.602 18.0007 11.95 18C12.298 17.9993 12.594 17.8783 12.838 17.637M11.05 14.15H12.9C12.9 13.6 12.9627 13.1667 13.088 12.85C13.2133 12.5333 13.5673 12.1 14.15 11.55C14.5833 11.1167 14.925 10.704 15.175 10.312C15.425 9.92 15.55 9.44934 15.55 8.9C15.55 7.96667 15.2083 7.25001 14.525 6.75001C13.8417 6.25001 13.0333 6.00001 12.1 6.00001C11.15 6.00001 10.3793 6.25001 9.788 6.75001C9.19667 7.25001 8.784 7.85001 8.55 8.55001L10.2 9.20001C10.2833 8.9 10.471 8.57501 10.763 8.22501C11.055 7.87501 11.5007 7.70001 12.1 7.70001C12.6333 7.70001 13.0333 7.846 13.3 8.138C13.5667 8.43 13.7 8.75067 13.7 9.10001C13.7 9.43334 13.6 9.74601 13.4 10.038C13.2 10.33 12.95 10.6007 12.65 10.85C11.9167 11.5 11.4667 11.9917 11.3 12.325C11.1333 12.6583 11.05 13.2667 11.05 14.15ZM12 22C10.6167 22 9.31667 21.7377 8.1 21.213C6.88334 20.6883 5.825 19.9757 4.925 19.075C4.025 18.1743 3.31267 17.116 2.788 15.9C2.26333 14.684 2.00067 13.384 2 12C1.99933 10.616 2.262 9.31601 2.788 8.10001C3.314 6.88401 4.02633 5.82567 4.925 4.92501C5.82367 4.02434 6.882 3.31201 8.1 2.78801C9.318 2.26401 10.618 2.00134 12 2.00001C13.382 1.99867 14.682 2.26134 15.9 2.78801C17.118 3.31467 18.1763 4.02701 19.075 4.92501C19.9737 5.82301 20.6863 6.88134 21.213 8.10001C21.7397 9.31867 22.002 10.6187 22 12C21.998 13.3813 21.7353 14.6813 21.212 15.9C20.6887 17.1187 19.9763 18.177 19.075 19.075C18.1737 19.973 17.1153 20.6857 15.9 21.213C14.6847 21.7403 13.3847 22.0027 12 22ZM12 20C14.2333 20 16.125 19.225 17.675 17.675C19.225 16.125 20 14.2333 20 12C20 9.76667 19.225 7.875 17.675 6.32501C16.125 4.77501 14.2333 4.00001 12 4.00001C9.76667 4.00001 7.875 4.77501 6.325 6.32501C4.775 7.875 4 9.76667 4 12C4 14.2333 4.775 16.125 6.325 17.675C7.875 19.225 9.76667 20 12 20Z" fill="currentColor" fill-opacity="0.74" />
                                        </svg>
                                    </button>

                                    <!--The relative parent container changes depending on the screen: on mobile it is the parent container div of #help-box, from sm screens and up #help-box is relative 
                                        and the positioning and size of #help-message below also change depending on the screen-->
                                    <div id="help-message" class="hidden w-5/6 absolute top-21 left-1/2 -translate-x-1/2 rounded-lg border-1 border-primary-blue bg-white p-2 
                                sm:left-16 sm:-top-5 sm:-translate-x-0 sm:w-96 ">
                                        <button type="button" id=close-help-message-btn aria-label="Close client code help" class="block w-[20px] h-[20px] p-2 mb-2 flex items-center justify-center ml-auto text-primary-grey font-sm border-2
                                     rounded-3xl cursor-pointer">X</button>
                                        <p class="text-xs py-2">The client code is <b>unique</b> for every client and will be used as part of new coupon codes created for each client
                                            (e.g. CO-PHYS-FIRST-20-<b>CLIENT CODE</b>).</p>
                                        <p class="text-xs py-2">For this reason, the client code should be as descriprive as possible and similar to the client's name.</p>
                                        <p class="text-xs py-2 text-red-700">Client codes are permanente and can't be changed once you've set them</b>.</p>
                                    </div>
                                </div>
                            </div>


                            <!-- Value attribute to be injected with PHP and API based on current info-->
                            <input type="text" name="client-code-create-input" id="client-code-create-input" aria-label="Create client code" aria-describedby="new-client-code-error" placeholder="e.g. PHARMASMITH-NY "
                                required class="w-full sm:w-1/2 input-normal-style" placeholder="Code"></input>
                        </div>
                        <!-- <Error message text will be injected dynamically:-->
                        <span id="new-client-code-error" class="block min-h-[25px] w-full text-xs text-left sm:text-right text-primary-orange"></span>
                    </div>

                    <div class="w-full flex flex-col gap-2">
                        <div class="flex flex-col items-left gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <span id="client-type-label" class="font-bold">Client type<span class="text-primary-orange ml-1">*</span></span>
                            <div id="client-type-group" role="radiogroup" aria-labelledby="client-type-label" aria-describedby="new-client-type-error" aria-required="true" class="flex flex-col items-left gap-2 sm:flex-row sm:items-center sm:justify-between md:gap-3 lg:gap-8">
                                <!--Radio inputs with same name attribute to assure mutual exclusion and to manage the checked value in JS  -->
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
                        <!-- <Error message text will be injected dynamically:-->
                        <span id="new-client-type-error" class="block min-h-[25px] w-full text-xs text-left sm:text-right text-primary-orange"></span>
                    </div>


                    <div class="w-full flex flex-col gap-2">
                        <div class="flex flex-col items-left gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <label for="client-city-create-input" class="font-bold">City<span class="text-primary-orange ml-1">*</span></label>
                            <!-- Value attribute to be injected with PHP and API based on current info-->
                            <input type="text" name="client-city-create-input" id="client-city-create-input" aria-label="Create client city" aria-describedby="new-client-city-error" placeholder="e.g. Bassano del Grappa, Berlin"
                                required class="w-full sm:w-1/2 input-normal-style" placeholder="City"></input>
                        </div>
                        <!-- <Error message text will be injected dynamically:-->
                        <span id="new-client-city-error" class="block min-h-[25px] w-full text-xs text-left sm:text-right text-primary-orange"></span>
                    </div>

                    <div class="w-full flex flex-col gap-2">
                        <div class="flex flex-col items-left gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <label for="client-province-create-input" class="font-bold">Province / Region<span class="text-primary-orange ml-1">*</span></label>
                            <!-- Value attribute to be injected with PHP and API based on current info-->
                            <input type="text" name="client-province-create-input" id="client-province-create-input" aria-label="Create client province" aria-describedby="new-client-province-error" placeholder="e.g. ‘NY’, ‘VI’, 'LND'"
                                required class="w-full sm:w-1/3 input-normal-style" placeholder="Province"></input>
                        </div>
                        <!-- <Error message text will be injected dynamically:-->
                        <span id="new-client-province-error" class="block min-h-[25px] w-full text-xs text-left sm:text-right text-primary-orange"></span>
                    </div>


                    <div id="new-client-btns-box" class="flex gap-8 mt-7 mx-auto sm:mx-0">
                        <a href="./dashboard.php" class="btn-custom btn-empty-orange min-w-16 min-w-[85px]">Go back</a>
                        <button id="new-client-submit-btn" type="submit" form="new-client-form" class="btn-custom btn-filled-blue-submit min-w-[85px]">Create</button>
                    </div>
                </form>
            </div>
    </div>
    </section>
    </div>

</main>

</div>
<footer></footer>

<script src="../assets/js/client-create.js"></script>
</body>

</html>