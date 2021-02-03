<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model\ResourceModel\CreditmemoExport;

class Collection extends \Magento\Sales\Model\ResourceModel\Order\Creditmemo\Collection
{
    /**
     * Model initialization
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->entitySnapshot = null;
        $this->_init(
            \Emarsys\Emarsys\Model\CreditmemoExport::class,
            \Emarsys\Emarsys\Model\ResourceModel\CreditmemoExport::class
        );
    }

    /**
     * @inheritdoc
     */
    protected function beforeAddLoadedItem(\Magento\Framework\DataObject $item)
    {
        return $item;
    }
}