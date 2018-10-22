<?php

class ExportTest extends PHPUnit_Framework_TestCase
{

    protected function setUp()
    {
        $this->exportModel = $this->getMockBuilder('Fyndiq_Fyndiq_Model_Export')
            ->disableOriginalConstructor()
            ->setMethods(array('getUnmappedCategoriesCount'))
            ->getMock();
    }

    public function testGetUnmappedCategoriesCountProvider()
    {
        return array(
            array(
                array(
                    array(
                        1 => array(
                            'product-category-id' => 1
                        ),
                    ),
                ),
                0
            ),
            array(
                array(
                    array(),
                ),
                0
            ),
        );
    }

    /**
     * testGetUnmappedCategoriesCount
     * @dataProvider testGetUnmappedCategoriesCountProvider
     */
    public function testGetUnmappedCategoriesCount($cache, $expected)
    {
        $result = $this->exportModel->getUnmappedCategoriesCount($cache);
        $this->assertEquals($expected, $result);
    }
}
