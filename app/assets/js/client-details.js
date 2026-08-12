'use strict';

// ----------------------------------------
// API CALLS AND RELATED FRONTEND LOGIC
// ----------------------------------------

// ----------------------------------------
// GET — DROPDOWN-MENUS-API.PHP
// Retrieves the discount-rule options and related metadata used by the New Coupon form.
// ----------------------------------------
async function loadUpdatedDropdown(selectTag) {
    //This will read the data-dropdown-type attribute of the <select> (function argument) so the API will know which DB table retrive the data from
    const dropdownDataType = selectTag.dataset.dropdownType;

    try {
        const response = await fetch(`../api/dropdown-menus-api.php?dataType=${encodeURIComponent(dropdownDataType)}`,
            {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                },
            }
        );

        const result = await response.json();

        //Create an exception to 400/500 errors that otherwise wouldn't trigger the final catch
        if (!response.ok || !result.success) {
            throw new Error(result.error || 'Unable to retrive dropdown menu options')
        }

        //Remove all existing <option> tags inside the <select> tag first
        selectTag.replaceChildren();

        //Create first static <option> with empty value not retrived by db
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'Select option';
        defaultOption.selected = true;

        selectTag.append(defaultOption);

        //Then create the other <option> tags dynamically based on the data retrived by db
        result.data.forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = row.name;

            // In this page also create some additional attributes (which will be added only if they're actually retrived by the api, which happens
            // only when we retrieve the data for the discount_rule dropdown menus):

            // data-visit-type will help to determine if the usage-cap input field (in the new coupon form) will 
            // become required or not (if the selected disocunt rule contains a visit_type_id = 1, first visit only)
            if (row.visit_type_id !== null) {
                option.dataset.visitTypeId = row.visit_type_id;
            }

            //The following 3 attrributes will be used to generate the coupon code preview in real time inside the form
            if (row.discount_perc !== null) {
                option.dataset.discountPerc = row.discount_perc;
            }

            if (row.service_category_name !== null) {
                option.dataset.serviceCategory = row.service_category_name;
            }

            if (row.visit_type_name !== null) {
                option.dataset.visitType = row.visit_type_name;
            }

            selectTag.append(option);
        })

    } catch (error) {
        console.error(error);
    }
}

const dynamicDropdowns = document.querySelectorAll(
    'select[data-dropdown-type]'
);

dynamicDropdowns.forEach(selectTag => {
    loadUpdatedDropdown(selectTag);
});



// ----------------------------------------
// GET — CLIENTS-API.PHP
// Retrieves the current client's profile data for the page summary and for client-dependent coupon-form logic.
// ----------------------------------------

//recover client id from url query string
const clientId = new URLSearchParams(window.location.search).get('id');

//I'll also initiate an empty variable containing ALL the client info, which will be then updated by loadClientsInfo() after retrieving all the client's data. 
// I do this cause I'll need to use specific client data (like client_type_id) for certain if conditions here in JS (especially for the new coupon form, when I'll need
// to set defautl values for the usage_cap input field)
let currentClient = null;

const clientPageTitle = document.getElementById('single-client-name');

const clientStatusInfo = document.getElementById('client-status-info');
const clientTypeInfo = document.getElementById('client-type-info');
const clientCodeInfo = document.getElementById('client-code-info');
const clientCityInfo = document.getElementById('client-city-info');
const clientProvinceInfo = document.getElementById('client-province-info');

const deleteClientBtn = document.getElementById('delete-client-btn');
const clientEditLink = document.getElementById('client-edit-info-link');



async function loadClientsInfo(clientId) {
    try {
        const queryParams = new URLSearchParams({
            id: clientId, //use the ide saved in clientId constant (when we select a client on dashboard to see the details, the link will always contains the client id)
        });

        const response = await fetch(`../api/clients-api.php?${queryParams.toString()}`, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
            },
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.error || 'Unable to retrieve client information');
        }

        //Print forntend list if everything is ok
        renderClientInfo(result.data);

        //update the currentClient variable by storing all the retrieved client's data inside it
        currentClient = result.data;

    } catch (error) {
        console.error(error);
        //Print this frontend text in each client's <span> info field dashboard.php in case sql query or db connection set in clients-api.php API is unsuccessful
        const clientInfoFields = document.querySelectorAll('.info-client-field');
        clientInfoFields.forEach(field => {
            field.textContent = 'N.A.';
        });
    }
}

