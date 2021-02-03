<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Cron;

use Emarsys\Emarsys\Model\Product as EmarsysProductModel;
use Emarsys\Emarsys\Helper\Cron as CronHelper;
use Emarsys\Emarsys\Model\Logs;
use Emarsys\Emarsys\Model\ProductExportAsync as ProductExportAsync;
use Magento\Store\Model\StoreManagerInterface;
use Emarsys\Emarsys\Helper\Data;

class ProductBulkExport
{
    /**
     * @var CronHelper
     */
    protected $cronHelper;

    /**
     * @var EmarsysProductModel
     */
    protected $emarsysProductModel;

    /**
     * @var Logs
     */
    protected $emarsysLogs;

    /**
     * @var ProductExportAsync
     */
    private $productAsync;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * ProductBulkExport constructor.
     *
     * @param CronHelper $cronHelper
     * @param EmarsysProductModel $emarsysProductModel
     * @param Logs $emarsysLogs
     * @param StoreManagerInterface $storeManager
     * @param ProductExportAsync $productAsync
     */
    public function __construct(
        CronHelper $cronHelper,
        EmarsysProductModel $emarsysProductModel,
        Logs $emarsysLogs,
        StoreManagerInterface $storeManager,
        ProductExportAsync $productAsync
    ) {
        $this->cronHelper = $cronHelper;
        $this->emarsysProductModel = $emarsysProductModel;
        $this->emarsysLogs = $emarsysLogs;
        $this->storeManager = $storeManager;
        $this->productAsync = $productAsync;
    }

    public function execute()
    {
        try {
            set_time_limit(0);
            $currentCronInfo = $this->cronHelper->getCurrentCronInformation(CronHelper::CRON_JOB_CATALOG_BULK_EXPORT);
            $emarsysProductSyncCronInfo = $this->cronHelper->getCurrentCronInformation('emarsys_product_sync');

            if (!$currentCronInfo && !$emarsysProductSyncCronInfo) {
                return;
            }

            $async = false;
            foreach ($this->storeManager->getStores(true) as $store) {
                $async = $store->getConfig('emarsys_predict/enable/async');
                if ($async) {
                    break;
                }
            }

            $data = \Zend_Json::decode($currentCronInfo->getParams());
            $includeBundle = isset($data['includeBundle']) ? $data['includeBundle'] : null;
            if (!$async) {
                $this->emarsysProductModel->consolidatedCatalogExport(Data::ENTITY_EXPORT_MODE_MANUAL, $includeBundle);
            } else {
                echo "Async \n";
                $this->productAsync->run(Data::ENTITY_EXPORT_MODE_MANUAL);
            }
        } catch (\Exception $e) {
            $this->emarsysLogs->addErrorLog(
                'ProductBulkExport',
                $e->getMessage(),
                0,
                'ProductBulkExport::execute()'
            );
        }
    }
}
