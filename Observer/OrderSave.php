<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Observer;

use Emarsys\Emarsys\Model\OrderQueueFactory as OrderQueueFactoryAlias;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Class OrderSave
 */
class OrderSave implements ObserverInterface
{
    /**
     * @var OrderQueueFactoryAlias
     */
    private $orderQueueFactory;

    /**
     * OrderSave constructor.
     *
     * @param OrderQueueFactoryAlias $orderQueueFactory
     */
    public function __construct(
        OrderQueueFactoryAlias $orderQueueFactory
    ) {
        $this->orderQueueFactory = $orderQueueFactory;
    }

    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        if (!((bool)$order->getStore()->getConfig(\Emarsys\Emarsys\Helper\Data::XPATH_EMARSYS_ENABLED))
            || !((bool)$order->getStore()->getConfig(\Emarsys\Emarsys\Helper\Data::XPATH_SMARTINSIGHT_ENABLED))
        ) {
            return true;
        }

        $orderQueue = $this->orderQueueFactory->create();
        $orderQueueData = $orderQueue->getCollection()
            ->addFieldToFilter('entity_id', $order->getId())
            ->addFieldToFilter('entity_type_id', 1);

        if ($orderQueueData->getSize()) {
            $orderQueue = $orderQueueData->getFirstItem();
        }

        if ($order->getState() == \Magento\Sales\Model\Order::STATE_CLOSED) {
            return true;
        }

        $orderQueue->setEntityId($order->getId());
        $orderQueue->setEntityTypeId(1);
        $orderQueue->setWebsiteId($order->getStore()->getWebsiteId());
        $orderQueue->setStoreId($order->getStoreId());
        $orderQueue->save();
    }
}