function renderClientInfo(infoObject) {
    clientStatusInfo.textContent = infoObject.client_status;
    clientStatusInfo.className = infoObject.status === 1 ? "info-client-field status-tag status-tag-active" : "info-client-field status-tag status-tag-inactive";

    clientPageTitle.textContent = infoObject.name;
    clientTypeInfo.textContent = infoObject.client_type;
    clientCodeInfo.textContent = infoObject.client_code;
    clientCityInfo.textContent = infoObject.city;
    clientProvinceInfo.textContent = infoObject.province;
}

if (clientId !== null) {
    loadClientsInfo(clientId);
} else {
    console.log('No client id in the url of this page. Please passa a vlaid id to retrive data.')
}

//Update the href attibute of the link to edit place with the current client id
clientEditLink.href = `./client-edit.php?id=${clientId}`;


// ----------------------------------------
// GET — COUPONS-API.PHP
// Retrieves the current client's paginated coupon list using the selected status filter, then renders the table and pagination.
// ----------------------------------------

const couponFilters = document.getElementById('coupon-filters');
const couponsTableBody = document.getElementById('coupons-table-body');
const couponsPagination = document.getElementById('coupons-pagination');
const couponsResultsStatus = document.getElementById('coupons-results-status');

//Keep track of the page currently shown in the coupons table.
//This lets toggle/delete operations refresh the same page instead of always sending the user back to page 1.
let currentCouponsPage = 1;

couponFilters.addEventListener('change', () => {
    loadCoupons(
        clientId,
        couponFilters.value,
        1 //a new status filter always starts again from the first page
    );
});


async function loadCoupons(clientId, status = 'all', page = 1) {
    try {
        const queryParams = new URLSearchParams({
            client_id: clientId,
            status,
            page,
        });

        const response = await fetch(
            `../api/coupons-api.php?${queryParams.toString()}`,
            {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                },
            }
        );

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(
                result.error || 'Unable to retrieve coupons'
            );
        }

        //Save the page actually returned by the API.
        //The backend may correct it after a deletion if the previous last page no longer exists.
        currentCouponsPage = result.pagination.current_page;

        renderCoupons(result.data);

        //Announce the updated coupon count to screen readers after filtering, pagination and table refreshes (a <p> will work as invisible live region for screen readers).
        couponsResultsStatus.textContent = `${result.pagination.total_results} coupon${result.pagination.total_results === 1 ? '' : 's'} found.`;

        //Reuse the same pagination renderer already used by Dashboard and Discount Rules.
        //pagination.js only detects which page was selected. This callback tells client-details.js
        //to reload this client's coupons using that selected page number.
        // Function defined in /assets/pagination.js (see my comment there to understand how it works)
        renderPagination(
            couponsPagination,
            result.pagination,
            (selectedPage) => loadCoupons(
                clientId,
                couponFilters.value,
                selectedPage
            )
        );

    } catch (error) {
        console.error(error);

        couponsTableBody.innerHTML = `
            <tr>
                <td colspan="5" class="p-4 text-center">
                    Unable to retrieve coupons.
                </td>
            </tr>
        `;

        couponsPagination.innerHTML = '';
        couponsResultsStatus.textContent = 'Unable to retrieve coupons.';
    }
}


function renderCoupons(coupons) {
    if (coupons.length === 0) {
        couponsTableBody.innerHTML = `
            <tr>
                <td colspan="5" class="p-4 text-center">
                    No coupons found.
                </td>
            </tr>
        `;

        return;
    }

    couponsTableBody.innerHTML = coupons
        .map((coupon) => createCouponRow(coupon))
        .join('');
}


