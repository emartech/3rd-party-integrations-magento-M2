<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Emarsys\Emarsys\Helper\Data;

class JavascriptTracking extends Template
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CategoryFactory
     */
    protected $categoryFactory;

    /**
     * @var Registry
     */
    protected $coreRegistry;

    /**
     * @var CurrencyFactory
     */
    protected $currencyFactory;

    /**
     * @var CategoryCollectionFactory
     */
    protected $categoryCollectionFactory;

    /**
     * JavascriptTracking constructor.
     *
     * @param Context $context
     * @param CategoryFactory $categoryFactory
     * @param Http $request
     * @param Registry $registry
     * @param CurrencyFactory $currencyFactory
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        CategoryFactory $categoryFactory,
        Http $request,
        Registry $registry,
        CurrencyFactory $currencyFactory,
        CategoryCollectionFactory $categoryCollectionFactory,
        array $data = []
    ) {
        $this->storeManager = $context->getStoreManager();
        $this->categoryFactory = $categoryFactory;
        $this->_request = $request;
        $this->coreRegistry = $registry;
        $this->currencyFactory = $currencyFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        parent::__construct($context, $data);
    }

    /**
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getPageHandleStatus()
    {
        $handle = $this->_request->getFullActionName();

        $pageResult = [];

        $pageHandles = [
            'cms_index_index' => Data::XPATH_CMS_INDEX_INDEX,
            'catalog_category_view' => Data::XPATH_CATALOG_CATEGORY_VIEW,
            'catalog_product_view' => Data::XPATH_CATALOG_PRODUCT_VIEW,
            'checkout_cart_index' => Data::XPATH_CHECKOUT_CART_INDEX,
            'checkout_onepage_success' => Data::XPATH_CHECKOUT_ONEPAGE_SUCCESS,
            'catalogsearch_result_index' => Data::XPATH_CATALOGSEARCH_RESULT_INDEX,
            'search' => Data::XPATH_CATALOGSEARCH_RESULT_INDEX,
        ];

        if (array_key_exists($handle, $pageHandles)) {
            if ($this->getJsEnableStatusForAllPages()) {
                $path = $pageHandles[$handle];
                $pageValue = $this->storeManager->getStore()->getConfig($path);
                $pageData = explode('||', $pageValue);
                $pageResult['logic'] = $pageData[0];
                $pageResult['templateId'] = $pageData[1];
                $pageResult['status'] = true;
            } else {
                $pageResult['status'] = false;
            }
        } else {
            $pageResult['status'] = true;
        }

        return $pageResult;
    }

    /**
     * Get Merchant Id from DB
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getMerchantId()
    {
        return $this->storeManager->getStore()->getConfig(Data::XPATH_WEBEXTEND_MERCHANT_ID);
    }

    /**
     * Get Status of Web Extended Javascript integration from DB
     *
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getJsEnableStatusForAllPages()
    {
        return (bool)$this->storeManager->getWebsite()->getConfig(Data::XPATH_EMARSYS_ENABLED)
            && (bool)$this->storeManager->getStore()->getConfig(Data::XPATH_WEBEXTEND_JS_TRACKING_ENABLED);
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isTestModeEnabled()
    {
        return (bool)$this->storeManager->getStore()->getConfig(Data::XPATH_WEBEXTEND_MODE);
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     */
    public function useBaseCurrency()
    {
        return (bool)$this->storeManager->getStore()->getConfig(Data::XPATH_WEBEXTEND_USE_BASE_CURRENCY);
    }

    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getUniqueIdentifier()
    {
        return $this->storeManager->getStore()->getConfig(Data::XPATH_WEBEXTEND_UNIQUE_ID);
    }

    /**
     * Get Tracking Data
     *
     * @return mixed
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getTrackingData()
    {
        return \Zend_Json::encode(
            [
                'product' => $this->getCurrentProduct(),
                'category' => $this->getCategory(),
                'search' => $this->getSearchData(),
                'useBaseCurrency' => $this->useBaseCurrency(),
                'slug' => $this->getStoreSlug(),
                'displayCurrency' => $this->getDisplayCurrency(),
            ]
        );
    }

    /**
     * Get Current Product
     *
     * @return bool|mixed
     */
    public function getCurrentProduct()
    {
        $product = $this->coreRegistry->registry('current_product');
        if ($product instanceof Product) {
            return [
                'sku' => 'g/' . $product->getSku(),
                'id' => $product->getId(),
            ];
        }

        return false;
    }

    /**
     * Get Category
     *
     * @return mixed
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getCategory()
    {
        $category = $this->coreRegistry->registry('current_category');
        if ($category instanceof Category) {
            $categoryList = [];

            $categoryIds = $this->removeDefaultCategories($category->getPathIds());

            /**
             * @var Collection $categoryCollection
             */
            $categoryCollection = $this->categoryCollectionFactory->create()
                ->addIdFilter($categoryIds)
                ->setStore($this->storeManager->getStore())
                ->addAttributeToSelect('name');

            /**
             * @var Category $category
             */
            foreach ($categoryCollection as $categoryItem) {
                $categoryList[] = $categoryItem->getName();
            }

            return [
                'names' => $categoryList,
                'ids' => $categoryIds,
            ];
        }
        return false;
    }

    /**
     * @param array $categoryIds
     *
     * @return array
     * @throws NoSuchEntityException
     */
    protected function removeDefaultCategories($categoryIds)
    {
        $returnArray = [];
        $basicCategoryIds = [
            1,
            $this->storeManager->getStore()->getRootCategoryId(),
        ];
        foreach ($categoryIds as $categoryId) {
            if (!in_array($categoryId, $basicCategoryIds)) {
                $returnArray[] = $categoryId;
            }
        }

        return $returnArray;
    }

    /**
     * Get Search Data
     *
     * @return bool|mixed
     * @throws Exception
     */
    public function getSearchData()
    {
        $q = $this->_request->getParam('q');
        if ($q != '') {
            return [
                'term' => $this->escapeJs($q),
            ];
        }
        return false;
    }

    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getDisplayCurrency()
    {
        if ($this->useBaseCurrency()) {
            return $this->storeManager->getStore()->getBaseCurrency()->getCode();
        } else {
            return $this->storeManager->getStore()->getCurrentCurrency()->getCode();
        }
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getStoreSlug()
    {
        if ($this->isDefault($this->storeManager->getStore())) {
            return '';
        }
        return $this->storeManager->getStore()->getCode();
    }

    /**
     * @param  $store
     * @return bool
     */
    public function isDefault($store)
    {
        if (!$store->getId() && $store->getWebsite() && $store->getWebsite()->getStoresCount() == 0) {
            return true;
        }
        return $store->getGroup()->getDefaultStoreId() == $store->getId();
    }
}
