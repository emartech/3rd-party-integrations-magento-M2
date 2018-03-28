<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */
namespace Emarsys\Emarsys\Model\Template;

use Magento\Framework\App\TemplateTypesInterface;
use Magento\Framework\Mail\MessageInterface;
use Magento\Framework\Mail\TransportInterfaceFactory;
use Magento\Framework\ObjectManagerInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Magento\Framework\Mail\Template\FactoryInterface;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Emarsys\Emarsys\Model\Logs;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\ProductFactory;
use Emarsys\Emarsys\Helper\Data\Proxy as EmarsysHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;

/**
 * Class TransportBuilder
 * @package Emarsys\Emarsys\Model\Template
 */
class TransportBuilder extends \Magento\Framework\Mail\Template\TransportBuilder
{
    /**
     * @var Http
     */
    protected $requestHttp;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @var EmarsysHelper
     */
    protected $emarsysDataHelper;

    /**
     * @var string
     */
    public $productCollObj = '';

    /**
     * @var Logs
     */
    protected $emarsysLogs;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager ;

    /**
     * TransportBuilder constructor.
     * @param FactoryInterface $templateFactory
     * @param MessageInterface $message
     * @param SenderResolverInterface $senderResolver
     * @param ObjectManagerInterface $objectManager
     * @param TransportInterfaceFactory $mailTransportFactory
     * @param Logs $emarsysLogs
     * @param StoreManagerInterface $storeManager
     * @param ProductFactory $productFactory
     * @param EmarsysHelper $emarsysDataHelper
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param Http $requestHttp
     */
    public function __construct(
        FactoryInterface $templateFactory,
        MessageInterface $message,
        SenderResolverInterface $senderResolver,
        ObjectManagerInterface $objectManager,
        TransportInterfaceFactory $mailTransportFactory,
        Logs $emarsysLogs,
        StoreManagerInterface $storeManager,
        ProductFactory $productFactory,
        EmarsysHelper $emarsysDataHelper,
        ScopeConfigInterface $scopeConfigInterface,
        Http $requestHttp
    ) {
		$this->emarsysDataHelper = $emarsysDataHelper;
		$this->productFactory = $productFactory;
        $this->emarsysLogs = $emarsysLogs;
        $this->storeManager = $storeManager;
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->requestHttp = $requestHttp;
        parent::__construct(
            $templateFactory,
            $message,
            $senderResolver,
            $objectManager,
            $mailTransportFactory
        );
    }

    /**
     * Get mail transport
     *
     * @return $this
     */
    public function prepareMessage()
    {
        $handle = '';
        $template = $this->getTemplate();
        $types = [
            TemplateTypesInterface::TYPE_TEXT => MessageInterface::TYPE_TEXT,
            TemplateTypesInterface::TYPE_HTML => MessageInterface::TYPE_HTML,
        ];

        $body = $template->processTemplate();
        $storeId = $template->getEmailStoreId();
        $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();
        $templateIdentifier = $this->templateIdentifier;
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;

        list($magentoEventID, $configPath) = $this->emarsysDataHelper->getMagentoEventIdAndPath(
            $templateIdentifier,
            $storeScope,
            $storeId
        );

        if (!$magentoEventID) {
            $this->message->setMessageType($types[$template->getType()])
                ->setBody($body)
                ->setSubject($template->getSubject())
                ->setEmarsysData([
                    "emarsysPlaceholders" => '',
                    "emarsysEventId" => '',
                    "store_id" => $storeId
                ]);
            return $this;
        }
        $enableOptin = $this->scopeConfigInterface->getValue('opt_in/optin_enable/enable_optin', 'websites', $websiteId);
        if ($enableOptin) {
            $handle = $this->requestHttp->getFullActionName();
        }
        $emarsysEventMappingID = $this->emarsysDataHelper->getEmarsysEventMappingId($magentoEventID, $storeId);
        if (!$emarsysEventMappingID) {
            $this->message->setMessageType($types[$template->getType()])
                ->setBody($body)
                ->setSubject($template->getSubject())
                ->setEmarsysData([
                    "emarsysPlaceholders" => '',
                    "emarsysEventId" => '',
                    "store_id" => $storeId
                ]);
            return $this;
        }

        $emarsysEventApiID = $this->emarsysDataHelper->getEmarsysEventApiId($magentoEventID, $storeId);
        if (!$emarsysEventApiID) {
            $this->message->setMessageType($types[$template->getType()])
                ->setBody($body)
                ->setSubject($template->getSubject())
                ->setEmarsysData([
                    "emarsysPlaceholders" => '',
                    "emarsysEventId" => '',
                    "store_id" => $storeId
                ]);
            return $this;
        }

        $emarsysPlaceholders = $this->emarsysDataHelper->getPlaceHolders($emarsysEventMappingID);
        if (!$emarsysPlaceholders) {
            $emarsysPlaceholders = $this->emarsysDataHelper->insertFirstimeMappingPlaceholders($emarsysEventMappingID, $storeId);
            $emarsysPlaceholders = $this->emarsysDataHelper->getPlaceHolders($emarsysEventMappingID);
        }

        $emarsysHeaderPlaceholders = $this->emarsysDataHelper->emarsysHeaderPlaceholders($emarsysEventMappingID, $storeId);
        if (!$emarsysHeaderPlaceholders) {
            $emarsysInsertFirstHeaderPlaceholders = $this->emarsysDataHelper->insertFirstimeHeaderMappingPlaceholders(
                $emarsysEventMappingID,
                $storeId
            );
            $emarsysHeaderPlaceholders = $this->emarsysDataHelper->emarsysHeaderPlaceholders($emarsysEventMappingID, $storeId);
        }

        $emarsysFooterPlaceholders = $this->emarsysDataHelper->emarsysFooterPlaceholders($emarsysEventMappingID, $storeId);
        if (!$emarsysFooterPlaceholders) {
            $emarsysInsertFirstFooterPlaceholders = $this->emarsysDataHelper->insertFirstimeFooterMappingPlaceholders(
                $emarsysEventMappingID,
                $storeId
            );
            $emarsysFooterPlaceholders = $this->emarsysDataHelper->emarsysFooterPlaceholders($emarsysEventMappingID, $storeId);
        }

        $processedVariables = [];
        if ($order = $template->checkOrder()) {
            foreach ($order->getAllVisibleItems() as $item) {
                $orderData[] = $this->getOrderData($item);
            }
            $processedVariables['product_purchases'] = $orderData;
        }

        foreach ($emarsysHeaderPlaceholders as $key => $value) {
            if ($key == 'css_file_css_email_css') {
                continue;
            } else {
                $processedVariables['global'][$key] = $template->getProcessedVariable($value);
            }
        }
        foreach ($emarsysPlaceholders as $key => $value) {
            $processedVariables['global'][$key] = $template->getProcessedVariable($value);
        }
        foreach ($emarsysFooterPlaceholders as $key => $value) {
            $processedVariables['global'][$key] = $template->getProcessedVariable($value);
        }
        $this->message->setMessageType($types[$template->getType()])
            ->setBody($body)
            ->setSubject($template->getSubject())
            ->setEmarsysData([
                "emarsysPlaceholders" => $processedVariables,
                "emarsysEventId" => $emarsysEventApiID,
                "store_id" => $storeId
            ]);

        return $this;
    }

