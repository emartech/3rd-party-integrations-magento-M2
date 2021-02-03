<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Schedular
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Scheduler\Renderer;

use Magento\Framework\DataObject;
use Magento\Backend\Block\Widget\Grid\Column\Renderer\AbstractRenderer;

class StatusColor extends AbstractRenderer
{
    /**
     * @param DataObject $row
     * @return string
     */
    public function render(DataObject $row)
    {
        $value = $row->getData($this->getColumn()->getIndex());
        switch (strtolower($value)) {
            case 'error':
                $color = 'red';
                break;
            case 'notice':
                $color = '#EB5202';
                break;
            default:
                $color = 'green';
        }

        return '<span style="color:' . $color . ';">' . ucwords($value) . '</span>';
    }
}