function createCouponRow(coupon) {
    const isActive = coupon.status === 1;

    const toggleActionLabel = isActive
        ? `Deactivate coupon ${coupon.code}`
        : `Reactivate coupon ${coupon.code}`;

    const discountRuleRecap = [
        coupon.service_category,
        coupon.visit_type,
        `${coupon.discount_perc}%`,
    ].join(' - ');

    const remainingUsage = coupon.usage_cap === null
        ? 'no limit'
        : coupon.usage_cap;

    return `
        <tr class="min-h-[30px] odd:bg-light-grey even:bg-white text-xs md:text-sm" data-coupon-id="${coupon.id}">
            <td data-label="Coupon full text code" class="text-left align-middle p-2 md:p-3">
                <div class="flex items-center justify-start mb-2">
                    ${renderCouponIcon(coupon.coupon_service_type)}
                </div>
                ${escapeHtml(coupon.code)}
            </td>

            <td data-label="Applied discount rule recap" class="text-left align-middle p-2 md:p-3 hidden md:table-cell">
                ${escapeHtml(discountRuleRecap)}
            </td>

            <td data-label="Coupon remaining usage left" class="text-center align-middle p-2 md:p-3">
                ${escapeHtml(remainingUsage)}
            </td>

            <td data-label="Coupon status" class="text-center align-middle p-2 md:p-3">
                <div class="coupon-toggle-status status-toggle flex justify-center items-center">
                    <label class="inline-flex items-center cursor-pointer">
                        <span class="sr-only">${escapeHtml(toggleActionLabel)}</span>

                        <input type="checkbox" name="coupon-status-change" value="active" data-coupon-id="${coupon.id}" data-client-id="${coupon.client_id}" aria-label="${escapeHtml(toggleActionLabel)}" class="sr-only toggle-checkbox small-size-toggle-checkbox" ${isActive ? 'checked' : ''}>

                        <div class="toggle-track relative w-7 h-4 rounded-full transition-colors duration-300">
                            <span class="toggle-knob absolute top-0.5 left-0.5 w-3 h-3 bg-white rounded-full transition-transform duration-300 transform"></span>
                        </div>
                    </label>
                </div>
            </td>

            <td data-label="Coupon actions" class="text-center align-middle p-2 md:p-3">
                <button type="button" aria-label="Permanently delete coupon ${escapeHtml(coupon.code)}" title="Delete coupon" class="delete-coupon-btn mx-2 cursor-pointer" data-client-id="${coupon.client_id}" data-coupon-id="${coupon.id}">
                    ${deleteCouponIcon}
                </button>
            </td>
        </tr>
    `;
}

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

function renderCouponIcon(type) {
    const svg = couponIconsSvg[type] ?? couponIconsSvg.none;
    const label = getCouponLabel(type);

    return `
        <span role="img" aria-label="${label}" title="${label}">
            ${svg}
        </span>
    `;
}

function getCouponLabel(type) {
    const labels = {
        medical: 'Medical coupon',
        physiotherapy: 'Physiotherapy coupon',
        all: 'Medical and physiotherapy coupon',
        none: 'Unknown coupon category',
    };

    return labels[type] ?? labels.none;
}

const deleteCouponIcon = `
    <svg aria-hidden="true" focusable="false" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-primary-blue">
        <path d="M7 21C6.45 21 5.97933 20.8043 5.588 20.413C5.19667 20.0217 5.00067 19.5507 5 19V6H4V4H9V3H15V4H20V6H19V19C19 19.55 18.8043 20.021 18.413 20.413C18.0217 20.805 17.5507 21.0007 17 21H7ZM9 17H11V8H9V17ZM13 17H15V8H13V17Z"
            fill="currentColor"/>
    </svg>
`;

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

if (clientId !== null) {
    loadCoupons(
        clientId,
        couponFilters.value,
        1
    );
}


// ----------------------------------------
// POST — COUPONS-API.PHP
// Creates a new coupon from the New Coupon form and handles field validation, feedback and the subsequent coupon-table refresh.
// ----------------------------------------
const feedbackMessage = document.getElementById('feedback-msg');

const newCouponForm = document.getElementById('new-coupon-form');
const newCouponRuleSelect = document.getElementById('new-coupon-rule-choice');
const newCouponCap = document.getElementById('new-coupon-cap-set');
const newCouponExp = document.getElementById('new-coupon-exp-date');

const newCouponPreview = document.getElementById('new-coupon-preview');

const newCouponSubmitBtn = document.getElementById('new-coupon-submit-btn');

//capNumber attribute is passed only when updateUsageCapField() hasn't set its value as ''. 
//It's required only when a discount rule with a 'first visit only' (1) visit type has been selected.
//Also: no need to pass a default  status = 1 value cause db already set that value automatically for that column when a new record is created

