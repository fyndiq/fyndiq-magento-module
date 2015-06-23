<?php

/**
 * Created by PhpStorm.
 * User: confact
 * Date: 03/09/14
 * Time: 10:35
 */
class FmConfig
{

    const CONFIG_NAME = 'fyndiq/fyndiq_group';

    private static function key($name)
    {
        return self::CONFIG_NAME . '/' . $name;
    }

    public static function delete($name)
    {
        return Mage::getConfig()->deleteConfig(self::key($name));
    }

    public static function get($name, $storeId)
    {
        return Mage::getStoreConfig(self::key($name), $storeId);
    }

    public static function getBool($name)
    {
        return (bool)Mage::getStoreConfigFlag(self::key($name));
    }

    public static function set($name, $value)
    {
        return Mage::getConfig()->saveConfig(self::key($name), serialize($value));
    }

    public static function getVersion()
    {
        return (string)Mage::getConfig()->getNode()->modules->Fyndiq_Fyndiq->version;
    }

    public static function getFeedPath($storeId)
    {
        return 'fyndiq/files/feed-' . $storeId . '.csv';
    }
}
