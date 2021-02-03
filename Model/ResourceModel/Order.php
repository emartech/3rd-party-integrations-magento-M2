<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model\ResourceModel;

use Emarsys\Emarsys\Model\Logs;
use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Eav\Model\Entity\Type;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Order
 */
class Order extends AbstractDb
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
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var array
     */
    protected $emarsysOrderFields = [];

    /**
     * @var Logs
     */
    protected $emarsysLogs;

    /**
     * Order constructor.
     *
     * @param Context $context
     * @param Type $entityType
     * @param Attribute $attribute
     * @param StoreRepositoryInterface $storeRepository
     * @param EmarsysHelper $emarsysHelper
     * @param StoreManagerInterface $storeManager
     * @param Logs $emarsysLogs
     * @param null $connectionName
     */
    public function __construct(
        Context $context,
        Type $entityType,
        Attribute $attribute,
        StoreRepositoryInterface $storeRepository,
        EmarsysHelper $emarsysHelper,
        StoreManagerInterface $storeManager,
        Logs $emarsysLogs,
        $connectionName = null
    ) {
        $this->entityType = $entityType;
        $this->attribute = $attribute;
        $this->storeRepository = $storeRepository;
        $this->emarsysHelper = $emarsysHelper;
        $this->_storeManager = $storeManager;
        $this->emarsysLogs = $emarsysLogs;
        parent::__construct($context, $connectionName);
    }

    /**
     * Define main table
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('emarsys_order_field_mapping', 'id');
    }

    /**
     * @param $storeId
     * @return string
     */
    public function getEmarsysAttrCount($storeId)
    {
        $select = $this->getConnection()
            ->select()
            ->from($this->getTable('emarsys_event_mapping'), 'count(*)')
            ->where("store_id = ?", $storeId);

        return $this->getConnection()->fetchOne($select);
    }

    /**
     * @return array
     * @throws \Zend_Db_Statement_Exception
     */
    public function getSalesOrderColumnNames()
    {
        $stmt = $this->getConnection()->query("
            SELECT `COLUMN_NAME` 
            FROM `INFORMATION_SCHEMA`.`COLUMNS` 
            WHERE table_schema = DATABASE() 
            AND `TABLE_NAME`='" . $this->getTable('sales_order') . "'
        ");
        return $stmt->fetchAll();
    }

    /**
     * @param $data
     * @param $storeId
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function insertIntoMappingTable($data, $storeId)
    {
        foreach ($data as $key => $value) {
            $select = $this->getConnection()
                ->select()
                ->from($this->getMainTable())
                ->where('magento_column_name = ?', $value['COLUMN_NAME'])
                ->where('store_id = ?', $storeId);

            $result = $this->getConnection()->fetchAll($select);

            if (empty($result)) {
                $this->getConnection()->insert($this->getMainTable(), [
                    'magento_column_name' => $value['COLUMN_NAME'],
                    'store_id' => $storeId
                ]);
            }
        }
    }

    /**
     * @param $data
     * @param $storeId
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function insertIntoMappingTableStaticData($data, $storeId)
    {
        foreach ($data as $key => $value) {
            $select = $this->getConnection()
                ->select()
                ->from($this->getMainTable())
                ->where('magento_column_name = ?', $value)
                ->where('store_id = ?', $storeId);

            $result = $this->getConnection()->fetchRow($select);

            $data = [];
            if (!empty($result)) {
                $data['id'] = $result['id'];
            }
            $data['magento_column_name'] = $value;
            $data['emarsys_order_field'] = $key;
            $data['store_id'] = $storeId;

            $this->getConnection()->insertOnDuplicate($this->getMainTable(), $data, ['emarsys_order_field']);
        }
    }

    /**
     * @param $data
     * @param $storeId
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function insertIntoMappingTableCustomValue($data, $storeId)
    {
        foreach ($data as $key => $value) {
            $select = $this->getConnection()
                ->select()
                ->from($this->getMainTable(), 'magento_column_name')
                ->where("magento_column_name = ?", $key);

            $result = $this->getConnection()->fetchOne($select);
            if (!$result) {
                //insert the attribute
                //for the first time enter the empty records for the emarsys id
                $this->getConnection()->insert($this->getMainTable(), [
                    'magento_column_name' => $key,
                    'emarsys_order_field' => $value,
                    'store_id' => $storeId
                ]);
            } else {
                //else update the attribute value
                $this->getConnection()->update(
                    $this->getMainTable(),
                    ['emarsys_order_field' => $value],
                    ['store_id = ?' => $storeId, 'magento_column_name = ?' => $key]
                );
            }
        }
    }

    /**
     * @param $storeId
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function orderMappingExists($storeId)
    {
        $select = $this->getConnection()
            ->select()
            ->from($this->getMainTable())
            ->where('store_id = ?', $storeId);

        return $this->getConnection()->fetchAll($select);
    }

    /**
     * @param $storeId
     * @return array|false
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getSalesMappedAttrs($storeId)
    {
        $headers = [];
        $select = $this->getConnection()
            ->select()
            ->from($this->getMainTable())
            ->where('store_id = ?', $storeId)
            ->where('emarsys_order_field != ?', '');

        $result = $this->getConnection()->fetchAll($select);
        if (!empty($result)) {
            foreach ($result as $key => $value) {
                array_push($headers, $value['emarsys_order_field']);
            }
        }

        return $headers;
    }

    /**
     * @param $storeId
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getEmarsysOrderFields($storeId)
    {
        if (!isset($this->emarsysOrderFields[$storeId])) {
            $heading = $this->emarsysHelper->getSalesOrderCsvDefaultHeader();
            $select = $this->getConnection()
                ->select()
                ->from($this->getMainTable())
                ->where('emarsys_order_field NOT IN (?)', $heading)
                ->where('emarsys_order_field != ?', '')
                ->where('store_id = ?', $storeId);

            $this->emarsysOrderFields[$storeId] = $this->getConnection()->fetchAll($select);
        }

        return $this->emarsysOrderFields[$storeId];
    }

    /**
     * @param $emarsysOrderField
     * @param $storeId
     * @return int|void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteOrderAttributeMapping($emarsysOrderField, $storeId)
    {
        $select = $this->getConnection()
            ->select()
            ->from($this->getMainTable())
            ->where('emarsys_order_field = (?)', $emarsysOrderField)
            ->where('store_id = (?)', $storeId);

        $result = $this->getConnection()->fetchAll($select);
        if (!empty($result)) {
            $updateResult = $this->getConnection()
                ->update(
                    $this->getMainTable(),
                    ['emarsys_order_field' => ''],
                    ['emarsys_order_field = ?' => $emarsysOrderField, 'store_id = ?' => $storeId]
                );
            return $updateResult;
        }
        return;
    }
}
