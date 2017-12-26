<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */
namespace Emarsys\Emarsys\Controller\Adminhtml\Productexport;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Store\Model\StoreManagerInterface;
use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Emarsys\Emarsys\Model\Product as EmarsysProductModel;
use Magento\Framework\App\Request\Http;

/**
 * Class ProductExport
 * @package Emarsys\Emarsys\Controller\Adminhtml\Productexport
 */
class ProductExport extends Action
{
    /**
     * @var Http
     */
    protected $request;

    /**
     * @var EmarsysProductModel
     */
    protected $emarsysProductModel;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * ProductExport constructor.
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param Http $request
     * @param EmarsysProductModel $emarsysProductModel
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        Http $request,
        EmarsysProductModel $emarsysProductModel
    ) {
        $this->request = $request;
        $this->emarsysProductModel =  $emarsysProductModel;
        $this->storeManager = $storeManager;
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
        $includeBundle = $data['includeBundle'];
        $excludedCategories = $data['excludeCategories'];

        $this->emarsysProductModel->syncProducts(
            $storeId,
            EmarsysHelper::ENTITY_EXPORT_MODE_MANUAL,
            $includeBundle,
            $excludedCategories
        );

        $resultRedirect = $this->resultRedirectFactory->create();
        $url = $this->getUrl("emarsys_emarsys/productexport/index/store/$storeId");

        return $resultRedirect->setPath($url);
    }
}
