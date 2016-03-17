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
        return new GetConfig();
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
        return new GetLocale();
    }

    static function getModel($model)
    {
        switch ($model) {
            case 'fyndiq/product':
                return new Fyndiq_Fyndiq_Model_Product();
                break;
            case 'catalog/product':
                return new Catalog_Product();
                break;
            case 'cataloginventory/stock_item':
                return new Catalog_Stock();
                break;
        }
    }

    static function reinitStores()
    {
        return true;
    }
}

class GetConfig
{
    function getNode()
    {
        return new GetNode();
    }

    function saveConfig($key, $value)
    {
        return true;
    }
    function reinit()
    {
        return true;
    }
}

class GetNode
{
    function __construct()
    {
        $this->modules = new getModule();
    }

    function getCurrentCurrencyCode()
    {
        return "SEK";
    }

    function getStoreId()
    {
        return 1;
    }
}
class getModule
{
    function __construct()
    {
        $this->Fyndiq_Fyndiq = new FyndiqModule();
    }
}
class FyndiqModule
{
    function __construct()
    {
        $this->version = "1.0.0";
    }
}

class GetLocale
{
    function getLocaleCode()
    {
        return "SE";
    }
}