    /**
     * @param $value
     * @return string
     */
    protected function _formatPrice($value = 0)
    {
        return sprintf('%01.2f', $value);
    }

    /**
     * @param $value
     * @return string
     */
    protected function _formatQty($value = 0)
    {
        return sprintf('%01.0f', $value);
    }

    /**
     * @param $item
     * @return array
     */
    public function getOrderData($item)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        try {
            $optionGlue = " - ";
            $optionSeparator = " : ";

            $unitTaxAmount = $item->getTaxAmount() / $item->getQtyOrdered();
            $order = [
                'unitary_price_exc_tax' => $this->_formatPrice($item->getPriceInclTax() - $unitTaxAmount),
                'unitary_price_inc_tax' => $this->_formatPrice($item->getPriceInclTax()),
                'unitary_tax_amount' => $this->_formatPrice($unitTaxAmount),
                'line_total_price_exc_tax' => $this->_formatPrice($item->getRowTotalInclTax() - $item->getTaxAmount()),
                'line_total_price_inc_tax' => $this->_formatPrice($item->getRowTotalInclTax()),
                'line_total_tax_amount' => $this->_formatPrice($item->getTaxAmount())
            ];
            $order['product_id'] = $item->getData('product_id');
            $order['product_type'] = $item->getData('product_type');
            $order['base_original_price'] = $this->_formatPrice($item->getData('base_original_price'));
            $order['sku'] = $item->getData('sku');
            $order['product_name'] = $item->getData('name');
            $order['product_weight'] = $item->getData('weight');
            $order['qty_ordered'] = $this->_formatQty($item->getData('qty_ordered'));
            $order['original_price'] = $this->_formatPrice($item->getData('original_price'));
            $order['price'] = $this->_formatPrice($item->getData('price'));
            $order['base_price'] = $this->_formatPrice($item->getData('base_price'));
            $order['tax_percent'] = $this->_formatPrice($item->getData('tax_percent'));
            $order['tax_amount'] = $this->_formatPrice($item->getData('tax_amount'));
            $order['discount_amount'] = $this->_formatPrice($item->getData('discount_amount'));
            $order['price_line_total'] = $this->_formatPrice($order['qty_ordered'] * $order['price']);

            $_product = $this->productFactory->create()->load($order['product_id']);

            $base_url = $this->storeManager->getStore($item->getData('store_id'))->getBaseUrl();
            $base_url = trim($base_url, '/');
            $order['_external_image_url'] = $base_url . '/media/catalog/product' . $_product->getData('thumbnail');
            $order['_url'] = $base_url . "/" . $_product->getUrlPath();
            $order['_url_name'] = $order['product_name'];
            $order['product_description'] = $_product->getData('description');
            $order['short_description'] = $_product->getData('short_description');
            $attributes = $_product->getAttributes();
            $prodData = $_product->getData();
            foreach ($attributes as $attribute) {
                if ($attribute->getFrontendInput() != "gallery") {
                    if (!isset($prodData[$attribute->getAttributeCode()])) {
                        //do nothing
                    } else {
                        $order['attribute_' . $attribute->getAttributeCode()] = $prodData[$attribute->getAttributeCode()];
                    }
                }
            }
            $order['full_options'] = [];
            $prodOptions = $item->getProductOptions();

            if (isset($prodOptions['attributes_info'])) {
                foreach ($prodOptions['attributes_info'] as $option) {
                    $order['full_options'][] = $option['label'] . $optionSeparator . $option['value'];
                }
                $order['full_options'] = implode($optionGlue, $order['full_options']);
            }

            $order = array_filter($order);
            $order['additional_data'] = ($item->getData('additional_data') ? $item->getData('additional_data') : "");

            return $order;
        } catch (Exception $e) {
            $emarsysLogs = $objectManager->create('\Emarsys\Emarsys\Model\Logs');
            $emarsysLogs->addErrorLog(
                $e->getMessage(),
                $this->storeManager->getStore()->getId(),
                'TransportBuilder::getOrderData()'
            );
        }
    }
}