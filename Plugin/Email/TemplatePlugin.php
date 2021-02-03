<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Plugin\Email;

use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Emarsys\Emarsys\Helper\Logs as EmarsysLogsHelper;
use Emarsys\Emarsys\Model\Api\Api as EmarsysModelApiApi;
use Emarsys\Emarsys\Model\AsyncFactory;
use Emarsys\Emarsys\Model\ResourceModel\Customer as CustomerResourceModel;
use Emarsys\Emarsys\Registry\EmailSendState;
use Magento\Catalog\Helper\Image;
use Magento\Email\Model\Template;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Mail\Template\TransportBuilder;

/**
 * Class TemplatePlugin
 */
class TemplatePlugin
{
    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * @var EmarsysLogsHelper
     */
    protected $logsHelper;

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * @var Image
     */
    protected $imageHelper;

    /**
     * @var EmailSendState
     */
    protected $state;

    /**
     * @var EmarsysModelApiApi
     */
    protected $api;

    /**
     * @var CustomerResourceModel
     */
    protected $customerResourceModel;

    /**
     * @var AsyncFactory
     */
    protected $asyncModel;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var TransportBuilder
     */
    protected $transportBuilder;

    /**
     * Temp variables
     */
    protected $storeId = false;
    protected $websiteId = false;
    protected $email = false;
    protected $customerId = false;
    protected $subscribeId = false;
    protected $templateId = false;
    protected $magentoEventId = false;
    protected $emarsysEventApiId = false;
    protected $logsArray = [];

    /**
     * TransportPlugin constructor.
     *
     * @param EmarsysHelper $emarsysHelper
     * @param EmarsysLogsHelper $logsHelper
     * @param DateTime $date
     * @param Image $imageHelper
     * @param EmailSendState $state
     * @param EmarsysModelApiApi $api
     * @param CustomerResourceModel $customerResourceModel
     * @param AsyncFactory $asyncModel
     * @param StoreManagerInterface $storeManager
     * @param TransportBuilder $transportBuilder
     */
    public function __construct(
        EmarsysHelper $emarsysHelper,
        EmarsysLogsHelper $logsHelper,
        DateTime $date,
        Image $imageHelper,
        EmailSendState $state,
        EmarsysModelApiApi $api,
        CustomerResourceModel $customerResourceModel,
        AsyncFactory $asyncModel,
        StoreManagerInterface $storeManager,
        TransportBuilder $transportBuilder
    ) {
        $this->emarsysHelper = $emarsysHelper;
        $this->logsHelper = $logsHelper;
        $this->date = $date;
        $this->imageHelper = $imageHelper;
        $this->state = $state;
        $this->api = $api;
        $this->customerResourceModel = $customerResourceModel;
        $this->asyncModel = $asyncModel;
        $this->storeManager = $storeManager;
        $this->transportBuilder = $transportBuilder;
    }

    /**
     * @param Template $subject
     * @param callable $proceed
     * @return mixed
     * @throws \Exception
     */
    public function aroundProcessTemplate(
        Template $subject,
        callable $proceed
    ) {
        $this->state->set(EmailSendState::STATE_NO);
        if (!$this->emarsysHelper->isEmarsysEnabled()) {
            return $proceed();
        }
        try {
            $this->logsArray['message_type'] = 'Success';
            $this->logsArray['status'] = 'success';
            if (!$this->prepareEmarsysData($subject)) {
                $this->logsArray['message_type'] = 'Error';
                $this->logsArray['status'] = 'error';
                $this->logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
                $this->logsHelper->manualLogs($this->logsArray);
                if ($this->state->get() != EmailSendState::STATE_YES) {
                    return $proceed();
                }
            }
            $this->logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
            $this->logsHelper->manualLogs($this->logsArray);
            $this->state->set(EmailSendState::STATE_YES);
        } catch (\Exception $e) {
            $this->emarsysHelper->addErrorLog(
                'Email workaround',
                $e->getMessage() . ' ' . $e->getTraceAsString(),
                0,
                'TemplatePlugin::aroundProcessTemplate'
            );
            $this->logsArray['message_type'] = 'Error';
            $this->logsArray['status'] = 'error';
            $this->logsArray['messages'] = $e->getMessage();
            $this->logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
            $this->logsHelper->manualLogs($this->logsArray);
            return $proceed();
        }
        return '';
    }

