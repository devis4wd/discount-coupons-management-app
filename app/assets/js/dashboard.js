/*
How this work (commplete sequence):
-- browser open public/dashboard.php page
-- dashboard.php renders n empty html table (no clients data still available)
-- this dashboard.js gets executed and does the following:
   --- get list of clients in dashboard table
    ---- use fetch to call GET /api/clients-api.php and receive output JSON returned by GET query (array with existing clients and their data);
    ---- generates frontend code with table rows in dahsboard.php using the renderClients() function, which generates the <tr> in dashboard.php;

   --- call again GET /api/clients-api.php when table filter has been changed in frontend page

   --- use fetch to call GET /api/clients-api.php?search=.. when user typed a search term in the search bar to show only certain clients in the table;

   --- use fetch to call PATCH /api/clients-api.php when deactivate/reactivate button is clicked on a single client row;

   --- use fetch to call DELETE /api/clients-api.php after permanent delete button has been cliecked and the confirmation pop-up was confirmed. 
*/

'use strict';

// ----------------------------------------
// API CALLS AND RELATED FRONTEND LOGIC
// ----------------------------------------

// ----------------------------------------
// GET — CLIENTS-API.PHP
// Retrieves the paginated client list using the status/search filters, then renders the dashboard rows and pagination.
// ----------------------------------------

//Save value of selected <option> in the dropdown filters
const dashboardStatusFilter = document.getElementById('dash-filters');
const clientsPagination = document.getElementById('dash-pagination');
const clientsResultsStatus = document.getElementById('clients-results-status');

//Keep track of the currently displayed page.
//This is useful when a client is activated/deactivated or deleted and the table must be reloaded without forcing the user back to page 1.
let currentClientsPage = 1;

//Every time a a different <option> filter is selected, run the loadClients()function passing the filter value as argument
dashboardStatusFilter.addEventListener('change', () => {
    loadClients(
        dashboardStatusFilter.value,
        searchbar.value.trim(), //keeps saved possible search filters too
        1 // a new filter always starts again from the first page (page = 1)
          // e.g. we're currently at pag.3 > we search a term > there are only 2 records matching 
          // the search > we can't stay at page 3  if each page show 10 records
    );

});

//REAL-TIME SEARCH filter: save the text typed into the searchbar-input. A timeout will delay the saving task by 0.5s from the last charachter typed, so the user 
// will have enough time to type everything before triggering a new loadClients() and update the list.
// The searched term will act as a filter passed by a new GET call, which will return only clients containing that text in one of their db properties.
const searchbar = document.getElementById('searchbar-input');

let searchTimeout;

searchbar.addEventListener('input', () => {

    //This will reset the timeor at every letter users will tipe. So the 500ms will run from the last typed letter only
    clearTimeout(searchTimeout);

    searchTimeout = setTimeout(() => {
        const searchTerm = searchbar.value.trim();
        //console.log(searchTerm);
        loadClients(
            dashboardStatusFilter.value, //keep saved the applied dashboard filters too (if any)
            searchTerm,
            1 //a new search always starts again from the first page 
              
        );
    }, 500);
});



const clientsTableBody = document.getElementById('dash-table-body');

//Function to retrieve the list of existing clients to show in dashboard.php through a GET API call (clients-api.php)
//This function accepts three arguments:
// - status (with 'all' as default value) means no status filter is applied
// - search (optional argument) filters clients by the text typed in the searchbar
// - page tells the backend which group of rows must be returned (each page will show 10 records, limit set by api)
// All values are sent via query string to the backend.
async function loadClients(status = 'all', search = '', page = 1) {
    try {
        const queryParams = new URLSearchParams({
            status,
            search,
            page,
        });

        const response = await fetch(`../api/clients-api.php?${queryParams.toString()}`, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
            },
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.error || 'Unable to retrieve clients');
        }

        //Save the page actually returned by the API (it may have been corrected by backend after a deletion).
        currentClientsPage = result.pagination.current_page;

        //Print frontend list and update pagination if everything is ok.
        renderClients(result.data);

        //Announce the updated result count to screen readers after filters and pagination changes (a <p> will work as invisible live region for screen readers).
        clientsResultsStatus.textContent = `${result.pagination.total_results} client${result.pagination.total_results === 1 ? '' : 's'} found.`;

        //Function defined in /assets/pagination.js (see my comment there to understand how it works)
        renderPagination(
            clientsPagination,
            result.pagination,
            (selectedPage) => loadClients(
                dashboardStatusFilter.value,
                searchbar.value.trim(),
                selectedPage
            )
        );

    } catch (error) {
        console.error(error);
        //Print this html <tr>> in dashboard.php in case sql query or db connection set in clients-api.php API is unsuccessful
        clientsTableBody.innerHTML = `
            <tr>
                <td colspan="8" class="p-4 text-center">
                    Unable to retrieve clients.
                </td>
            </tr>
        `;

        clientsPagination.innerHTML = '';
        clientsResultsStatus.textContent = 'Unable to retrieve clients.';
    }
}

//Print this html <tr>> in dashboard.php in case there are no existing clients in db despite a successful db connection and query exeution via clients-api.php API
function renderClients(clients) {
    if (clients.length === 0) {
        clientsTableBody.innerHTML = `
            <tr>
                <td colspan="8" class="p-4 text-center">
                    No clients found.
                </td>
            </tr>
        `;

        return;
    }

    clientsTableBody.innerHTML = clients
        .map((client) => createClientRow(client))
        .join('');

}


