<?php

class Fyndiq_Fyndiq_Block_Adminhtml_Fyndiq_Mapping_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{

    protected function _prepareForm()
    {
        $categoryId  = (int)$this->getRequest()->getParam('id');

        // Instantiate a new form to display our brand for editing.
        $form = new Varien_Data_Form(array(
            'id' => 'edit_form',
            'action' => $this->getUrl(
                '*/*/save',
                array(
                    '_current' => true,
                    'continue' => 0,
                )
            ),
            'method' => 'post',
        ));
        $form->setUseContainer(true);
        $this->setForm($form);

        $fieldset = $form->addFieldset(
            'general',
            array(
                'legend' => Mage::helper('fyndiq_fyndiq')->__('Map Category')
            )
        );

        $fieldset->addField('category_name', 'label', array(
            'label'     => Mage::helper('fyndiq_fyndiq')->__('Category'),
            'name'      => 'category_name',
            'value'     => Mage::getModel('fyndiq/export')->getCategoryName($categoryId),
        ));

        $fyndiqCategoryId = 0;
        if ($categoryId) {
            $category = Mage::getModel('catalog/category')
                ->setStoreId($this->getRequest()->getParam('store', 0))
                ->load($categoryId);
            $fyndiqCategoryId = (int)$category->getFyndiqCategoryId();
        }


        // FIXME: Use proper model once you figure out how it is supposed to work
        $langCode = Mage::app()->getLocale()->getLocaleCode();
        $fieldName = substr($langCode, 0, 2) == 'de' ? 'name_de' : 'name_sv';

        $categories = Mage::getModel('fyndiq/category')->getCategories();

        // Add the zero option
        $values = array(
            array(
                'value' => 0,
                'label' => Mage::helper('fyndiq_fyndiq')->__('none'),
            )
        );
        foreach ($categories as $item) {
            $values[] = array(
                'value' => $item['id'],
                'label' => $item[$fieldName],
            );
        }

        $field = $fieldset->addField(
            'fyndiq_category_id',
            'select',
            array(
                'label'     => Mage::helper('fyndiq_fyndiq')->__('Fyndiq Category'),
                'class'     => 'required-entry',
                'required'  => true,
                'name'      => 'fyndiq_category_id',
                'value'     => $fyndiqCategoryId,
                'values'    => $values,
            )
        );

        $field->setAfterElementHtml("
            <style>
                .hor-scroll{
                    height:500px
                }
                #fyndiq_category_id{
                    width: 100%;
                }
            </style>
            <script>
            document.observe('dom:loaded', function(evt) {
                var element = $$('#fyndiq_category_id');
                if (element.length > 0) {
                    new Chosen(element[0], {});
                }
            });
        </script>");

        return $this;
    }
}
