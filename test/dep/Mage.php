<?php

class Mage
{
    static function App()
    {
        return new Mage();
    }

    static function getStoreConfig($test)
    {
        return serialize("blablabla");
    }
    static function getStoreConfigFlag($test)
    {
        return $test;
    }

    static function getConfig()
    {
        return new getConfig();
    }

    static function getVersion()
    {
        return "1.9.0.3";
    }

    function getStore()
    {
        return new getNode();
    }

    function getLocale()
    {
        return new getLocale();
    }

    static function getModel($model)
    {
        switch($model) {
            case 'fyndiq/product':
                return new Fyndiq_Fyndiq_Model_Product();
                break;
            case 'catalog/product':
                return new Catalog_Product();
                break;
        }
    }
}

class getConfig
{
    function getNode()
    {
        return new getNode();
    }

    function saveConfig($key, $value)
    {
        return true;
    }
}

class getNode
{
    function __construct() {
        $this->modules = new getModule();
    }

    function getCurrentCurrencyCode()
    {
        return "SEK";
    }

    function getStoreId() {
        return 1;
    }
}
class getModule
{
    function __construct() {
        $this->Fyndiq_Fyndiq = new FyndiqModule();
    }
}
class FyndiqModule
{
    function __construct() {
        $this->version = "1.0.0";
    }
}

class getLocale
{
    function getLocaleCode()
    {
        return "SE";
    }
}