//Functions to render client tupe icon (icons are different for PR and CO clients)
const clientTypeIcons = {
    company: `
        <svg
            width="15"
            height="15"
            viewBox="0 0 15 15"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            class="text-primary-blue"
            aria-hidden="true"
            focusable="false"
        >
            <path
                d="M12 10H10.5V11.6667H12M12 6.66667H10.5V8.33333H12M13.5 13.3333H7.5V11.6667H9V10H7.5V8.33333H9V6.66667H7.5V5H13.5M6 3.33333H4.5V1.66667H6M6 6.66667H4.5V5H6M6 10H4.5V8.33333H6M6 13.3333H4.5V11.6667H6M3 3.33333H1.5V1.66667H3M3 6.66667H1.5V5H3M3 10H1.5V8.33333H3M3 13.3333H1.5V11.6667H3M7.5 3.33333V0H0V15H15V3.33333H7.5Z"
                fill="currentColor"
            />
        </svg>
    `,

    private: `
        <svg
            width="15"
            height="15"
            viewBox="0 0 15 15"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            class="text-primary-blue"
            aria-hidden="true"
            focusable="false"
        >
            <path
                d="M4.85156 6.39844C4.11719 5.66406 3.75 4.78125 3.75 3.75C3.75 2.71875 4.11719 1.83594 4.85156 1.10156C5.58594 0.367188 6.46875 0 7.5 0C8.53125 0 9.41406 0.367188 10.1484 1.10156C10.8828 1.83594 11.25 2.71875 11.25 3.75C11.25 4.78125 10.8828 5.66406 10.1484 6.39844C9.41406 7.13281 8.53125 7.5 7.5 7.5C6.46875 7.5 5.58594 7.13281 4.85156 6.39844ZM0 13.125V12.375C0 11.8437 0.136875 11.3556 0.410625 10.9106C0.684375 10.4656 1.0475 10.1256 1.5 9.89062C2.46875 9.40625 3.45312 9.04312 4.45312 8.80125C5.45312 8.55937 6.46875 8.43812 7.5 8.4375C8.53125 8.43687 9.54687 8.55812 10.5469 8.80125C11.5469 9.04437 12.5312 9.4075 13.5 9.89062C13.9531 10.125 14.3166 10.465 14.5903 10.9106C14.8641 11.3562 15.0006 11.8444 15 12.375V13.125C15 13.6406 14.8166 14.0822 14.4497 14.4497C14.0828 14.8172 13.6412 15.0006 13.125 15H1.875C1.35937 15 0.918125 14.8166 0.55125 14.4497C0.184375 14.0828 0.000625 13.6412 0 13.125Z"
                fill="currentColor"
            />
        </svg>
    `,
};

function renderClientTypeIcon(typeId) {
    return typeId === 2
        ? clientTypeIcons.company
        : clientTypeIcons.private;
}

function getClientTypeLabel(typeId) {
    return typeId === 2
        ? 'Company client'
        : 'Private client';
}


