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
        return FmConfig::get('country');
    }

    public function getCurrency()
    {
        return FmConfig::get('currency');
    }

    public function getPercentage()
    {
        return FmConfig::get('percentage');
    }

    public function getAutoOrderImport()
    {
        return FmConfig::getBool('automaticOrderImport');
    }

    public function getAutoQuantityExport()
    {
        return FmConfig::getBool('automaticquantityexport');
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
}