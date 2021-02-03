<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\Mapping\Event;

use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Emarsys\Emarsys\Model\EmarsysmagentoeventsFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;

class Placeholderjson extends Action
{
    /**
     * @var EmarsysmagentoeventsFactory
     */
    protected $emarsysMagentoEventsFactory;

    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * Placeholderjson constructor.
     *
     * @param Context $context
     * @param EmarsysmagentoeventsFactory $emarsysMagentoEventsFactory
     * @param EmarsysHelper $emarsysHelper
     */
    public function __construct(
        Context $context,
        EmarsysmagentoeventsFactory $emarsysMagentoEventsFactory,
        EmarsysHelper $emarsysHelper
    ) {
        parent::__construct($context);
        $this->emarsysMagentoEventsFactory = $emarsysMagentoEventsFactory;
        $this->emarsysHelper = $emarsysHelper;
    }

    /**
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        $magentoEventId = $this->getRequest()->getParam('mapping_id');
        $storeId = $this->emarsysHelper->getFirstStoreIdOfWebsiteByStoreId(
            $this->getRequest()->getParam('store_id')
        );

        $emarsysPlaceholders = $this->emarsysHelper->getPlaceHolders($magentoEventId, $storeId);

        $magentoEventName = $this->emarsysMagentoEventsFactory->create()
            ->getCollection()
            ->addFieldToFilter('id', $magentoEventId)
            ->getFirstItem()
            ->getMagentoEvent();

        if (empty($emarsysPlaceholders)) {
            $emarsysPlaceholders = $this->emarsysHelper->insertFirstTimeMappingPlaceholders($magentoEventId, $storeId);
        }

        $emarsysHeaderPlaceholders = $this->emarsysHelper->emarsysHeaderPlaceholders($storeId);
        $emarsysFooterPlaceholders = $this->emarsysHelper->emarsysFooterPlaceholders($storeId);
        $dataArray['global'] = array_merge(
            $emarsysHeaderPlaceholders,
            $emarsysPlaceholders,
            $emarsysFooterPlaceholders
        );

        if (!empty($dataArray['global'])) {
            if (stristr($magentoEventName, "Order")
                || stristr($magentoEventName, "Shipment")
                || stristr($magentoEventName, "Invoice")
                || stristr($magentoEventName, "Credit Memo")
                || stristr($magentoEventName, "RMA")
            ) {
                $emarsysDefaultPlaceholders = $this->emarsysHelper->emarsysDefaultPlaceholders();
                foreach ($emarsysDefaultPlaceholders['product_purchases'] as $key => $value) {
                    $dataProductPurchasesArray[$key] = $value;
                }
                $dataArray['product_purchases'] = $dataProductPurchasesArray;
            }
            $finalArray = ["external_id" => "RECIPIENT_EMAIL", "key_id" => "KEY_ID", "data" => $dataArray];

            $this->getResponse()
                ->setHeader('Content-type', 'application/json')
                ->setBody(\Zend_Json::prettyPrint(\Zend_Json::encode($finalArray)));
        }
    }
}
