<?php

class Mage_Adminhtml_Controller_Action
{
    public function loadLayout($layout)
    {
        return true;
    }

    public function getRequest()
    {
        return new ParamClass();
    }

    public function renderLayout($test = false)
    {
        return true;
    }

    public function getLayout()
    {
        return new GetLayout();
    }
}

class ParamClass
{
    public function getParam($test)
    {
        return 1;
    }
}

class GetLayout
{
    public function getBlock($test)
    {
        return new GetBlock();
    }
    public function CreateBlock($test, $test2)
    {
        return new GetBlock();
    }
}

class GetBlock
{
    public function append($test)
    {
        return true;
    }

    public function setTemplate($template)
    {
        return $this;
    }

    public function setData($data, $data2)
    {
        return true;
    }
}
