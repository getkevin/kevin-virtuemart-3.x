// check if any of initial values are empty and if they are not enable save and show error messages
window.onload = () => {
    let hasErrors = false;

    const fields = document.getElementsByClassName('kevin-configuration-field');
    [].forEach.call(fields, field => {
       if (field.required && !field.value) {
           showRequiredMessage(field.id);
           hasErrors = true;
       }
    });

    if (!hasErrors) {
        turnOnSaveButtons();
    }
};

const validateField = (fieldId, isRequired) => {
    const fieldElement = document.getElementById(fieldId);

    // validate is required
    if (isRequired && !fieldElement.value) {
        showRequiredMessage(fieldId);
    } else {
        hideRequiredMessage(fieldId);
    }

    switch (fieldId) {
        case 'params_client_id':
            validateClientId(fieldElement);
            break;
        case 'params_company_name':
            validateCompanyName(fieldElement);
            break;
        case 'params_company_bank_account':
            validateIban(fieldElement);
            break;
    }
};

const validateClientId = field => {
    const format = /^[a-zA-Z0-9-]*$/ // allow English alphabet, numbers and a dash

    format.test(field.value) ? hideSpecialCharactersMessage(field.id) : showSpecialCharactersMessage(field.id);
};

const validateCompanyName = field => {
    const format = /^[a-zA-Z0-9 ]*$/ // allow English alphabet, numbers and a space

    format.test(field.value) ? hideSpecialCharactersMessage(field.id) : showSpecialCharactersMessage(field.id);
}

const validateIban = field => {
    const formatIban = /[A-Z]{2}[0-9]{2}[a-zA-Z0-9]{4}[0-9]{7}([a-zA-Z0-9]?){0,16}/; // allow IBAN
    const formatSpecial = /^[A-Z0-9 ]*$/ // allow English alphabet, numbers and a space

    // if field is empty hide invalid format message
    if (!field.value) {
        hideIbanMessage(field.id);
        return;
    }

    formatIban.test(field.value) && formatSpecial.test(field.value) && field.value.length <= 31
        ? hideIbanMessage(field.id)
        : showIbanMessage(field.id);
}

const turnOffSaveButtons = () => {
    [].forEach.call(document.getElementsByClassName('button-apply'), button => button.disabled = true);
    [].forEach.call(document.getElementsByClassName('button-save'), button => button.disabled = true);
};

// turn off saving buttons by default
turnOffSaveButtons();

const turnOnSaveButtons = () => {
    let hasErrors = false;

    // check if any of kevin. error messages are being shown
    const validationErrorMessageElements = document.getElementsByClassName('kevin-validation-error');
    [].forEach.call(validationErrorMessageElements, element => {
        if (element.hidden === false) {
            hasErrors = true;
        }
    });

    // if all error messages are hidden, enable save buttons
    if (!hasErrors) {
        [].forEach.call(document.getElementsByClassName('button-apply'), button => button.disabled = false);
        [].forEach.call(document.getElementsByClassName('button-save'), button => button.disabled = false);
    }
};

const showSpecialCharactersMessage = (fieldId) => {
    document.getElementById(`${fieldId}-special-characters`).hidden = false;
    turnOffSaveButtons();
};

const hideSpecialCharactersMessage = (fieldId) => {
    document.getElementById(`${fieldId}-special-characters`).hidden = true;
    turnOnSaveButtons();
};

const showRequiredMessage = (fieldId) => {
    document.getElementById(`${fieldId}-is-required`).hidden = false;
    turnOffSaveButtons();
};

const hideRequiredMessage = (fieldId) => {
    document.getElementById(`${fieldId}-is-required`).hidden = true;
    turnOnSaveButtons();
};

const showIbanMessage = (fieldId) => {
    document.getElementById(`${fieldId}-invalid-iban-format`).hidden = false;
    turnOffSaveButtons();
};

const hideIbanMessage = (fieldId) => {
    document.getElementById(`${fieldId}-invalid-iban-format`).hidden = true;
    turnOnSaveButtons();
};