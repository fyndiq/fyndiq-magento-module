<?php

class Catalog_Stock extends Mage_Core_Model_Abstract
{
    public function loadByProduct($product)
    {
        return $this;
    }

    public function getIsInStock()
    {
        return 1;
    }

    public function getQty()
    {
        return 3;
    }
}
