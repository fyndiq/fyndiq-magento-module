<?php

class AdminControllerTest extends PHPUnit_Framework_TestCase
{
    function setUp() {
        parent::setUp();
        $this->admincontroller = $this->getMockBuilder('Fyndiq_Fyndiq_AdminController')->setMethods(array('loadLayout', 'setupTemplate', 'renderLayout', 'getUsername', 'getAPIToken', 'callAPI'))->disableOriginalConstructor()->getMock();
    }

    function testIndexAction() {
        $this->admincontroller->expects($this->once())->method('loadLayout')->willReturn(true);
        $this->admincontroller->expects($this->once())->method('renderLayout')->willReturn(true);
        $this->admincontroller->expects($this->once())->method('callAPI')->willReturn(true);
        $return = $this->admincontroller->indexAction();
        $this->assertEquals(false, $return);
    }

    function testOrderlistAction() {
        $this->admincontroller->expects($this->once())->method('loadLayout')->willReturn(true);
        $this->admincontroller->expects($this->once())->method('renderLayout')->willReturn(true);
        $this->admincontroller->expects($this->once())->method('callAPI')->willReturn(true);
        $return = $this->admincontroller->orderlistAction();
        $this->assertEquals(false, $return);
    }

    function testSetTemplate() {
        $this->admincontroller->expects($this->once())->method('renderLayout')->willReturn(true);
        $this->admincontroller->expects($this->once())->method('getUsername')->willReturn("testuser");
        $this->admincontroller->expects($this->once())->method('getAPIToken')->willReturn("testapi");
        $this->admincontroller->expects($this->once())->method('callAPI')->willReturn(true);
        $return = $this->admincontroller->setTemplate(true);
        $this->assertEquals(true, $return);
    }
}