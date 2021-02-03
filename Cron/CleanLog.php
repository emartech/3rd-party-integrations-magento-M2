<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Cron;

use Magento\Framework\App\ResourceConnection;
use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Magento\Store\Model\StoreManagerInterface;

class CleanLog
{
    /**
     * @var DateTime
     */
    protected $date;

    /**
     * @var ResourceConnection
     */
    protected $_resource;

    /**
     * @var Config
     */
    protected $resourceConfig;

    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * CleanLog constructor.
     *
     * @param ResourceConnection $resource
     * @param Config $resourceConfig
     * @param DateTime $date
     * @param EmarsysHelper $emarsysHelper
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ResourceConnection $resource,
        Config $resourceConfig,
        DateTime $date,
        EmarsysHelper $emarsysHelper,
        StoreManagerInterface $storeManager
    ) {
        $this->date = $date;
        $this->_resource = $resource;
        $this->resourceConfig = $resourceConfig;
        $this->emarsysHelper = $emarsysHelper;
        $this->storeManager = $storeManager;
    }

    public function execute()
    {
        /** @var \Magento\Store\Model\Store $store */
        foreach ($this->storeManager->getStores() as $store) {
            $logCleaning = $store->getConfig('logs/log_setting/log_cleaning');
            if ($logCleaning) {
                $logCleaningDays = $store->getConfig('logs/log_setting/log_days');
                $cleanUpDate = $this->date->date('Y-m-d', strtotime("-" . $logCleaningDays . " days"));
                $cleanUpDate = $this->emarsysHelper->getDateTimeInLocalTimezone($cleanUpDate);

                try {
                    /* Delete record from log_details tables */
                    $sqlConnection = $this->_resource->getConnection(
                        \Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION
                    );
                    $sqlConnection->delete(
                        $this->resourceConfig->getTable('emarsys_log_details'),
                        'DATE(created_at) <= "' . $cleanUpDate . '"'
                    );
                } catch (\Exception $e) {
                    $errorResult[] = $e->getMessage();
                }
            }
        }
    }
}
