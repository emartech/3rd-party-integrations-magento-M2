<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Mapping;

use Emarsys\Emarsys\Block\Adminhtml\Mapping\Placeholders\Grid;
use Magento\Backend\Block\Widget\Container;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;

class Placeholders extends Container
{
    /**
     * @var string
     */
    protected $_template = 'mapping/event/view.phtml';

    /**
     * Placeholders constructor.
     *
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return $this
     * @throws LocalizedException
     */
    protected function _prepareLayout()
    {
        $this->setChild(
            'grid',
            $this->getLayout()->createBlock(
                Grid::class,
                'emarsys.placeholders.grid'
            )
        );
        return parent::_prepareLayout();
    }

    /**
     * @return string
     */
    public function getGridHtml()
    {
        return $this->getChildHtml('grid');
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getLoadImageUrl()
    {
        $store = $this->_storeManager->getStore();
        $url = $store->getBaseUrl(UrlInterface::URL_TYPE_STATIC);

        return $url . "adminhtml/Magento/backend/en_US/images/loader-2.gif";
    }
}
