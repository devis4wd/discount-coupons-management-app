'use strict';

/*
Reusable pagination renderer used by pages that load table rows through API calls.

The API decides how many rows belong to each page and returns a pagination object.
This function only builds the frontend controls and tells the page-specific JS
which page the user selected through the onPageChange callback function, which is the third argument.

QUICK LOGICK OVERVIEW OF PAGINATION CODE

Think of the pagination flow as 4 parts passing information to each other:
1. DASHBOARD
   Calls renderPagination() and passes it:
   * the pagination container
   * pagination data returned by the API
   * a callback function to execute when the user selects another page
2. PAGINATION
   Builds the pagination HTML and registers the click handler.
   It does NOT know anything about clients, APIs or loadClients().
   Its only job is to detect which page the user selected.
3. USER CLICK
   When a pagination button is clicked, pagination.js reads its data-page value
   and calls onPageChange(selected page number).
4. BACK TO DASHBOARD
   onPageChange is the callback originally passed by dashboard.js.
   The selected page number becomes the callback's selectedPage parameter,
   which is then passed to loadClients(..., selectedPage).
This starts a new GET request for that page, and the whole cycle repeats.

Example:
user clicks page 3
→ pagination.js detects data-page="3"
→ onPageChange(3)
→ selectedPage becomes 3
→ loadClients(..., 3)
→ API returns page 3 data
→ pagination is rendered again
*/

function renderPagination(container, pagination, onPageChange) {

    //Define 4 variables to store the values returned by the api and saved inside the pagination object.
    const {
        current_page: currentPage,
        rows_per_page: rowsPerPage,
        total_results: totalResults,
        total_pages: totalPages,
    } = pagination;

    //If there are no results, there is nothing to paginate.
    if (totalResults === 0 || totalPages === 0) {
        container.innerHTML = '';
        return;
    }

    //Calculate the first and last result numbers currently visible in the table.
    //Example: page 2 with 10 rows per page -> results 11 to 20.
    const firstVisibleResult = ((currentPage - 1) * rowsPerPage) + 1;
    const lastVisibleResult = Math.min(currentPage * rowsPerPage, totalResults);

    //For this project the number of pages is expected to stay small, so all page
    //numbers are rendered directly. This keeps the logic easy to read and maintain.
    let pageButtons = '';

    for (let page = 1; page <= totalPages; page++) {

        const isCurrentPage = page === currentPage;

        pageButtons += `
            <button
                type="button"
                class="pagination-page-btn relative inline-flex items-center px-3 py-1 text-sm font-semibold cursor-pointer disabled:cursor-not-allowed
                    ${isCurrentPage
                        ? 'z-10 bg-primary-blue text-white'
                        : 'text-primary-blue inset-ring inset-ring-mid-blue hover:bg-gray-50'}"
                data-page="${page}"
                ${isCurrentPage ? 'aria-current="page"' : ''}
                ${isCurrentPage ? 'disabled' : ''}
            >
                ${page}
            </button>
        `;
    }

    container.innerHTML = `
        <div class="flex items-center justify-between border-t border-gray-200 bg-white my-8 px-4 py-4 sm:px-6">

            <!-- On small screens only Previous / Next controls are shown -->
            <div class="flex flex-1 justify-between sm:hidden">
                <button
                    type="button"
                    class="pagination-prev-btn relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"
                    ${currentPage === 1 ? 'disabled' : ''}
                >
                    Previous
                </button>

                <button
                    type="button"
                    class="pagination-next-btn relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"
                    ${currentPage === totalPages ? 'disabled' : ''}
                >
                    Next
                </button>
            </div>

            <!-- On larger screens also show the result counter and page numbers -->
            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                <p class="text-sm text-gray-700">
                    Showing
                    <span class="font-medium">${firstVisibleResult}</span>
                    to
                    <span class="font-medium">${lastVisibleResult}</span>
                    of
                    <span class="font-medium">${totalResults}</span>
                    results
                </p>

                <nav aria-label="Pagination" tabindex="-1" class="isolate inline-flex -space-x-px rounded-md shadow-xs">
                    <button
                        type="button"
                        class="pagination-prev-btn relative inline-flex items-center rounded-l-md px-2 py-2 text-primary-grey inset-ring inset-ring-mid-blue hover:bg-gray-50 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                        aria-label="Previous page"
                        ${currentPage === 1 ? 'disabled' : ''}
                    >
                        <span aria-hidden="true">&lsaquo;</span>
                    </button>

                    ${pageButtons}

                    <button
                        type="button"
                        class="pagination-next-btn relative inline-flex items-center rounded-r-md px-2 py-2 text-primary-grey inset-ring inset-ring-mid-blue hover:bg-gray-50 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                        aria-label="Next page"
                        ${currentPage === totalPages ? 'disabled' : ''}
                    >
                        <span aria-hidden="true">&rsaquo;</span>
                    </button>
                </nav>
            </div>
        </div>
    `;

    //Event delegation keeps one listener on the pagination container even though
    //its buttons are completely rebuilt every time a new page is loaded. This will be used to keep track of the selected currentPage and update the pagination track and the result.
    container.onclick = (event) => {

        const pageButton = event.target.closest('.pagination-page-btn');
        const previousButton = event.target.closest('.pagination-prev-btn');
        const nextButton = event.target.closest('.pagination-next-btn');

        let selectedPage = null;

        if (pageButton) {
            selectedPage = Number(pageButton.dataset.page);
        } else if (previousButton && !previousButton.disabled) {
            selectedPage = currentPage - 1;
        } else if (nextButton && !nextButton.disabled) {
            selectedPage = currentPage + 1;
        }

        if (selectedPage === null) {
            return;
        }

        //The page-specific callback normally rebuilds this pagination markup after the new API response.
        //Once that asynchronous update is complete, move focus to the newly rendered pagination navigation
        //so keyboard users don't lose their position when the button they activated disappears from the DOM.
        Promise.resolve(onPageChange(selectedPage)).then(() => {
            const paginationNav = container.querySelector('nav[aria-label="Pagination"]');

            if (paginationNav && paginationNav.offsetParent !== null) {
                paginationNav.focus();
                return;
            }

            //On small screens the desktop <nav> is hidden, so keep focus inside the visible
            //Previous / Next controls instead of leaving it on an element removed from the DOM.
            const visibleMobileControl = [...container.querySelectorAll('.pagination-prev-btn, .pagination-next-btn')]
                .find((button) => button.offsetParent !== null && !button.disabled);

            visibleMobileControl?.focus();
        });
    };
}
