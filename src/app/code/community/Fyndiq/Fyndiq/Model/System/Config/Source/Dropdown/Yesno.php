<?php

class Fyndiq_Fyndiq_Model_System_Config_Source_Dropdown_Yesno
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => '0',
                'label' => 'No',
            ),
            array(
                'value' => '1',
                'label' => 'Yes',
            ),
        );
    }
}
