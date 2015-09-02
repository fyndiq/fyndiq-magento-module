<?php

class Mage_Core_Controller_Front_Action
{
    function getRequest()
    {
        return new paramClass();
    }
}
