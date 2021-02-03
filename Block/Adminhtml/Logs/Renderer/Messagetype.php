<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Logs\Renderer;

use Magento\Backend\Block\Widget\Grid\Column\Renderer\AbstractRenderer;
use Magento\Framework\DataObject;

class Messagetype extends AbstractRenderer
{
    /**
     * @param DataObject $row
     * @return string|void
     */
    public function render(DataObject $row)
    {
        $rowData = $row->getData();
        if (strtolower($rowData['message_type']) == 'error') {
            $usermsg = '<span style="color:red">Failed</span>';
        } elseif (strtolower($rowData['message_type']) == 'notice') {
            $usermsg = '<span style="color:orange">Notice</span>';
        } else {
            $usermsg = '<span style="color:green">Success</span>';
        }

        return $usermsg;
    }
}
