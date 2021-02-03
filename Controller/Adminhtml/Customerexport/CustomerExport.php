<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\Customerexport;

use Emarsys\Emarsys\Helper\Cron as EmarsysCronHelper;
use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Emarsys\Emarsys\Model\EmarsysCronDetails;
use Emarsys\Emarsys\Model\Logs;
use Emarsys\Emarsys\Model\ResourceModel\Customer;
use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\Timezone;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\StoreManagerInterface;

class CustomerExport extends Action
{
    const MAX_CUSTOMER_RECORDS = 100000;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Customer
     */
    protected $customerResourceModel;

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * @var Http
     */
    protected $request;

    /**
     * @var Timezone
     */
    protected $timezone;

    /**
     * @var TimezoneInterface
     */
    protected $timezoneInterface;

    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * @var EmarsysCronDetails
     */
    protected $emarsysCronDetails;

    /**
     * @var EmarsysCronHelper
     */
    protected $cronHelper;

    /**
     * @var Logs
     */
    protected $emarsysLogs;

    /**
     * CustomerExport constructor.
     *
     * @param Context $context
     * @param DateTime $date
     * @param StoreManagerInterface $storeManager
     * @param Customer $customerResourceModel
     * @param Timezone $timezone
     * @param TimezoneInterface $timezoneInterface
     * @param Http $request
     * @param EmarsysHelper $emarsysHelper
     * @param EmarsysCronDetails $emarsysCronDetails
     * @param EmarsysCronHelper $cronHelper
     * @param Logs $emarsysLogs
     */
    public function __construct(
        Context $context,
        DateTime $date,
        StoreManagerInterface $storeManager,
        Customer $customerResourceModel,
        Timezone $timezone,
        TimezoneInterface $timezoneInterface,
        Http $request,
        EmarsysHelper $emarsysHelper,
        EmarsysCronDetails $emarsysCronDetails,
        EmarsysCronHelper $cronHelper,
        Logs $emarsysLogs
    ) {
        $this->storeManager = $storeManager;
        $this->customerResourceModel = $customerResourceModel;
        $this->date = $date;
        $this->request = $request;
        $this->timezone = $timezone;
        $this->timezoneInterface = $timezoneInterface;
        $this->emarsysHelper = $emarsysHelper;
        $this->emarsysCronDetails = $emarsysCronDetails;
        $this->cronHelper = $cronHelper;
        $this->emarsysLogs = $emarsysLogs;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Redirect|ResultInterface
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $data = $this->request->getParams();
        $storeId = $data['storeId'];
        $store = $this->storeManager->getStore($storeId);
        $websiteId = $store->getWebsiteId();
        $data['website'] = $websiteId;
        $resultRedirect = $this->resultRedirectFactory->create();
        $returnUrl = $this->getUrl("emarsys_emarsys/customerexport/index", ["store" => $storeId]);

        try {
            //check emarsys enable for website
            if (!$this->emarsysHelper->isEmarsysEnabled($websiteId)) {
                //emarsys is disabled for this website
                $this->messageManager->addErrorMessage(__('Emarsys is disabled for the website %1', $websiteId));
                return $resultRedirect->setPath($returnUrl);
            }
            //calculate time difference
            if (isset($data['fromDate']) && $data['fromDate'] != '') {
                $data['fromDate'] = $this->date->date('Y-m-d', strtotime($data['fromDate'])) . ' 00:00:01';
            }
            if (isset($data['toDate']) && $data['toDate'] != '') {
                $data['toDate'] = $this->date->date('Y-m-d', strtotime($data['toDate'])) . ' 23:59:59';
            }

            /**
             * @var Customer $customerCollection
             */
            $customerCollection = $this->customerResourceModel->getCustomerCollection($data, $storeId);
            if (!$customerCollection->getSize()) {
                //no customer found for the store
                $this->messageManager->addErrorMessage(__('No Customers Found for the Store %1.', $store->getName()));
                return $resultRedirect->setPath($returnUrl);
            }

            //export customers through API
            $isCronjobScheduled = $this->cronHelper->checkCronjobScheduled(
                EmarsysCronHelper::CRON_JOB_CUSTOMER_BULK_EXPORT_API,
                $storeId
            );
            if ($isCronjobScheduled) {
                //cron job already scheduled
                $this->messageManager->addErrorMessage(
                    __('A cron is already scheduled to export customers for the store %1', $store->getName())
                );
                return $resultRedirect->setPath($returnUrl);
            }

            //no cron job scheduled yet, schedule a new cron job
            $cron = $this->cronHelper->scheduleCronjob(EmarsysCronHelper::CRON_JOB_CUSTOMER_BULK_EXPORT_API, $storeId);

            //format and encode data in json to be saved in the table
            $params = $this->cronHelper->getFormattedParams($data);

            //save details in cron details table
            $this->emarsysCronDetails->addEmarsysCronDetails($cron->getScheduleId(), $params);

            $this->messageManager->addSuccessMessage(
                __(
                    'A cron named "%1" have been scheduled for customers export for the store %2.',
                    EmarsysCronHelper::CRON_JOB_CUSTOMER_BULK_EXPORT_API,
                    $store->getName()
                )
            );
        } catch (Exception $e) {
            //add exception to logs
            $this->emarsysLogs->addErrorLog(
                'CustomerExport',
                $e->getMessage(),
                0,
                'CustomerExport::execute()'
            );
            //report error
            $this->messageManager->addErrorMessage(
                __('There was a problem while customer export. %1', $e->getMessage())
            );
        }

        return $resultRedirect->setPath($returnUrl);
    }
}
