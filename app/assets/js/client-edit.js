'use strict';

// ----------------------------------------
// API CALLS AND RELATED FRONTEND LOGIC
// ----------------------------------------


// Get the client ID from the current page query string.
// This ID will be used both to retrieve the existing client data and to identify
// which database record must be updated through PATCH calls.
const clientId = new URLSearchParams(window.location.search).get('id');


// ----------------------------------------
// FRONTEND ELEMENTS
// ----------------------------------------

const clientEditPageTitle = document.getElementById('edit-client-page-title');

const feedbackMessage = document.getElementById('feedback-msg');

const editClientForm = document.getElementById('edit-client-form');

const clientStatusToggle = document.getElementById('client-status-change');

const clientNameInput = document.getElementById('client-name-edit');
const clientCodeInput = document.getElementById('client-code-edit');
const clientCityInput = document.getElementById('client-city-edit');
const clientProvinceInput = document.getElementById('client-province-edit');

const clientTypeInputs = document.querySelectorAll('input[name="client-type-input"]');
const clientTypeGroup = document.getElementById('client-type-group');

const editClientBackLink = document.getElementById('edit-client-back-link');


// ----------------------------------------
// GET — CLIENTS-API.PHP
// Retrieves the current client's database values and populates the edit form and page navigation.
// ----------------------------------------
async function loadClientInfo(clientId) {

    try {

        const response = await fetch(
            `../api/clients-api.php?id=${clientId}`,
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
                result.error || 'Unable to retrieve client information'
            );
        }

        renderClientInfo(result.data);

    } catch (error) {

        console.error(error);

        showGlobalFeedback(
            false,
            error.message
        );
    }
}


// Inject the retrieved database values into the corresponding form fields.
function renderClientInfo(client) {

    clientEditPageTitle.textContent = `${client.name} - Edit Client Info`; 
    clientNameInput.value = client.name;
    clientCodeInput.value = client.client_code;
    clientCityInput.value = client.city;
    clientProvinceInput.value = client.province;

    // Database status uses 1 = active and 0 = inactive.
    // checked therefore always represents the actual current database status.
    clientStatusToggle.checked = client.status === 1;

    // Check the radio button whose value corresponds to clients.type_id.
    clientTypeInputs.forEach((radio) => {
        radio.checked = Number(radio.value) === client.type_id;
    });

    // Keep navigation back to the details page linked to the same client.
    editClientBackLink.href =
        `./client-details.php?id=${clientId}`;
}


// ----------------------------------------
// PATCH — CLIENTS-API.PHP — EDIT CLIENT INFO
// Validates and saves the editable client fields; client code remains intentionally immutable.
// ----------------------------------------
async function editClient(
    clientId,
    clientName,
    clientType,
    clientCity,
    clientProvince
) {

    // Clear errors returned by the previous submit before validating again.
    clearAllInputFieldErrors();

    try {

        const queryParams = new URLSearchParams();

        queryParams.set('name', clientName);
        queryParams.set('type_id', clientType);
        queryParams.set('city', clientCity);
        queryParams.set('province', clientProvince);

        const response = await fetch(
            //The '&action=edit-info' will tell the clients-api.php  what kind of PATCH call I'm doing from here
            //cause that api handles two different PATCH calls: one from the dashboard and from this page to manage active/inactive state only and one to edit all the other
            // client info this client-edit page
            `../api/clients-api.php?id=${clientId}&action=edit-info`,
            {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: queryParams,
            }
        );

        const result = await response.json();

        // Input validation errors are returned separately so each message
        // can be printed under its corresponding frontend form field.
        if (!result.success && result.input_errors && Object.keys(result.input_errors).length > 0) {
            showInputFieldErrors(result.input_errors);
            return;
        }

        // Global errors are reserved for invalid IDs, foreign key problems
        // or other database/request errors.
        if (!response.ok || !result.success) {
            showGlobalFeedback(
                false,
                result.error || 'Unable to update client'
            );

            return;
        }

        showGlobalFeedback(
            true,
            result.message
        );

    } catch (error) {

        // Technical/network errors remain visible in console only.
        console.error(error);
    }
}


