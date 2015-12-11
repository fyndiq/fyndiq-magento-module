<?php

class Fyndiq_Fyndiq_Model_System_Config_Source_Dropdown_Description
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 1,
                'label' => Mage::helper('adminhtml')->__('Description'),
            ),
            array(
                'value' => 2,
                'label' => Mage::helper('adminhtml')->__('Short Description'),
            ),
            array(
                'value' => 3,
                'label' => Mage::helper('adminhtml')->__('Short and Long Description'),
            ),
        );
    }

    public function toArray()
    {
        return array(
            1 => Mage::helper('adminhtml')->__('Description'),
            2 => Mage::helper('adminhtml')->__('Short Description'),
            3 => Mage::helper('adminhtml')->__('Short and Long Description'),
        );
    }

}
