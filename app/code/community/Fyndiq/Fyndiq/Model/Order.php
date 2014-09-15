<?php
class Fyndiq_Fyndiq_Model_Order extends Mage_Core_Model_Abstract
{

    public function _construct()
    {
        parent::_construct();
        $this->_init('fyndiq/order');
    }

    public function orderExists($fyndiq_order) {
        $collection = $this->getCollection()
            ->addFieldToFilter('fyndiq_orderid', $fyndiq_order)
            ->getFirstItem();
        if($collection->getId()){
            return true;
        }
        else {
            return false;
        }
    }
}