async function createNewCoupon(ruleId, capNumber, expDate) {

    //First clear all previous input errors on frontend page
    clearAllInputFieldErrors();

    //Prevent multiple POST requests while the current one is still being processed.
    //It's not strictly necessary since I use event delegation for catching the submit but still better to implement this feature 
    newCouponSubmitBtn.disabled = true;


    //Try API call
    try {
        const queryParams = new URLSearchParams();
        // build the body of the POST request (params to be sent to backend)
        queryParams.set('coupon_client_id', clientId); //this won't be required as function argument since we already created a clientId constant in this JS file
        queryParams.set('coupon_discount_rule_id', ruleId);
        queryParams.set('coupon_exp_date', expDate); // browser will send date in YYYY-MM-DD format (keep that in mind for PHP data validation)

        //capBumer will be passed as argument only if updateUsageCapField() hasn't set its value as ''
        if (capNumber !== '') {
            queryParams.set('coupon_usage_cap', capNumber);
        }

        const response = await fetch(`../api/coupons-api.php`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: queryParams,
        });

        //save json response received via api and save it in the result variable
        const result = await response.json();

        //Manage form input validation errors
        if (
            !result.success &&
            result.input_errors &&
            Object.keys(result.input_errors).length > 0
        ) {
            showInputFieldErrors(result.input_errors);
            return;
        }

        //Global feedback message (failed new discount rule creation ): only UNIQUE, foreign key or database errors
        if (!result.success) {
            showGlobalFeedback(false, result.error);
            return;
        }

        //Global feedback message (successful new coupon creation)
        showGlobalFeedback(true, result.message);
        //Empty form fields after a successful submit 
        newCouponForm.reset();

        //Refresh the coupons table showed in this page so the new coupon will appear too
        loadCoupons(
            clientId,
            couponFilters.value,
            1 //new coupons are ordered first, so reload page 1 to show the newly created coupon
        );

    } catch (error) {
        // Technical errors remain in the console only (no frontend)
        console.error(error);

    } finally {
        //Reactivate btn
        newCouponSubmitBtn.disabled = false;
    }
}

// Function to print the frontend global feedback massage: 
// if api call succes=true (oupon successfully created) print success msg. If not, print fail msg
function showGlobalFeedback(success, message) {
    feedbackMessage.textContent = message;
    //className() will replace *all* the existing classes with the following ones based on success value
    feedbackMessage.className = success
        ? 'w-full inline-block min-h-[28px] text-xs text-pretty text-left opacity-100 transition-opacity duration-200 status-tag status-tag-active'
        : 'w-full inline-block min-h-[28px] text-xs text-pretty text-left opacity-100 transition-opacity duration-200 status-tag status-tag-inactive';
}

//Function to hide the global feedback message that will be triggered by eventListeners (see end of this file)
function hideGlobalFeedback() {
    feedbackMessage.textContent = '';
    feedbackMessage.className = 'w-full inline-block min-h-[28px] text-xs text-pretty opacity-0 transition-opacity duration-200';
}

// Input error messages management system
// 1) Map for input errors received by API and to show under each gform input field
const fieldErrors = {

    discount_rule_id_err: {
        input: newCouponRuleSelect, //this will tell showInputFieldErrors() which input field tag should be applied the error-input class
        message: document.getElementById('new-coupon-rule-error'), //this will thel what message print inside the corresponding <span> for frontend input error messages
    },

    coupon_usage_cap_err: {
        input: newCouponCap,
        message: document.getElementById('new-coupon-cap-error'),
    },

    exp_date_err: {
        input: newCouponExp,
        message: document.getElementById('new-coupon-exp-date-error'),
    },
};

// 2) Function to show input errors on frontend page (under each input field). It uses the $input_errors array created by API
function showInputFieldErrors(errors) {
    let firstInvalidInput = null;

    // Process every couple of input_err_key: 'Error description' stored in $input_errors
    Object.entries(errors).forEach(([fieldName, errorMessage]) => {
        //association of values contained in $input_errors array and JS fieldErrors array
        const field = fieldErrors[fieldName];

        if (!field) {
            return;
        }

        //custom tailwind css class I've already created for form input errors 
        field.input.classList.add('input-error');
        field.input.setAttribute('aria-invalid', 'true');

        if (!firstInvalidInput) {
            firstInvalidInput = field.input;
        }

        //inject the input_error message in the frontend tag
        field.message.textContent = errorMessage;
    });

    firstInvalidInput?.focus();
}

