<?php

class OrderFetchTest extends PHPUnit_Framework_TestCase
{

    /**
     * @var Fyndiq_Fyndiq_Model_OrderFetch
     */
    protected $orderFetch;

    public function setUp()
    {
        parent::setUp();
        $this->orderFetch = $this->getMockBuilder('Fyndiq_Fyndiq_Model_OrderFetch')
            ->setMethods(array('getOrderModel'))
            ->disableOriginalConstructor()
            ->getMock();

        $orderModel = $this->getMockBuilder('stdClass')
            ->setMethods(array('orderExists'))
            ->getMock();

        $orderModel->expects($this->any())
            ->method('orderExists')
            ->will($this->returnValue(true));

        $this->orderFetch->expects($this->any())
            ->method('getOrderModel')
            ->will($this->returnValue($orderModel));
    }

    public function testProcessDataProvider()
    {
        return array(
            array(
                array(),
                date('r', 0),
                true,
                'Return 0 if there are no orders'
            ),
            array(
                array(
                    (object)array(
                        'id' => 1,
                        'created' => '2014-12-01T14:25:32 +0100',
                    ),
                ),
                'Mon, 01 Dec 2014 14:25:32 +0100',
                true,
                'Return the last timestamp if there is an order with timestamp'
            ),
            array(
                array(
                    (object)array(
                        'id' => 1,
                        'created' => '2014-12-01T14:25:31 +0100',
                    ),
                    (object)array(
                        'id' => 2,
                        'created' => '2014-12-01T14:25:32 +0100',
                    ),
                ),
                'Mon, 01 Dec 2014 14:25:32 +0100',
                true,
                'Return the last timestamp if there are more than one timestamps'
            )
        );
    }


    /**
     * testProcessData
     * @param  object $data
     * @param  int $timestamp
     * @param  bool $result
     * @param  string $message
     * @dataProvider testProcessDataProvider
     */
    public function testProcessData($data, $timestamp, $expected, $message)
    {
        $result = $this->orderFetch->processData($data);
        $this->assertEquals($expected, $result);
        $this->assertEquals($timestamp, date('r', $this->orderFetch->getLastTimestamp()), $message);
    }
}