// ----------------------------------------
// PATCH — CLIENTS-API.PHP — TOGGLE CLIENT STATUS
// Updates the client's active/inactive status (soft delete) immediately and independently from the profile edit form.
// ----------------------------------------
async function toggleClientStatus(toggle) {

    try {

        toggle.disabled = true;

        const response = await fetch(
            //The '&action=edit-info' will tell the clients-api.php  what kind of PATCH call I'm doing from here
            //cause that api handles two different PATCH calls: one from the dashboard and from this page to manage active/inactive state only and one to edit all the other
            // client info this client-edit page
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

        //Print a global feedback message when you update the status since it'll happen immediately and users will know they don't need to submit the whole form for that
        showGlobalFeedback(true, toggle.checked ? 'Client re-activated successfully' : 'Client deactivated successfully');

    } catch (error) {
        console.error(error);
        // The checkbox has already changed visually when the change event fires.
        // If PATCH fails, restore its previous position so UI and DB stay consistent.
        toggle.checked = !toggle.checked;
        alert(error.message);

    } finally {
        toggle.disabled = false;
    }
}

// GLOBAL FEEDBACK MESSAGE

function showGlobalFeedback(success, message) {

    feedbackMessage.textContent = message;

    // Replace all existing classes according to the result of the operation.
    feedbackMessage.className = success
        ? 'min-h-[36px] inline-block text-xs text-pretty opacity-100 transition-opacity duration-200 status-tag status-tag-active px-3 py-2 my-3'
        : 'min-h-[36px] inline-block text-xs text-pretty opacity-100 transition-opacity duration-200 status-tag status-tag-inactive px-3 py-2 my-3';
}


function hideGlobalFeedback() {
    feedbackMessage.textContent = '';
    feedbackMessage.className =
        'min-h-[36px] inline-block opacity-0 transition-opacity duration-200 my-3';
}


// INPUT ERROR MESSAGES

// Map each backend validation-error key to the corresponding frontend input and error-message element.
const fieldErrors = {

    client_name_err: {
        input: clientNameInput,
        message: document.getElementById('edit-client-name-error'),
    },

    client_type_err: {
        // No input-error class is applied to radio buttons.
        // The validation message is still printed below the radio group.
        group: clientTypeGroup,
        focusTarget: clientTypeInputs[0],
        message: document.getElementById('edit-client-type-error'),
    },

    client_city_err: {
        input: clientCityInput,
        message: document.getElementById('edit-client-city-error'),
    },

    client_province_err: {
        input: clientProvinceInput,
        message: document.getElementById('edit-client-province-error'),
    },
};


function showInputFieldErrors(errors) {

    let firstInvalidInput = null;

    Object.entries(errors).forEach(
        ([fieldName, errorMessage]) => {

            const field = fieldErrors[fieldName];

            if (!field) {
                return;
            }

            // Radio buttons intentionally have no input property in fieldErrors.
            if (field.input) {
                field.input.classList.add('input-error');
                field.input.setAttribute('aria-invalid', 'true');
            }

            if (field.group) {
                field.group.setAttribute('aria-invalid', 'true');
            }

            if (!firstInvalidInput) {
                firstInvalidInput = field.input || field.focusTarget;
            }

            field.message.textContent = errorMessage;
        }
    );

    firstInvalidInput?.focus();
}


function clearInputFieldError(fieldName) {

    const field = fieldErrors[fieldName];

    if (!field) {
        return;
    }

    if (field.input) {
        field.input.classList.remove('input-error');
        field.input.removeAttribute('aria-invalid');
    }

    if (field.group) {
        field.group.removeAttribute('aria-invalid');
    }

    field.message.textContent = '';
}


function clearAllInputFieldErrors() {

    Object.keys(fieldErrors).forEach(
        clearInputFieldError
    );
}


// EVENT LISTENERS

// Read the selected radio only when the form is submitted. As in the create-client page, this guarantees that 
// the variable always contains the radio button currently selected by the user.
editClientForm.addEventListener('submit', (event) => {

    event.preventDefault();

    const selectedClientType =
        document.querySelector(
            'input[name="client-type-input"]:checked'
        )?.value ?? '';

    editClient(
        clientId,
        clientNameInput.value,
        selectedClientType,
        clientCityInput.value,
        clientProvinceInput.value
    );
});


// The status checkbox is independent from the Save button and updates the database immediately whenever the user switches its position.
clientStatusToggle.addEventListener('change', () => {
    toggleClientStatus(clientStatusToggle);
});


// Hide previous global feedback when the user starts editing the form again.
editClientForm.addEventListener(
    'input',
    hideGlobalFeedback
);

// Hide previous global feedback when the user edits the form again.
// Ignore the status toggle, since it has its own immediate feedback message and doesn0t depend on form submit.
editClientForm.addEventListener('change', (event) => {
    if (event.target === clientStatusToggle) {
        return;
    }

    hideGlobalFeedback();
});


// INITIAL PAGE LOAD

if (clientId) {
    loadClientInfo(clientId);

} else {
    showGlobalFeedback(
        false,
        'Invalid client ID'
    );
}
