<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Log
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Log\Renderer;

use Magento\Framework\DataObject;

class ViewButton extends \Magento\Backend\Block\Widget\Grid\Column\Renderer\AbstractRenderer
{
    /**
     * @var \Magento\Backend\Model\Session
     */
    protected $session;

    /**
     * @var \Magento\Backend\Helper\Data
     */
    protected $backendHelper;

    /**
     * @param \Magento\Backend\Model\Session $session
     * @param \Magento\Backend\Helper\Data $backendHelper
     */
    public function __construct(
        \Magento\Backend\Model\Session $session,
        \Magento\Backend\Helper\Data $backendHelper
    ) {
    
        $this->session = $session;
        $this->backendHelper = $backendHelper;
    }

    /**
     * @param Varien_Object $row
     * @return string
     */
    public function render(DataObject $row)
    {
        $rowData = $row->getData();
        $url = 'log/logdetails/logdetails/id/' . $rowData['id'];
        $url = $this->backendHelper->getUrl($url);
        if ($rowData['message_type'] == 'Success') {
            $usermsg = "<span style='color:green'>Success</span>";
        } else {
            $usermsg = "<span style='color:red'>Failed</span>";
        }
         printf("<a href='" . $url . "' style='text-decoration:none'>" . $usermsg. "</a>");
    }
}
