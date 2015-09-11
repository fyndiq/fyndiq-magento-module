<?php

class ObserverTest extends PHPUnit_Framework_TestCase
{
    function setUp()
    {
        parent::setUp();
        $this->observer = new Fyndiq_Fyndiq_Model_Observer();
    }

    function testQuantity()
    {
        $product = new Catalog_Product();
        $qtystock = $this->observer->getQuantity($product, 1);

        $this->assertEquals(3, $qtystock);
    }
}
