<?php

/**
 * Created by PhpStorm.
 * User: confact
 * Date: 03/09/14
 * Time: 10:35
 */
class FmConfig
{

    const COMMIT = 'XXXXXX';
    const REPOSITORY_PATH = 'fyndiq/fyndiq-magento-module/';
    const DISABLE_UPDATE_CHECK = 0;

    const CONFIG_NAME = 'fyndiq/fyndiq_group';

    private static function key($name)
    {
        return self::CONFIG_NAME . '/' . $name;
    }

    private static function getScope($storeId)
    {
        if ($storeId == 0) {
            return 'default';
        }
        return 'stores';
    }

    public static function delete($name)
    {
        return Mage::getConfig()->deleteConfig(self::key($name));
    }

    public static function get($name, $storeId)
    {
        $result = Mage::getStoreConfig(self::key($name), $storeId);
        // FIXME: Since prior versions use serialized values, we first try to unserialize the data
        // if that fails return the naked value; At some point this should be removed when we're sure
        // all data is stored as plain unserialized strings
        $data = @unserialize($result);
        if ($data !== false) {
            return $data;
        }
        return $result;
    }

    public static function getBool($name)
    {
        return (bool)Mage::getStoreConfigFlag(self::key($name));
    }

    public static function set($name, $value, $storeId)
    {
        return Mage::getConfig()->saveConfig(
            self::key($name),
            $value,
            self::getScope($storeId),
            $storeId
        );
    }

    public static function reInit()
    {
        Mage::getConfig()->reinit();
        Mage::app()->reinitStores();
    }

    public static function getVersion()
    {
        return (string)Mage::getConfig()->getNode()->modules->Fyndiq_Fyndiq->version;
    }

    public static function getUserAgent()
    {
        return FyndiqUtils::getUserAgentString("Magento", Mage::getVersion(), "module", FmConfig::getVersion(), FmConfig::COMMIT);
    }

    public static function getFeedPath($storeId)
    {
        return Mage::getBaseDir('cache') . '/feed-' . $storeId . '.csv';
    }
}
