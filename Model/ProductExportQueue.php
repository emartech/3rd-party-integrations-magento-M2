<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model;

use Magento\Framework\Model\AbstractExtensibleModel;

/**
 * Class Field
 */
class ProductExportQueue extends AbstractExtensibleModel
{
    const ID = 'id';
    const FROM = 'from';
    const TO = 'to';
    const STATUS = 'status';

    /**
     * constructor
     */
    public function _construct()
    {
        parent::_construct();
        $this->_init(ResourceModel\ProductExportQueue::class);
    }

    /**
     * @return int
     */
    public function getFrom()
    {
        return $this->_getData(self::FROM);
    }

    /**
     * @param int $from
     * @return void
     */
    public function setFrom($from)
    {
        $this->setData(self::FROM, $from);
    }

    /**
     * @return int|null
     */
    public function getTo()
    {
        return $this->_getData(self::TO);
    }

    /**
     * @param int|null $to
     * @return void
     */
    public function setTo($to)
    {
        $this->setData(self::TO, $to);
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->_getData(self::STATUS);
    }

    /**
     * @param string $status
     * @return void
     */
    public function setStatus($status)
    {
        $this->setData(self::STATUS, $status);
    }
}
