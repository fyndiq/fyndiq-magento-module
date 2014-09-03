<?php
require_once(dirname(dirname(__FILE__)) . '/includes/messages.php');
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');
class Fyndiq_Fyndiq_Block_Admin extends Mage_Core_Block_Template
{

    public function getModulePath()
    {
        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . "app/code/community/Fyndiq/Fyndiq/";
    }

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

    public function getAutoOrderImport()
    {
        return FmConfig::getBool('automaticOrderImport');
    }

    public function getAutoProductExport()
    {
        return FmConfig::getBool('automaticProductExport');
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