<?php

if (!defined('_JEXEC')) {
    exit();
}

jimport('joomla.form.formfield');
class JFormFieldConfiguration extends JFormField
{
    public $type = 'configuration';

    protected function getInput()
    {
        // create input field template
        $htmlTemplate = '<input id="%s" name="%s" class="%s" type="%s" size="50" required="%s" value="%s" oninput="%s" onload="$s"><br>';

        // add all possible error messages
        $errorMessageTemplate = '<div><b id="%s" class="kevin-validation-error" style="color: red;" hidden>%s</b></div>';

        $htmlTemplate .= sprintf($errorMessageTemplate, "$this->id-special-characters", vmText::_('KEVIN_VALIDATION_SPECIAL_CHARACTERS'));
        $htmlTemplate .= sprintf($errorMessageTemplate, "$this->id-is-required", vmText::_('KEVIN_VALIDATION_REQUIRED'));
        $htmlTemplate .= sprintf($errorMessageTemplate, "$this->id-invalid-iban-format", vmText::_('KEVIN_VALIDATION_INVALID_IBAN'));

        $type = $this->getAttribute('password') ? 'password' : 'text';

        return sprintf(
            $htmlTemplate,
            $this->id,
            $this->fieldname,
            'kevin-configuration-field',
            $type,
            $this->required,
            $this->value,
            "validateField('$this->id', $this->required)",
            "validateField('$this->id', $this->required)",
            "$this->id-error"
        );
    }
}
