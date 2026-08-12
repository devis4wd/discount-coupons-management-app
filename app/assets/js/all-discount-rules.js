'use strict';

// ----------------------------------------
// API CALLS AND RELATED FRONTEND LOGIC
// ----------------------------------------

// ----------------------------------------
// GET — DROPDOWN-MENUS-API.PHP
// Retrieves the current service-category and visit-type options and renders the filter dropdowns.
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

            selectTag.append(option);
        })

    } catch (error) {
        console.error(error);
    }
}

//Invoke the loadUpdateDropdown() dor every <select> in the page that contains a data-dropdown-type, cause
//those ones will be the only ones who need their <option> ags to be generated dynamically and which has the attribute
// data-dropddown-type managed by loadUpdateDropdown() 
const dynamicDropdowns = document.querySelectorAll(
    'select[data-dropdown-type]'
);

dynamicDropdowns.forEach(selectTag => {
    loadUpdatedDropdown(selectTag);
});

// ----------------------------------------
// GET — DISCOUNT-RULES-API.PHP
// Retrieves the paginated discount-rule list using the selected filters, then renders the table rows and pagination.
// ----------------------------------------
// -------------------------------------------------------------------------------

const discRulesServCatFilter = document.getElementById('serv-categ-filters');
const discRulesEligVisitFilter = document.getElementById('visit-type-filters');
const discountRulesPagination = document.getElementById('disc-rules-pagination');
const discountRulesResultsStatus = document.getElementById('discount-rules-results-status');

discRulesServCatFilter.addEventListener('change', () => {
    loadDiscRules(discRulesServCatFilter.value, discRulesEligVisitFilter.value, 1);
})

discRulesEligVisitFilter.addEventListener('change', () => {
    loadDiscRules(discRulesServCatFilter.value, discRulesEligVisitFilter.value, 1);
});


const discountRulesTableBody = document.getElementById('disc-rules-table-body');

async function loadDiscRules(servicesFilter = '', visitsFilter = '', page = 1) {
    try {
        const queryParams = new URLSearchParams({
            servicesFilter,
            visitsFilter,
            page,
        });

        const response = await fetch(`../api/discount-rules-api.php?${queryParams.toString()}`, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
            },
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.error || 'Unable to retrieve discount rules');
        }

        renderDiscountRules(result.data);

        //Announce the updated result count to screen readers after filters and pagination changes (a <p> will work as invisible live region for screen readers).
        discountRulesResultsStatus.textContent = `${result.pagination.total_results} discount rule${result.pagination.total_results === 1 ? '' : 's'} found.`;

        //Function defined in /assets/pagination.js (see my comment there to understand how it works)
        renderPagination(
            discountRulesPagination,
            result.pagination,
            (selectedPage) => loadDiscRules(
                discRulesServCatFilter.value,
                discRulesEligVisitFilter.value,
                selectedPage
            )
        );
    } catch (error) {
        console.error(error);

        discountRulesTableBody.innerHTML = `
            <tr>
                <td colspan="8" class="p-4 text-center">
                    Unable to retrieve discount rules.
                </td>
            </tr>
        `;

        discountRulesPagination.innerHTML = '';
        discountRulesResultsStatus.textContent = 'Unable to retrieve discount rules.';
    }
}

function renderDiscountRules(discountRules) {
    if (discountRules.length === 0) {
        discountRulesTableBody.innerHTML = `
            <tr>
                <td colspan="8" class="p-4 text-center">
                    No discount rules found. No discount rules have been created yet or none matches the selected filters.
                </td>
            </tr>
        `;

        return;;
    }

    discountRulesTableBody.innerHTML = discountRules
        .map((discountRule) => createDiscountRuleRow(discountRule))
        .join('');
}

function createDiscountRuleRow(discountRule) {

    return `
    <tr class="min-h-[30px] odd:bg-light-grey even:bg-white">
        <td data-label="Service category" class="text-left align-top sm:align-middle p-3">${escapeHtml(discountRule.service_category)}</td>
        <td data-label="Discount-eligible visits" class="text-left align-top sm:align-middle p-3">
            ${escapeHtml(discountRule.visit_type)}</td>
        <td data-label="Percentage of discount applied by discount rule"
            class="text-center sm:text-left align-top sm:align-middle p-3">${escapeHtml(discountRule.discount_perc)}
        </td>
    </tr>
    `;
}


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

loadDiscRules();