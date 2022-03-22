<?php

require __DIR__.'/vendor/autoload.php';

use Joomla\CMS\Factory;
use Kevin\Client;
use Kevin\KevinException;
use Kevin\VirtueMart\KevinHelper;
use Kevin\VirtueMart\KevinInterface;
use Kevin\VirtueMart\vmPSPluginBase;

/**
 * kevin. payment initiation gateway for VirtueMart 3.
 *
 * @see https://www.kevin.eu/
 *
 * @copyright  Copyright© 2022 kevin.
 * @license    GPL2
 *
 * @since      1.0.0
 */
class plgVmPaymentKevin extends vmPSPluginBase implements KevinInterface
{
    use KevinHelper;

    /**
     * pglVmPaymentKevin constructor.
     *
     * @param JEventDispatcher &$subject
     * @param string[]         $config
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);

        $jLang = JFactory::getLanguage();
        $jLang->load('plg_vmpayment_kevin', JPATH_ADMINISTRATOR, null, true);
        $jLang->load('com_virtuemart_countries', JPATH_ADMINISTRATOR.'/components/com_virtuemart');

        $this->_loggable = true;
        $this->_debug = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->setConfigParameterable($this->_configTableFieldName, $this->getVarsToPush());
    }

    /**
     * VM calls this function to format HTML for given plugin to be shown in the payment methods list for customer.
     *
     * @param VirtueMartCart $cart
     * @param string         $selected - currently selected VMPayment plugin id
     * @param array          &$htmlIn
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function plgVmDisplayListFEPayment($cart, $selected = 0, &$htmlIn)
    {
        $paymentMethodId = $cart->virtuemart_paymentmethod_id;
        $isSelected = $this->isCurrentPaymentSelected($paymentMethodId);

        $paymentMethod = $this->getVmPluginMethod($paymentMethodId);

        if (!$isSelected || !(bool) $paymentMethod->list_banks_in_checkout) {
            $this->unsetKevinSession(); //reset session to prevent issues when the setting is flicked

            return $this->displayListFE($cart, $selected, $htmlIn);
        }

        $methodSalesPrice = $this->setCartPrices($cart, $cart->cartPrices, $paymentMethod);

        $kevinSession = $this->getKevinSession();

        $htmlTotal[] = $this->getPluginHtml($paymentMethod, $selected, $methodSalesPrice);

        if (!$this->isKevinTheOnlyPayment()) {
            $htmlTotal[] = $this->renderBankList(
                $paymentMethod,
                $kevinSession->selectedCountryCode,
                $kevinSession->selectedBankId,
                $cart->BT['virtuemart_country_id']
            );
        }

        $htmlIn[] = $htmlTotal;

        return true;
    }

    /**
     * This function is triggered when the user clicks on the Checkout Out Now button. It is responsible for
     * initiating kevin. payment.
     *
     * @param VirtueMartCart $cart
     * @param array          $order
     *
     * @return bool|void|null - documented return value is string|void|null but actual return value is bool|void|null
     *
     * @since 1.0.0
     */
    public function plgVmConfirmedOrder($cart, $order)
    {
        $paymentMethodId = $order['details']['BT']->virtuemart_paymentmethod_id;

        if (!$this->isCurrentPaymentSelected($paymentMethodId)) {
            return null;
        }

        $paymentMethod = $this->getVmPluginMethod($paymentMethodId);

        $clientId = $this->sanitize($paymentMethod->client_id);
        $clientSecret = $this->sanitize($paymentMethod->client_secret);
        $creditorName = $this->sanitize($paymentMethod->company_name, false);
        $creditorIban = $this->sanitize($paymentMethod->company_bank_account);
        $isRedirectPreferred = (bool) $paymentMethod->redirect_preferred;
        $isListBanksInCheckout = (bool) $paymentMethod->list_banks_in_checkout;
        $isCardPayment = 'card' === $this->getKevinSession()->selectedBankId;
        $bankId = $this->getKevinSession()->selectedBankId;

        if ($isListBanksInCheckout && !$this->validateUserInput()) {
            return null;
        }

        $currencyModel = new VirtueMartModelCurrency();

        $currencyCode = $currencyModel->getCurrency($paymentMethod->payment_currency)->currency_code_3
            ?: $currencyModel->getCurrency($paymentMethod->currency_id)->currency_code_3;

        $orderData = $order['details']['BT'];

        $pluginUrlRoot = JURI::root().'index.php?option=com_virtuemart&view=vmplg';

        $redirectUrl = sprintf($pluginUrlRoot.'&task=pluginresponsereceived&on=%s&op=%s&pm=%s',
            $orderData->order_number,
            $orderData->order_pass,
            $orderData->virtuemart_paymentmethod_id
        );

        $webhookUrl = sprintf($pluginUrlRoot.'&task=pluginNotification&on=%s&op=%s&pm=%s',
            $orderData->order_number,
            $orderData->order_pass,
            $orderData->virtuemart_paymentmethod_id
        );

        $attr = [
            'amount' => $orderData->order_total,
            'currencyCode' => $currencyCode,
            'description' => $orderData->virtuemart_order_id,
            'identifier' => ['email' => $orderData->email],
            'redirectPreferred' => $isRedirectPreferred,
            'Redirect-URL' => $redirectUrl,
            'Webhook-URL' => $webhookUrl,
        ];

        try {
            $client = new Client($clientId, $clientSecret, $this->getClientOptions());
            $projectSettings = $client->auth()->getProjectSettings();
        } catch (KevinException $e) {
            $this->handleConfirmedOrderException($e);

            return null;
        }

        if (in_array('bank', $projectSettings['paymentMethods'])
        ) {
            $attr['bankPaymentMethod'] = [
                'creditorName' => $creditorName,
                'endToEndId' => $orderData->order_number,
                'creditorAccount' => [
                    'iban' => $creditorIban,
                ],
            ];
        }

        if (in_array('card', $projectSettings['paymentMethods'])
            && ($isCardPayment || !$isListBanksInCheckout)
        ) {
            $attr['cardPaymentMethod'] = [];
        }

        if ($isCardPayment) {
            $attr['paymentMethodPreferred'] = 'card';
        } else {
            $attr['bankId'] = $bankId;
        }

        try {
            $response = $client->payment()->initPayment($attr);
        } catch (KevinException $e) {
            $this->handleConfirmedOrderException($e);

            return null;
        }

        $databaseValuesToWrite = [
            'virtuemart_order_id' => $orderData->virtuemart_order_id,
            'payment_order_total' => $orderData->order_total,
            'payment_currency' => $currencyCode,
            'payment_name' => $paymentMethod->payment_name,
            'bank_id' => $bankId,
        ];

        $this->storePSPluginInternalData($databaseValuesToWrite);

        header("Location: {$response['confirmLink']}");
    }

