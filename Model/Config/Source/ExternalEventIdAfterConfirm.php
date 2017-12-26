<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model\Config\Source;

class ExternalEventIdAfterConfirm implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @var
     */
    protected $resource;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;
    /**
     * @var
     */
    protected $connection;
    /**
     * @var \Magento\Config\Model\ResourceModel\Config
     */
    protected $config;

    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Config\Model\ResourceModel\Config $config,
        \Emarsys\Emarsys\Model\Logs $emarsysLogs,
        \Magento\Framework\App\ResourceConnection $resource
    ) {
    
        $this->_storeManager = $storeManager;
        $this->config = $config;
        $this->emarsysLogs = $emarsysLogs;
        $this->_resource = $resource;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $connection = $this->_resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
        $storeId = $this->_storeManager->getStore()->getStoreId();
        if ($storeId == 0) {
            $storeId = 1;
        }
        $result = [];
        $sql = "SELECT * FROM " . $this->config->getTable('emarsys_events') . " WHERE store_id = " . $storeId;
        try {
            $result = $connection->fetchAll($sql);
        } catch (\Exception $e) {
            $this->emarsysLogs->addErrorLog($e->getMessage(),$storeId,'toOptionArray(ExternalEventIdAfterConfirm)');
        }
        $eventData = [];
        $eventData[] = ['value' => '', 'label' => 'None'];
        foreach ($result as $event) {
            $eventData[] = ['value' => $event['event_id'], 'label' => $event['emarsys_event']];
        }
        return $eventData;
    }
}
