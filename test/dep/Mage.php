<?php

class Mage
{
    static function App()
    {
        return new Mage();
    }

    static function getStoreConfig($test)
    {
        return $test;
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
}

class getConfig
{
    function getNode()
    {
        return new getNode();
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