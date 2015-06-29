<?php

class Fyndiq_Fyndiq_Model_Resource_Setting extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('fyndiq/setting', 'id');
    }
}
