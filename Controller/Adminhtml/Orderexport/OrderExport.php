<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\Orderexport;

use Emarsys\Emarsys\Helper\Cron as EmarsysCronHelper;
use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Emarsys\Emarsys\Model\EmarsysCronDetails;
use Emarsys\Emarsys\Model\Logs;
use Emarsys\Emarsys\Model\Order as EmarsysOrderModel;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\Timezone as TimeZone;
use Magento\Store\Model\StoreManagerInterface;

class OrderExport extends Action
{
    /**
     * @var Http
     */
    protected $request;

    /**
     * @var EmarsysOrderModel
     */
    protected $emarsysOrderModel;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * @var MessageManagerInterface
     */
    protected $messageManager;

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * @var TimeZone
     */
    protected $timezone;

    /**
     * @var EmarsysCronHelper
     */
    protected $cronHelper;

    /**
     * @var EmarsysCronDetails
     */
    protected $emarsysCronDetails;

    /**
     * @var Logs
     */
    protected $emarsysLogs;

    /**
     * OrderExport constructor.
     *
     * @param Context $context
     * @param Http $request
     * @param EmarsysOrderModel $emarsysOrderModel
     * @param StoreManagerInterface $storeManager
     * @param EmarsysHelper $emarsysHelper
     * @param DateTime $date
     * @param TimeZone $timezone
     * @param EmarsysCronHelper $cronHelper
     * @param EmarsysCronDetails $emarsysCronDetails
     * @param Logs $emarsysLogs
     */
    public function __construct(
        Context $context,
        Http $request,
        EmarsysOrderModel $emarsysOrderModel,
        StoreManagerInterface $storeManager,
        EmarsysHelper $emarsysHelper,
        DateTime $date,
        TimeZone $timezone,
        EmarsysCronHelper $cronHelper,
        EmarsysCronDetails $emarsysCronDetails,
        Logs $emarsysLogs
    ) {
        $this->request = $request;
        $this->emarsysOrderModel = $emarsysOrderModel;
        $this->storeManager = $storeManager;
        $this->emarsysHelper = $emarsysHelper;
        $this->messageManager = $context->getMessageManager();
        $this->date = $date;
        $this->timezone = $timezone;
        $this->cronHelper = $cronHelper;
        $this->emarsysCronDetails = $emarsysCronDetails;
        $this->emarsysLogs = $emarsysLogs;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\Controller\Result\Redirect
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        //collect params
        $data = $this->request->getParams();
        $storeId = $data['storeId'];
        $store = $this->storeManager->getStore($storeId);
        $websiteId = $store->getWebsiteId();
        $url = $this->getUrl("emarsys_emarsys/orderexport/index", ["store" => $storeId]);
        try {
            //check emarsys enabled for the website
            if ($this->emarsysHelper->isEmarsysEnabled($websiteId)) {
                //check smart insight enabled for the website
                if ($this->emarsysHelper->getCheckSmartInsight($websiteId)) {
                    if (isset($data['fromDate']) && $data['fromDate'] != '') {
                        $data['fromDate'] = $this->date->date(
                            'Y-m-d',
                            strtotime($data['fromDate'])
                        ) . ' 00:00:01';
                    }

                    if (isset($data['toDate']) && $data['toDate'] != '') {
                        $data['toDate'] = $this->date->date('Y-m-d', strtotime($data['toDate'])) . ' 23:59:59';
                    }

                    //check sales collection exist
                    $isCronjobScheduled = $this->cronHelper->checkCronjobScheduled(
                        EmarsysCronHelper::CRON_JOB_SI_BULK_EXPORT,
                        $storeId
                    );
                    if (!$isCronjobScheduled) {
                        //no cron job scheduled yet, schedule a new cron job
                        $cron = $this->cronHelper->scheduleCronJob(
                            EmarsysCronHelper::CRON_JOB_SI_BULK_EXPORT,
                            $storeId
                        );

                        //format and encode data in json to be saved in the table
                        $params = $this->cronHelper->getFormattedParams($data);

                        //save details cron details table
                        $this->emarsysCronDetails->addEmarsysCronDetails($cron->getScheduleId(), $params);

                        $this->messageManager->addSuccessMessage(
                            __(
                                'A cron named "%1" have been scheduled for smart insight export for the store %2.',
                                EmarsysCronHelper::CRON_JOB_SI_BULK_EXPORT,
                                $store->getName()
                            )
                        );
                    } else {
                        //cron job already scheduled
                        $this->messageManager->addErrorMessage(
                            __(
                                'A cron is already scheduled to export orders for the store %1 ',
                                $store->getName()
                            )
                        );
                    }
                } else {
                    //smart insight is disabled for this website
                    $this->messageManager->addErrorMessage(
                        __(
                            'Smart Insight is disabled for the store %1.',
                            $store->getName()
                        )
                    );
                }
            } else {
                //emarsys is disabled for this website
                $this->messageManager->addErrorMessage(
                    __(
                        'Emarsys is disabled for the website %1',
                        $websiteId
                    )
                );
            }
        } catch (\Exception $e) {
            //add exception to logs
            $this->emarsysLogs->addErrorLog(
                'OrderExport',
                $e->getMessage(),
                $this->storeManager->getStore()->getId(),
                'OrderExport::execute()'
            );
            //report error
            $this->messageManager->addErrorMessage(
                __(
                    'There was a problem while orders export. %1',
                    $e->getMessage()
                )
            );
        }

        return $this->resultRedirectFactory->create()->setPath($url);
    }
}
