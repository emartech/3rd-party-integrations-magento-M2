<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Support;

use Magento\Backend\Block\Widget\Context;
use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Magento\Backend\Block\Widget\Form as BlockForm;

class Requirements extends BlockForm
{
    /**
     * @var EmarsysHelper
     */
    protected $helper;

    /**
     * Requirements constructor.
     *
     * @param Context $context
     * @param EmarsysHelper $helper
     * @param array $data
     */
    public function __construct(
        Context $context,
        EmarsysHelper $helper,
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