//Functions to render svg icons of different coupons on each client's row
const couponIconsSvg = {
    medical: `
    <svg width="43" height="17" viewBox="0 0 43 17" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"> 
    <path d="M40.6104 0C41.6228 0.000149904 42.4565 0.753303 42.5889 1.72949C42.2807 1.74066 42.0342 1.9928 42.0342 2.30371C42.0343 2.5819 42.2318 2.81353 42.4941 2.86719L42.6104 2.87891V3.74316C42.2923 3.74316 42.0342 4.00132 42.0342 4.31934C42.0343 4.59747 42.2318 4.82918 42.4941 4.88281L42.6104 4.89453V5.75781C41.0205 5.75781 39.7318 7.04698 39.7314 8.63672C39.7314 10.1273 40.8638 11.3534 42.3154 11.501L42.6104 11.5166V12.3799C42.2924 12.3799 42.0342 12.6381 42.0342 12.9561C42.0342 13.2344 42.2317 13.4669 42.4941 13.5205L42.6104 13.5322V14.3955C42.2923 14.3955 42.0342 14.6537 42.0342 14.9717C42.0344 15.2626 42.2509 15.4995 42.5312 15.5381C42.2916 16.3741 41.5233 16.9862 40.6104 16.9863H2C1.08683 16.9863 0.317637 16.3743 0.078125 15.5381C0.358958 15.4999 0.575959 15.2629 0.576172 14.9717C0.576172 14.6934 0.378666 14.4609 0.116211 14.4072L0 14.3955V13.5322C0.318012 13.5322 0.576172 13.2741 0.576172 12.9561C0.576155 12.6381 0.318002 12.3799 0 12.3799V11.5166C1.58992 11.5164 2.87891 10.2267 2.87891 8.63672C2.87853 7.04708 1.58969 5.75797 0 5.75781V4.89453C0.317893 4.89453 0.57598 4.63718 0.576172 4.31934C0.576172 4.00132 0.318012 3.74316 0 3.74316V2.87891C0.317941 2.87891 0.576056 2.62162 0.576172 2.30371C0.576172 1.99247 0.329149 1.74015 0.0205078 1.72949C0.152808 0.753142 0.987372 0 2 0H40.6104Z" fill="#FF72DE"/>
    <path d="M7.69957 5.45455H8.89808L10.9819 10.5426H11.0586L13.1424 5.45455H14.3409V12H13.4013V7.26349H13.3406L11.4102 11.9904H10.6303L8.69993 7.2603H8.6392V12H7.69957V5.45455ZM15.7767 12V5.45455H19.8804V6.30469H16.7643V8.29901H19.6663V9.14595H16.7643V11.1499H19.9188V12H15.7767ZM23.3185 12H21.1996V5.45455H23.3857C24.027 5.45455 24.5778 5.58558 25.038 5.84766C25.4982 6.1076 25.8509 6.48153 26.0959 6.96946C26.343 7.45526 26.4666 8.038 26.4666 8.71768C26.4666 9.3995 26.342 9.98544 26.0927 10.4755C25.8455 10.9656 25.4876 11.3427 25.0188 11.6069C24.5501 11.869 23.9833 12 23.3185 12ZM22.1871 11.1371H23.2642C23.7628 11.1371 24.1772 11.0433 24.5075 10.8558C24.8377 10.6662 25.0849 10.3924 25.2489 10.0344C25.413 9.67436 25.495 9.23544 25.495 8.71768C25.495 8.20419 25.413 7.76847 25.2489 7.41051C25.087 7.05256 24.8452 6.78089 24.5234 6.59553C24.2017 6.41016 23.8022 6.31747 23.3249 6.31747H22.1871V11.1371ZM28.6823 5.45455V12H27.6947V5.45455H28.6823ZM35.5569 7.5831H34.5597C34.5214 7.37003 34.45 7.18253 34.3456 7.0206C34.2412 6.85866 34.1134 6.72124 33.9621 6.60831C33.8108 6.49538 33.6414 6.41016 33.4539 6.35263C33.2686 6.2951 33.0715 6.26634 32.8627 6.26634C32.4855 6.26634 32.1478 6.36115 31.8495 6.55078C31.5534 6.74041 31.319 7.01847 31.1464 7.38494C30.9759 7.75142 30.8907 8.19886 30.8907 8.72727C30.8907 9.25994 30.9759 9.70952 31.1464 10.076C31.319 10.4425 31.5544 10.7195 31.8527 10.907C32.151 11.0945 32.4866 11.1882 32.8595 11.1882C33.0661 11.1882 33.2622 11.1605 33.4475 11.1051C33.635 11.0476 33.8044 10.9634 33.9557 10.8526C34.107 10.7418 34.2348 10.6065 34.3392 10.4467C34.4458 10.2848 34.5193 10.0994 34.5597 9.89062L35.5569 9.89382C35.5036 10.2156 35.4003 10.5117 35.2469 10.7823C35.0956 11.0508 34.9007 11.283 34.662 11.479C34.4255 11.6729 34.1549 11.8232 33.8502 11.9297C33.5455 12.0362 33.2132 12.0895 32.8531 12.0895C32.2863 12.0895 31.7813 11.9553 31.3382 11.6868C30.895 11.4162 30.5455 11.0295 30.2899 10.5266C30.0363 10.0238 29.9095 9.42401 29.9095 8.72727C29.9095 8.02841 30.0374 7.42862 30.2931 6.92791C30.5487 6.42507 30.8982 6.03942 31.3414 5.77095C31.7845 5.50035 32.2884 5.36506 32.8531 5.36506C33.2004 5.36506 33.5242 5.41513 33.8247 5.51527C34.1272 5.61328 34.3989 5.75817 34.6396 5.94993C34.8804 6.13956 35.0796 6.3718 35.2373 6.64666C35.395 6.91939 35.5015 7.23153 35.5569 7.5831Z" fill="#7C0761"/>
    </svg>`,
    physiotherapy: `
    <svg width="43" height="17" viewBox="0 0 43 17" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
    <path d="M40.6104 0C41.6227 0.000149919 42.4564 0.752466 42.5889 1.72852C42.2807 1.73967 42.0342 1.99186 42.0342 2.30273C42.0342 2.58106 42.2317 2.81357 42.4941 2.86719L42.6104 2.87891V3.74219C42.2923 3.74219 42.0342 4.00035 42.0342 4.31836C42.0342 4.59667 42.2317 4.82921 42.4941 4.88281L42.6104 4.89453V5.75781C41.0205 5.75781 39.7317 7.04688 39.7314 8.63672C39.7317 10.1271 40.8639 11.3534 42.3154 11.501L42.6104 11.5166V12.3799C42.2924 12.3799 42.0344 12.6372 42.0342 12.9551C42.0342 13.2334 42.2317 13.4659 42.4941 13.5195L42.6104 13.5312V14.3945C42.2923 14.3945 42.0342 14.6527 42.0342 14.9707C42.0342 15.2618 42.2508 15.4995 42.5312 15.5381C42.2914 16.3738 41.5231 16.9862 40.6104 16.9863H2C1.08706 16.9863 0.317844 16.374 0.078125 15.5381C0.359097 15.4999 0.576104 15.2621 0.576172 14.9707C0.576172 14.6527 0.318012 14.3945 0 14.3945V13.5312C0.318012 13.5312 0.576172 13.2731 0.576172 12.9551C0.576019 12.6769 0.378542 12.4452 0.116211 12.3916L0 12.3799V11.5166C1.58978 11.5164 2.87868 10.2265 2.87891 8.63672C2.87865 7.04698 1.58976 5.75797 0 5.75781V4.89453C0.317991 4.89453 0.576138 4.63634 0.576172 4.31836C0.576172 4.00035 0.318012 3.74219 0 3.74219V2.87891C0.318012 2.87891 0.576172 2.62075 0.576172 2.30273C0.57613 1.99153 0.329128 1.73917 0.0205078 1.72852C0.152934 0.752306 0.987472 0 2 0H40.6104Z" fill="#27D4F3"/>
    <path d="M10.0243 11.2656V4.72017H12.3574C12.8666 4.72017 13.2885 4.81285 13.623 4.99822C13.9575 5.18359 14.2079 5.43714 14.3741 5.75888C14.5403 6.07848 14.6234 6.43857 14.6234 6.83913C14.6234 7.24183 14.5392 7.60405 14.3709 7.92578C14.2047 8.24538 13.9533 8.49893 13.6166 8.68643C13.2821 8.8718 12.8613 8.96449 12.3542 8.96449H10.7498V8.12713H12.2647C12.5864 8.12713 12.8474 8.07173 13.0477 7.96094C13.248 7.84801 13.395 7.6946 13.4888 7.50071C13.5825 7.30682 13.6294 7.08629 13.6294 6.83913C13.6294 6.59197 13.5825 6.37251 13.4888 6.18075C13.395 5.98899 13.2469 5.83878 13.0445 5.73011C12.8442 5.62145 12.58 5.56712 12.2519 5.56712H11.0119V11.2656H10.0243ZM15.7811 11.2656V4.72017H16.7687V7.56463H20.0318V4.72017H21.0226V11.2656H20.0318V8.41158H16.7687V11.2656H15.7811ZM21.9558 4.72017H23.0776L24.7875 7.69567H24.8578L26.5677 4.72017H27.6895L25.3149 8.69602V11.2656H24.3305V8.69602L21.9558 4.72017ZM32.1919 6.43963C32.1578 6.13707 32.0172 5.9027 31.77 5.73651C31.5229 5.56818 31.2118 5.48402 30.8368 5.48402C30.5683 5.48402 30.3361 5.52663 30.14 5.61186C29.944 5.69496 29.7917 5.81001 29.683 5.95703C29.5765 6.10192 29.5232 6.26705 29.5232 6.45241C29.5232 6.60795 29.5594 6.74219 29.6319 6.85511C29.7065 6.96804 29.8034 7.06285 29.9227 7.13956C30.0442 7.21413 30.1741 7.27699 30.3126 7.32812C30.4511 7.37713 30.5843 7.41761 30.7121 7.44957L31.3513 7.61577C31.5601 7.6669 31.7743 7.73615 31.9937 7.82351C32.2132 7.91087 32.4167 8.02592 32.6042 8.16868C32.7917 8.31143 32.943 8.48828 33.058 8.69922C33.1752 8.91016 33.2338 9.16264 33.2338 9.45668C33.2338 9.82741 33.1379 10.1566 32.9462 10.4442C32.7565 10.7319 32.4806 10.9588 32.1184 11.125C31.7583 11.2912 31.3226 11.3743 30.8112 11.3743C30.3212 11.3743 29.8972 11.2965 29.5392 11.141C29.1812 10.9854 28.9011 10.7649 28.6986 10.4794C28.4962 10.1918 28.3844 9.85085 28.3631 9.45668H29.3538C29.373 9.69318 29.4497 9.89027 29.5839 10.0479C29.7203 10.2035 29.894 10.3196 30.1049 10.3963C30.318 10.4709 30.5513 10.5082 30.8048 10.5082C31.0839 10.5082 31.3322 10.4645 31.5495 10.3771C31.769 10.2876 31.9415 10.1641 32.0672 10.0064C32.193 9.84659 32.2558 9.66016 32.2558 9.44709C32.2558 9.2532 32.2004 9.09446 32.0896 8.97088C31.981 8.8473 31.8329 8.74503 31.6454 8.66406C31.46 8.5831 31.2501 8.51172 31.0158 8.44993L30.2423 8.23899C29.7182 8.09624 29.3027 7.88636 28.9959 7.60938C28.6912 7.33239 28.5388 6.96591 28.5388 6.50994C28.5388 6.13281 28.6411 5.80362 28.8457 5.52237C29.0502 5.24112 29.3272 5.02273 29.6766 4.86719C30.0261 4.70952 30.4202 4.63068 30.8592 4.63068C31.3023 4.63068 31.6933 4.70845 32.0321 4.86399C32.373 5.01953 32.6415 5.23366 32.8375 5.50639C33.0335 5.77699 33.1358 6.08807 33.1443 6.43963H32.1919Z" fill="#095462"/>
    </svg>`,
    all: `
    <svg width="43" height="17" viewBox="0 0 43 17" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
    <path d="M40.6104 0C41.6229 0.000149934 42.4567 0.753111 42.5889 1.72949C42.2808 1.74066 42.0344 1.992 42.0342 2.30273C42.0342 2.58104 42.2317 2.81355 42.4941 2.86719L42.6104 2.87891V3.74316C42.2924 3.74316 42.0343 4.00045 42.0342 4.31836C42.0342 4.59668 42.2317 4.82918 42.4941 4.88281L42.6104 4.89453V5.75781C41.0203 5.75781 39.7314 7.04764 39.7314 8.6377C39.7317 10.128 40.864 11.3534 42.3154 11.501L42.6104 11.5166V12.3799C42.2923 12.3799 42.0342 12.638 42.0342 12.9561C42.0344 13.2342 42.2318 13.4659 42.4941 13.5195L42.6104 13.5312V14.3955C42.2924 14.3955 42.0343 14.6528 42.0342 14.9707C42.0342 15.2618 42.2507 15.4995 42.5312 15.5381C42.2915 16.3739 41.5232 16.9862 40.6104 16.9863H2C1.08697 16.9863 0.317759 16.3741 0.078125 15.5381C0.359123 15.4999 0.576172 15.2622 0.576172 14.9707C0.576103 14.6924 0.378676 14.4598 0.116211 14.4062L0 14.3955V13.5312C0.317888 13.5312 0.575972 13.2739 0.576172 12.9561C0.576172 12.6777 0.378671 12.4452 0.116211 12.3916L0 12.3799V11.5166C1.58972 11.5164 2.87858 10.2274 2.87891 8.6377C2.87891 7.04773 1.58992 5.75797 0 5.75781V4.89453C0.318012 4.89453 0.576172 4.63637 0.576172 4.31836C0.576068 4.04017 0.378564 3.80853 0.116211 3.75488L0 3.74316V2.87891C0.318012 2.87891 0.576172 2.62075 0.576172 2.30273C0.575981 1.99167 0.329023 1.74015 0.0205078 1.72949C0.152636 0.752951 0.987236 0 2 0H40.6104Z" fill="#27F2C2"/>
    <path d="M14.5411 12H13.4928L15.8482 5.45455H16.9892L19.3447 12H18.2964L16.4459 6.64347H16.3948L14.5411 12ZM14.7168 9.43679H18.1174V10.2678H14.7168V9.43679ZM20.2899 12V5.45455H21.2775V11.1499H24.2434V12H20.2899ZM25.37 12V5.45455H26.3576V11.1499H29.3235V12H25.37Z" fill="#095B47"/>
    </svg>`,
    none: `
    <svg width="43" height="17" viewBox="0 0 43 17" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
    <path d="M40.6104 0C41.6273 0.000150556 42.4641 0.759697 42.5908 1.74219C42.2818 1.75234 42.0344 2.005 42.0342 2.31641C42.0342 2.59472 42.2317 2.82722 42.4941 2.88086L42.6104 2.89258V3.75684C42.2924 3.75684 42.0343 4.01412 42.0342 4.33203C42.0342 4.61035 42.2317 4.84286 42.4941 4.89648L42.6104 4.9082V5.77148C41.0203 5.77148 39.7314 7.06131 39.7314 8.65137C39.7317 10.1417 40.864 11.3671 42.3154 11.5146L42.6104 11.5303V12.3936C42.2923 12.3936 42.0342 12.6517 42.0342 12.9697C42.0344 13.2479 42.2318 13.4796 42.4941 13.5332L42.6104 13.5449V14.4092C42.2924 14.4092 42.0343 14.6664 42.0342 14.9844C42.0342 15.2742 42.2487 15.5115 42.5273 15.5518C42.2831 16.3806 41.5182 16.9862 40.6104 16.9863H2C1.09195 16.9863 0.326102 16.3808 0.0820312 15.5518C0.361153 15.5119 0.576172 15.2745 0.576172 14.9844C0.576103 14.7061 0.378676 14.4735 0.116211 14.4199L0 14.4092V13.5449C0.317888 13.5449 0.575972 13.2876 0.576172 12.9697C0.576172 12.6914 0.378671 12.4589 0.116211 12.4053L0 12.3936V11.5303C1.58972 11.5301 2.87858 10.2411 2.87891 8.65137C2.87891 7.06141 1.58992 5.77164 0 5.77148V4.9082C0.318012 4.9082 0.576172 4.65004 0.576172 4.33203C0.576068 4.05384 0.378564 3.82221 0.116211 3.76855L0 3.75684V2.89258C0.318012 2.89258 0.576172 2.63442 0.576172 2.31641C0.57598 2.00467 0.327997 1.75183 0.0185547 1.74219C0.145198 0.75954 0.982916 0 2 0H40.6104Z" fill="#EBEBEB"/>
    <path d="M14.0021 5.45455V12H13.0945L9.7674 7.19957H9.70668V12H8.71911V5.45455H9.63317L12.9634 10.2614H13.0241V5.45455H14.0021ZM21.1285 8.72727C21.1285 9.42614 21.0006 10.027 20.7449 10.5298C20.4893 11.0305 20.1388 11.4162 19.6934 11.6868C19.2503 11.9553 18.7464 12.0895 18.1817 12.0895C17.615 12.0895 17.1089 11.9553 16.6636 11.6868C16.2204 11.4162 15.871 11.0295 15.6153 10.5266C15.3596 10.0238 15.2318 9.42401 15.2318 8.72727C15.2318 8.02841 15.3596 7.42862 15.6153 6.92791C15.871 6.42507 16.2204 6.03942 16.6636 5.77095C17.1089 5.50035 17.615 5.36506 18.1817 5.36506C18.7464 5.36506 19.2503 5.50035 19.6934 5.77095C20.1388 6.03942 20.4893 6.42507 20.7449 6.92791C21.0006 7.42862 21.1285 8.02841 21.1285 8.72727ZM20.1505 8.72727C20.1505 8.1946 20.0642 7.74609 19.8916 7.38175C19.7211 7.01527 19.4868 6.73828 19.1885 6.55078C18.8923 6.36115 18.5567 6.26634 18.1817 6.26634C17.8046 6.26634 17.468 6.36115 17.1718 6.55078C16.8756 6.73828 16.6412 7.01527 16.4687 7.38175C16.2982 7.74609 16.213 8.1946 16.213 8.72727C16.213 9.25994 16.2982 9.70952 16.4687 10.076C16.6412 10.4403 16.8756 10.7173 17.1718 10.907C17.468 11.0945 17.8046 11.1882 18.1817 11.1882C18.5567 11.1882 18.8923 11.0945 19.1885 10.907C19.4868 10.7173 19.7211 10.4403 19.8916 10.076C20.0642 9.70952 20.1505 9.25994 20.1505 8.72727ZM27.6428 5.45455V12H26.7351L23.408 7.19957H23.3473V12H22.3597V5.45455H23.2738L26.604 10.2614H26.6648V5.45455H27.6428ZM29.0834 12V5.45455H33.1871V6.30469H30.0709V8.29901H32.9729V9.14595H30.0709V11.1499H33.2254V12H29.0834Z" fill="#6C757D"/>
    </svg>`,
};

