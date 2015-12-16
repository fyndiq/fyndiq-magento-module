<?php

require_once(Mage::getModuleDir('', 'Fyndiq_Fyndiq') . '/lib/shared/src/FyndiqUtils.php');
require_once(Mage::getModuleDir('', 'Fyndiq_Fyndiq') . '/lib/shared/src/FyndiqTranslation.php');

class Fyndiq_Fyndiq_Helper_Data extends Mage_Core_Helper_Abstract
{
    private $loaded = false;

    public function __()
    {
        $args = func_get_args();
        $string = array_shift($args);
        if (!$string) {
            return '';
        }
        if (!$this->loaded) {
            FyndiqTranslation::init(Mage::app()->getLocale()->getLocaleCode());
            $this->loaded = true;
        }
        return FyndiqTranslation::get($string);
    }
}
