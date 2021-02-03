<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Customerexport\Edit\Tab;

use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Magento\Backend\Block\Widget\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Registry;

class Form extends Generic
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
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
     * @param array $data
     * @param Http $request
     * @param EmarsysHelper $emarsysHelper
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        array $data = [],
        Http $request,
        EmarsysHelper $emarsysHelper
    ) {
        parent::__construct($context, $registry, $formFactory, $data);
        $this->storeManager = $context->getStoreManager();
        $this->request = $request;
        $this->emarsysHelper = $emarsysHelper;
    }

    /**
     * Init form
     */
    protected function _prepareForm()
    {
        $params = $this->request->getParams();
        $storeId = $params['store'];
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
            "entitytype",
            "select",
            [
                'label' => 'Export Entity Type',
                'name' => 'entity_type',
                'values' => $values,
                'style' => 'width:200px',
                'value' => 'customer',
                'onchange' => "bulkExport(this.value)",
            ]
        );

        $fieldset->addField(
            "fromdate",
            "date",
            [
                'label' => 'From',
                'name' => 'from_date',
                'type' => 'datetime',
                'date_format' => $this->_localeDate->getDateFormat(\IntlDateFormatter::SHORT),
                'time_format' => $this->_localeDate->getTimeFormat(\IntlDateFormatter::SHORT),
                'style' => 'width:200px',

            ]
        );

        $fieldset->addField(
            "todate",
            "date",
            [
                'label' => 'To',
                'name' => 'to_date',
                'date_format' => $this->_localeDate->getDateFormat(\IntlDateFormatter::SHORT),
                'time_format' => $this->_localeDate->getTimeFormat(\IntlDateFormatter::SHORT),
                'time' => 'true',
                'style' => 'width:200px',
            ]
        );

        return parent::_prepareForm();
    }
}
