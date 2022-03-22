<?php

namespace Kevin\VirtueMart;

use Exception;
use JFactory;
use JRoute;
use JUri;
use JVersion;
use Kevin\KevinException;
use stdClass;
use vmText;
use vmVersion;
use vRequest;

/**
 * A set of helper functions for kevin. payment plugin.
 *
 * @since 1.0.0
 */
trait KevinHelper
{
    /**
     * Check if kevin. is the only active payment method.
     *
     * @return bool
     *
     * @since version 1.0.0
     */
    public function isKevinTheOnlyPayment()
    {
        $db = JFactory::getDBO();
        $q = 'SELECT COUNT(*) FROM #__virtuemart_paymentmethods WHERE published=1';

        $activePaymentMethodsCount = $db->setQuery($q)->loadResult();

        return 1 === (int) $activePaymentMethodsCount;
    }

    /**
     * Generate custom styled alert HTML.
     *
     * @param string $message
     * @param string $type
     *
     * @return string
     *
     * @since version 1.0.0
     */
    public function generateAlertHtml($message, $type)
    {
        switch ($type) {
            case self::ALERT_SUCCESS:
                $color = '#23C552'; // green
                break;
            case self::ALERT_ERROR:
                $color = '#F84F31'; // red
                break;
            case self::ALERT_INFO:
                $color = '#FAAD15'; // orange
                break;
            default:
                $color = '#9A9A9A'; // grey
        }

        return "<h4 style='padding: 10px; background-color: $color; color: white; margin-bottom: 15px;'>$message</h4>";
    }

    /**
     * Get country names from the database by matching given 2-letter country codes.
     *
     * @var string[]
     *
     * @return string[]
     *
     * @since version 1.0.0
     */
    public function getCountryNamesBy2Codes($countryCodes)
    {
        $db = JFactory::getDBO();

        if (!\count($countryCodes)) {
            return [];
        }

        $where = "WHERE `country_2_code` = '$countryCodes[0]'";
        for ($i = 1; $i < \count($countryCodes); ++$i) {
            $where .= " OR `country_2_code` = '$countryCodes[$i]'";
        }

        $countryQuery = "SELECT `country_2_code`, `country_3_code` FROM `#__virtuemart_countries` $where";

        $db->setQuery($countryQuery);
        $result = $db->loadObjectList();

        $countries = [];
        foreach ($result as $country) {
            $countryCode = $country->country_2_code;
            $countryName = vmText::_("COM_VIRTUEMART_COUNTRY_{$country->country_3_code}");

            $countries[$countryCode] = $countryName;
        }

        return $countries;
    }

    /**
     * Get kevin. data user session.
     *
     * @return ?stdClass stdClass(selectedCountryCode, selectedBankId)
     *
     * @since version 1.0.0
     */
    public function getKevinSession()
    {
        $session = JFactory::getSession();

        return json_decode($session->get('kevin', null, 'vm'));
    }

    /**
     * Set kevin. data user session.
     *
     * @var stdClass
     *
     * @return void
     *
     * @since version 1.0.0
     */
    public function setKevinSession($kevinSession)
    {
        $session = JFactory::getSession();
        $session->set('kevin', json_encode($kevinSession), 'vm');
    }

    /**
     * Unset kevin. session data to prevent http code 400 after placing an order.
     *
     * @since version 1.0.0
     */
    public function unsetKevinSession()
    {
        $session = JFactory::getSession();
        $session->clear('kevin', 'vm');
    }

    /**
     * Sanitize string to prevent user input errors or inconsistencies for API use.
     *
     * @param string $text
     * @param bool   $removeAllSpaces
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function sanitize($text, $removeAllSpaces = true)
    {
        $text = trim($text);

        if ($removeAllSpaces) {
            $text = str_replace(' ', '', $text);
        }

        return $text;
    }

    /**
     * Generate consistent exception message.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function generateExceptionMessage(Exception $e)
    {
        return \get_class($e).': '.$e->getMessage();
    }

    /**
     * Get currently running Joomla! version.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function getJoomlaVersion()
    {
        jimport('joomla.version');
        $version = new JVersion();

        return $version->RELEASE;
    }

    /**
     * Get currently running VirtueMart version.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function getVirtueMartVersion()
    {
        return vmVersion::$RELEASE;
    }

    /**
     * Get kevin payment status by VirtueMart order id.
     *
     * @param int    $orderId
     * @param string $tableName
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function getKevinPaymentStatus($orderId, $tableName)
    {
        $db = JFactory::getDBO();

        $db->setQuery(
            $db->getQuery(true)
                ->select('kevin_status')
                ->from($tableName)
                ->where("virtuemart_order_id = {$db->escape($orderId)}")
        );

        return strtolower($db->loadObject()->kevin_status);
    }

    /**
     * Update kevin. order status by order id.
     *
     * @param int    $orderId
     * @param string $tableName
     * @param string $status    - either 'completed' or 'failed'
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function updateKevinOrderStatus($orderId, $tableName, $status)
    {
        $db = JFactory::getDBO();

        $db->setQuery(
            $db->getQuery(true)
                ->update($tableName)
                ->set("kevin_status = '{$db->escape($status)}'")
                ->where("virtuemart_order_id = {$db->escape($orderId)}")
        )->loadResult();
    }

    /**
     * Validate user input.
     *
     * @return ?bool false on failed | true on success | null on exception
     *
     * @since version 1.0.0
     */
    public function validateUserInput()
    {
        $sessionData = $this->getKevinSession();

        if (!$sessionData->selectedBankId) {
            vmWarn(vmText::_('KEVIN_ALERT_BANK_NOT_SELECTED'));
        } else {
            return true;
        }

        try {
            $mainframe = JFactory::getApplication();
        } catch (Exception $e) {
            echo $this->generateExceptionMessage($e);

            return null;
        }

        $mainframe->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart', false));

        return false;
    }

    /**
     * Get kevin. client options.
     *
     * @return string[]
     *
     * @since 1.0.0
     */
    public function getClientOptions()
    {
        return [
            'version' => '0.3',
            'pluginVersion' => self::VERSION,
            'pluginPlatform' => 'Joomla/VirtueMart',
            'pluginPlatformVersion' => "Joomla! v{$this->getJoomlaVersion()} VirtueMart v{$this->getVirtuemartVersion()}",
        ];
    }

    /**
     * Handle an exception during payment initiation.
     *
     * @param KevinException $e
     *
     * @return void
     *
     * @since version 1.0.0
     */
    private function handleConfirmedOrderException($e)
    {
        // don't show "Thank you for your order" line
        vRequest::setVar('display_title', '0');

        // show error alert
        vRequest::setVar(
            'html',
            $this->renderByLayout('post_payment',
                [
                    'message' => $this->generateAlertHtml($this->generateExceptionMessage($e), self::ALERT_ERROR),
                    'buttonText' => vmText::_('KEVIN_BUTTON_TEXT_RETRY'),
                    'buttonLink' => sprintf('%sindex.php?option=com_virtuemart&view=cart', JURI::root()),
                ]
            )
        );
    }
}
