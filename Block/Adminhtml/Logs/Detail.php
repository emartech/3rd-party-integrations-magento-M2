<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Logs;

use Emarsys\Emarsys\Model\LogsFactory;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Widget\Context;

/**
 * Class Detail
 *
 * @package Emarsys\Emarsys\Block\Adminhtml\Logs
 */
class Detail extends Template
{
    protected $_template = 'logs/detail.phtml';

    protected $logsFactory;

    /**
     * Detail constructor.
     *
     * @param Context $context
     * @param LogsFactory $logsFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        LogsFactory $logsFactory,
        $data = []
    ) {
        $this->logsFactory = $logsFactory;
        parent::__construct($context, $data);
    }

    public function getLog()
    {
        $collection = $this->logsFactory->create()->load($this->_request->getParam('id'));
        return $collection->getData();
    }
}
