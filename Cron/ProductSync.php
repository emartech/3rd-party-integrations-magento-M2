<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Cron;

use Emarsys\Emarsys\Helper\Cron as CronHelper;
use Emarsys\Emarsys\Model\Product as EmarsysProductModel;
use Emarsys\Emarsys\Model\Logs;
use Emarsys\Emarsys\Model\ProductExportAsync as ProductExportAsync;
use Magento\Store\Model\StoreManagerInterface;
use Emarsys\Emarsys\Helper\Data;

/**
 * Class ProductSync
 */
class ProductSync
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
     * ProductSync constructor.
     *
     * @param CronHelper $cronHelper
     * @param EmarsysProductModel $emarsysProductModel
     * @param Logs $emarsysLogs
     * @param ProductExportAsync $productAsync
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        CronHelper $cronHelper,
        EmarsysProductModel $emarsysProductModel,
        Logs $emarsysLogs,
        ProductExportAsync $productAsync,
        StoreManagerInterface $storeManager
    ) {
        $this->cronHelper = $cronHelper;
        $this->emarsysProductModel = $emarsysProductModel;
        $this->emarsysLogs = $emarsysLogs;
        $this->productAsync = $productAsync;
        $this->storeManager = $storeManager;
    }

    public function execute()
    {
        try {
            set_time_limit(0);
            $currentCronInfo = $this->cronHelper->getCurrentCronInformation('emarsys_product_sync');
            $emarsysCatalogBulkExportCronInfo = $this->cronHelper->getCurrentCronInformation(CronHelper::CRON_JOB_CATALOG_BULK_EXPORT);

            if (!$currentCronInfo && !$emarsysCatalogBulkExportCronInfo) {
                return;
            }

            $async = false;
            foreach ($this->storeManager->getStores(true) as $store) {
                $async = $store->getConfig('emarsys_predict/enable/async');
                if ($async) {
                    break;
                }
            }
            if (!$async) {
                $this->emarsysProductModel->consolidatedCatalogExport(Data::ENTITY_EXPORT_MODE_AUTOMATIC);
            } else {
                $this->productAsync->run(Data::ENTITY_EXPORT_MODE_AUTOMATIC);
            }
        } catch (\Exception $e) {
            $this->emarsysLogs->addErrorLog(
                'ProductSync',
                $e->getMessage(),
                0,
                'ProductSync::execute()'
            );
        }
        return true;
    }
}
