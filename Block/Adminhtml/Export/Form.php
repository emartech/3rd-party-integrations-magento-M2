<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Export;

use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Magento\Backend\Block\Widget\Context;

class Form extends \Magento\Backend\Block\Widget\Form
{
    protected $_template = 'bulkexport/bulkexport.phtml';

    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * Form constructor.
     *
     * @param Context $context
     * @param array $data
     * @param EmarsysHelper $emarsysHelper
     */
    public function __construct(
        Context $context,
        array $data = [],
        EmarsysHelper $emarsysHelper
    ) {
        parent::__construct($context, $data);
        $this->setId('orderExportForm');
        $this->emarsysHelper = $emarsysHelper;
    }

    /**sudo a
     * @return $this
     */
    protected function _beforeToHtml()
    {
        $this->isFromError = $this->getRequest()->getParam('error') === 'true';
        return parent::_beforeToHtml();
    }

    /**
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getStoreId()
    {
        $storeId = $this->getRequest()->getParam('store');
        return $this->emarsysHelper->getFirstStoreIdOfWebsiteByStoreId($storeId);
    }
}
