'use strict';

// ----------------------------------------
// API CALLS AND RELATED FRONTEND LOGIC
// ----------------------------------------

// ----------------------------------------
// GET — DROPDOWN-MENUS-API.PHP
// Retrieves the current service-category and visit-type options and renders the New Discount Rule form dropdowns.
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
// POST — DISCOUNT-RULES-API.PHP
// Creates a new discount rule and handles form validation, field-specific errors and global success/error feedback.
// ----------------------------------------
// ------------------------------------------------------------------------------------------------------------------------

const feedbackMessage = document.getElementById('feedback-msg');

const newDiscountRuleForm = document.getElementById('new-rules-form');
const serviceCategorySelect = document.getElementById('new-rule-serv-cat');
const visitTypeSelect = document.getElementById('new-rule-type-visit');
const discountPercInput = document.getElementById('new-rule-perc-discount');

const newDiscountRuleSubmitBtn = document.getElementById('new-rule-submit-btn');

async function createNewRule(categoryId, visitId, percentage) {

    //First clear all previous input errors on frontend page
    clearAllInputFieldErrors();

    //Prevent multiple POST requests while the current one is still being processed.
    //It's not strictly necessary since I use event delegation for catching the submit but still better to implement this feature 
    newDiscountRuleSubmitBtn.disabled = true;

    //Try API call
    try {
        const queryParams = new URLSearchParams();
        // build the body of the POST request (params to be sent to backend)
        queryParams.set('service_category_id', categoryId);
        queryParams.set('visit_type_id', visitId);
        queryParams.set('discount_perc', percentage);

        const response = await fetch(`../api/discount-rules-api.php`, {
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

        //Global feedback message (successful new discount rule creation)
        showGlobalFeedback(true, result.message);
        //Empty form fields after a successful submit 
        newDiscountRuleForm.reset();

    } catch (error) {
        // Technical errors remain in the console only (no frontend)
        console.error(error);

     } finally {
        //Reactivate btn
        newDiscountRuleSubmitBtn.disabled = false;
    }
}

// Function to print the frontend global feedback massage: 
// if api call succes=true (discount rule successfully created) print success msg. If not, print fail msg
function showGlobalFeedback(success, message) {
    feedbackMessage.textContent = message;
    //className() will replace *all* the existing classes with the following ones based on success value
    feedbackMessage.className = success
        ? 'min-h-[36px] inline-block text-pretty opacity-100 transition-opacity duration-200 status-tag status-tag-active px-3 py-2 my-3'
        : 'min-h-[36px] inline-block text-pretty opacity-100 transition-opacity duration-200 status-tag status-tag-inactive px-3 py-2 my-3';
}

//Function to hide the global feedback message that will be triggered by eventListeners (see end of this file)
function hideGlobalFeedback() {
    feedbackMessage.textContent = '';
    feedbackMessage.className = 'min-h-[36px] inline-block text-xs text-pretty opacity-0 transition-opacity duration-200 my-3';
}

// Input error messages management system
// 1) Map for input errors received by API and to show under each gform input field
const fieldErrors = {
    service_category_id_err: {
        input: serviceCategorySelect, //this will tell showInputFieldErrors() which input field tag should be applied the error-input class
        message: document.getElementById('new-rule-serv-cat-error'), //this will thel what message print inside the corresponding <span> for frontend input error messages
    },

    visit_type_id_err: {
        input: visitTypeSelect,
        message: document.getElementById('new-rule-type-visit-error'),
    },

    discount_percentage_err: {
        input: discountPercInput,
        message: document.getElementById('new-rule-perc-discount-error'),
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

// 4) Function to reset all input errors on frontend at every new submit (each time createNewRule() gets executed)
function clearAllInputFieldErrors() {
    Object.keys(fieldErrors).forEach(clearInputFieldError);
}



//Every time a submit happens in the form (by clicking the submit btn or by typing Enter), run createNewRule() with the 3 input values
newDiscountRuleForm.addEventListener('submit', (e) => {
    //submit will be managed via js > api instead of default browser
    e.preventDefault();
    createNewRule(serviceCategorySelect.value, visitTypeSelect.value, discountPercInput.value);
});

// Hide the previous feedback when the user starts changing the form
newDiscountRuleForm.addEventListener('input', hideGlobalFeedback);
newDiscountRuleForm.addEventListener('change', hideGlobalFeedback);