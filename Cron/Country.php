<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Cron;

use Emarsys\Emarsys\Helper\Cron as EmarsysCronHelper;
use Magento\Store\Model\StoreManagerInterface;
use Emarsys\Emarsys\Model\Logs as EmarsysModelLogs;
use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Emarsys\Emarsys\Helper\Country as EmarsysCountryHelper;

/**
 * Class CustomerSyncQueue
 */
class Country
{
    /**
     * @var EmarsysCronHelper
     */
    protected $cronHelper;

    /**
     * @var EmarsysCountryHelper
     */
    protected $countryHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManagerInterface;

    /**
     * @var EmarsysModelLogs
     */
    protected $emarsysLogs;

    /**
     * CustomerSyncQueue constructor.
     *
     * @param EmarsysCronHelper $cronHelper
     * @param EmarsysCountryHelper $countryHelper
     * @param StoreManagerInterface $storeManagerInterface
     * @param EmarsysModelLogs $emarsysLogs
     */
    public function __construct(
        EmarsysCronHelper $cronHelper,
        EmarsysCountryHelper $countryHelper,
        StoreManagerInterface $storeManagerInterface,
        EmarsysModelLogs $emarsysLogs
    ) {
        $this->cronHelper = $cronHelper;
        $this->countryHelper = $countryHelper;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->emarsysLogs = $emarsysLogs;
    }

    public function execute()
    {
        try {
            $stores = $this->storeManagerInterface->getStores(false);
            $this->countryHelper->truncate();
            foreach ($stores as $store) {
                if (!$store->getConfig(EmarsysHelper::XPATH_EMARSYS_ENABLED)
                    || !$store->getConfig(EmarsysHelper::XPATH_EMARSYS_ENABLE_CONTACT_FEED)
                ) {
                    continue;
                }
                $this->countryHelper->getMapping($store->getStoreId());
            }
        } catch (\Exception $e) {
            $this->emarsysLogs->addErrorLog(
                'Country Mapping Update',
                $e->getMessage(),
                0,
                'Country::execute()'
            );
        }
    }
}
