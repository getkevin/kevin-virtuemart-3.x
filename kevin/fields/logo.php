<?php

defined('JPATH_BASE') or die();

jimport('joomla.form.formfield');
class JFormFieldLogo extends JFormField
{
    public $type = 'logo';

    protected function getInput()
    {
        vmJsApi::addJScript( '/plugins/vmpayment/kevin/kevin/fields/js/field.js');

        $html  = '<a href="https://www.kevin.eu" target="_blank">';
        $html .= '<h1 style="color: #f50028; font-family: sans-serif;" >kevin.</h1>';
        $html .= '</a>';

        return $html;
    }
}