<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Eav\Model\Entity\Type;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Store\Api\StoreRepositoryInterface;

/**
 * Class Order
 * @package Emarsys\Emarsys\Model\ResourceModel
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
     * Order constructor.
     *
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
        $this->_init('emarsys_order_field_mapping', 'id');
    }

    /**
     * @param $storeId
     * @return string
     */
    public function getEmarsysAttrCount($storeId)
    {
        $emarsysFieldCount = $this->getConnection()->fetchOne("SELECT count(*) FROM " .
            $this->getTable('emarsys_event_mapping') . " WHERE store_id=" . $storeId . "");
        return $emarsysFieldCount;
    }

    /**
     * @param $storeId
     * @return string
     */
    public function checkOrderMapping($storeId)
    {
        $customerAttributes = $this->getConnection()->fetchOne("SELECT count(*) FROM " .
            $this->getTable('emarsys_event_mapping') . " WHERE store_id =" . $storeId);
        return $customerAttributes;
    }

    /**
     * @return array
     */
    public function getSalesOrderColumnNames()
    {
        $stmt = $this->getConnection()->query("SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE  `TABLE_NAME`='" .
            $this->getTable('sales_order') . "'");
        $result = $stmt->fetchAll();
        return $result;
    }

    /**
     * @param $data
     * @param $storeId
     */
    public function insertIntoMappingTable($data, $storeId)
    {
        foreach ($data as $key => $value) {
            $value['COLUMN_NAME'] = $this->getConnection()->quote($value['COLUMN_NAME']);
            $stmt = $this->getConnection()->query("SELECT magento_column_name FROM " .
                $this->getTable('emarsys_order_field_mapping') . " WHERE magento_column_name = " .
                $value['COLUMN_NAME'] . " ");

            $result = $stmt->fetch();
            if ($result == 0) {
                //insert the attribute
                //for the first time enter the empty records for the emarsys id
                $stmt = $this->getConnection()->query("INSERT INTO  " .
                    $this->getTable('emarsys_order_field_mapping') .
                    " (magento_column_name,emarsys_order_field,store_id) VALUES (" .
                    $value['COLUMN_NAME'] . "," . "''" . "," . $storeId . ") ");
            }
        }
    }

    /**
     * @param $data
     * @param $storeId
     */
    public function insertIntoMappingTableStaticData($data, $storeId)
    {
        foreach ($data as $key => $value) {
            $value = $this->getConnection()->quote($value);
            $key = $this->getConnection()->quote($key);
            $stmt = $this->getConnection()->query("SELECT magento_column_name FROM " .
                $this->getTable('emarsys_order_field_mapping') . " WHERE magento_column_name = " .
                $value . " ");

            $result = $stmt->fetch();
            if ($result == 0) {
                //insert the attribute
                //for the first time enter the empty records for the emarsys id
                $stmt = $this->getConnection()->query("INSERT INTO  " . $this->getTable('emarsys_order_field_mapping') .
                    " (magento_column_name,emarsys_order_field,store_id) VALUES (" . $value . "," . $key . ",'" .
                    $storeId . "')  ");
            }
        }
    }

    /**
     * @param $data
     * @param $storeId
     */
    public function insertIntoMappingTableCustomValue($data, $storeId)
    {
        foreach ($data as $key => $value) {
            $key = $this->getConnection()->quote($key);
            $value = $this->getConnection()->quote($value);
            $stmt = $this->getConnection()->query("SELECT magento_column_name FROM " .
                $this->getTable('emarsys_order_field_mapping') . " WHERE magento_column_name = " . $key . " ");
            $result = $stmt->fetch();
            if ($result == 0) {
                //insert the attribute
                //for the first time enter the empty records for the emarsys id
                $stmt = $this->getConnection()->query("INSERT INTO  " . $this->getTable('emarsys_order_field_mapping') .
                    " (magento_column_name,emarsys_order_field,store_id) VALUES (" . $key . "," . $value . ",'" . $storeId . "')  ");
            } else {
                //else update the attribute value
                $stmt = $this->getConnection()->query("UPDATE " . $this->getTable('emarsys_order_field_mapping') .
                    " SET emarsys_order_field = " . $value . " WHERE magento_column_name = " . $key . "");
            }
        }
    }

    /**
     * @return mixed
     */
    public function orderMappingExists()
    {
        $stmt = $this->getConnection()->query("SELECT magento_column_name FROM " .
            $this->getTable('emarsys_order_field_mapping') . "  ");
        $result = $stmt->fetch();
        return $result;
    }

    /**
     * @return array
     */
    public function getEmarsysOrderFields()
    {
        $heading = ['order', 'date', 'customer', 'item', 'unit_price', 'c_sales_amount', 'quantity'];
        $excludeFields = implode("', '", $heading);
        $headingStr = implode(',', $heading);
        $stmt = $this->getConnection()->query("SELECT emarsys_order_field FROM " . $this->getTable('emarsys_order_field_mapping') . " WHERE emarsys_order_field NOT IN ('" . $excludeFields . "') ");
        $result = $stmt->fetchAll();
        return $result;
    }

    /**
     * @param $emarsysField
     * @param $orderId
     * @return mixed|void
     */
    public function getOrderColValue($emarsysField, $orderId)
    {
        $emarsysField = $this->getConnection()->quote($emarsysField);
        $heading = ['order', 'date', 'customer', 'item', 'unit_price', 'c_sales_amount', 'quantity'];
        if (in_array($emarsysField, $heading)) {
            return;
        }
        $stmt = $this->getConnection()->query("SELECT magento_column_name FROM " . $this->getTable('emarsys_order_field_mapping') . " WHERE emarsys_order_field = " . $emarsysField);
        $result = $stmt->fetch();
        if ($result['magento_column_name'] == 'created_at') {
            $stmt = $this->getConnection()->query("SELECT ".$result['magento_column_name']." as created_at FROM ".$this->getTable('sales_order')." WHERE entity_id = '".$orderId."'  ");
        } elseif ($result['magento_column_name'] == 'updated_at') {
            $stmt = $this->getConnection()->query("SELECT ".$result['magento_column_name']." as updated_at FROM ".$this->getTable('sales_order')." WHERE entity_id = '".$orderId."'  ");
        } elseif ($result['magento_column_name'] == 'customer_dob') {
            $stmt = $this->getConnection()->query("SELECT DATE_FORMAT(".$result['magento_column_name'].",'%Y-%m-%d') as magento_column_value FROM ".$this->getTable('sales_order')." WHERE entity_id = '".$orderId."'  ");
        } else {
            $stmt = $this->getConnection()->query("SELECT ".$result['magento_column_name']." as magento_column_value FROM ".$this->getTable('sales_order')." WHERE entity_id = '".$orderId."'  ");
        }

        return $stmt->fetch();
    }

    /**
     * @return array
     */
    public function getStores()
    {
        $stmt = $this->getConnection()->query("SELECT * FROM " . $this->getTable('store') . " ");
        $result = $stmt->fetchAll();
        return $result;
    }
}

