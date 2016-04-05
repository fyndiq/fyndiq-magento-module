<?php

class Fyndiq_Fyndiq_Helper_Export_Test extends PHPUnit_Framework_TestCase
{

    public $helper = null;

    public function setUp()
    {
        parent::setUp();
        $this->helper = $this->getMockBuilder('Fyndiq_Fyndiq_Helper_Export')
            ->setMethods(
                array()
            )
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getProduct($typeId)
    {
        $stub = $this->getMockBuilder('stdClass')
            ->setMethods(array('getTypeId'))
            ->getMock();
        $stub->method('getTypeId')
             ->willReturn($typeId);
        return $stub;
    }

    public function testIsExportableStatusProvider()
    {
        return array(
            array(
                $this->getProduct('simple'),
                Fyndiq_Fyndiq_Helper_Export::IS_EXPORTABLE,
                'Simple product is exportable'
            ),

        );
    }

    /**
     * testIsExportableStatus
     * @dataProvider testIsExportableStatusProvider
     */
    public function testIsExportableStatus($product, $expected, $message)
    {
        $result = $this->helper->isExportableStatus($product);
        $this->assertEquals($expected, $result, $message);
    }
}
