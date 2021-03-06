<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */
namespace Emarsys\Emarsys\Block\System\Config\Button;

/**
 * Class WebDavButton
 * @package Emarsys\Emarsys\Block\System\Config\Button
 */
class WebDavButton extends AbstractButton
{
    /**
     * Path to block template
     */
    const TEST_CONNECTION_TEMPLATE = 'system/config/button/webdav.phtml';

    /**
     * Set template to itself
     * @return $this
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if (!$this->getTemplate()) {
            $this->setTemplate(static::TEST_CONNECTION_TEMPLATE);
        }
        return $this;
    }

    /**
     * @param $websiteId
     * @return string
     */
    protected function getAjaxActionUrl($websiteId)
    {
        return $this->getUrl("emarsys_emarsys/webdav/index", ["website" => $websiteId]);
    }
}
