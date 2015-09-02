<?php

class NotificationControllerTest extends PHPUnit_Framework_TestCase
{
    function setUp()
    {
        parent::setUp();
        $this->notification = $this->getMockBuilder('Fyndiq_Fyndiq_NotificationController')
            ->setMethods(array('getRequest', 'pingObserver', 'getFyndiqOutput', 'updateProductInfo'))
            ->disableOriginalConstructor()
            ->getMock();
        $this->request = $this->getMockBuilder('stdClass')
            ->setMethods(array('getParam'))
            ->getMock();
        $this->notification->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($this->request));
        $fyndiqOutput = $this->getMockBuilder('stdClass')
            ->setMethods(array('showError', 'flushHeader'))
            ->getMock();
        $this->notification->expects($this->any())
            ->method('getFyndiqOutput')
            ->will($this->returnValue($fyndiqOutput));
    }

    function testIndexAction()
    {
        $return = $this->notification->indexAction();
        $this->assertEquals(false, $return);
    }

    function testIndexActionWorking()
    {
        $this->request->expects($this->at(0))
            ->method('getParam')
            ->will($this->returnValue("ping"));
        $this->request->expects($this->at(1))
            ->method('getParam')
            ->will($this->returnValue("blablabla"));
        $this->notification->expects($this->once())
            ->method('pingObserver')->willReturn(true);
        $this->notification->indexAction();
    }
}
