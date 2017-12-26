<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Support\Edit;

class Tabs extends \Magento\Backend\Block\Widget\Tabs
{
    /**
     * Constructor
     */
    public function _construct()
    {
        parent::_construct();
        $this->setId("support_tabs");
        $this->setDestElementId("edit_form");
    }

    /**
     * @return Mage_Core_Block_Abstract
     * @throws Exception
     */
    protected function _beforeToHtml()
    {
        /*$this->addTab("form_section", array(
            "content" => $this->getLayout()->createBlock('Emarsys\Emarsys\Adminhtml\Support\Edit\Tab\Form')->toHtml(),
        ));*/
        $this->addTab(
            'form_section',
            [
                'content' => $this->getLayout()->createBlock('Emarsys\Emarsys\Adminhtml\Support\Edit\Tab\Form')->toHtml(),
                'active' => true
            ]
        );
        return parent::_beforeToHtml();
    }

    /**
     * @return Mage_Core_Block_Abstract|void
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $wysiwyg = $objectManager->create('Magento\Cms\Model\Wysiwyg\Config');
        if ($wysiwyg->isEnabled()) {
            $this->getLayout()->getBlock('head')->setCanLoadTinyMce(true);
        }
    }
}
