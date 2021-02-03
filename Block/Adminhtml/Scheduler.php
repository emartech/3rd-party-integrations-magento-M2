<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Schedular
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml;

use Emarsys\Emarsys\Block\Adminhtml\Scheduler\Grid;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Exception\LocalizedException;

class Scheduler extends Template
{
    protected $_template = 'cron/grid.phtml';

    /**
     * Scheduler constructor.
     *
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return $this
     * @throws LocalizedException
     */
    protected function _prepareLayout()
    {
        $this->setChild(
            'grid',
            $this->getLayout()->createBlock(
                Grid::class,
                'emarsys.logs.grid'
            )
        );
        return parent::_prepareLayout();
    }

    /**
     * Render grid
     *
     * @return string
     */
    public function getGridHtml()
    {
        return $this->getChildHtml('grid');
    }
}