// 3) Function to hide single input error on frontend (used by clearAllInputFieldErrors(), which does this for each input field)
function clearInputFieldError(fieldName) {
    const field = fieldErrors[fieldName];

    if (!field) {
        return;
    }

    field.input.classList.remove('input-error');
    field.input.removeAttribute('aria-invalid');
    field.message.textContent = '';
}

// 4) Function to reset all input errors on frontend at every new submit (each time createNewCoupon() gets executed)
function clearAllInputFieldErrors() {
    Object.keys(fieldErrors).forEach(clearInputFieldError);
}


//Create the function to generate the coupon code preview in real-time while filling the new coupon form

function updateCouponPreview() {
    const selectedOption = newCouponRuleSelect.selectedOptions[0];

    if (!selectedOption?.value) {
        newCouponPreview.textContent = 'Select a discount rule';
        return;
    }

    //retrieved the data saved in <option> attributes to generate the coupon code preview by using the maps below
    const serviceCategory = selectedOption.dataset.serviceCategory;
    const visitType = selectedOption.dataset.visitType;
    const discountPerc = selectedOption.dataset.discountPerc;

    //Also retrieve the client code by "reciclying" the data we already retrieved and printed earlier in frontend by using the GET call to clients.api.php  
    const clientCode = clientCodeInfo.textContent.trim().toUpperCase();

    //This map also exists in backend, but I had to duplicate it to implement this 'nice-to-have' feature
    const serviceCategoryCodes = {
        Physiotherapy: 'PHYS',
        Medical: 'MED',
        'All services': 'ALL',
    };

    const visitTypeCodes = {
        'First visit only': 'FIRST',
        'All visits': 'ALL',
    };

    const serviceCode =
        serviceCategoryCodes[serviceCategory];

    const visitCode =
        visitTypeCodes[visitType];

    if (!serviceCode || !visitCode || !discountPerc || !clientCode) {
        newCouponPreview.textContent = 'Preview unavailable';
        return;
    }

    newCouponPreview.textContent = [
        serviceCode,
        visitCode,
        discountPerc,
        clientCode,
    ].join('-');
}


// Now set an add event listener that will run the updateUsageCapField() function and 
// the newCouponPreview() function every time a different discount rule is selected in the New Coupon Form Select
newCouponRuleSelect.addEventListener('change', () => {
    updateCouponUsageCapField();
    updateCouponPreview();
});

//Derfine a updateUsageCapField() function that will perform 3 tasks related to the New Coupon Form:
// 1) will check if the selected discount rule has a visit_type_id corresponding to 'first visit only' and, if yes, will enable the newCouponCap input field and it'll make it required
// 2) once the newCouponCap input field is enabled, ths function will set a defualt value: 1 if the currentClient is PR (1) type, 5 if the currentCLient is CO (2) type
// 3) if the selected discount rule has different visit_type_id (all visits) then it'll disable the newCouponCap input field, will remove the required attribute and will set the 
//    usage cap value to empty since it shouldn't be applied.'
function updateCouponUsageCapField() {
    const selectedOption =
        newCouponRuleSelect.options[newCouponRuleSelect.selectedIndex];

    //check if the data-visit-type-id of the selected option is =1 and return a boolean value
    const isFirstVisitOnly =
        selectedOption?.dataset.visitTypeId === '1';

    console.log({
        selectedRuleId: selectedOption?.value,
        visitTypeId: selectedOption?.dataset.visitTypeId,
        isFirstVisitOnly,
        clientTypeId: currentClient?.type_id,
        clientTypeIdType: typeof currentClient?.type_id,
    });


    //Set as true or false (= add or remove) the newCouponCap input field attributed disabled/required based on the value of isFirstVisitOnly
    newCouponCap.disabled = !isFirstVisitOnly;
    newCouponCap.required = isFirstVisitOnly;

    if (isFirstVisitOnly && currentClient.type_id === 1) {
        newCouponCap.value = '1';
    } else if (isFirstVisitOnly && currentClient.type_id === 2) {
        newCouponCap.value = '5';
    } else {
        newCouponCap.value = '';
    }
}


//Every time a submit happens in the form (by clicking the submit btn or by typing Enter), run createNewCoupon() with the 3 input values
newCouponForm.addEventListener('submit', (e) => {
    //submit will be managed via js > api instead of default browser
    e.preventDefault();
    createNewCoupon(newCouponRuleSelect.value, newCouponCap.value, newCouponExp.value);
});