    /**
     * This function is fired when client is redirected from kevin. back to the store.
     * It handles payment status cases and acts accordingly.
     *
     * @param string &$html      - pointer to thank you page body html
     * @param string &$pageTitle - pointer to thank you page title
     *
     * @throws KevinException
     * @throws Exception
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function plgVmOnPaymentResponseReceived(&$html, &$pageTitle)
    {
        $request = Factory::getApplication()->input;

        if (!$this->isCurrentPaymentSelected($request->getString('pm', 0))) {
            return null;
        }

        $orderNumber = $request->getString('on', 0);
        $orderPass = $request->getString('op', 0);
        $status = $request->getString('statusGroup', 0);

        $virtueMartOrderModel = new VirtueMartModelOrders();
        $orderId = (int) ($virtueMartOrderModel->getOrderIdByOrderPass($orderNumber, $orderPass));

        $kevinPaymentStatus = $this->getKevinPaymentStatus($orderId, $this->_tablename);

        // if status has already been set, use it instead of provided status parameter
        if ($kevinPaymentStatus) {
            $status = $kevinPaymentStatus;
            $persistKevinPaymentStatus = false;
        } else {
            $persistKevinPaymentStatus = true;
        }

        switch ($status) {
            case self::KEVIN_PAYMENT_STATUS_PENDING:
                $pageTitle = $this->generateAlertHtml(
                    vmText::_('KEVIN_ALERT_PAYMENT_PENDING'),
                    self::ALERT_INFO
                );

                $html = $this->renderByLayout('post_payment',
                    [
                        'message' => vmText::_('KEVIN_MESSAGE_SUCCESS'),
                        'buttonText' => vmText::_('KEVIN_BUTTON_TEXT_CONTINUE'),
                        'buttonLink' => JURI::root(),
                    ]
                );

                $keepCart = false;

                break;
            case self::KEVIN_PAYMENT_STATUS_COMPLETED:
                $pageTitle = $this->generateAlertHtml(
                    vmText::_('KEVIN_ALERT_PAYMENT_SUCCESSFUL'),
                    self::ALERT_SUCCESS
                );

                $html = $this->renderByLayout('post_payment',
                    [
                        'message' => vmText::_('KEVIN_MESSAGE_SUCCESS'),
                        'buttonText' => vmText::_('KEVIN_BUTTON_TEXT_CONTINUE'),
                        'buttonLink' => JURI::root(),
                    ]
                );

                $keepCart = false;

                break;
            case self::KEVIN_PAYMENT_STATUS_STARTED:
                $pageTitle = $this->generateAlertHtml(
                    vmText::_('KEVIN_ALERT_PAYMENT_CANCELLED'),
                    self::ALERT_ERROR
                );

                $html = $this->renderByLayout('post_payment',
                    [
                        'buttonText' => vmText::_('KEVIN_BUTTON_TEXT_RETRY'),
                        'buttonLink' => sprintf('%sindex.php?option=com_virtuemart&view=cart', JURI::root()),
                    ]
                );

                $keepCart = true;

                break;
            case self::KEVIN_PAYMENT_STATUS_FAILED:
                $pageTitle = $this->generateAlertHtml(
                    vmText::_('KEVIN_ALERT_PAYMENT_FAILED'),
                    self::ALERT_ERROR
                );

                $html = $this->renderByLayout('post_payment',
                    [
                        'buttonText' => vmText::_('KEVIN_BUTTON_TEXT_RETRY'),
                        'buttonLink' => sprintf('%sindex.php?option=com_virtuemart&view=cart', JURI::root()),
                    ]
                );

                $keepCart = true;

                break;
            default:
                return;
        }

        $cart = VirtueMartCart::getCart();
        if ($keepCart) {
            // restart cart because if it's not then the checkout page becomes broken
            $products = $cart->cartProductsData;
            $cart->emptyCart();
            $cart->cartProductsData = $products;
            $cart->setCartIntoSession();
        } else {
            $cart->emptyCart();
        }

        // if valid status was found, update kevin. payment record
        if ($persistKevinPaymentStatus) {
            $this->updateKevinOrderStatus($orderId, $this->_tablename, $status);
        }
    }

    /**
     * Handle incoming webhook. This function is also used to update kevin. session data used for bank list in checkout.
     *
     * @see https://docs.virtuemart.net/manual/configuration-menu/order-statuses.html
     *
     * @throws Exception
     *
     * @return void
     *
     * @since version 1.0.0
     */
    public function plgVmOnPaymentNotification()
    {
        $request = Factory::getApplication()->input;

        $selectedCountryCode = $request->post->get('selectedCountryCode');
        $selectedBankId = $request->post->get('selectedBankId');

        $kevinSession = $this->getKevinSession();

        if ($selectedCountryCode) {
            $kevinSession->selectedCountryCode = $selectedCountryCode;
            $kevinSession->selectedBankId = null; // if country was changed, unset bank
        }

        if ($selectedBankId) {
            $kevinSession->selectedBankId = $selectedBankId;
        }

        // if request is for updating session, save data and quit
        if ($selectedCountryCode || $selectedBankId) {
            $this->setKevinSession($kevinSession);
            http_response_code(200);
            exit;
        }

        $paymentMethodId = $request->getString('pm', 0);

        if (!$this->isCurrentPaymentSelected($paymentMethodId)) {
            return null;
        }

        $orderNumber = $request->getString('on', 0);
        $orderPass = $request->getString('op', 0);

        $headers = getallheaders();
        $requestBodyRaw = file_get_contents('php://input');
        $requestBody = json_decode($requestBodyRaw, true);

        $paymentMethod = $this->getVmPluginMethod($paymentMethodId);
        $endpointSecret = $this->sanitize($paymentMethod->endpoint_secret);

        $webhookUrl = sprintf(JURI::root().'index.php?option=com_virtuemart&view=vmplg&task=pluginNotification&on=%s&op=%s&pm=%s',
            $orderNumber,
            $orderPass,
            $paymentMethodId
        );

        if (!Kevin\SecurityManager::verifySignature(
            $endpointSecret,
            $requestBodyRaw,
            $headers,
            $webhookUrl,
            self::SIGNATURE_TIMESTAMP_TIMEOUT
        )) {
            http_response_code(400);
            exit('invalid signature');
        }

        $orderModel = new VirtueMartModelOrders();
        $orderId = $orderModel->getOrderIdByOrderPass($orderNumber, $orderPass);

        // no order found
        if (!$orderId) {
            http_response_code(200);
            exit('order not found');
        }

        $order = $orderModel->getOrder($orderId);

        // prevent successful order from being changed to failed by an expired payment
        if ('C' === $order['details']['BT']->order_status) {
            http_response_code(200);

            return;
        }

        $paymentMethod = $this->getVmPluginMethod($paymentMethodId);

        $clientId = $this->sanitize($paymentMethod->client_id);
        $clientSecret = $this->sanitize($paymentMethod->client_secret);

        $client = new Client($clientId, $clientSecret, $this->getClientOptions());
        $kevinPayment = $client->payment()->getPayment($requestBody['id']);

        $order = [
            'virtuemart_order_id' => $orderId,
            'comments' => sprintf('[kevin. webhook] Payment id: %s', $requestBody['id']),
        ];

        if ($kevinPayment['bankPaymentMethod']['bankId']) {
            $order['comments'] .= sprintf(' (%s)', $kevinPayment['bankPaymentMethod']['bankId']);
        }

        // possible statusGroup values: ["completed", "failed", "pending", "started"]
        $status = $requestBody['statusGroup'];

        switch ($status) {
            case self::KEVIN_PAYMENT_STATUS_COMPLETED:
                $order['order_status'] = 'C'; // C = Confirmed
                break;
            case self::KEVIN_PAYMENT_STATUS_FAILED:
                $order['order_status'] = 'X'; // X = Cancelled
                break;
            case self::KEVIN_PAYMENT_STATUS_PENDING:
            case self::KEVIN_PAYMENT_STATUS_STARTED:
                // do nothing because default status is 'pending'
                break;
            default:
                $order['order_status'] = 'D'; // D = Denied
        }

        $this->updateKevinOrderStatus($orderId, $this->_tablename, $status);

        $orderModel->updateStatusForOneOrder($orderId, $order);

        http_response_code(200);
    }

