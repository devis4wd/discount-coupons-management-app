<?php

//Session start
session_start();

$pageTitle = 'Client Detail Page';

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
    <div class="w-full max-w-7xl mx-auto min-h-80 py-8 px-3 rounded-2xl"> <!-- add flex here -->
        <div id="single-client-main-panel" class="bg-white rounded-2xl p-3 border-2 border-mid-blue relative sm:static">
            <section class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center sm:gap-0">
                <div id="single-client-head" class="flex items-center gap-4">
                    <div id="single-client-type-icon" class="py-3 text-primary-blue">
                        <svg width="25" height="25" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 10H10.5V11.6667H12M12 6.66667H10.5V8.33333H12M13.5 13.3333H7.5V11.6667H9V10H7.5V8.33333H9V6.66667H7.5V5H13.5M6 3.33333H4.5V1.66667H6M6 6.66667H4.5V5H6M6 10H4.5V8.33333H6M6 13.3333H4.5V11.6667H6M3 3.33333H1.5V1.66667H3M3 6.66667H1.5V5H3M3 10H1.5V8.33333H3M3 13.3333H1.5V11.6667H3M7.5 3.33333V0H0V15H15V3.33333H7.5Z" fill="currentColor" />
                        </svg>
                    </div>
                    <div>
                        <!-- Title dynamically updated by JS -->
                        <h1 id="single-client-name" class="text-2xl py-3"></h1>
                    </div>

                </div>

                <div class="flex justify-between items-center gap-10 my-3 sm:my-0">
                    <div class=" sm:ml-10 py-1 px-2 cursor-pointer rounded-lg text-sm bg-primary-blue text-white transition-text transition-bg duration-200 hover:bg-mid-blue hover:text-primary-blue">
                        <a href="./dashboard.php">Go back</a>
                    </div>
                    <div id="single-client-delete-option" class="self-end md:self-center">
                        <!-- No need to specify here a data-client-id attribute since I'll retrive the ID via JS based on the client details we're loaded on this page -->
                        <button id="delete-client-btn" type="submit" aria-label="Permanently delete client profile" class="hard-delete-client md:block px-0 text-primary-orange rounded-lg transition duration-100 ease-in cursor-pointer hover:bg-primary-orange hover:text-white sm:px-2">Delete client</button>
                    </div>
                </div>
            </section>

            <section id="single-client-manage-dashboard" class="grid grid-cols-1 grid-rows-3 lg:grid-cols-2 gap-3 py-5">
                <!-- The DOM order matters to make the boxes appear correctly in the bento grid -->
                <div id="client-main-info-box" class="col-span-1 row-span-1 flex flex-col gap-3 justify-between md:flex-row bg bg-white rounded-2xl p-5 border-2 border-mid-grey">
                    <div id="client-main-info" class="w-full order-2 md:order-1 flex flex-col justify-between gap-4 md:max-w-2/3">
                        <p class="font-bold">Client status: <span id="client-status-info" class="info-client-field status-tag status-tag-active">Active</span></p>
                        <p class="font-bold">Client type: <span id="client-type-info" class="info-client-field font-normal">CO (company)</span></p>
                        <p class="font-bold">Client code: <span id="client-code-info" class="info-client-field font-normal">VALL8CAR - VI</span></p>
                        <div id="client-location-info" class="flex gap-5">
                            <p class="font-bold">City: <span id="client-city-info" class="info-client-field font-normal">Pove del Grappa</span></p>
                            <p class="font-bold">Province: <span id="client-province-info" class="info-client-field font-normal">VI</span></p>
                        </div>
                    </div>
                    <div id="client-edit-main-info" class="w-full order-1 md:order-2 flex items-start justify-end  md:max-w-1/3">
                        <!-- href will be updated dynamically by JS using the right id -->
                        <a id="client-edit-info-link" aria-label="Edit client main info" href="" class="block text-primary-blue hover:underline flex items-center gap-2"><svg aria-hidden="true" focusable="false" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M5.6404 9.69608L10.0778 5.25864C9.33134 4.94679 8.65334 4.49139 8.08231 3.91829C7.50893 3.34714 7.05332 2.66893 6.74136 1.92216L2.30392 6.3596C1.95773 6.70579 1.78433 6.87919 1.63554 7.06998C1.45998 7.29526 1.30931 7.53886 1.18615 7.79655C1.08236 8.01495 1.00496 8.24774 0.850167 8.71212L0.0329961 11.1618C-0.00461429 11.274 -0.0101944 11.3944 0.016883 11.5096C0.0439603 11.6247 0.102622 11.7301 0.186274 11.8137C0.269925 11.8974 0.375251 11.956 0.490412 11.9831C0.605573 12.0102 0.726004 12.0046 0.838167 11.967L3.28788 11.1498C3.75286 10.995 3.98505 10.9176 4.20345 10.8138C4.46224 10.6906 4.70443 10.5409 4.93002 10.3645C5.12081 10.2157 5.29421 10.0423 5.6404 9.69608ZM11.309 4.02749C11.7514 3.58504 12 2.98496 12 2.35925C12 1.73354 11.7514 1.13345 11.309 0.691008C10.8665 0.248563 10.2665 4.66191e-09 9.64075 0C9.01504 -4.66191e-09 8.41496 0.248563 7.97251 0.691008L7.44033 1.22319L7.46313 1.28979C7.72531 2.04023 8.15449 2.72133 8.71829 3.28171C9.29543 3.86238 10.0004 4.30002 10.7768 4.55967L11.309 4.02749Z" fill="currentColor" />
                            </svg>
                            Edit
                        </a>
                    </div>
                </div>

                <div id="manage-existing-coupons-box" class="col-span-1 row-span-3 bg-white rounded-2xl p-5 border-2 border-mid-grey">
                    <div id="coupons-action-bar" class="flex flex-col items-start justify-between gap-3 py-3 sm:flex-row sm:items-center sm:gap-0 md:gap-6">
                        <h2 id="manage-existing-coupons-title" class="text-xl font-semibold">Coupons</h2>
                        <div class="ml-auto">
                            <label for="coupon-filters" class="sr-only">Filter by status</label>
                            <select name="coupon-filters" id="coupon-filters" aria-label="Filter by status"
                                class="w-auto max-w-32 md:max-w-48 px-3 py-1 border border-mid-blue rounded-lg bg-white text-primary-black focus:outline-none focus:border-primary-blue">
                                <!-- <option> tags for active/inactive status filters are NOT generated dynamically (only exception to dynamic generation system build for dropdowns)  -->
                                <option class="coupon-status-filter" value="all">All</option> <!--JS fetch will use the attribute value for the GET API call-->
                                <option class="coupon-status-filter" value="active">Active</option>
                                <option class="coupon-status-filter" value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
		    
                    <!--Invisible live region for screen readers only (WCAGG/EAA accessibility standards). Will be updated by JS -->
                    <p id="coupons-results-status" class="sr-only" role="status" aria-live="polite" aria-atomic="true"></p>
                    <table id="coupons-table" class="table-auto w-full rounded-xl overflow-hidden mt-2">
                        <thead class="bg-mid-blue border-b-15 border-white text-sm md:text-base"> <!-- exclude thead from PHP if -->
                            <tr>
                                <th scope="col" class="align-center md:align-top text-left p-2 md:p-3">Coupon code</th>
                                <th scope="col" class="align-center md:align-top text-left p-2 md:p-3 hidden md:table-cell">Discount rule</th>
                                <th scope="col" class="align-center md:align-top text-center sm:text-left p-2 md:p-3">Remaining</th>
                                <th scope="col" class="align-center md:align-top text-center p-2 md:p-3">Active</th>
                                <th scope="col" class="align-center md:align-top text-center p-2 md:p-3">Action</th>
                            </tr>
                        </thead>
                        <tbody id="coupons-table-body">
                            <!-- Placeholder row that will be replaced by client-details.js through a call to GET coupons-api.php -->
                            <tr class="min-h-[30px] odd:bg-light-grey even:bg-white py-2 text-xs md:text-sm">
                                <td colspan="5" class="p-4 text-center">
                                    Loading coupons...
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination markup will be generated dynamically by pagination.js using data returned by coupons-api.php -->
                    <div id="coupons-pagination"></div>
                </div>

                <div id="add-new-coupon-box" class="col-span-1 row-span-2 bg-white rounded-2xl p-5 border-2 border-mid-grey">
                    <div class="w-full flex flex-col gap-2 justify-between align-center mb-8">
                        <h2 id="new-discount-coupon-title" class="text-xl font-semibold">Add new discount coupon</h2>
                        <!-- Feedback message text will be injected dynamically by JS -->
                        <p id="feedback-msg" role="status" aria-live="polite" aria-atomic="true" class="w-full inline-block min-h-[28px] text-xs text-pretty opacity-0 transition-opacity duration-200 my-3"></p>
                    </div>
                    <!-- Form that will have to use CONDITIONAL FORM FIELDS: Usage cap field selectable only if the selected Discount rule provides only 'first visit only'
                     (SEE CHAT 'Coupon generation architecture + conditional form' DEEPSEEK FOR IMPLEMENTATION SUGGESTIONS) -->
                    <div id="add-new-coupon-form-container" class="flex flex-col gap-10 relative sm:static">
                        <form novalidate id="new-coupon-form" action="../api/coupons.php" method="post" aria-labelledby="new-discount-coupon-title" class="flex flex-col gap-6">
                            <div class="w-full flex flex-col items-start justify-between gap-2">
                                <div class="w-full flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center sm:gap-0">
                                    <label for="new-coupon-rule-choice" class="font-bold">Discount rule<span class="text-primary-orange ml-1">*</span></label>
                                    <select name="new-coupon-rule-choice" id="new-coupon-rule-choice" aria-describedby="new-coupon-rule-error" data-dropdown-type="discount_rules" aria-label="Select discount rule to apply" required class="w-2/3 sm:w-1/2 input-normal-style">
                                        <!-- <option> tags will be injected dynamically-->
                                    </select>
                                </div>
                                <!-- <Error message text will be injected dynamically:-->
                                <span id="new-coupon-rule-error" class="block min-h-[25px] w-full text-xs text-left sm:text-right text-primary-orange"></span>
                            </div>
                            <div class="w-full flex flex-col items-start justify-between gap-2">
                                <div class="w-full flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center sm:gap-0">
                                    <div class="flex">
                                        <label for="new-coupon-cap-set" class="font-bold mr-2">Max usage allowed (times): </label>
                                        <!--Help icon-button - #help-box becomes relative only from sm screens and up-->
                                        <div id="help-box" class="flex items-start justify-center mr-auto sm:relative">
                                            <button type="button" id="help-btn" aria-label="Help about coupon usage limits" aria-controls="help-message" aria-expanded="false" class="cursor-pointer">
                                                <svg aria-hidden="true" focusable="false" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-help-grey">
                                                    <path d="M12.838 17.638C13.0793 17.396 13.2 17.1 13.2 16.75C13.2 16.4 13.0793 16.104 12.838 15.862C12.5967 15.62 12.3007 15.4993 11.95 15.5C11.5993 15.5007 11.3037 15.6217 11.063 15.863C10.8223 16.1043 10.7013 16.4 10.7 16.75C10.6987 17.1 10.8197 17.396 11.063 17.638C11.3063 17.88 11.602 18.0007 11.95 18C12.298 17.9993 12.594 17.8783 12.838 17.637M11.05 14.15H12.9C12.9 13.6 12.9627 13.1667 13.088 12.85C13.2133 12.5333 13.5673 12.1 14.15 11.55C14.5833 11.1167 14.925 10.704 15.175 10.312C15.425 9.92 15.55 9.44934 15.55 8.9C15.55 7.96667 15.2083 7.25001 14.525 6.75001C13.8417 6.25001 13.0333 6.00001 12.1 6.00001C11.15 6.00001 10.3793 6.25001 9.788 6.75001C9.19667 7.25001 8.784 7.85001 8.55 8.55001L10.2 9.20001C10.2833 8.9 10.471 8.57501 10.763 8.22501C11.055 7.87501 11.5007 7.70001 12.1 7.70001C12.6333 7.70001 13.0333 7.846 13.3 8.138C13.5667 8.43 13.7 8.75067 13.7 9.10001C13.7 9.43334 13.6 9.74601 13.4 10.038C13.2 10.33 12.95 10.6007 12.65 10.85C11.9167 11.5 11.4667 11.9917 11.3 12.325C11.1333 12.6583 11.05 13.2667 11.05 14.15ZM12 22C10.6167 22 9.31667 21.7377 8.1 21.213C6.88334 20.6883 5.825 19.9757 4.925 19.075C4.025 18.1743 3.31267 17.116 2.788 15.9C2.26333 14.684 2.00067 13.384 2 12C1.99933 10.616 2.262 9.31601 2.788 8.10001C3.314 6.88401 4.02633 5.82567 4.925 4.92501C5.82367 4.02434 6.882 3.31201 8.1 2.78801C9.318 2.26401 10.618 2.00134 12 2.00001C13.382 1.99867 14.682 2.26134 15.9 2.78801C17.118 3.31467 18.1763 4.02701 19.075 4.92501C19.9737 5.82301 20.6863 6.88134 21.213 8.10001C21.7397 9.31867 22.002 10.6187 22 12C21.998 13.3813 21.7353 14.6813 21.212 15.9C20.6887 17.1187 19.9763 18.177 19.075 19.075C18.1737 19.973 17.1153 20.6857 15.9 21.213C14.6847 21.7403 13.3847 22.0027 12 22ZM12 20C14.2333 20 16.125 19.225 17.675 17.675C19.225 16.125 20 14.2333 20 12C20 9.76667 19.225 7.875 17.675 6.32501C16.125 4.77501 14.2333 4.00001 12 4.00001C9.76667 4.00001 7.875 4.77501 6.325 6.32501C4.775 7.875 4 9.76667 4 12C4 14.2333 4.775 16.125 6.325 17.675C7.875 19.225 9.76667 20 12 20Z" fill="currentColor" fill-opacity="0.74" />
                                                </svg>
                                            </button>

                                            <!--The relative parent container changes depending on the screen: on mobile it is #add-new-coupon-form-container, from sm screens and up #help-box is relative 
                                        and the positioning and size of #help-message below also change depending on the screen-->
                                            <div id="help-message" class="hidden w-5/6 absolute top-21 left-1/2 -translate-x-1/2 rounded-lg border-1 border-primary-blue bg-white p-2  sm:left-8 sm:-top-5 sm:-translate-x-0 sm:w-96 ">
                                                <button type="button" id=close-help-message-btn aria-label="Close coupon usage help" class="block w-[20px] h-[20px] p-2 mb-2 flex items-center justify-center ml-auto text-primary-grey font-sm border-2 rounded-3xl cursor-pointer">X</button>
                                                <p class="text-xs">'First visit only' coupons should be used <b>only once per person</b>. </p>
                                                <p class="text-xs py-2"><b>Default values</b> will apply if nothing else is entered:</p>
                                                <ul class="list-disc list-inside">
                                                    <li class="text-xs indent-2"><b>For private clients (PR):</b> default minimum value is <b>1</b> unless otherwise agreed</li>
                                                    <li class="text-xs indent-2"><b>For company clients (CO):</b> default minimum value is <b>5</b> but you should enter the exact or approximate number of employees.</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Becomes required when a 'first visit only' Discount Rule is applied  -->
                                    <input disabled type="number" name="new-coupon-cap-setting" id="new-coupon-cap-set" aria-describedby="new-coupon-cap-error" required min="1" step="1" placeholder="0 " aria-label="Set a usage cap for this coupon" class="w-2/3 sm:w-1/2 input-normal-style text-right">
                                </div>
                                <!-- <Error message text will be injected dynamically:-->
                                <span id="new-coupon-cap-error" class="block min-h-[25px] w-full text-xs text-left sm:text-right text-primary-orange"></span>
                            </div>

                            <div class="w-full flex flex-col items-start justify-between gap-2">
                                <div class="w-full flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center sm:gap-0">
                                    <label for="new-coupon-exp-date" class="font-bold">Expiration date</label>
                                    <!-- Min date must be today, MAX date 2 years from today -->
                                    <input type="date" name="new-coupon-exp-date" id="new-coupon-exp-date" aria-describedby="new-coupon-exp-date-error" min="2026-01-01" max="2028-01-01" class="max-w-1/2 input-normal-style text-right">
                                </div>
                                <span id="new-coupon-exp-date-error" class="block min-h-[25px] w-full text-xs text-left sm:text-right text-primary-orange"></span>
                            </div>

                            <div class="w-full flex flex-col gap-2 sm:flex-row sm:items-center">
                                <p class="font-bold">Coupon preview:</p>
                                <!-- Code preview text will be replaced dynamically:-->
                                <p id="new-coupon-preview" class="text-sm text-primary-grey sm:pl-2">select a discount rule to see the coupon preview here</p>
                            </div>
                        </form>

                        <button id="new-coupon-submit-btn" type="submit" form="new-coupon-form" class="btn-custom btn-filled-blue max-1/2 md:w-1/4 ml-auto">Add</button>
                    </div>
                </div>
            </section>

        </div>
    </div>

</main>

</div>


<footer></footer>

<script src="../assets/js/pagination.js"></script>
<script src="../assets/js/client-details.js"></script>
</body>

</html>