    /**
     * @param Template $subject
     * @return bool|$this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \ReflectionException
     * @throws \Zend_Json_Exception
     */
    public function prepareEmarsysData($subject)
    {
        $reflection = new \ReflectionClass($subject);
        $varsReflection = $reflection->getProperty('_vars');
        $varsReflection->setAccessible(true);
        $vars = $varsReflection->getValue($subject);

        /** @var \Magento\Store\Model\StoreManager $store */
        if (isset($vars['store'])) {
            $store = $vars['store'];
        } else {
            $store = $this->storeManager->getStore();
        }
        $this->storeId = $this->emarsysHelper->getFirstStoreIdOfWebsiteByStoreId($store->getId());
        $this->websiteId = $store->getWebsiteId();

        if (!(bool)$store->getConfig('transaction_mail/transactionmail/enable_customer')) {
            return false;
        }

        $this->email = false;
        $this->customerId = false;
        $this->subscribeId = false;
        $this->templateId = $subject->getTemplateId();

        $this->magentoEventId = $this->emarsysHelper->getMagentoEventId($this->templateId, $this->storeId);
        if (!$this->magentoEventId) {
            $this->emarsysHelper->addErrorLog(
                'Email workaround',
                'no magento event in collection for ' . $this->templateId,
                $this->storeId,
                'TemplatePlugin::prepareEmarsysData | ' . $this->templateId
            );
            return false;
        }

        if ((bool)$store->getConfig('transaction_mail/transactionmail/lock_magento_emails')) {
            $this->state->set(EmailSendState::STATE_YES);
        }

        $this->emarsysEventApiId = $this->emarsysHelper->getEmarsysEventApiId($this->magentoEventId, $this->storeId);
        if (!$this->emarsysEventApiId) {
            $this->emarsysHelper->addErrorLog(
                'Email workaround',
                'no mapping for magento event: ' . $this->magentoEventId . ' | ' . $this->templateId,
                $this->storeId,
                'TemplatePlugin::prepareEmarsysData ' . $this->magentoEventId . ' | ' . $this->templateId
            );
            return false;
        }

        $emarsysPlaceholders = $this->emarsysHelper->getPlaceHolders($this->magentoEventId, $this->storeId);
        if (!$emarsysPlaceholders) {
            $emarsysPlaceholders = $this->emarsysHelper->insertFirstTimeMappingPlaceholders(
                $this->magentoEventId,
                $this->storeId
            );
        }

        $emarsysHeaderPlaceholders = $this->emarsysHelper->emarsysHeaderPlaceholders($this->storeId);
        if (!$emarsysHeaderPlaceholders) {
            $emarsysHeaderPlaceholders = $this->emarsysHelper->insertFirstTimeHeaderMappingPlaceholders($this->storeId);
        }

        $emarsysFooterPlaceholders = $this->emarsysHelper->emarsysFooterPlaceholders($this->storeId);
        if (!$emarsysFooterPlaceholders) {
            $emarsysFooterPlaceholders = $this->emarsysHelper->insertFirstTimeFooterMappingPlaceholders($this->storeId);
        }

        $emarsysPlaceholders = (!is_array($emarsysPlaceholders) ? [] : $emarsysPlaceholders)
            + (!is_array($emarsysHeaderPlaceholders) ? [] : $emarsysHeaderPlaceholders)
            + (!is_array($emarsysFooterPlaceholders) ? [] : $emarsysFooterPlaceholders);

        $applyDesignConfig = $reflection->getMethod('applyDesignConfig');
        $applyDesignConfig->setAccessible(true);
        $isDesignApplied = $applyDesignConfig->invoke($subject);

        if (is_numeric($this->templateId)) {
            $subject->load($this->templateId);
        } else {
            $subject->loadDefault($this->templateId);
        }

        if (!$subject->getId()) {
            throw new \Magento\Framework\Exception\MailException(
                __('Invalid transactional email code: %1', $this->templateId)
            );
        }

        $processedVariables = [];
        $processor = $subject->getTemplateFilter()
            ->setUseSessionInUrl(false)
            ->setPlainTemplateMode($subject->isPlain())
            ->setIsChildTemplate($subject->isChildTemplate())
            ->setTemplateProcessor([$subject, 'getTemplateContent']);

        $processor->setDesignParams($subject->getDesignParams())
            ->setStoreId($this->storeId);

        $vars['this'] = $subject;
        $processor->setVariables($vars);

        foreach ($emarsysPlaceholders as $key => $value) {
            $processedVariables['global'][$key] = $processor->filter($value);
        }

        if (isset($vars['customer'])) {
            $this->logsArray['job_code'] = 'customer';
            $this->email = $vars['customer']->getEmail();
            $this->customerId = $vars['customer']->getId();
        }

        if (isset($vars['subscriber'])) {
            $this->logsArray['job_code'] = 'subscriber';
            $processedVariables['subscriber'] = $this->getSubscriberData($vars['subscriber']);
            $this->email = $vars['subscriber']->getSubscriberEmail();
            $this->customerId = $vars['subscriber']->getCustomerId();
            $this->subscribeId = $vars['subscriber']->getId();
        }

        if (isset($vars['order'])) {
            $this->logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
            $this->logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $this->logsArray['job_code'] = 'order';
            /** @var \Magento\Sales\Model\Order $order */
            $order = $vars['order'];
            $orderData = [];
            foreach ($order->getAllVisibleItems() as $item) {
                $orderData[] = $this->getOrderData($item, $store);
            }
            $processedVariables['product_purchases'] = $orderData;
            $this->email = $order->getCustomerEmail();
            $this->customerId = $order->getCustomerId();
        }

        if (isset($vars['shipment'])) {
            $this->logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
            $this->logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $this->logsArray['job_code'] = 'shipment';
            /** @var \Magento\Sales\Model\Order $order */
            $order = $vars['order'];
            /** @var \Magento\Sales\Model\Order\Shipment $shipment */
            $shipment = $vars['shipment'];
            $shipmentData = [];
            /** @var \Magento\Sales\Model\Order\Shipment\Item $item */
            foreach ($shipment->getItems() as $item) {
                $shipmentItem = $this->getShipmentData($item);
                $shipmentItem['order_item'] = $this->getOrderData($order->getItemById($item->getOrderItemId()), $store);
                $shipmentData[] = $shipmentItem;
            }
            $processedVariables['shipment_items'] = $shipmentData;
            $processedVariables['shipment_tracks'] = $this->getShipmentTracks($shipment->getTracks());
        }

        if (isset($vars['invoice'])) {
            $this->logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
            $this->logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $this->logsArray['job_code'] = 'invoice';
            /** @var \Magento\Sales\Model\Order $order */
            $order = $vars['order'];
            /** @var \Magento\Sales\Model\Order\Invoice $invoce */
            $invoice = $vars['invoice'];
            $invoiceData = [];
            /** @var \Magento\Sales\Model\Order\Invoice\Item $item */
            foreach ($invoice->getItems() as $item) {
                $invoiceItem = $this->getInvoiceData($item);
                $invoiceItem['order_item'] = $this->getOrderData($order->getItemById($item->getOrderItemId()), $store);
                $invoiceData[] = $invoiceItem;
            }
            $processedVariables['invoice_items'] = $invoiceData;
        }

        if (isset($vars['creditmemo'])) {
            $this->logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
            $this->logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $this->logsArray['job_code'] = 'creditmemo';
            /** @var \Magento\Sales\Model\Order $order */
            $order = $vars['order'];
            /** @var \Magento\Sales\Model\Order\Creditmemo $invoce */
            $creditmemo = $vars['creditmemo'];
            $creditmemoData = [];
            /** @var \Magento\Sales\Model\Order\Creditmemo\Item $item */
            foreach ($creditmemo->getItems() as $item) {
                $creditmemoItem = $this->getCreditmemoData($item);
                $creditmemoItem['order_item'] = $this->getOrderData(
                    $order->getItemById($item->getOrderItemId()),
                    $store
                );
                $creditmemoData[] = $creditmemoItem;
            }
            $processedVariables['invoice_items'] = $creditmemoData;
        }

        if (isset($vars['rma'])) {
            $this->logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
            $this->logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $this->logsArray['job_code'] = 'rma';
            /** @var \Magento\Sales\Model\Order $order */
            $order = $vars['order'];
            /** @var \Magento\Rma\Model\Rma $rma */
            $rma = $vars['rma'];
            $returnItems = [];
            /** @var \Magento\Rma\Model\Item $item */
            foreach ($rma->getItemsForDisplay() as $item) {
                $rmaItem = $this->getRmaData($item);
                $rmaItem['order_item'] = $this->getOrderData($order->getItemById($item->getOrderItemId()), $store);
                $returnItems[] = $rmaItem;
            }
            $processedVariables['returned_items'] = $returnItems;
        }

        if (!$this->email) {
            $transportBuilder = \Closure::bind(function (\Magento\Framework\Mail\Template\TransportBuilder $transportBuilder) {
                return $transportBuilder->messageData;
            }, null, '\Magento\Framework\Mail\Template\TransportBuilder');

            $messageData = $transportBuilder($this->transportBuilder);

            if (isset($messageData['to'][0])) {
                $this->email = $messageData['to'][0]->getEmail();
            }
        }

        if ($isDesignApplied) {
            $cancelDesignConfig = $reflection->getMethod('cancelDesignConfig');
            $cancelDesignConfig->setAccessible(true);
            $cancelDesignConfig->invoke($subject);
        }

        return $this->sendEmail($processedVariables);
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
     * @param \Magento\Sales\Model\Order\Item $item
     * @param \Magento\Store\Model\Store $store
     * @return array
     */
    public function getOrderData($item, $store)
    {
        $optionGlue = " - ";
        $optionSeparator = " : ";
        $unitTaxAmount = $item->getTaxAmount() / $item->getQtyOrdered();
        $order = [
            'unitary_price_exc_tax' => $this->_formatPrice($item->getPriceInclTax() - $unitTaxAmount),
            'unitary_price_inc_tax' => $this->_formatPrice($item->getPriceInclTax()),
            'unitary_tax_amount' => $this->_formatPrice($unitTaxAmount),
            'line_total_price_exc_tax' => $this->_formatPrice(
                $item->getRowTotalInclTax() - $item->getTaxAmount()
            ),
            'line_total_price_inc_tax' => $this->_formatPrice($item->getRowTotalInclTax()),
            'line_total_tax_amount' => $this->_formatPrice($item->getTaxAmount()),
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

        $_product = $item->getProduct();
        if (is_object($_product)) {
            $_product->setStoreId($item->getStoreId());

            $base_url = $store->getBaseUrl();
            $base_url = trim($base_url, '/');

            /** @var \Magento\Catalog\Helper\Image $helper */
            try {
                $url = $this->imageHelper
                    ->init($_product, 'product_base_image')
                    ->setImageFile($_product->getImage())
                    ->getUrl();
            } catch (\Exception $e) {
                $url = '';
            }

            $order['_external_image_url'] = $url;

            $order['_url'] = $base_url . "/" . $_product->getUrlPath();
            $order['_url_name'] = $order['product_name'];
            $order['product_description'] = $_product->getData('description');
            $order['short_description'] = $_product->getData('short_description');

            $attributes = $_product->getAttributes();
            $prodData = $_product->getData();
            foreach ($attributes as $attribute) {
                if ($attribute->getFrontendInput() != "gallery" && $attribute->getFrontendInput() != 'price') {
                    if (isset($prodData[$attribute->getAttributeCode()])) {
                        if (!is_array($prodData[$attribute->getAttributeCode()])
                            && ($attributeText = $_product->getAttributeText($attribute->getAttributeCode()))
                        ) {
                            $text = is_object($attributeText) ? $attributeText->getText() : $attributeText;
                        } else {
                            $text = $prodData[$attribute->getAttributeCode()];
                        }
                        $order['attribute_' . $attribute->getAttributeCode()] = $text;
                    }
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
        $order['additional_data'] = (
        $item->getData('additional_data')
            ? $item->getData('additional_data')
            : ""
        );

        return $order;
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment\Item $item
     * @return array
     */
    public function getShipmentData($item)
    {
        $item = $item->getData();
        unset($item['order'], $item['source']);

        return $item;
    }

    /**
     * @param array
     * @return array
     */
    public function getShipmentTracks($tracks)
    {
        $tracksInfo = [];

        foreach ($tracks as $track) {
            $tracksInfo[] = $track->getData();
        }

        return $tracksInfo;
    }

    /**
     * @param \Magento\Sales\Model\Order\Invoice\Item $item
     * @return array
     */
    public function getInvoiceData($item)
    {
        $item = $item->getData();
        unset($item['invoice'], $item['order'], $item['source']);

        return $item;
    }

    /**
     * @param \Magento\Sales\Model\Order\Creditmemo\Item $item
     * @return array
     */
    public function getCreditmemoData($item)
    {
        $item = $item->getData();
        unset($item['creditmemo'], $item['order'], $item['source']);

        return $item;
    }

    /**
     * @param \Magento\Rma\Model\Item $item
     * @return array
     * @throws \Zend_Json_Exception
     */
    public function getRmaData($item)
    {
        $returnItem = [];
        /** @var \Magento\Rma\Model\Item\Attribute $attribute */
        foreach ($item->getAttributes() as $attribute) {
            if (!is_null($item->getData($attribute->getAttributeCode()))) {
                if ($attributeText = $attribute->getFrontend()->getValue($item)) {
                    $returnItem[$attribute->getAttributeCode()] = is_object($attributeText)
                        ? $attributeText->getText()
                        : $attributeText;
                } else {
                    $returnItem[$attribute->getAttributeCode()] = $item->getData($attribute->getAttributeCode());
                }
            }
        }
        if ($item->getProductOptions() && !empty($item->getProductOptions())) {
            $productOptions = \Zend_Json::decode($item->getProductOptions());
            if ($productOptions) {
                $returnItem['product_options'] = $productOptions;
            }
        }

        return $returnItem;
    }

    /**
     * @param \Magento\Newsletter\Model\Subscriber $item
     * @return array
     */
    public function getSubscriberData($item)
    {
        $item = $item->getData();
        unset($item['emarsys_no_export']);

        return $item;
    }

    /**
     * @param array $processedVariables
     * @return bool
     * @throws \Exception
     */
    public function sendEmail($processedVariables)
    {
        $buildRequest = [];
        $buildRequest['key_id'] = $this->customerResourceModel->getKeyId(EmarsysHelper::CUSTOMER_EMAIL, $this->storeId);
        $buildRequest[$buildRequest['key_id']] = $this->email;

        if (!empty($this->customerId)) {
            $customerIdKey = $this->customerResourceModel->getKeyId(EmarsysHelper::CUSTOMER_ID, $this->storeId);
            $buildRequest[$customerIdKey] = $this->customerId;
        }

        if (!empty($this->subscribeId)) {
            $subscriberIdKey = $this->customerResourceModel->getKeyId(EmarsysHelper::SUBSCRIBER_ID, $this->storeId);
            $buildRequest[$subscriberIdKey] = $this->subscribeId;
        }

        $arrCustomerData = [
            "key_id" => $buildRequest['key_id'],
            "external_id" => $buildRequest[$buildRequest['key_id']],
            "data" => $processedVariables,
        ];

        if ($this->emarsysHelper->isAsyncEnabled()) {
            $this->asyncModel->create()->saveToAsyncQueue(
                $this->websiteId,
                'event/' . $this->emarsysEventApiId . '/trigger',
                $this->email,
                $this->customerId,
                null,
                [$buildRequest, $arrCustomerData]
            );

            $this->logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
            $this->logsArray['emarsys_info'] = 'Added to Async queue';
            $this->logsArray['description'] = 'Added to Async queue';
            $this->logsHelper->manualLogs($this->logsArray);
            return true;
        }

        //log information that is about to send for contact sync
        $this->logsArray['emarsys_info'] = 'Send Contact to Emarsys';
        $this->logsArray['description'] = 'PUT '
            . EmarsysModelApiApi::CONTACT_CREATE_IF_NOT_EXISTS
            . ' ' . \Zend_Json::encode($buildRequest);
        $this->logsArray['action'] = EmarsysModelApiApi::CONTACT_CREATE_IF_NOT_EXISTS;
        $this->logsHelper->manualLogs($this->logsArray);

        $this->api->setWebsiteId($this->websiteId);
        //sync contact to emarsys
        $response = $this->api->createContactInEmarsys($buildRequest);

        $this->logsArray['emarsys_info'] = 'Transactional Mail request';
        $this->logsArray['action'] = 'event/' . $this->emarsysEventApiId . '/trigger';
        if (($response['status'] == 200) || ($response['status'] == 400 && $response['body']['replyCode'] == 2009)) {
            //contact synced to emarsys successfully
            //log information that is about to send for email sync
            $this->logsArray['description'] = 'POST '
                . 'event/' . $this->emarsysEventApiId . '/trigger ' . \Zend_Json::encode($arrCustomerData);
            $this->logsHelper->manualLogs($this->logsArray);

            //trigger email event
            $response = $this->api->sendRequest(
                'POST',
                'event/' . $this->emarsysEventApiId . '/trigger',
                $arrCustomerData
            );

            //check email event's response status
            if ($response['status'] == 200) {
                //email send successfully
                $this->logsArray['description'] = \Zend_Json::encode($response);
                $this->logsArray['messages'] = __('Transactional Email Completed. %1', $this->templateId);

                return true;
            }
        }

        //failed to sync contact to emarsys
        $this->logsArray['description'] = 'Failed to Sync Contact to Emarsys. Emarsys Event ID :'
            . $this->emarsysEventApiId
            . ', Due to this error, Email Sent From Magento for Store Id: ' . $this->storeId
            . ' (' . $this->templateId . ')'
            . ' Request: ' . \Zend_Json::encode($buildRequest)
            . '\n Response: ' . \Zend_Json::encode($response);
        $this->logsArray['messages'] = __(
            'Error while sending Transactional Email (%1), Email Sent From Magento',
            $this->templateId
        );

        return false;
    }
}
