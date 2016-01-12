<?php

class Fyndiq_Fyndiq_Model_System_Config_Source_Dropdown_Interval
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 10,
                'label' => sprintf(Mage::helper('fyndiq_fyndiq')->__('%d minutes'), 10),
            ),
            array(
                'value' => 30,
                'label' => sprintf(Mage::helper('fyndiq_fyndiq')->__('%d minutes'), 30),
            ),
            array(
                'value' => 60,
                'label' => sprintf(Mage::helper('fyndiq_fyndiq')->__('%d minutes'), 60),
            ),
        );
    }

    public function toArray()
    {
        return array(
            10 => 10,
            30 => 30,
            60 => 60,
        );
    }
}
