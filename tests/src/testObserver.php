<?php

class ObserverTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->observer = new Fyndiq_Fyndiq_Model_Observer();
    }

    /**
     * @group ignore
     */
    public function testQuantity()
    {
        $product = new Catalog_Product();
        $qtystock = $this->observer->getQuantity($product, 1);

        $this->assertEquals(3, $qtystock);
    }

}
