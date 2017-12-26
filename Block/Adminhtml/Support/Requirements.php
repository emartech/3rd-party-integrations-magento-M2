<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */
namespace Emarsys\Emarsys\Block\Adminhtml\Support;

use Magento\Backend\Block\Widget\Context;
use Emarsys\Emarsys\Helper\Data;
use Magento\Backend\Block\Widget\Form;

/**
 * Class Requirements
 * @package Emarsys\Emarsys\Block\Adminhtml\Support
 */
class Requirements extends Form
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * Requirements constructor.
     *
     * @param Context $context
     * @param Data $helper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Data $helper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->helper = $helper;
        $this->setId('developmentInspectionRequirements');
        $this->setTemplate('requirements.phtml');
    }

    protected function _beforeToHtml()
    {
        $this->requirements = $this->helper->getRequirementsInfo();
        return parent::_beforeToHtml();
    }
}

