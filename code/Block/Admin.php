<?php
require_once(dirname(dirname(__FILE__)) . '/includes/messages.php');
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');

/**
 * Admin block handling all the data to the view
 */
class Fyndiq_Fyndiq_Block_Admin extends Mage_Core_Block_Template
{

    /**
     * Get frontend path
     *
     * @return string
     */
    public function getFrontendPath()
    {
        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . "fyndiq/";
    }

    /**
     * Get admin path
     *
     * @param $path
     * @param null $section
     * @return mixed
     */
    function getAdminPath($path, $section = null)
    {
        return Mage::helper("adminhtml")->getUrl($path, $section);
    }

    public function getLanguage()
    {
        return Mage::getStoreConfig('general/country/default');
    }

    public function getCurrency()
    {
        return Mage::app()->getStore()->getCurrentCurrencyCode();
    }

    public function getPercentage()
    {
        return FmConfig::get('percentage');
    }

    public function getUsername()
    {
        return FmConfig::get('username');
    }

    public function getMessage($key)
    {
        return FmMessages::get($key);
    }

    public function getMessages()
    {
        return FmMessages::get_all();
    }

    public function getVersion()
    {
        return FmConfig::getVersion();
    }

    public function getLastUpdatedDate()
    {
        $date = Mage::getModel('fyndiq/setting')->getSetting("order_lastdate");
        if($date != false) {
            return $date["value"];
        }
        return false;
    }
}