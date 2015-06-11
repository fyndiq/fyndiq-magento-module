<?php

class AdminControllerTest extends PHPUnit_Framework_TestCase
{
    function setUp() {
        parent::setUp();
        $this->admincontroller = $this->getMockBuilder('Fyndiq_Fyndiq_AdminController')->setMethods(array('loadLayout','setTemplate'))->disableOriginalConstructor()->getMock();
    }

    function testIndexAction() {
        $this->admincontroller->expects($this->once())->method('loadLayout')->willReturn(true);
        $this->admincontroller->expects($this->once())->method('setTemplate')->willReturn(true);
        $return = $this->admincontroller->indexAction();
        $this->assertEquals($return, true);
    }

    function testOrderListAction() {

    }
}