    /**
     * This function is meant to fix bank listing in checkout page compatibility for newer VirtueMart versions.
     *
     * @param TablePaymentmethods $plugin
     *
     * @return mixed|string|void
     *
     * @since version 1.0.0
     */
    public function renderPluginName($plugin)
    {
        $cart = VirtueMartCart::getCart();

        // if kevin. is not the only active payment, render default name w/o custom HTML
        if (!$plugin->list_banks_in_checkout || !$this->isKevinTheOnlyPayment()) {
            return parent::renderPluginName($plugin);
        }

        $kevinSession = $this->getKevinSession();

        return $this->renderBankList(
            $plugin,
            $kevinSession->selectedCountryCode,
            $kevinSession->selectedBankId,
            $cart->BT['virtuemart_country_id']
        );
    }

    /**
     * Render bank list displayed in checkout page.
     *
     * @param VirtueMartModelPaymentmethod|TablePaymentmethods $paymentMethod
     * @param ?string                                          $selectedCountryCode - Alpha-2 code
     * @param ?string                                          $selectedBankId
     * @param ?string                                          $addressCountryId
     *
     * @return string|null
     *
     * @since version 1.0.0
     */
    public function renderBankList(
        $paymentMethod,
        $selectedCountryCode = '',
        $selectedBankId = '',
        $addressCountryId = ''
    ) {
        $clientId = $this->sanitize($paymentMethod->client_id);
        $clientSecret = $this->sanitize($paymentMethod->client_secret);

        if (!$selectedCountryCode) {
            $selectedCountryCode = ShopFunctions::getCountryByID($addressCountryId, 'country_2_code');
        }

        try {
            $client = new Client($clientId, $clientSecret, $this->getClientOptions());
            $banks = $selectedCountryCode ? $client->auth()->getBanks(['countryCode' => $selectedCountryCode])['data'] : [];
            $supportedCountryCodes = $client->auth()->getCountries();
            $projectSettings = $client->auth()->getProjectSettings();
        } catch (KevinException $e) {
            return $this->generateAlertHtml(
                $this->generateExceptionMessage($e),
                self::ALERT_ERROR
            );
        }

        return $this->renderByLayout('bank_list',
            [
                'selectedCountryCode' => $selectedCountryCode,
                'selectedBankId' => $selectedBankId,
                'countries' => $this->getCountryNamesBy2Codes($supportedCountryCodes['data']),
                'banks' => $banks,
                'isCardEnabled' => in_array('card', $projectSettings['paymentMethods']),
            ]
        );
    }