//Then create function to render svg icons in frontend
function renderCouponIcon(type) {
    const svg = couponIconsSvg[type] ?? couponIconsSvg.none;
    const label = getCouponLabel(type);

    return `
        <span
            role="img"
            aria-label="${label}"
            title="${label}"
        >
            ${svg}
        </span>
    `;
}

//Define labels that will be applied to svg coupon icon for accessibility, based on the printed frontend icon
function getCouponLabel(type) {
    const labels = {
        medical: 'Medical coupons',
        physiotherapy: 'Physiotherapy coupons',
        all: 'Medical and physiotherapy coupons',
        none: 'No active coupons',
    };

    return labels[type] ?? labels.none;
}

//Define function to decide which action icons must be rendered based on the current client.status
const toggleStatusIcons = {
    active: `
        <svg aria-hidden="true" focusable="false" width="21" height="21" viewBox="0 0 21 21" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-primary-blue">
            <path
                d="M3.05288 17.1929C2.09778 16.2704 1.33596 15.167 0.811868 13.9469C0.287778 12.7269 0.0119157 11.4147 0.000377568 10.0869C-0.0111606 8.7591 0.241856 7.44231 0.744665 6.21334C1.24747 4.98438 1.99001 3.86786 2.92893 2.92893C3.86786 1.99001 4.98438 1.24747 6.21334 0.744665C7.44231 0.241856 8.7591 -0.0111606 10.0869 0.000377568C11.4147 0.0119157 12.7269 0.287778 13.9469 0.811868C15.167 1.33596 16.2704 2.09778 17.1929 3.05288C19.0145 4.9389 20.0224 7.46493 19.9996 10.0869C19.9768 12.7089 18.9251 15.217 17.0711 17.0711C15.217 18.9251 12.7089 19.9768 10.0869 19.9996C7.46493 20.0224 4.9389 19.0145 3.05288 17.1929ZM15.7829 15.7829C17.284 14.2818 18.1273 12.2458 18.1273 10.1229C18.1273 7.99997 17.284 5.96401 15.7829 4.46288C14.2818 2.96176 12.2458 2.11843 10.1229 2.11843C7.99997 2.11843 5.96401 2.96176 4.46288 4.46288C2.96176 5.96401 2.11843 7.99997 2.11843 10.1229C2.11843 12.2458 2.96176 14.2818 4.46288 15.7829C5.96401 17.284 7.99997 18.1273 10.1229 18.1273C12.2458 18.1273 14.2818 17.284 15.7829 15.7829ZM7.12288 6.12288H9.12288V14.1229H7.12288V6.12288ZM11.1229 6.12288H13.1229V14.1229H11.1229V6.12288Z"
                fill="currentColor"
            />
        </svg>
    `,

    inactive: `
        <svg aria-hidden="true" focusable="false" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-primary-blue">
            <path
                d="M3.05957 13.0001C3.30499 15.1939 4.34816 17.2211 5.99071 18.696C7.63325 20.1709 9.76059 20.9907 11.9681 20.9995C14.1757 21.0082 16.3094 20.2053 17.9636 18.7434C19.6178 17.2816 20.677 15.2628 20.9398 13.0709C21.2026 10.879 20.6506 8.66702 19.3889 6.85557C18.1271 5.04412 16.2437 3.75958 14.0966 3.24624C11.9496 2.7329 9.68873 3.02658 7.74408 4.07141C5.79944 5.11625 4.30663 6.83937 3.54957 8.91306"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
            <path
                d="M11.2929 12.7071C11.1054 12.5195 11 12.2652 11 12C11 11.7348 11.1054 11.4804 11.2929 11.2929C11.4804 11.1053 11.7348 11 12 11C12.2652 11 12.5196 11.1053 12.7071 11.2929C12.8946 11.4804 13 11.7348 13 12C13 12.2652 12.8946 12.5195 12.7071 12.7071C12.5196 12.8946 12.2652 13 12 13C11.7348 13 11.4804 12.8946 11.2929 12.7071Z"
                fill="currentColor"
            />
            <path
                d="M3 4.00098V9.00098H8M11 12C11 12.2652 11.1054 12.5195 11.2929 12.7071C11.4804 12.8946 11.7348 13 12 13C12.2652 13 12.5196 12.8946 12.7071 12.7071C12.8946 12.5195 13 12.2652 13 12C13 11.7348 12.8946 11.4804 12.7071 11.2929C12.5196 11.1053 12.2652 11 12 11C11.7348 11 11.4804 11.1053 11.2929 11.2929C11.1054 11.4804 11 11.7348 11 12Z"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
        </svg>
    `
};

