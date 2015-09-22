<?php

class AdminControllerTest extends PHPUnit_Framework_TestCase
{
    function setUp()
    {
        parent::setUp();
        $this->fyndiq_Fyndiq_AdminController = $this->getMockBuilder('Fyndiq_Fyndiq_AdminController')
            ->setMethods(array('loadLayout', 'setupTemplate', 'renderLayout', 'getUsername', 'getAPIToken', 'callAPI'))
            ->disableOriginalConstructor()->getMock();
    }

    function testIndexAction()
    {
        $this->fyndiq_Fyndiq_AdminController->expects($this->once())->method('getAPIToken')->willReturn('true');
        $this->fyndiq_Fyndiq_AdminController->expects($this->once())->method('getUsername')->willReturn('true');
        $this->fyndiq_Fyndiq_AdminController->expects($this->once())->method('loadLayout')->willReturn(true);
        $this->fyndiq_Fyndiq_AdminController->expects($this->once())->method('callAPI')->willReturn(true);
        $return = $this->fyndiq_Fyndiq_AdminController->indexAction();
        $this->assertEquals(false, $return);
    }

    function testSetTemplate()
    {
        $this->fyndiq_Fyndiq_AdminController->expects($this->once())->method('getAPIToken')->willReturn("testapi");
        $this->fyndiq_Fyndiq_AdminController->expects($this->once())->method('getUsername')->willReturn("testuser");
        $this->fyndiq_Fyndiq_AdminController->expects($this->once())->method('loadLayout')->willReturn(true);
        $this->fyndiq_Fyndiq_AdminController->expects($this->once())->method('callAPI')->willReturn(true);
        $this->fyndiq_Fyndiq_AdminController->expects($this->once())->method('setupTemplate')->willReturn(true);
        $result = $this->fyndiq_Fyndiq_AdminController->indexAction();
        $this->assertTrue($result);
    }
}
