<?php

namespace Emarsys\Emarsys\Controller\Index;

use Magento\Framework\App\Action\Context;

class Index extends \Magento\Framework\App\Action\Action
{
    public function execute()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $hitCountMaxTry = 5;
        $queueDataBunchCount = 1000;
        $queueCollection = $objectManager->create('Emarsys\Emarsys\Model\ResourceModel\Queue\Collection');
        $queueCollection->addFieldToSelect('hit_count');
        $queueCollection->addFieldToSelect('entity_id');
        $queueCollection->addFieldToFilter('hit_count', ['lt' => $hitCountMaxTry]);
        $data = $queueCollection->getData();
        $custModel = $objectManager->create('Magento\Customer\Model\Customer');
        $contact = [];

        foreach ($data as $dataSingle) {
            $objCustomer = $custModel->load($dataSingle['entity_id']);
            $arrCustomer = $objCustomer->getData();
            $contact[] = ["3" => $arrCustomer['email'], "2" => $arrCustomer['firstname']];
        }
        $arrayChunk = array_chunk($contact, $queueDataBunchCount);

        foreach ($arrayChunk as $arr) {
            $model = $objectManager->create('Emarsys\Emarsys\Model\Api\Contact');
            $model->syncMultipleContacts($arr);
        }
    }
}

