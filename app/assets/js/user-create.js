'use strict';


// ----------------------------------------
// POST — USERS-API.PHP
// Sends the Create User form data to the API and renders validation/general feedback returned by the backend.
// ----------------------------------------

const newUserForm = document.getElementById('new-user-form');
const newUserSubmitBtn = document.getElementById('new-user-submit-btn');
const newUserFeedbackMsg = document.getElementById('feedback-msg');

const newUserName = document.getElementById('user-name');
const newUserSurname = document.getElementById('user-surname');
const newUserEmail = document.getElementById('user-email');
const newUserPassword = document.getElementById('user-password');
const newUserConfirmPassword = document.getElementById('confirm-password');
const newUserRole = document.getElementById('user-role');


//Map every backend input_errors key to its corresponding frontend input and error <span>.
const userFormFields = {
    name_err: {
        input: document.getElementById('user-name'),
        message: document.getElementById('name-error'),
    },

    surname_err: {
        input: document.getElementById('user-surname'),
        message: document.getElementById('surname-error'),
    },

    email_err: {
        input: document.getElementById('user-email'),
        message: document.getElementById('email-error'),
    },

    password_err: {
        input: document.getElementById('user-password'),
        message: document.getElementById('password-error'),
    },

    confirm_password_err: {
        input: document.getElementById('confirm-password'),
        message: document.getElementById('confirm-password-error'),
    },

    role_err: {
        input: document.getElementById('user-role'),
        message: document.getElementById('user-role-error'),
    },
};

newUserForm.addEventListener('submit', createNewUser);

async function createNewUser(event) {
    //Prevent the normal browser form submission because this form is handled through fetch().
    event.preventDefault();

    //Remove possible errors / feedback left by the previous submission attempt.
    clearUserFormErrors();
    hideUserFeedback();

    try {
        //Prevent multiple POST requests while the current one is still being processed.
        //It's not strictly necessary since I use event delegation for catching the submit but still better to implement this feature 
        newUserSubmitBtn.disabled = true;

        const queryParams = new URLSearchParams();

        queryParams.set('user_name', newUserName.value.trim());
        queryParams.set('user_surname', newUserSurname.value.trim());
        queryParams.set('user_email', newUserEmail.value.trim());
        //I won't trim the password value cause etyped mpty spaces may be intentional
        queryParams.set('user_password', newUserPassword.value);
        queryParams.set('confirm_password', newUserConfirmPassword.value);
        queryParams.set('user_role', newUserRole.value);

        const response = await fetch(
            '../api/users-api.php',
            {
                method: 'POST',

                headers: {
                    Accept: 'application/json',
                },

                body: queryParams,
            }
        );

        const result = await response.json();

        //Validation errors are different from global/request errors.
        //A 400 response containing input_errors is expected when one or more submitted form values fail backend
        //validation. So those errors must be rendered next to their corresponding fields.
        if (!response.ok || !result.success) {
            if (result.input_errors) {
                showInputFieldErrors(result.input_errors);
                return;
            }

            throw new Error(
                result.error || 'Unable to create new user'
            );
        }

        //If the API successfully created the new user, clear the form.
        newUserForm.reset();

        //The normal/default role after resetting the form remains "user".
        showGlobalFeedback(
            result.message || 'New user successfully added.',
            true
        );

    } catch (error) {
        console.error(error);
        showGlobalFeedback(
            error.message || 'Unable to create new user.',
            false
        );

    } finally {
        //Reactivate btn
        newUserSubmitBtn.disabled = false;
    }
}

// ----------------------------------------
// FRONTEND FORM ERROR RENDERING
// ----------------------------------------
function showInputFieldErrors(errors) {

    let firstInvalidInput = null;

    Object.entries(errors).forEach(([fieldName, errorMessage]) => {
        // Process every couple of input_err_key: 'Error description' stored in $input_errors
        const field = userFormFields[fieldName];

        //Ignore unexpected error keys rather than causing a frontend JS error.
        if (!field) {
            return;
        }

        field.input.classList.add('input-error');
        field.input.setAttribute('aria-invalid', 'true');

        if (!firstInvalidInput) {
            firstInvalidInput = field.input;
        }

        field.message.textContent = errorMessage;
    });

    firstInvalidInput?.focus();
}


//Remove previous backend validation errors before another POST attempt.
function clearUserFormErrors() {

    Object.values(userFormFields).forEach(field => {

        field.input.classList.remove('input-error');
        field.input.setAttribute('aria-invalid', 'false');

        field.message.textContent = '';
    });
}

// ----------------------------------------
// GLOBAL SUCCESS / ERROR FEEDBACK
// ----------------------------------------

function showGlobalFeedback(message, success) {

    newUserFeedbackMsg.textContent = message;
    //className() will replace *all* the existing classes with the following ones based on success value
    newUserFeedbackMsg.className = success
        ? 'min-h-[36px] inline-block text-pretty opacity-100 transition-opacity duration-200 status-tag status-tag-active px-3 py-2 my-3'
        : 'min-h-[36px] inline-block text-pretty opacity-100 transition-opacity duration-200 status-tag status-tag-inactive px-3 py-2 my-3';
}

function hideUserFeedback() {

    newUserFeedbackMsg.textContent = '';
    newUserFeedbackMsg.className = 'min-h-[36px] inline-block text-xs text-pretty opacity-0 transition-opacity duration-200 my-3';
}
