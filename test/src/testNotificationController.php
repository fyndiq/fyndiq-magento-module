<?php

class NotificationControllerTest extends PHPUnit_Framework_TestCase
{
    function setUp() {
        parent::setUp();;
        $this->notification = $this->getMockBuilder('Fyndiq_Fyndiq_NotificationController')
            ->setMethods(array('getParam', 'pingObserver', 'getFyndiqOutput'))
            ->disableOriginalConstructor()->getMock();
        $fyndiqOutput = $this->getMockBuilder('stdClass')
            ->setMethods(array('showError', 'flushHeader'))
            ->getMock();
        $this->notification->expects($this->any())
            ->method('getFyndiqOutput')
            ->will($this->returnValue($fyndiqOutput));
    }

    function testIndexAction() {
        $return = $this->notification->indexAction();
        $this->assertEquals(false, $return);
    }

    function testIndexActionWorking() {
        $this->notification->expects($this->at(0))->method('getParam')->will($this->returnValue("ping"));
        $this->notification->expects($this->at(1))->method('getParam')->will($this->returnValue("blablabla"));
        $this->notification->expects($this->once())->method('pingObserver')->willReturn(true);
        $this->setExpectedException('FyndiqAPIAuthorizationFailed');
        $this->notification->indexAction();
    }
}
