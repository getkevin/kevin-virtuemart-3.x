<?php

namespace Kevin\VirtueMart;

use stdClass;
use TablePaymentmethods;
use VirtueMartCart;
use vmPSPlugin;

defined('_JEXEC') or exit('Restricted access');
if (!class_exists('vmPSPlugin')) {
    require JPATH_VM_PLUGINS.DS.'vmpsplugin.php';
}

/**
 * VirtueMart payment plugin boilerplate functions.
 *
 * @since version 1.0.0
 */
class vmPSPluginBase extends vmPSPlugin
{
    protected $tableFields;

    /**
     * This function is used for fetching payment name e.g. when generating invoices.
     *
     * @param string $virtuemart_order_id
     * @param string $virtuemart_paymentmethod_id
     * @param string $payment_name
     *
     * @since version 1.0.0
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /**
     * Create database table for this payment method.
     *
     * @param string $pluginId
     *
     * @since 1.0.0
     */
    public function plgVmOnStoreInstallPaymentPluginTable($pluginId)
    {
        return $this->onStoreInstallPluginTable($pluginId);
    }

    /**
     * This function is used to autofill plugin parameters on configuration page in admin panel.
     *
     * @param TablePaymentmethods &$data
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function plgVmDeclarePluginParamsPaymentVM3(&$data)
    {
        return $this->declarePluginParams('payment', $data);
    }

    /**
     * Save plugin parameters submitted from configuration page in admin panel.
     *
     * @param string              $name
     * @param string              $id
     * @param TablePaymentmethods &$table
     *
     * @return bool - true on success and false on failure
     *
     * @since 1.0.0
     */
    public function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    /**
     * VM calls this function to check whether the payment method can be used in given situation.
     *
     * @param VirtueMartCart $cart
     * @param stdClass       $method
     * @param array          $cart_prices
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function checkConditions($cart, $method, $cart_prices)
    {
        return true;
    }

    /**
     * Validate whether selected payment is valid for current use case.
     *
     * @param VirtueMartCart $cart
     * @param string         &$msg
     *
     * @return bool|null
     *
     * @since version 1.0.0
     */
    public function plgVmOnSelectCheckPayment($cart, &$msg)
    {
        return $this->OnSelectCheck($cart);
    }

    /**
     * If there are any price modifications required for payment method, they should be done here.
     *
     * @param VirtueMartCart $cart
     * @param array          $cart_prices
     * @param string         $cart_prices_name
     *
     * @return bool|null
     *
     * @since version 1.0.0
     */
    public function plgVmonSelectedCalculatePricePayment($cart, &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * Get payment currency.
     *
     * @param string $virtuemart_paymentmethod_id - payment method which user has selected
     * @param string &$payment_currency_id
     *
     * @return bool|null
     *
     * @since version 1.0.0
     */
    public function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$payment_currency_id)
    {
        if (!$this->isCurrentPaymentSelected($virtuemart_paymentmethod_id)) {
            return null;
        }

        $this->getPaymentCurrency($method);

        $payment_currency_id = $method->payment_currency;

        return true;
    }

    /**
     * Checks how many payment methods are available. If there's only one, there will not be payment selection section.
     *
     * @param VirtueMartCart $cart
     * @param array          $cart_prices
     * @param int            &$paymentCounter
     *
     * @return string|null - documented return type is ?int but actual return type is ?string
     *
     * @since 1.0.0
     */
    public function plgVmOnCheckAutomaticSelectedPayment($cart, $cart_prices = [], &$paymentCounter)
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices);
    }

    /**
     * Check if payment method is the one that is selected. This check has to be done because VirtueMart calls
     * all the payment methods one after another and expects irrelevant plugins to do nothing.
     *
     * @param string $paymentMethodId
     *
     * @return bool
     *
     * @since version 1.0.0
     */
    public function isCurrentPaymentSelected($paymentMethodId)
    {
        $paymentMethod = $this->getVmPluginMethod($paymentMethodId);

        return $paymentMethod && $this->selectedThisElement($paymentMethod->payment_element);
    }
}
