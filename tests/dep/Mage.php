<?php

class Mage
{
    public static function App()
    {
        return new Mage();
    }

    public static function getStoreConfig($test)
    {
        return serialize("blablabla");
    }
    public static function getStoreConfigFlag($test)
    {
        return $test;
    }

    public static function getConfig()
    {
        return new GetConfig();
    }

    public static function getVersion()
    {
        return "1.9.0.3";
    }

    public function getStore()
    {
        return new getNode();
    }

    public function getLocale()
    {
        return new GetLocale();
    }

    public static function getModel($model)
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

    public static function reinitStores()
    {
        return true;
    }
}

class GetConfig
{
    public function getNode()
    {
        return new GetNode();
    }

    public function saveConfig($key, $value)
    {
        return true;
    }
    public function reinit()
    {
        return true;
    }
}

class GetNode
{
    public function __construct()
    {
        $this->modules = new getModule();
    }

    public function getCurrentCurrencyCode()
    {
        return "SEK";
    }

    public function getStoreId()
    {
        return 1;
    }
}
class getModule
{
    public function __construct()
    {
        $this->Fyndiq_Fyndiq = new FyndiqModule();
    }
}
class FyndiqModule
{
    public function __construct()
    {
        $this->version = "1.0.0";
    }
}

class GetLocale
{
    public function getLocaleCode()
    {
        return "SE";
    }
}
