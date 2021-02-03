<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Schedular
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Scheduler\Renderer;

use Magento\Backend\Block\Widget\Grid\Column\Renderer\AbstractRenderer;
use Magento\Backend\Helper\Data;
use Magento\Framework\DataObject;

class ViewButton extends AbstractRenderer
{
    /**
     * @var Data
     */
    protected $backendHelper;

    /**
     * ViewButton constructor.
     *
     * @param Data   $backendHelper
     */
    public function __construct(
        Data $backendHelper
    ) {
        $this->backendHelper = $backendHelper;
    }

    /**
     * @param  DataObject $row
     * @return string|void
     */
    public function render(DataObject $row)
    {
        $url = $this->backendHelper->getUrl(
            'logs/grid/index',
            [
                'job_code' => $row['job_code'],
                'schedule_id' => $row['id'],
                'store' => $row['store_id'],
            ]
        );

        $html = '<a href="' . $url . '">'
            . '<div style="color:#EB5202; text-decoration:underline; text-decoration-color:#EB5202;">View</div>'
            . '</a>';

        return $html;
    }
}
