<?php

class NotificationControllerTest extends PHPUnit_Framework_TestCase
{
    function setUp() {
        parent::setUp();
        $this->notification = $this->getMockBuilder('Fyndiq_Fyndiq_NotificationController')
        ->setMethods(array('error', 'getParam', 'flushHeader', 'pingObserver', '_update_product_info'))
        ->disableOriginalConstructor()->getMock();
    }

    function testIndexAction() {
        $this->notification->expects($this->once())->method('error')->willReturn(true);
        $return = $this->notification->indexAction();
        $this->assertEquals(false, $return);
    }

    function testIndexActionWorking() {
        $this->notification->expects($this->at(0))->method('getParam')->will($this->returnValue("ping"));
        $this->notification->expects($this->at(1))->method('getParam')->will($this->returnValue("blablabla"));
        $this->notification->expects($this->once())->method('flushHeader')->willReturn(true);
        $this->notification->expects($this->once())->method('pingObserver')->willReturn(true);
        $this->setExpectedException('FyndiqAPIAuthorizationFailed');
        $this->notification->indexAction();
    }
}
