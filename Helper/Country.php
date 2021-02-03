<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Helper;

use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Emarsys\Emarsys\Model\Api\Api as EmarsysModelApiApi;
use Emarsys\Emarsys\Model\CountryRepository;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface as StoreManager;
use Magento\Framework\Api\SearchCriteriaBuilder;

/**
 * Class Country
 */
class Country extends AbstractHelper
{
    protected $_mapping = [];

    /**
     * @var Data
     */
    protected $emarsysHelper;

    /**
     * @var EmarsysModelApiApi
     */
    protected $api;

    /**
     * @var StoreManager
     */
    protected $storeManager;

    /**
     * @var CountryRepository
     */
    protected $countryRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    protected $_overrides = [
        'Hong Kong' => 'HK',
        'Macau' => 'MO',
        'Brunei Darussalam' => 'BN',
        'Cote d\'Ivoire' => 'CI',
        'Gambia, The' => 'GM',
        'Congo' => 'CG',
        'Congo, Democratic Republic of the' => 'CD',
        'Korea, South' => 'KR',
        'Korea, North' => 'KP',
        'Myanmar' => 'MM',
        'The Netherlands' => 'NL',
        'St. Kitts and Nevis' => 'KN',
        'St. Lucia' => 'LC',
        'St. Vincent and The Grenadines' => 'VC',
        'United States of America' => 'US',
        'Antigua and Barbuda' => 'AG',
        'Bosnia and Herzegovina' => 'BA',
        'Burma' => 'BU',
        'Canary Islands' => 'IC',
        'Czech Republic' => 'CZ',
        'East Timor' => 'TL',
        'Macedonia' => 'MK',
        'Netherlands Antilles' => 'AN',
        'Palestine' => 'PS',
        'São Tomé and Príncipe' => 'ST',
        'Swaziland' => 'SZ',
        'Taiwan' => 'TW',
        'Trinidad and Tobago' => 'TT',
        'Virgin Islands' => 'VI',
        'Yugoslavia' => 'YU',
        'Zaire' => 'ZR',
        'French Southern and Antarctic Lands' => 'TF',
        'Heard Island and McDonald Islands' => 'HM',
        'Palestinian territories' => 'PS',
        'Saint Barthélemy' => 'BL',
        'Saint Helena' => 'SH',
        'Saint Martin' => 'MF',
        'Saint Pierre and Miquelon' => 'PM',
        'South Georgia and the South Sandwich Islands' => 'GS',
        'South Sudan' => 'SS',
        'Svalbard and Jan Mayen' => 'SJ',
        'Turks and Caicos Islands' => 'TC',
        'United States Minor Outlying Islands' => 'UM',
        'Wallis and Futuna' => 'WF',
    ];

    /**
     * Country constructor.
     *
     * @param Context $context
     * @param CountryCollectionFactory $countryFactory
     * @param Data $emarsysHelper
     * @param EmarsysModelApiApi $api
     * @param StoreManager $storeManager
     * @param CountryRepository $countryRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        Context $context,
        CountryCollectionFactory $countryFactory,
        EmarsysHelper $emarsysHelper,
        EmarsysModelApiApi $api,
        StoreManager $storeManager,
        CountryRepository $countryRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->context = $context;
        $this->_countryFactory = $countryFactory;
        $this->emarsysHelper = $emarsysHelper;
        $this->api = $api;
        $this->storeManager = $storeManager;
        $this->countryRepository = $countryRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;

        parent::__construct($context);
    }

    /**
     * @param $storeId
     * @return array
     * @throws \Exception
     */
    protected function _getMapping($storeId)
    {
        $countries = [];
        $mappedCountries = [];

        $store = $this->storeManager->getStore($storeId);
        $filter = $this->searchCriteriaBuilder
            ->addFilter('website_id', 0)
            ->create();
        $list = $this->countryRepository->getList($filter);
        if (!$list->getTotalCount()) {
            $this->api->setWebsiteId($store->getWebsiteId());
            $response = $this->api->sendRequest('GET', 'field/14/choice/translate/en');
            if (isset($response['body']['data'])) {
                $magentoCountries = $this->_countryFactory->create();
                foreach ($magentoCountries as $country) {
                    $countries[$country->getName()] = $country->getId();
                }
                foreach ($response['body']['data'] as $item) {
                    if (isset($countries[$item['choice']])) {
                        $item['magento_id'] = $countries[$item['choice']];
                    } elseif (isset($this->_overrides[$item['choice']])) {
                        $item['magento_id'] = $this->_overrides[$item['choice']];
                    }
                    $model = $this->countryRepository->getById($item['id']);
                    $item['website_id'] = 0;
                    $model->setData($item);
                    $model->isObjectNew(true);
                    $this->countryRepository->save($model);
                }
                $list = $this->countryRepository->getList($filter);
            }
        }

        foreach ($list->getItems() as $item) {
            $mappedCountries[$item->getMagentoId()] = $item->getId();
        }

        return $mappedCountries;
    }

    /**
     * @param $store
     * @return array
     * @throws \Zend_Json_Exception
     */
    public function getMapping($store)
    {
        if (empty($this->_mapping)) {
            $mapping = $this->_getMapping($store);
            $this->_mapping = $mapping;
        }

        return $this->_mapping;
    }

    public function truncate()
    {
        $model = $this->countryRepository->getById(0);
        $this->countryRepository->truncate($model);
    }
}
