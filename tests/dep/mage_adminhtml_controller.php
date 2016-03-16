<?php

class Mage_Adminhtml_Controller_Action
{
    function loadLayout($layout)
    {
        return true;
    }

    function getRequest()
    {
        return new ParamClass();
    }

    function renderLayout($test = false)
    {
        return true;
    }

    function getLayout()
    {
        return new GetLayout();
    }
}

class ParamClass
{
    function getParam($test)
    {
        return 1;
    }
}

class GetLayout
{
    function getBlock($test)
    {
        return new GetBlock();
    }
    function CreateBlock($test, $test2)
    {
        return new GetBlock();
    }
}

class GetBlock
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
