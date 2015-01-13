<?php
/**
 * Created by PhpStorm.
 * User: confact
 * Date: 03/09/14
 * Time: 10:35
 */

class FmConfig {

    private static $config_name = "fyndiq/fyndiq_group";

    public static function delete($name) {
        return Mage::getConfig()->deleteConfig(self::$config_name.'/'.$name);
    }

    public static function get($name) {
        return Mage::getStoreConfig(self::$config_name.'/'.$name);
    }

    public static function getBool($name) {
        return (bool)Mage::getStoreConfigFlag(self::$config_name.'/'.$name);
    }

    public static function set($name, $value) {
        return Mage::getConfig()->saveConfig(self::$config_name.'/'.$name, serialize($value));
    }

    public static function getVersion() {
        return (string)Mage::getConfig()->getNode()->modules->Fyndiq_Fyndiq->version;
    }

    public static function getFeedPath() {
        return "fyndiq/files/feed.csv";
    }
} 