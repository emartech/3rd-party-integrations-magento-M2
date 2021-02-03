<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Mapping\Order\Renderer;

use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Magento\Backend\Block\Widget\Grid\Column\Renderer\AbstractRenderer;
use Magento\Framework\DataObject;

class EmarsysOrderField extends AbstractRenderer
{
    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * EmarsysOrderField constructor.
     *
     * @param EmarsysHelper $emarsysHelper
     */
    public function __construct(
        EmarsysHelper $emarsysHelper
    ) {
        $this->emarsysHelper = $emarsysHelper;
    }

    /**
     * @param DataObject $row
     * @return string
     */
    public function render(DataObject $row)
    {
        $heading = $this->emarsysHelper->getSalesOrderCsvDefaultHeader();
        array_unshift($heading, 'website_id');
        if (in_array($row->getData('emarsys_order_field'), $heading)
            || (in_array($row->getData('magento_column_name'), $heading))
        ) {
            $html = '<label >' . $row->getData('emarsys_order_field') . '</label>';
        } else {
            $html = '<input class="admin__control-text emarsysatts" type="text"'
                . ' name="' . $row->getData('magento_column_name') . '"'
                . ' value="' . $row->getData('emarsys_order_field') . '"/>';
        }

        return $html;
    }
}
