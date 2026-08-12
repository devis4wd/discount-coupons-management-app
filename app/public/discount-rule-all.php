<?php

//Session start
session_start();

$pageTitle = 'All Discount Rules';

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
        <section id="disc-rules-main-panel" class="bg-white rounded-2xl p-3 border-2 border-mid-blue relative sm:static">
            <div class="flex items-center">
                <h1 class="text-2xl py-3">Discount rules</h1>

                <!--Help icon-button - #help-box becomes relative only from sm screens and up-->
                <div id="help-box" class="flex items-center justify-center ml-4 sm:relative">
                    <button type="button" id="help-btn" aria-label="Help about discount rules" aria-controls="help-message" aria-expanded="false" class="cursor-pointer">
                        <svg aria-hidden="true" focusable="false" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-help-grey">
                            <path d="M12.838 17.638C13.0793 17.396 13.2 17.1 13.2 16.75C13.2 16.4 13.0793 16.104 12.838 15.862C12.5967 15.62 12.3007 15.4993 11.95 15.5C11.5993 15.5007 11.3037 15.6217 11.063 15.863C10.8223 16.1043 10.7013 16.4 10.7 16.75C10.6987 17.1 10.8197 17.396 11.063 17.638C11.3063 17.88 11.602 18.0007 11.95 18C12.298 17.9993 12.594 17.8783 12.838 17.637M11.05 14.15H12.9C12.9 13.6 12.9627 13.1667 13.088 12.85C13.2133 12.5333 13.5673 12.1 14.15 11.55C14.5833 11.1167 14.925 10.704 15.175 10.312C15.425 9.92 15.55 9.44934 15.55 8.9C15.55 7.96667 15.2083 7.25001 14.525 6.75001C13.8417 6.25001 13.0333 6.00001 12.1 6.00001C11.15 6.00001 10.3793 6.25001 9.788 6.75001C9.19667 7.25001 8.784 7.85001 8.55 8.55001L10.2 9.20001C10.2833 8.9 10.471 8.57501 10.763 8.22501C11.055 7.87501 11.5007 7.70001 12.1 7.70001C12.6333 7.70001 13.0333 7.846 13.3 8.138C13.5667 8.43 13.7 8.75067 13.7 9.10001C13.7 9.43334 13.6 9.74601 13.4 10.038C13.2 10.33 12.95 10.6007 12.65 10.85C11.9167 11.5 11.4667 11.9917 11.3 12.325C11.1333 12.6583 11.05 13.2667 11.05 14.15ZM12 22C10.6167 22 9.31667 21.7377 8.1 21.213C6.88334 20.6883 5.825 19.9757 4.925 19.075C4.025 18.1743 3.31267 17.116 2.788 15.9C2.26333 14.684 2.00067 13.384 2 12C1.99933 10.616 2.262 9.31601 2.788 8.10001C3.314 6.88401 4.02633 5.82567 4.925 4.92501C5.82367 4.02434 6.882 3.31201 8.1 2.78801C9.318 2.26401 10.618 2.00134 12 2.00001C13.382 1.99867 14.682 2.26134 15.9 2.78801C17.118 3.31467 18.1763 4.02701 19.075 4.92501C19.9737 5.82301 20.6863 6.88134 21.213 8.10001C21.7397 9.31867 22.002 10.6187 22 12C21.998 13.3813 21.7353 14.6813 21.212 15.9C20.6887 17.1187 19.9763 18.177 19.075 19.075C18.1737 19.973 17.1153 20.6857 15.9 21.213C14.6847 21.7403 13.3847 22.0027 12 22ZM12 20C14.2333 20 16.125 19.225 17.675 17.675C19.225 16.125 20 14.2333 20 12C20 9.76667 19.225 7.875 17.675 6.32501C16.125 4.77501 14.2333 4.00001 12 4.00001C9.76667 4.00001 7.875 4.77501 6.325 6.32501C4.775 7.875 4 9.76667 4 12C4 14.2333 4.775 16.125 6.325 17.675C7.875 19.225 9.76667 20 12 20Z" fill="currentColor" fill-opacity="0.74" />
                        </svg>
                    </button>

                    <!--The relative parent container changes depending on the screen: on mobile it is the #disc-rules-main-panel container, from sm screens and up #help-box is relative 
                        and the positioning and size of #help-message below also change depending on the screen-->
                    <div id="help-message" class="hidden w-64 absolute top-3 left-1/2 -translate-x-1/2 rounded-lg border-1 border-primary-blue bg-white p-2 sm:top-0 sm:left-8 sm:-translate-x-0 sm:w-96 ">
                        <button type="button" id=close-help-message-btn aria-label="Close discount rules help" class="block w-[20px] h-[20px] p-2 mb-2 flex items-center justify-center ml-auto text-primary-grey font-sm border-2 rounded-3xl cursor-pointer">X</button>
                        <p class="text-xs"><b>Discount rules</b> are one of the two building blocks you need to create a <b>Discount Coupon</b> – the other is the client's information.</p>
                        <p class="text-xs py-2">You can create <b>new Discount Coupons</b> from each client's detail page, using the dedicated 'Add new discount coupon' section.</p>
                        <p class="text-xs"><b>Note:</b> Discount Rules can't be deleted once created, but you can always delete any Discount Coupons that use them.</p>
                    </div>
                </div>
            </div>

            <div id="disc-rul-action-bar" class="flex flex-col justify-between items-start md:flex-row md:items-center gap-6 py-5">
                <a id="disc-rul-new-rule-btn" href="./discount-rule-create.php" class="btn-custom btn-filled-green">+ New rule</a>

                <div id="disc-rul-filters-panel" class="w-full flex flex-col items-start gap-4 md:gap-9 md:max-w-3/4 sm:flex-row md:items-center md:justify-end ">
                    <div id="serv-cat-filter-box" class="flex w-full sm:w-auto gap-2 justify-between md:justify-end">
                        <label for="serv-categ-filters" class="inline-block font-bold">Service category</label>
                        <select name="serv-categ-filters" id="serv-categ-filters" data-dropdown-type="service_categories" aria-label="Filter discount rules by service category"
                            class="inline-block w-auto min-w-32 max-w-48 px-3 py-1 border border-mid-blue rounded-lg bg-white text-primary-black focus:outline-none focus:border-primary-blue">
                            <!-- <option> tags will be injected dynamically-->
                        </select>
                    </div>
                    <div id="visit-type-filter-box" class="flex w-full sm:w-auto gap-2 justify-between md:justify-end">
                        <label for="visit-type-filters" class="inline-block font-bold">Eligible visits</label>
                        <select name="visit-type-filters" id="visit-type-filters" data-dropdown-type="visit_types" aria-label="Filter discount rules by type of visit"
                            class="inline-block w-auto min-w-32 max-w-48 px-3 py-1 border border-mid-blue rounded-lg bg-white text-primary-black focus:outline-none focus:border-primary-blue">
                            <!-- <option> tags will be injected dynamically-->
                        </select>
                    </div>
                </div>
            </div>

            <!--Invisible live region for screen readers only (WCAGG/EAA accessibility standards). Will be updated by JS -->
            <p id="discount-rules-results-status" class="sr-only" role="status" aria-live="polite" aria-atomic="true"></p>
            <table id="disc-rul-table" class="table-auto w-full rounded-xl overflow-hidden">
                <thead class="bg-mid-blue border-b-15 border-white"> <!-- exclude thead from PHP if -->
                    <tr>
                        <th scope="col" class="align-top text-left px-4 py-3">Service category</th>
                        <th scope="col" class="align-top text-left p-3">Eligible visits</th>
                        <th scope="col" class="align-top text-center sm:text-left p-3">Discount %</th>
                    </tr>
                </thead>
                <tbody id="disc-rules-table-body">
                    <tr class="min-h-[30px] odd:bg-light-grey even:bg-white">
                        <td colspan="8" class="p-4 text-center">
                            Loading discount rules...
                        </td>
                    </tr>

                </tbody>
            </table>

            <!-- Pagination markup will be generated dynamically by pagination.js using data returned by the API -->
            <div id="disc-rules-pagination"></div>
        </section>
    </div>

</main>

</div>


<footer></footer>

<script src="../assets/js/pagination.js"></script>
<script src="../assets/js/all-discount-rules.js"></script>
</body>

</html>