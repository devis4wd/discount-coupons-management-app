'use strict';

// ----------------------------------------
// API CALLS AND RELATED FRONTEND LOGIC
// ----------------------------------------

// ----------------------------------------
// POST — CLIENTS-API.PHP
// Creates a new client and handles the related form validation, field-specific errors and global success/error feedback.
// ----------------------------------------

const feedbackMessage = document.getElementById('feedback-msg');

const newClientForm = document.getElementById('new-client-form');
const newClientNameInput = document.getElementById('client-name-create-input');
const newClientCodeInput = document.getElementById('client-code-create-input');
const newClientCityInput = document.getElementById('client-city-create-input');
const newClientProvinceInput = document.getElementById('client-province-create-input');
const newClientSubmitBtn = document.getElementById('new-client-submit-btn');
const newClientTypeGroup = document.getElementById('client-type-group');
const newClientTypeInputs = document.querySelectorAll('input[name="client-type-input"]');

// I'll define a newClientTypeInput for the radio buttons only inside the submit event listener, so the createNewClient()
// won't be called using a null value

//No need to specify status = 1 for new clients: I already set that value as default in db when new records are added
async function createNewClient(clientName, clientCode, clientType, clientCity, clientProvince) {
    //First clear all previous input errors on frontend page
    clearAllInputFieldErrors();

    //Prevent multiple POST requests while the current one is still being processed.
    //It's not strictly necessary since I use event delegation for catching the submit but still better to implement this feature 
    newClientSubmitBtn.disabled = true;

    //try API call
    try {
        const queryParams = new URLSearchParams();
        //build body of POST request
        queryParams.set('name', clientName);
        queryParams.set('client_code', clientCode);
        queryParams.set('type_id', clientType);
        queryParams.set('city', clientCity);
        queryParams.set('province', clientProvince);

        const response = await fetch(`../api/clients-api.php`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: queryParams,
        });

        //save json response
        const result = await response.json();

        //Manage form input validation errors
        if (!result.success && result.input_errors && Object.keys(result.input_errors).length > 0) {
            showInputFieldErrors(result.input_errors);
            return;
        }

        //Global feedback message (failed new discount rule creation ): only UNIQUE, foreign key or database errors
        if (!result.success) {
            showGlobalFeedback(false, result.error);
            return;
        }

        //Global feedback message (successful new client creation)
        showGlobalFeedback(true, result.message);
        //Empty form fields after a successful submit 
        newClientForm.reset();
    }
    catch (error) {
        // Technical errors remain in the console only (no frontend)
        console.error(error);
   
    } finally {
        //Reactivate btn
        newClientSubmitBtn.disabled = false;
    }
}


// Function to print the frontend global feedback massage: 
// if api call succes=true (discount rule successfully created) print success msg. If not, print fail msg
function showGlobalFeedback(success, message) {
    feedbackMessage.textContent = message;
    //className() will replace *all* the existing classes with the following ones based on success value
    feedbackMessage.className = success
        ? 'min-h-[36px] inline-block text-xs text-pretty opacity-100 transition-opacity duration-200 status-tag status-tag-active px-3 py-2 my-3'
        : 'min-h-[36px] inline-block text-xs text-pretty opacity-100 transition-opacity duration-200 status-tag status-tag-inactive px-3 py-2 my-3';
}

//Function to hide the global feedback message that will be triggered by eventListeners (see end of this file)
function hideGlobalFeedback() {
    feedbackMessage.textContent = '';
    feedbackMessage.className = 'min-h-[36px] inline-block text-xs text-pretty opacity-0 transition-opacity duration-200 my-3';
}

// Input error messages management system
// 1) Map for input errors received by API and to show under each gform input field
const fieldErrors = {
    client_name_err: {
        input: newClientNameInput, //this will tell showInputFieldErrors() which input field tag should be applied the error-input class
        message: document.getElementById('new-client-name-error'), //this will thel what message print inside the corresponding <span> for frontend input error messages
    },

    client_code_err: {
        input: newClientCodeInput,
        message: document.getElementById('new-client-code-error'),
    },

    client_type_err: {
        // No input here, cause I decided there's no need to apply the input-error class (red borders etc.) to the radio buttons in case of unselected radio.
        // A frontend input error message will still be regularly printed, but making the radio dots red would have required me to overcoplicate this code
        // just for an isolated and minor "special case".
        group: newClientTypeGroup,
        focusTarget: newClientTypeInputs[0],
        message: document.getElementById('new-client-type-error'),
    },

    client_city_err: {
        input: newClientCityInput,
        message: document.getElementById('new-client-city-error'),
    },

    client_province_err: {
        input: newClientProvinceInput,
        message: document.getElementById('new-client-province-error'),
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

        //I added an if condition before applying the class to make sure that, even if the fieldErrors doesn't have any input property for
        //the radio buttons, this exception will be simply ignored and won't throw an error just cause it can't find an input tag to apply the input-error class 
        if (field.input) {
            field.input.classList.add('input-error');         //custom tailwind css class I've already created for form input errors 
            field.input.setAttribute('aria-invalid', 'true');
        }

        if (field.group) {
            field.group.setAttribute('aria-invalid', 'true');
        }

        if (!firstInvalidInput) {
            firstInvalidInput = field.input || field.focusTarget;
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

    //if condition added before removing classes and aria attributes for the same reason explained in showInputFieldErrors()
    if (field.input) {
        field.input.classList.remove('input-error');
        field.input.removeAttribute('aria-invalid');
    }

    if (field.group) {
        field.group.removeAttribute('aria-invalid');
    }

    field.message.textContent = '';
}

// 4) Function to reset all input errors on frontend at every new submit (each time createNewRule() gets executed)
function clearAllInputFieldErrors() {
    Object.keys(fieldErrors).forEach(clearInputFieldError);
}



//Every time a submit happens in the form (by clicking the submit btn or by typing Enter), run createNewRule() with the 3 input values
newClientForm.addEventListener('submit', (e) => {
    //submit will be managed via js > api instead of default browser
    e.preventDefault();

    //NOTE: I need to define this variable only now, when the user will hit submit. This is due to the following reasons:
    // - I have two radio buttons with same name attribute, so this variable needs to save only the value of the checked one
    // - If I save its value earlier 8as soon as the page load), there's no checked radio yet, so the variable will receive NULL value
    // - Even if the user will check one of the two radio buttons BEFORE hitting submit, the value of selectedClientTypeInput wouldn0t be updated automatically
    //   and it wuld still remain NULL. 
    // - At that point, createNewClient() will be called wiht a null parameter would throw a console error that will block the code.  
    // So that's why I need to define selectedClientTypeInput inside this Event Listener: cause only this way the value of
    // selectedClientTypeInput will be updated every time I hit the submit button or push Enter. 
    //ALSO: the value ?? '' part will allow us to assign an empty string to selectedClientTypeInput in case the user will actually
    //forget to select a radio option. But the API input data validation process already covers that case (empty input fields) and will
    //correctly print a frontend input error message   
    const newClientTypeInput = document.querySelector('input[name="client-type-input"]:checked')?.value ?? '';

    createNewClient(newClientNameInput.value, newClientCodeInput.value, newClientTypeInput, newClientCityInput.value, newClientProvinceInput.value);
});

// Hide the previous feedback when the user starts changing the form
newClientForm.addEventListener('input', hideGlobalFeedback);
newClientForm.addEventListener('change', hideGlobalFeedback);