function renderToggleStatusIcon(status) {
    return status === 1
        ? toggleStatusIcons.active
        : toggleStatusIcons.inactive;
}



//Define function that prints a frontend <tr> in dashboard.php table for each client retrieved thrugh GET clients-api.php API
function createClientRow(client) {

    const clientTypeLabel = getClientTypeLabel(client.type_id);

    const statusLabel =
        client.status === 1 ? 'Active' : 'Inactive';


    const statusClass =
        client.status === 1
            ? 'status-tag-active'
            : 'status-tag-inactive';

    const toggleActionLabel = client.status === 1
        ? `Deactivate ${client.name}`
        : `Reactivate ${client.name}`;


    return `
        <tr class="min-h-[30px] odd:bg-light-grey even:bg-white" data-client-id="${client.id}">
            <td data-label="Client Type" class="p-3 hidden sm:table-cell">
                <div class="flex items-center justify-center">
                    <span class="sr-only">
                        ${clientTypeLabel}
                    </span>
                    ${renderClientTypeIcon(client.type_id)}
                </div>
            </td>

            <td data-label="Client Name" class="text-left align-middle p-3">
                ${escapeHtml(client.name)}
            </td>

            <td data-label="Client Code" class="text-left align-middle p-3 hidden md:table-cell">
                ${escapeHtml(client.client_code)}
            </td>

            <td data-label="Client Location" class="text-left align-middle p-3 hidden lg:table-cell">
                ${formatLocation(client.city, client.province)}
            </td>

            <td data-label="Type of coupons linked to client" class="text-center align-middle p-3 hidden md:table-cell">
                <div class="flex items-center justify-center">
                    ${renderCouponIcon(client.coupon_service_type)}
                </div>
            </td>

            <td data-label="Client status" class="text-center align-middle p-3">
                <span class="status-tag ${statusClass}">
                    ${statusLabel}
                </span>
            </td>

            <td data-label="Actions to manage Client" class="text-center align-middle p-3 hidden lg:table-cell">
                <div role="group" aria-label="Action shortcut buttons to manage client" class="flex justify-between items-center">
                    <a href="./client-details.php?id=${client.id}" aria-label="Go to client detail page" title="See ${client.name} details" class="mx-2 cursor-pointer">
                        <svg aria-hidden="true" focusable="false" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-primary-blue">
                            <path
                                d="M11 12C9.9 12 8.95833 11.6083 8.175 10.825C7.39167 10.0417 7 9.1 7 8C7 6.9 7.39167 5.95833 8.175 5.175C8.95833 4.39167 9.9 4 11 4C12.1 4 13.0417 4.39167 13.825 5.175C14.6083 5.95833 15 6.9 15 8C15 9.1 14.6083 10.0417 13.825 10.825C13.0417 11.6083 12.1 12 11 12ZM21.4 22.8L18.9 20.3C18.55 20.5 18.175 20.6667 17.775 20.8C17.375 20.9333 16.95 21 16.5 21C15.25 21 14.1877 20.5627 13.313 19.688C12.4383 18.8133 12.0007 17.7507 12 16.5C11.9993 15.2493 12.437 14.187 13.313 13.313C14.189 12.439 15.2513 12.0013 16.5 12C17.7487 11.9987 18.8113 12.4363 19.688 13.313C20.5647 14.1897 21.002 15.252 21 16.5C21 16.95 20.9333 17.375 20.8 17.775C20.6667 18.175 20.5 18.55 20.3 18.9L22.8 21.4C22.9833 21.5833 23.075 21.8167 23.075 22.1C23.075 22.3833 22.9833 22.6167 22.8 22.8C22.6167 22.9833 22.3833 23.075 22.1 23.075C21.8167 23.075 21.5833 22.9833 21.4 22.8ZM18.275 18.275C18.7583 17.7917 19 17.2 19 16.5C19 15.8 18.7583 15.2083 18.275 14.725C17.7917 14.2417 17.2 14 16.5 14C15.8 14 15.2083 14.2417 14.725 14.725C14.2417 15.2083 14 15.8 14 16.5C14 17.2 14.2417 17.7917 14.725 18.275C15.2083 18.7583 15.8 19 16.5 19C17.2 19 17.7917 18.7583 18.275 18.275ZM5 20C4.45 20 3.97933 19.8043 3.588 19.413C3.19667 19.0217 3.00067 18.5507 3 18V17.225C3 16.6583 3.14167 16.1333 3.425 15.65C3.70833 15.1667 4.1 14.8 4.6 14.55C5.35 14.1667 6.17933 13.8377 7.088 13.563C7.99667 13.2883 8.99233 13.109 10.075 13.025C10.275 13.0083 10.425 13.0917 10.525 13.275C10.625 13.4583 10.6333 13.65 10.55 13.85C10.3667 14.2667 10.229 14.696 10.137 15.138C10.045 15.58 9.99933 16.0257 10 16.475C10 16.9083 10.0377 17.346 10.113 17.788C10.1883 18.23 10.3173 18.6507 10.5 19.05C10.6 19.2833 10.5917 19.5 10.475 19.7C10.3583 19.9 10.1833 20 9.95 20H5Z"
                                fill="currentColor"
                            />
                        </svg>
                    </a>

                    <button type="button" aria-label="${escapeHtml(toggleActionLabel)}" title="${escapeHtml(toggleActionLabel)}" class="client-toggle-status mx-2 cursor-pointer" data-client-id="${client.id}">
                        ${renderToggleStatusIcon(client.status)}
                    </button>

                    <button type="button" aria-label="Permanently delete client" title="Permanently delete ${client.name}" class="hard-delete-client mx-2 cursor-pointer" data-client-id="${client.id}">
                        <svg aria-hidden="true" focusable="false" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-primary-blue">
                            <path
                                d="M7 21C6.45 21 5.97933 20.8043 5.588 20.413C5.19667 20.0217 5.00067 19.5507 5 19V6H4V4H9V3H15V4H20V6H19V19C19 19.55 18.8043 20.021 18.413 20.413C18.0217 20.805 17.5507 21.0007 17 21H7ZM9 17H11V8H9V17ZM13 17H15V8H13V17Z"
                                fill="currentColor"
                            />
                        </svg>
                    </button>
                </div>
            </td>

            <td class="text-center align-middle px-4 py-3 lg:hidden">
                <div class="flex items-center justify-center">
                    <a href="./client-details.php?id=${client.id}" title="Open client details page" aria-label="Open client details page" class="inline-block px-2">
                        <svg aria-hidden="true" focusable="false" width="12" height="24" viewBox="0 0 12 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-primary-blue">
                            <path
                                d="M2.45199 6.57999L3.51299 5.51999L9.29199 11.297C9.38514 11.3896 9.45907 11.4996 9.50952 11.6209C9.55997 11.7421 9.58594 11.8722 9.58594 12.0035C9.58594 12.1348 9.55997 12.2648 9.50952 12.3861C9.45907 12.5073 9.38514 12.6174 9.29199 12.71L3.51299 18.49L2.45299 17.43L7.87699 12.005L2.45199 6.57999Z"
                                fill="currentColor"
                            />
                        </svg>
                    </a>
                </div>
            </td>
        </tr>
    `;
}

