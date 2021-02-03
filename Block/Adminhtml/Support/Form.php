<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Support;

use Emarsys\Emarsys\Controller\Adminhtml\Support;
use Magento\Backend\Block\Widget\Context;
use Magento\Backend\Block\Widget\Form as WidgetForm;
use Magento\Framework\Exception\LocalizedException;

class Form extends WidgetForm
{
    /**
     * @var string
     */
    protected $_template = 'support.phtml';

    /**
     * Form constructor.
     *
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->setId('supportGeneralForm');
        $this->setTemplate('support.phtml');
    }

    /**
     * This method is called before rendering HTML
     *
     * @return $this
     * @throws LocalizedException
     */
    protected function _beforeToHtml()
    {
        $this->isFromError = $this->getRequest()->getParam('error') === 'true';

        $systemRequirementsBlock = $this->getLayout()->createBlock(
            Requirements::class,
            '',
            ['is_support_mode' => true]
        );
        $this->setChild('system_requirements', $systemRequirementsBlock);

        $emarsysVersionDetailsBlock = $this->getLayout()->createBlock(
            About::class,
            '',
            ['is_support_mode' => true]
        );
        $this->setChild('about_emarsys', $emarsysVersionDetailsBlock);

        return parent::_beforeToHtml();
    }
}

