<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */
namespace Emarsys\Emarsys\Controller\Adminhtml\Orderexport;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Emarsys\Emarsys\Model\Order as EmarsysOrderModel;
use Emarsys\Emarsys\Helper\Data as EmarsysDataHelper;

/**
 * Class OrderExport
 * @package Emarsys\Emarsys\Controller\Adminhtml\Orderexport
 */
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
     * OrderExport constructor.
     *
     * @param Context $context
     * @param Http $request
     * @param EmarsysOrderModel $emarsysOrderModel
     */
    public function __construct(
        Context $context,
        Http $request,
        EmarsysOrderModel $emarsysOrderModel
    ) {
        $this->request = $request;
        $this->emarsysOrderModel = $emarsysOrderModel;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        set_time_limit(0);
        $data = $this->request->getParams();
        $storeId = $data['storeId'];

        $this->emarsysOrderModel->syncOrders(
            $storeId,
            EmarsysDataHelper::ENTITY_EXPORT_MODE_MANUAL,
            $data['fromDate'],
            $data['toDate']
        );

        $resultRedirect = $this->resultRedirectFactory->create();
        $url = $this->getUrl("emarsys_emarsys/orderexport/index/store/$storeId");

        return $resultRedirect->setPath($url);
    }
}
