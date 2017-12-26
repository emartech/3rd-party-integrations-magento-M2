<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */
namespace Emarsys\Emarsys\Block\System\Config\Form;

use Magento\Framework\Data\Form\Element\AbstractElement;

class ConfirmPassword extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $html = "
                <input  type='password' id='emarsys_settings_emarsys_setting_confirm_password' name='groups[emarsys_setting][fields][confirm_password][value]' onchange='javascript:confirmPassword(); return false;'>
                <span id='errorConfirmPassword' style='color: red'></span>
            ";
        return $html;
    }
}
