<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Mapping\Placeholders\Renderer;

use Magento\Backend\Block\Widget\Grid\Column\Renderer\AbstractRenderer;
use Magento\Framework\DataObject;

class EmarsysPlaceholders extends AbstractRenderer
{
    /**
     * @param DataObject $row
     * @return string
     */
    public function render(DataObject $row)
    {
        static $i = 0;

        $html = '<input name="emarsys_placeholder_name" id="' . $row->getId() . '"'
            . ' value="' . $row->getEmarsysPlaceholderName() . '" width="100%"/>'
            . '<div class="placeholder-error validation-advice" id="divErrPlaceholder_' . $i . '"'
            . ' style="display:none; color:red">'
            . 'Placeholders can only have Alphanumerics and Underscores.'
            . '</div>';
        $i++;

        return $html;
    }
}
