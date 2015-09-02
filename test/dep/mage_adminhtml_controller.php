<?php

class Mage_Adminhtml_Controller_Action
{
    function loadLayout($layout) {
        return true;
    }

    function getRequest() {
        return new paramClass();
    }

    function renderLayout($test = false) {
        return true;
    }

    function getLayout()
    {
        return new getLayout();
    }
}

class paramClass
{
    function getParam($test) {
        return 1;
    }
}

class getLayout
{
    function getBlock($test)
    {
        return new getBlock();
    }
    function CreateBlock($test, $test2)
    {
        return new getBlock();
    }
}

class getBlock
{
    function append($test)
    {
        return true;
    }

    function setTemplate($template)
    {
        return $this;
    }

    function setData($data, $data2)
    {
        return true;
    }
}
