<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model\ResourceModel\CreditmemoExportStatus;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \Emarsys\Emarsys\Model\CreditmemoExportStatus::class,
            \Emarsys\Emarsys\Model\ResourceModel\CreditmemoExportStatus::class
        );
    }
}
