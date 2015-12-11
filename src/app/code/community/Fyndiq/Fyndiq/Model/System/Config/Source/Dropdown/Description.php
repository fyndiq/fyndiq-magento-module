<?php

class Fyndiq_Fyndiq_Model_System_Config_Source_Dropdown_Description
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => '1',
                'label' => 'Description',
            ),
            array(
                'value' => '2',
                'label' => 'Short Description',
            ),
            array(
                'value' => '3',
                'label' => 'Short and Long Description',
            ),
        );
    }
}
