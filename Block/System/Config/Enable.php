<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Enable extends Field
{
    /**
     * @var ScopeConfigInterface
     */
    private $config;

    /**
     * Enable constructor.
     *
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        array $data = []
    ) {
        $this->config = $context->getScopeConfig();
        parent::__construct($context, $data);
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $value = (int)$this->config->getValue('emartech/emarsys_setting/enable');

        return '<input name="' . $element->getName() . '"'
            . ' id="' . $element->getId() . '"'
            . ' type="hidden"'
            . ' value="' . $value . '" />';
    }
}

