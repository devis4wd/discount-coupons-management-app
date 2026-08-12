<?php
//Session start
session_start();

$pageTitle = 'Dashboard | All Clients';

//Access this page only if the user is logged in and has an active account
if (
    !isset($_SESSION['user_id']) ||
    (int) ($_SESSION['user_status'] ?? 0) !== 1
) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';

?>


<main id="main-content" class="bg-light-grey">
    <div class="w-full max-w-7xl mx-auto min-h-80 py-8 px-3 rounded-2xl"> <!-- add flex here -->
        <section id="searchbar-section" class="mb-8 py-5 bg-white rounded-2xl border-2 border-mid-blue">
            <div id="main-searchbar" class="w-full px-3 max-w-7xl mx-auto">
                <!-- div containing search inpur and absolute svg icon. Input field has a peer class that hides icon when input is on focus (clicked) status.-->
                <div id="search-input-container" class="relative">
                    <label for="searchbar-input" class="sr-only">Search clients</label>
                    <input type="search" name="searchbar" id="searchbar-input"
                        placeholder="Client id, name, code, city or province"
                        class="peer w-full min-h-[40px] px-3 rounded-xl border-2 border-mid-blue outline:none placeholder:text-primary-grey placeholder:text-sm focus:outline-none focus:border-primary-green">

                    <!-- Serch lens svg icon-->
                    <svg width="25" height="25" viewBox="0 0 15 15" fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                        class="absolute hidden xs:block right-3 top-1/2 -translate-y-1/2 text-primary-grey transition-colors peer-focus:text-primary-green">
                        <!--peer-focus:hidden-->
                        <path fill-rule="evenodd" clip-rule="evenodd"
                            d="M10.3892 9.48238L13.0417 12.1349L12.158 13.0193L9.50798 10.3693C8.62446 11.0347 7.52171 11.3407 6.42163 11.2259C5.32156 11.111 4.30581 10.5837 3.57881 9.75016C2.85181 8.91659 2.46752 7.8386 2.50326 6.73312C2.53901 5.62764 2.99214 4.57673 3.77147 3.79187C4.55081 3.00701 5.59849 2.54647 6.70369 2.50291C7.80889 2.45935 8.88957 2.83602 9.72825 3.55711C10.5669 4.2782 11.1014 5.29019 11.224 6.38943C11.3466 7.48867 11.0484 8.59356 10.3892 9.48175V9.48238ZM10.0005 6.87488C10.0005 6.04608 9.67124 5.25122 9.08519 4.66517C8.49914 4.07912 7.70428 3.74988 6.87548 3.74988C6.04668 3.74988 5.25182 4.07912 4.66577 4.66517C4.07972 5.25122 3.75048 6.04608 3.75048 6.87488C3.75048 7.70368 4.07972 8.49854 4.66577 9.08459C5.25182 9.67064 6.04668 9.99988 6.87548 9.99988C7.70428 9.99988 8.49914 9.67064 9.08519 9.08459C9.67124 8.49854 10.0005 7.70368 10.0005 6.87488Z"
                            fill="currentColor" />
                    </svg>
                </div>
            </div>
        </section>

        <section id="dahboard-main-panel" class="bg-white rounded-2xl p-3 border-2 border-mid-blue">
            <h1 class="text-2xl py-3">All clients</h1>

            <div id="dash-action-bar" class="flex justify-between items-center gap-6 py-5">
                <a id="dash-new-cl-btn" href="./client-create.php" class="btn-custom btn-filled-green">+ New client</a>


                <label for="dash-filters" class="sr-only">Filter by status</label>
                <select name="dash-filters" id="dash-filters" aria-label="Filter by status"
                    class="w-auto max-w-32 md:max-w-48 px-3 py-1 border border-mid-blue rounded-lg bg-white text-primary-black focus:outline-none focus:border-primary-blue">
                    <!-- <option> tags for active/inactive status filters are NOT generated dynamically (only exception to dynamic generation system build for dropdowns)  -->
                    <option class="dash-filter" value="all">All</option> <!--JS fetch will use the attribute value for the GET API call-->
                    <option class="dash-filter" value="active">Active</option>
                    <option class="dash-filter" value="inactive">Inactive</option>
                </select>
            </div>

            <!--Invisible live region for screen readers only (WCAGG/EAA accessibility standards). Will be updated by JS -->
            <p id="clients-results-status" class="sr-only" role="status" aria-live="polite" aria-atomic="true"></p>
            <table id="dash-table" class="table-auto w-full rounded-xl overflow-hidden">
                <thead class="bg-mid-blue border-b-15 border-white"> <!-- exclude thead from PHP if -->
                    <tr>
                        <th scope="col" class="align-top text-center px-4 py-3 hidden sm:table-cell">Type</th>
                        <th scope="col" class="align-top text-left p-3">Name</th>
                        <th scope="col" class="align-top text-left p-3 hidden md:table-cell">Client code</th>
                        <th scope="col" class="align-top text-left p-3 hidden lg:table-cell">Location (city - province)</th>
                        <th scope="col" class="align-top text-center p-3 hidden md:table-cell">Active coupons</th>
                        <th scope="col" class="align-top text-center p-3">Status</th>
                        <th scope="col" class="align-top text-center p-3 hidden lg:table-cell">Actions</th>
                        <th scope="col" class="align-top text-center p-3 lg:hidden">Manage</th>
                    </tr>
                </thead>
                <tbody id="dash-table-body">
                    <!-- Placeholder row that will be replaced by dashboard.js through a call to GET clients.php API -->
                    <tr class="min-h-[30px] odd:bg-light-grey even:bg-white">
                        <td colspan="8" class="p-4 text-center">
                            Loading clients...
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination markup will be generated dynamically by pagination.js using data returned by the API -->
            <div id="dash-pagination"></div>
        </section>
    </div>

</main>

</div>


<footer></footer>

<script src="../assets/js/pagination.js"></script>
<script src="../assets/js/dashboard.js"></script>
</body>

</html>