    /**
     * Get configuration XML fields to be saved, their types and default values.
     *
     * 'xml_field_name' => ['default value', 'sql_data_type'],
     *
     * @return string[][]
     *
     * @since 1.0.0
     */
    public function getVarsToPush()
    {
        return [
            'module_name' => [vmText::_('KEVIN_PAYMENT_NAME'), 'char'],
            'client_id' => ['', 'char'],
            'client_secret' => ['', 'char'],
            'endpoint_secret' => ['', 'char'],
            'company_name' => ['', 'char'],
            'company_bank_account' => ['', 'char'],
            'redirect_preferred' => ['', 'tinyint'],
            'list_banks_in_checkout' => ['', 'tinyint'],
        ];
    }

    /**
     * Get fields which will be used to store order data and persisted to plugin's database table.
     *
     * 'field_name' => 'SQL_DATA_TYPE() SQL RULES'
     *
     * @return string[]
     *
     * @since version 1.0.0
     */
    public function getTableSQLFields()
    {
        return [
            'id' => 'INT(1) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'INT(1) UNSIGNED NOT NULL',  //same as virtuemart_orders table
            'payment_order_total' => 'DECIMAL(15,5) NOT NULL',
            'payment_currency' => 'VARCHAR(3) NOT NULL',
            'kevin_status' => 'VARCHAR(15)',
            'bank_id' => 'VARCHAR(50)',
            'payment_name' => 'VARCHAR(50)',
        ];
    }
}
