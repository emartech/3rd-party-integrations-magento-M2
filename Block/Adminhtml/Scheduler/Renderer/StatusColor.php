<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Schedular
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Scheduler\Renderer;

use Magento\Framework\DataObject;

class StatusColor extends \Magento\Backend\Block\Widget\Grid\Column\Renderer\AbstractRenderer
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
     * @param DataObject $row
     * @return string
     */
    public function render(DataObject $row)
    {
        $value =  $row->getData($this->getColumn()->getIndex());
        if ($value == 'success') {
            return '<span style="color:green;">'.ucwords($value).'</span>';
        } elseif ($value == 'error') {
            return '<span style="color:red;">' . ucwords($value) . '</span>';
        } elseif ($value == 'notice') {
            return '<span style="color:#EB5202;">'.ucwords($value).'</span>';
        } else {
            return '<span style="color:green;">'.ucwords($value).'</span>';
        }
    }
}
