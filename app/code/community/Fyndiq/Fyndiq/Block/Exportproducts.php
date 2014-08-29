<?php

class Fyndiq_Fyndiq_Block_Exportproducts extends Mage_Core_Block_Template
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
        return Mage::getStoreConfig('fyndiq/fyndiq_group4/country');
    }

    public function getCurrency()
    {
        return Mage::getStoreConfig('fyndiq/fyndiq_group4/currency');
    }

    public function getAutoOrderImport()
    {
        return (bool)Mage::getStoreConfigFlag('fyndiq/fyndiq_group4/automaticOrderImport');
    }

    public function getAutoProductExport()
    {
        return (bool)Mage::getStoreConfigFlag('fyndiq/fyndiq_group4/automaticProductExport');
    }

    public function getUsername()
    {
        return Mage::getStoreConfig('fyndiq/fyndiq_group/username');
    }


}