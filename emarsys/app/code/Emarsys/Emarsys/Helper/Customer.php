<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2016 Emarsys Solution Pvt.Ltd. (http://www.kensiumsolutions.com/)
 */
namespace Emarsys\Emarsys\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Emarsys\Emarsys\Model\Logs as EmarsysModelLogs;

/**
 * Class Customer
 * @package Emarsys\Emarsys\Helper
 */
class Customer extends AbstractHelper
{
    /**
     * @var Context
     */
    protected $context;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var Data
     */
    protected $dataHelper;

    /**
     * Customer constructor.
     * @param Context $context
     * @param EmarsysModelLogs $emarsysLogs
     * @param Data $dataHelper
     */
    public function __construct(
        Context $context,
        EmarsysModelLogs $emarsysLogs,
        Data $dataHelper
    ) {
        ini_set('default_socket_timeout', 1000);
        $this->context = $context;
        $this->dataHelper = $dataHelper;
        $this->emarsysLogs = $emarsysLogs;
        $this->logger = $context->getLogger();
    }

    /**
     * @param $storeId
     * @return bool|mixed
     */
    public function getEmarsysCustomerSchema($storeId)
    {
        try {
            $this->dataHelper->getEmarsysAPIDetails($storeId);
            $response = $this->dataHelper->send('GET', 'field/translate/en');
            $jsonDecode = \Zend_Json::decode($response);
            return $jsonDecode;
        } catch (\Exception $e) {
            $this->emarsysLogs->addErrorLog(htmlentities($e->getMessage()), $storeId, 'getEmarsysCustomerSchema');
            return false;
        }
    }
}
