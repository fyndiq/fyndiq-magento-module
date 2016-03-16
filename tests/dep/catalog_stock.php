<?php

class Catalog_Stock extends Mage_Core_Model_Abstract
{
    function loadByProduct($product)
    {
        return $this;
    }

    function getIsInStock()
    {
        return 1;
    }

    function getQty()
    {
        return 3;
    }
}
