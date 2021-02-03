<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\Mapping\Event;

use Emarsys\Emarsys\Helper\Data;
use Emarsys\Emarsys\Helper\Logs;
use Emarsys\Emarsys\Model\Api\Api;
use Emarsys\Emarsys\Model\ResourceModel\Emarsysevents\CollectionFactory;
use Emarsys\Emarsys\Model\ResourceModel\Emarsysmagentoevents\CollectionFactory as EmarsysmagentoeventsCollectionFactory;
use Emarsys\Emarsys\Model\ResourceModel\Event;
use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;

class SaveRecommended extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var Event
     */
    protected $eventResourceModel;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * SaveRecommended constructor.
     *
     * @param Context $context
     * @param Event $eventResourceModel
     * @param PageFactory $resultPageFactory
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param StoreManagerInterface $storeManager
     * @param Data $emarsysHelper
     * @param DateTime $date
     * @param CollectionFactory $EmarsyseventCollection
     * @param Logs $logHelper
     * @param EmarsysmagentoeventsCollectionFactory $magentoEventsCollection
     * @param Api $api
     */
    public function __construct(
        Context $context,
        Event $eventResourceModel,
        PageFactory $resultPageFactory,
        ScopeConfigInterface $scopeConfigInterface,
        StoreManagerInterface $storeManager,
        Data $emarsysHelper,
        DateTime $date,
        CollectionFactory $EmarsyseventCollection,
        Logs $logHelper,
        EmarsysmagentoeventsCollectionFactory $magentoEventsCollection,
        Api $api
    ) {
        parent::__construct($context);
        $this->session = $context->getSession();
        $this->resultPageFactory = $resultPageFactory;
        $this->eventResourceModel = $eventResourceModel;
        $this->magentoEventsCollection = $magentoEventsCollection;
        $this->emarsysEventCollection = $EmarsyseventCollection;
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->_storeManager = $storeManager;
        $this->emarsysHelper = $emarsysHelper;
        $this->_urlInterface = $context->getUrl();
        $this->date = $date;
        $this->logHelper = $logHelper;
        $this->api = $api;
    }

    /**
     * @return $this|ResponseInterface|ResultInterface
     * @throws Exception
     */
    public function execute()
    {
        $storeId = $this->getRequest()->getParam('store');
        $resultRedirect = $this->resultRedirectFactory->create();
        $errorStatus = true;
        $urlHasRecommendation = false;
        try {
            $eventsCreated = [];
            $websiteId = $this->_storeManager->getStore($storeId)->getWebsiteId();
            $logsArray['job_code'] = 'Event Mapping';
            $logsArray['status'] = 'started';
            $logsArray['messages'] = 'Running Events Recommended Mapping';
            $logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['run_mode'] = 'Automatic';
            $logsArray['auto_log'] = 'Complete';
            $logsArray['store_id'] = $storeId;
            $logsArray['website_id'] = $websiteId;
            $logId = $this->logHelper->manualLogs($logsArray);
            $logsArray['id'] = $logId;

            if (!$this->emarsysHelper->isEmarsysEnabled($websiteId)) {
                $logsArray['messages'] = 'Emarsys is Disabled for this Store';
                $logsArray['emarsys_info'] = 'Recommended Mapping';
                $logsArray['description'] = 'Recommended Mapping was not Successful';
                $logsArray['action'] = 'Reccommended Mapping';
                $logsArray['message_type'] = 'Error';
                $logsArray['log_action'] = 'True';
                $this->logHelper->manualLogs($logsArray);
                $this->messageManager->addErrorMessage('Emarsys is not Enabled for this store');
            } else {
                $logsArray['emarsys_info'] = 'Recommended Mapping';
                $logsArray['description'] = 'Recommended Mapping In progress';
                $logsArray['action'] = 'Schame Updated';
                $logsArray['message_type'] = 'Success';
                $logsArray['log_action'] = 'True';
                $logsArray['website_id'] = $websiteId;
                $this->logHelper->manualLogs($logsArray);

                $this->emarsysHelper->importEvents($storeId, $logId);
                $emarsysEvents = $this->emarsysEventCollection->create();
                $this->api->setWebsiteId($websiteId);
                $dbEvents = [];
                foreach ($emarsysEvents as $emarsysEvent) {
                    $dbEvents[] = $emarsysEvent->getEmarsysEvent();
                }
                $hasNewEvents = false;
                $magentoEvents = $this->magentoEventsCollection->create();
                foreach ($magentoEvents as $magentoEvent) {
                    if ($this->emarsysHelper->isReadonlyMagentoEventId($magentoEvent->getId())) {
                        continue;
                    }
                    $magentoEventname = $magentoEvent->getMagentoEvent();
                    $emarsysEventname = trim(str_replace(" ", "_", strtolower($magentoEventname)));
                    if (!in_array($emarsysEventname, $dbEvents)) {
                        $eventsCreated[] = $data['name'] = $emarsysEventname;
                        $hasNewEvents = true;
                        $this->api->sendRequest('POST', 'event', $data);
                    }
                }
                if ($eventsCreated && $logId) {
                    $logsArray['emarsys_info'] = 'Recommended Mapping';
                    $logsArray['description'] = 'Events Created ' . implode(",", $eventsCreated);
                    $logsArray['action'] = 'Recommended Mapping';
                    $logsArray['message_type'] = 'Success';
                    $logsArray['log_action'] = 'True';
                    $this->logHelper->manualLogs($logsArray);
                }
                if ($hasNewEvents) {
                    $this->emarsysHelper->importEvents($storeId, $logId);
                }
                $errorStatus = false;
                $urlHasRecommendation = true;
                $this->messageManager->addSuccessMessage(__('Recommended Emarsys Events Created Successfully!'));
                $this->messageManager->addSuccessMessage(__('Important: Hit "Save" to complete the mapping!'));
            }
        } catch (Exception $e) {
            $logsArray['id'] = $logId;
            $logsArray['emarsys_info'] = 'Recommended Mapping';
            $logsArray['description'] = $e->getMessage();
            $logsArray['action'] = 'Recommended Mapping not successful';
            $logsArray['message_type'] = 'Error';
            $logsArray['log_action'] = 'True';
            $logsArray['website_id'] = $websiteId;
            $this->logsHelper->logHelper($logsArray);
            $this->messageManager->addErrorMessage(__('Error occurred while Recommended Mapping %1', $e->getMessage()));
        }

        if ($errorStatus) {
            $logsArray['messages'] = 'Error occurred while Events Recommended Mapping';
            $logsArray['status'] = 'error';
        } else {
            $logsArray['messages'] = 'Events Recommended Mapping Successful';
            $logsArray['status'] = 'success';
        }
        $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
        $this->logHelper->manualLogs($logsArray);

        if ($urlHasRecommendation) {
            return $resultRedirect->setUrl(
                $this->_urlInterface->getUrl(
                    "emarsys_emarsys/mapping_event",
                    [
                        "store" => $storeId,
                        "recommended" => 1,
                        "limit" => 200,
                    ]
                )
            );
        } else {
            return $resultRedirect->setRefererOrBaseUrl();
        }
    }
}
