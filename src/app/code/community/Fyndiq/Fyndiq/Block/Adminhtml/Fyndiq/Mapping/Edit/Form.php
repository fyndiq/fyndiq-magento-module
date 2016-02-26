<?php

class Fyndiq_Fyndiq_Block_Adminhtml_Fyndiq_Mapping_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{

    protected function _prepareForm()
    {
        // Instantiate a new form to display our brand for editing.
        $form = new Varien_Data_Form(array(
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/save',
                array(
                    '_current' => true,
                    'continue' => 0,
                )
            ),
            'method' => 'post',
        ));
        $form->setUseContainer(true);
        $this->setForm($form);

        // Define a new fieldset. We need only one for our simple entity.
        $fieldset = $form->addFieldset(
            'general',
            array(
                'legend' => $this->__('Field map')
            )
        );

        $fieldset->addField('category_name', 'label', array(
            'label'     => $this->__('Category'),
            'name'      => 'category_name',
            'value'     => 'Tumbalumba',
        ));

        $fieldset->addField('fyndiq_category_id', 'text', array(
            'label'     => $this->__('Fyndiq Category'),
            'class'     => 'required-entry',
            'required'  => true,
            'name'      => 'fyndiq_category_id',
        ));

        return $this;
    }
}