// Hide the previous feedback when the user starts changing the form
newCouponForm.addEventListener('input', hideGlobalFeedback);
newCouponForm.addEventListener('change', hideGlobalFeedback);



// ----------------------------------------
// PATCH — COUPONS-API.PHP
// Toggles a coupon between active and inactive (soft delete) from the table and refreshes the current coupon page.
// ----------------------------------------

//take advantage of event delegation over the whole coupon table body
couponsTableBody.addEventListener('change', (event) => {
    const couponStatusToggle = event.target.closest(
        'input[name="coupon-status-change"]'
    );

    if (!couponStatusToggle) {
        return;
    }

    toggleCouponStatus(couponStatusToggle);
});


async function toggleCouponStatus(toggle) {

    //The html code for the toggle button will need to have two attributes that save both the coupon id and the client id
    const couponId = toggle.dataset.couponId;
    const clientId = toggle.dataset.clientId;

    try {

        toggle.disabled = true;

        const queryParams = new URLSearchParams({
            id: couponId,
            client_id: clientId,
        });

        const response = await fetch(
            `../api/coupons-api.php?${queryParams.toString()}`,
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
                result.error || 'Unable to update coupon status'
            );
        }

        // Reload the entire coupons table for simpler state management.
        // This also updates the toggle position and keeps the current filter.
        await loadCoupons(
            clientId,
            couponFilters.value,
            currentCouponsPage
        );

        //The table redraw removes the old checkbox. Restore focus to the new checkbox when the row
        //is still visible; if the current filter hides it after the status change, use the filter as fallback.
        const refreshedToggle = couponsTableBody.querySelector(`input[name="coupon-status-change"][data-coupon-id="${couponId}"]`);
        (refreshedToggle || couponFilters).focus();

    } catch (error) {

        console.error(error);

        // Restore the previous visual state if the request fails.
        toggle.checked = !toggle.checked;

        alert(error.message);

    } finally {

        // Additional check since after loadCoupons() the original toggle may no longer be in the DOM.
        if (toggle.isConnected) {
            toggle.disabled = false;
        }

    }
}

// ----------------------------------------
// DELETE — CLIENTS-API.PHP
// Permanently deletes (hard delete) the current client after confirmation, then returns the user to the dashboard.
// ----------------------------------------

deleteClientBtn.addEventListener('click', () => {
    hardDeleteClient(deleteClientBtn);
});

async function hardDeleteClient(button) {

    //Double confirmation pop-up before sending the DELETE call 
    if (!confirm('Are you sure you want to permanently delete this client?')) {
        return;
    }

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

        // Redirect to dashboard after successful deletion.
        window.location.href = 'dashboard.php';

    } catch (error) {

        console.error(error);
        alert(error.message);

    } finally {

        button.disabled = false;

    }
}


// ----------------------------------------
// DELETE — COUPONS-API.PHP
// Permanently deletes (hard delete) a coupon from the current client and refreshes the currently displayed coupon page.
// ----------------------------------------

//Since that, in this case (as in dashboard page), there will be many rows, each one with its own delete button, it's better relying on event delegation
//inisde the whole couponsTableBody
couponsTableBody.addEventListener('click', (event) => {
    const deleteCouponBtn = event.target.closest('.delete-coupon-btn');

    if (!deleteCouponBtn) {
        return;
    }

    hardDeleteCoupon(deleteCouponBtn);
});


async function hardDeleteCoupon(button) {

    if (!confirm('Are you sure you want to permanently delete this coupon?')) {
        return;
    }

    const couponId = button.dataset.couponId;
    const clientId = button.dataset.clientId;

    try {

        button.disabled = true;

        const queryParams = new URLSearchParams({
            id: couponId,
            client_id: clientId,
        });

        const response = await fetch(
            `../api/coupons-api.php?${queryParams.toString()}`,
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
                result.error || 'Unable to delete coupon'
            );
        }

        // Reload the entire coupons table for simpler and more maintainable state management
        await loadCoupons(
            clientId,
            couponFilters.value,
            currentCouponsPage
        );

        //The deleted coupon action no longer exists after the redraw, so move focus to the stable filter control.
        couponFilters.focus();

    } catch (error) {

        console.error(error);

        alert(error.message);

    } finally {
        // The button may no longer exist after loadCoupons() redraws the table, so it's better have an additional check
        if (button.isConnected) {
            button.disabled = false;
        }

    }
}
