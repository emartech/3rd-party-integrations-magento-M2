<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model\ResourceModel;

use Magento\Eav\Model\Entity\Attribute;
use Magento\Eav\Model\Entity\Type;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Store\Api\StoreRepositoryInterface;

/**
 * Class Event
 */
class Event extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * @var StoreRepositoryInterface
     */
    protected $storeRepository;
    /**
     * @var Attribute
     */
    protected $attribute;
    /**
     * @var Type
     */
    protected $entityType;

    /**
     * @param Context $context
     * @param Type $entityType
     * @param Attribute $attribute
     * @param StoreRepositoryInterface $storeRepository
     * @param null $connectionName
     */
    public function __construct(
        Context $context,
        Type $entityType,
        Attribute $attribute,
        StoreRepositoryInterface $storeRepository,
        $connectionName = null
    ) {
        $this->entityType = $entityType;
        $this->attribute = $attribute;
        $this->storeRepository = $storeRepository;
        parent::__construct($context, $connectionName);
    }

    /**
     * Define main table
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('magento_event_templates', 'id');
    }

    /**
     * @param $storeId
     * @return array
     */
    public function getEmarsysEvents($storeId)
    {
        $select = $this->getConnection()
            ->select()
            ->from($this->getTable('emarsys_events'))
            ->where("store_id = ?", $storeId);

        return $this->getConnection()->fetchAll($select);
    }

    /**
     * @param $storeId
     * @return string
     */
    public function getMagentoEventTemplateCount($storeId)
    {
        $select = $this->getConnection()
            ->select()
            ->from($this->getTable('emarsys_events'), 'count(*)')
            ->where("store_id = ?", $storeId);

        return $this->getConnection()->fetchOne($select);
    }

    /**
     * @param $storeId
     * @return string
     */
    public function checkEventMappingCount($storeId)
    {
        $select = $this->getConnection()
            ->select()
            ->from($this->getTable('emarsys_events'), 'count(*)')
            ->where("store_id = ?", $storeId);

        return $this->getConnection()->fetchOne($select);
    }

    /**
     * @param $eventId
     * @param $storeId
     * @return int
     */
    public function deleteEvent($eventId, $storeId)
    {
        return $this->getConnection()->delete(
            $this->getTable('emarsys_events'),
            $this->getConnection()->quoteInto("store_id = ?", $storeId)
            .  $this->getConnection()->quoteInto(" AND event_id = ?", $eventId)
        );
    }

    /**
     * @param $emarsysEventId
     * @param $storeId
     * @return int
     */
    public function deleteEventMapping($emarsysEventId, $storeId)
    {
        return $this->getConnection()->delete(
            $this->getTable('emarsys_event_mapping'),
            $this->getConnection()->quoteInto("store_id = ?", $storeId)
            .  $this->getConnection()->quoteInto(" AND emarsys_event_id = ?", $emarsysEventId)
        );
    }
}
