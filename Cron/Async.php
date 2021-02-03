<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Cron;

use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Emarsys\Emarsys\Model\Api\Api as EmarsysModelApiApi;
use Emarsys\Emarsys\Model\ResourceModel\Async\CollectionFactory;

class Async
{
    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * @var EmarsysModelApiApi
     */
    protected $api;

    /**
     * @var CollectionFactory
     */
    protected $asyncCollection;

    /**
     * Async constructor.
     *
     * @param EmarsysHelper $emarsysHelper
     * @param EmarsysModelApiApi $api
     * @param CollectionFactory $asyncCollection
     */
    public function __construct(
        EmarsysHelper $emarsysHelper,
        EmarsysModelApiApi $api,
        CollectionFactory $asyncCollection
    ) {
        $this->emarsysHelper = $emarsysHelper;
        $this->api = $api;
        $this->asyncCollection = $asyncCollection;
    }

    public function execute()
    {
        set_time_limit(0);

        $collection = $this->asyncCollection->create();

        /** @var \Emarsys\Emarsys\Model\Async $item */
        foreach ($collection as $item) {
            try {
                $this->api->setWebsiteId($item->getWebsiteId());

                if ($item->getEndpoint() == EmarsysModelApiApi::CONTACT_CREATE_IF_NOT_EXISTS) {
                    $response = $this->api->createContactInEmarsys(\Zend_Json::decode($item->getRequestBody()));
                } else {
                    list($buildRequest, $requestBody) = \Zend_Json::decode($item->getRequestBody());
                    $response = $this->api->createContactInEmarsys($buildRequest);
                    if (isset($response['status']) && ($response['status'] == 200)
                        || ($response['status'] == 400 && isset($response['body']['replyCode'])
                            && $response['body']['replyCode'] == 2009)
                    ) {
                        //contact synced to emarsys successfully
                        $response = $this->api->sendRequest('POST', $item->getEndpoint(), $requestBody);
                    }
                }
                $this->emarsysHelper->addSuccessLog(
                    $item->getEmail(),
                    \Zend_Json::encode($response),
                    $this->emarsysHelper->getFirstStoreIdOfWebsite($item->getWebsiteId()),
                    'Async::execute()'
                );
                if (isset($response['status']) && ($response['status'] = 200)) {
                    $item->delete();
                }
            } catch (\Exception $e) {
                $this->emarsysHelper->addErrorLog(
                    'Async',
                    $e->getMessage() . ' ~ ' . $item->getRequestBody(),
                    0,
                    'Async::execute()'
                );
            }
        }
    }
}
