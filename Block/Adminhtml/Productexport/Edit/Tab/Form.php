<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Productexport\Edit\Tab;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Registry;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\App\Request\Http;
use Magento\Backend\Block\Widget\Form\Generic;
use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Magento\Store\Model\StoreManagerInterface as StoreManagerInterface;

class Form extends Generic
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Http
     */
    protected $request;

    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * Form constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param Http $request
     * @param EmarsysHelper $emarsysHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        Http $request,
        EmarsysHelper $emarsysHelper,
        array $data = []
    ) {
        parent::__construct($context, $registry, $formFactory, $data);
        $this->storeManager = $context->getStoreManager();
        $this->request = $request;
        $this->emarsysHelper = $emarsysHelper;
    }

    /**
     * Init form
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _prepareForm()
    {
        $storeId = $this->request->getParam('store');
        $storeId = $this->emarsysHelper->getFirstStoreIdOfWebsiteByStoreId($storeId);
        $store = $this->storeManager->getStore($storeId);

        $form = $this->_formFactory->create();
        $this->setForm($form);
        $fieldset = $form->addFieldset("support_form", ["legend" => '']);
        $values = [];
        $values['customer'] = 'Customer';
        $values['subscriber'] = 'Subscriber';

        $smartInsightEnable = $store->getConfig(EmarsysHelper::XPATH_SMARTINSIGHT_ENABLED);
        if ($smartInsightEnable == 1) {
            $values['order'] = 'Order';
        }

        $productExportStatus = $store->getConfig(EmarsysHelper::XPATH_PREDICT_ENABLE_NIGHTLY_PRODUCT_FEED);
        if ($productExportStatus == 1 || $smartInsightEnable == 1) {
            $values['product'] = 'Product';
        }

        $fieldset->addField(
            "entitytype", "select", [
                'label' => 'Export Entity Type',
                'name' => 'entity_type',
                'values' => $values,
                'style' => 'width:350px',
                'value' => 'product',
                'onchange' => "bulkExport(this.value)",
            ]
        );

        $fieldset->addField(
            "include_bundle", "select", [
                'label' => 'Include Bundle Product',
                'name' => 'include_bundle',
                'values' => [
                    '0' => 'No',
                    '1' => 'Yes',
                ],
                'value' => $store->getConfig(EmarsysHelper::XPATH_PREDICT_INCLUDE_BUNDLE_PRODUCT),
                'style' => 'width:350px',
            ]
        );

        return parent::_prepareForm();
    }
}