function formatLocation(city, province) {
    const parts = [city, province].filter(Boolean);
    //If valid cahrachters are found (parts),sanitize and join them with ' - '. If no values (parts) are found, print a '—'
    return parts.length > 0 ? parts.map(escapeHtml).join(' - ') : '—';
}

/*
Sample of what above function does:
formatLocation('Roma', 'RM')     // → 'Roma - RM'
formatLocation('Milano', '')     // → 'Milano'
formatLocation('', 'MI')         // → 'MI'
formatLocation(null, undefined)  // → '—'
formatLocation('', '')           // → '—'
*/


//Apply 'sanitize then display' approach to the data retrieved via API call 
//Basically I used thi sfunction only in pages where JS generates HTML with innerHTML using data retrieved from the DB.
//If malicious HTML was somehow stored in the DB instead of the expected data, escaping these characters prevents the browser from interpreting it as actual HTML code.
function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

loadClients(); //first execution of loadClients() at the first age load will always have 'all' as applied argument



// ----------------------------------------
// PATCH — CLIENTS-API.PHP
// Toggles a client's active/inactive status (soft delete) from the dashboard and refreshes the current filtered page.
// ----------------------------------------

async function toggleClientStatus(button) {

    const clientId = button.dataset.clientId;

    try {

        button.disabled = true;

        const response = await fetch(
            //The '&action=edit-info' will tell the clients-api.php  what kind of PATCH call I'm doing from here
            //cause that api handles two different PATCH calls: one from this dashboard page and from the client-edit page to manage active/inactive state only
            //  and one to edit all the other client info this client-edit page
            `../api/clients-api.php?id=${clientId}&action=toggle-status`,
            {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                },
            }
        );

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(
                result.error || 'Unable to update client status'
            );
        }

        // So far I opted for reloading the whole tabel for easier maintenance instead of uloading the single table row
        await loadClients(dashboardStatusFilter.value, searchbar.value.trim(), currentClientsPage);

        //The old button has been removed by the table redraw. Restore focus to the equivalent new action
        //when the row is still visible; otherwise use the stable status filter as a fallback.
        const refreshedToggle = clientsTableBody.querySelector(`.client-toggle-status[data-client-id="${clientId}"]`);
        (refreshedToggle || dashboardStatusFilter).focus();

    } catch (error) {

        console.error(error);

        alert(error.message);

    } finally {
        button.disabled = false;

    }
}


