<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface  as StoreManager;
use Magento\AdminNotification\Model\InboxFactory;
use Emarsys\Emarsys\Model\ResourceModel\Event as EmarsysResourceModelEvent;
use Emarsys\Emarsys\Model\EmarsyseventsFactory;
use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Emarsys\Emarsys\Model\Api\Api as EmarsysModelApiApi;

/**
 * Class Event
 */
class Event extends AbstractHelper
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * @var StoreManager
     */
    protected $storeManager;

    /**
     * @var EmarsysModelApiApi
     */
    protected $api;

    /**
     * Event constructor.
     *
     * @param EmarsysHelper $emarsysHelper
     * @param EmarsysResourceModelEvent $resourceModelEvent
     * @param Context $context
     * @param StoreManager $storeManager
     * @param InboxFactory $adminNotification
     * @param EmarsyseventsFactory $emarsysEvents
     */
    public function __construct(
        EmarsysHelper $emarsysHelper,
        EmarsysResourceModelEvent $resourceModelEvent,
        Context $context,
        StoreManager $storeManager,
        InboxFactory $adminNotification,
        EmarsyseventsFactory $emarsysEvents
    ) {
        ini_set('default_socket_timeout', 1000);
        $this->logger = $context->getLogger();
        $this->storeManager = $storeManager;
        $this->emarsysHelper = $emarsysHelper;
        $this->resourceModelEvent = $resourceModelEvent;
        $this->adminNotification = $adminNotification;
        $this->emarsysEvents = $emarsysEvents;
    }

    /**
     * @return bool|mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getEventSchema()
    {
        $store = $this->storeManager->getStore();
        $storeId = $store->getId();
        try {
            $this->api->setWebsiteId($store->getWebsiteId());
            $response = $this->api->sendRequest('GET', 'event');
            return $response['body'];
        } catch (\Exception $e) {
            $this->emarsysHelper->addErrorLog(
                'getEventSchema',
                $e->getMessage(),
                $storeId,
                'Event::getEventSchema'
            );
        }
        return false;
    }

    /**
     * @return bool|mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getEventTemplateSchema()
    {
        $store = $this->storeManager->getStore();
        $storeId = $store->getId();
        try {
            $this->api->setWebsiteId($store->getWebsiteId());
            $response = $this->api->sendRequest('GET', 'email/templates');
            return $response['body'];
        } catch (\Exception $e) {
            $this->emarsysHelper->addErrorLog(
                'getEventTemplateSchema',
                $e->getMessage(),
                $storeId,
                'Event::getEventTemplateSchema'
            );
        }
        return false;
    }

    /**
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function saveEmarsysEventSchemaNotification()
    {
        try {
            $adminNotiColl = $this->adminNotification->create();
            $adminNotiColl->setSeverity(4);
            $adminNotiColl->setTitle('Emarsys Events Updates');
            $adminNotiColl->setDescription('Emarsys events has been update, Please update the emarsys event schema');
            $adminNotiColl->save();
            return true;
        } catch (\Exception $e) {
            $storeId = $this->storeManager->getStore()->getId();
            $this->emarsysHelper->addErrorLog(
                'saveEmarsysEventSchemaNotification',
                $e->getMessage(),
                $storeId,
                'Event::saveEmarsysEventSchemaNotification'
            );
        }
        return false;
    }

    /**
     * @param  $websiteId
     * @return array|bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getLocalEmarsysEvents($websiteId)
    {
        $emarsysLocalIds = [];
        try {
            $defaultStore = $this->emarsysHelper->getFirstStoreIdOfWebsite($websiteId);
            if (!$defaultStore) {
                throw new \Exception(__('There is no default store selected for website id %1', $websiteId));
            }
            $emarsysContactFields = $this->resourceModelEvent->getEmarsysEvents($defaultStore);

            foreach ($emarsysContactFields as $_emarsysContactField) {
                $emarsysLocalIds[] = $_emarsysContactField['event_id'];
            }
        } catch (\Exception $e) {
            $storeId = $this->storeManager->getStore()->getId();
            $this->emarsysHelper->addErrorLog(
                'getLocalEmarsysEvents',
                $e->getMessage(),
                $storeId,
                'Event::getLocalEmarsysEvents'
            );
        }
        return $emarsysLocalIds;
    }
}
