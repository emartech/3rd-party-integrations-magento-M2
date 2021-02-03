<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Mapping\Emrattribute;

use Magento\Backend\Block\Widget\Grid\Extended;
use Magento\Framework\DataObject;

class Grid extends Extended
{
    /**
     * @param DataObject $row
     * @return string
     */
    public function getRowUrl($row)
    {
        return '';
    }
}