// ----------------------------------------
// DELETE — CLIENTS-API.PHP
// Permanently deletes a client (hard delete) after confirmation and refreshes the current filtered page.
// ----------------------------------------

async function hardDeleteClient(button) {

    if (!confirm('Are you sure you want to permanently delete this client?')) {
        return;
    }

    const clientId = button.dataset.clientId;

    try {

        button.disabled = true;

        const response = await fetch(
            `../api/clients-api.php?id=${clientId}`,
            {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                },
            }
        );

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(
                result.error || 'Unable to delete client'
            );
        }

        // So far I opted for reloading the whole table for easier maintenance instead of uloading the single table row
        await loadClients(dashboardStatusFilter.value, searchbar.value.trim(), currentClientsPage);

        //The deleted row no longer exists after the redraw, so move focus to a stable control near the updated table.
        dashboardStatusFilter.focus();

    } catch (error) {

        console.error(error);

        alert(error.message);

    } finally {

        button.disabled = false;

    }
}


// ----------------------------------------
// SHARED TABLE EVENT HANDLER — PATCH / DELETE
// Delegates row actions to the status-toggle or permanent-delete functions even after the table body is re-rendered.
// ----------------------------------------

// Basically I wanna make sure that even if the whole table body gets rebuilt via clientsTableBody.innerHTML, the eventListener which
// triggers these two kinds of api calls threough the buttons with toggle and delete classes will keep working aeven after the DOM has been rebuilt. 

clientsTableBody.addEventListener('click', handleClientsTableClick);

function handleClientsTableClick(event) {
    const toggleButton = event.target.closest('.client-toggle-status');
    const deleteButton = event.target.closest('.hard-delete-client');

    if (toggleButton) {
        toggleClientStatus(toggleButton);
        return;
    }

    if (deleteButton) {
        hardDeleteClient(deleteButton);
    }
}
