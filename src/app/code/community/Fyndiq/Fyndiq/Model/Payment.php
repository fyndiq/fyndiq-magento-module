<?php

class Fyndiq_Fyndiq_Model_Payment extends Mage_Payment_Model_Method_Abstract
{

    protected $_code = 'fyndiq_fyndiq';

    protected $_isInitializeNeeded = true;
    protected $_canUseInternal = false;
    protected $_canUseForMultishipping = false;
    protected $_canUseCheckout = false;
}
