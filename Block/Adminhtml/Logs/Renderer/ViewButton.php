<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Log\Renderer;

use Magento\Backend\Block\Widget\Grid\Column\Renderer\AbstractRenderer;
use Magento\Backend\Helper\Data;
use Magento\Framework\DataObject;

/**
 * Class ViewButton
 *
 * @package Emarsys\Emarsys\Block\Adminhtml\Log\Renderer
 */
class ViewButton extends AbstractRenderer
{
    /**
     * @var Data
     */
    protected $backendHelper;

    /**
     * ViewButton constructor.
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
     * @return string
     */
    public function render(DataObject $row)
    {
        $rowData = $row->getData();
        $url = $this->backendHelper->getUrl('log/logdetails/logdetails', ['id' => $rowData['id']]);
        if (strtolower($rowData['message_type']) == 'error') {
            $usermsg = '<span style="color:red">Failed</span>';
        } elseif (strtolower($rowData['message_type']) == 'notice') {
            $usermsg = '<span style="color:orange">Notice</span>';
        } else {
            $usermsg = '<span style="color:green">Success</span>';
        }

        return '<a href="' . $url . '" style="text-decoration:none">' . $usermsg . '</a>';
    }
}
