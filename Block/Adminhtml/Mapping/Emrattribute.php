<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Mapping;

use Emarsys\Emarsys\Block\Adminhtml\Mapping\Emrattribute\Grid;
use Emarsys\Emarsys\Model\ResourceModel\Emrattribute\CollectionFactory;
use Magento\Backend\Block\Widget\Container;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Exception\LocalizedException;

class Emrattribute extends Container
{
    /**
     * @var string
     */
    protected $_template = 'mapping/emrattribute/view.phtml';

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * Emrattribute constructor.
     *
     * @param Context $context
     * @param CollectionFactory $CollectionFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        CollectionFactory $collectionFactory,
        $data = []
    ) {
        $this->collectionFactory = $collectionFactory;
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
                'emarsys.add.attribute.grid'
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
     * @return mixed
     */
    public function emarattarData()
    {
        $store_id = $this->getRequest()->getParam('store');
        return $this->collectionFactory->create()->addFieldToFilter('store_id', ['eq' => $store_id])->getData();
    }
}
