<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Cron;

use Emarsys\Emarsys\Model\Order as EmarsysOrderModel;
use Emarsys\Emarsys\Helper\Cron as EmarsysCronHelper;
use Emarsys\Emarsys\Model\Logs;
use Magento\Store\Model\StoreManagerInterface;

class SmartInsightBulkExport
{
    /**
     * @var EmarsysCronHelper
     */
    protected $cronHelper;

    /**
     * @var EmarsysOrderModel
     */
    protected $emarsysOrderModel;

    /**
     * @var Logs
     */
    protected $emarsysLogs;

    /**
     * SmartInsightBulkExport constructor.
     *
     * @param EmarsysCronHelper $cronHelper
     * @param EmarsysOrderModel $order
     * @param StoreManagerInterface $storeManager
     * @param Logs $emarsysLogs
     */
    public function __construct(
        EmarsysCronHelper $cronHelper,
        EmarsysOrderModel $order,
        StoreManagerInterface $storeManager,
        Logs $emarsysLogs
    ) {
        $this->cronHelper = $cronHelper;
        $this->emarsysOrderModel = $order;
        $this->storeManager = $storeManager;
        $this->emarsysLogs = $emarsysLogs;
    }

    public function execute()
    {
        try {
            set_time_limit(0);
            $currentCronInfo = $this->cronHelper->getCurrentCronInformation(
                \Emarsys\Emarsys\Helper\Cron::CRON_JOB_SI_BULK_EXPORT
            );

            if (!$currentCronInfo) {
                return;
            }

            $data = \Zend_Json::decode($currentCronInfo->getParams());

            $storeId = isset($data['storeId']) ? $data['storeId'] : 0;
            $fromDate = isset($data['fromDate']) ? $data['fromDate'] : null;
            $toDate = isset($data['toDate']) ? $data['toDate'] : null;
            if (!$storeId) {
                throw new \Exception('store_id not specify');
            }

            /** @var \Magento\Store\Model\Store $store */
            $store = $this->storeManager->getStore($storeId);
            if (!$store || !$store->getId()) {
                throw new \Exception('store_id not specify');
            }

            $stores = $store->getWebsite()->getStores();

            foreach ($stores as $storeId => $store) {
                $this->emarsysOrderModel->syncOrders(
                    $storeId,
                    \Emarsys\Emarsys\Helper\Data::ENTITY_EXPORT_MODE_MANUAL,
                    $fromDate,
                    $toDate
                );
            }
        } catch (\Exception $e) {
            $this->emarsysLogs->addErrorLog(
                'SmartInsightBulkExport',
                $e->getMessage(),
                0,
                'SmartInsightBulkExport::execute()'
            );
        }
    }
}
