<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Schedular
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Cron\Renderer;

use Magento\Backend\Block\Widget\Grid\Column\Renderer\AbstractRenderer;
use Magento\Backend\Helper\Data;
use Magento\Framework\DataObject;

class Messagetype extends AbstractRenderer
{
    /**
     * @var Data
     */
    protected $backendHelper;

    /**
     * Messagetype constructor.
     *
     * @param Data $backendHelper
     */
    public function __construct(
        Data $backendHelper
    ) {
        $this->backendHelper = $backendHelper;
    }

    /**
     * @param DataObject $row
     */
    public function render(DataObject $row)
    {
        $logconfigurl = $this->backendHelper->getUrl('adminhtml/system_config/edit/section/emarsyslog');
        return "<a href='" . $logconfigurl . "' style='text-decoration:none'><div style='color:red'>Edit</div></a>";
    }
}
