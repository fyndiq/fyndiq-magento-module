<?php

class Fyndiq_Fyndiq_Block_Modules extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);
        $result = array();
        $modules = (array)Mage::getConfig()->getNode('modules')->children();
        foreach ($modules as $name => $module) {
            $result[] = implode(
                "\t",
                array(
                    $name,
                    $module->codePool,
                    $module->version,
                    $module->active == 'true' ? 'active' : '-'
                )
            );
        }
        $html = '<textarea readonly="readonly">'.implode("\n", $result).'</textarea>';
        return $html;
    }
}
