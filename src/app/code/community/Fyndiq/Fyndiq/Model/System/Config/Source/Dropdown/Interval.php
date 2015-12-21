<?php

class Fyndiq_Fyndiq_Model_System_Config_Source_Dropdown_Interval
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 10,
                'label' => 10,
            ),
            array(
                'value' => 30,
                'label' => 30,
            ),
            array(
                'value' => 60,
                'label' => 60,
            ),
            array(
                'value' => 120,
                'label' => 120,
            ),
            array(
                'value' => 180,
                'label' => 180,
            ),
        );
    }

    public function toArray()
    {
        return array(
            10 => 10,
            30 => 30,
            60 => 60,
            120 => 120,
            180 => 180,
        );
    }
}
