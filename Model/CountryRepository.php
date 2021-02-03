<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model;

use Emarsys\Emarsys\Api\CountryRepositoryInterface;
use Emarsys\Emarsys\Api\Data\CountryInterface;
use Emarsys\Emarsys\Api\Data\CountrySearchResultInterfaceFactory;
use Emarsys\Emarsys\Model\Country;
use Emarsys\Emarsys\Model\CountryFactory;
use Emarsys\Emarsys\Model\ResourceModel\Country\Collection;
use Emarsys\Emarsys\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Exception\NoSuchEntityException;

class CountryRepository implements CountryRepositoryInterface
{
    /**
     * @var CountryFactory
     */
    private $countryFactory;

    /**
     * @var CountryCollectionFactory
     */
    private $countryCollectionFactory;

    /**
     * @var CountrySearchResultInterfaceFactory
     */
    private $searchResultFactory;

    public function __construct(
        CountryFactory $countryFactory,
        CountryCollectionFactory $countryCollectionFactory,
        CountrySearchResultInterfaceFactory $countrySearchResultInterfaceFactory
    ) {
        $this->countryFactory = $countryFactory;
        $this->countryCollectionFactory = $countryCollectionFactory;
        $this->searchResultFactory = $countrySearchResultInterfaceFactory;
    }

    /**
     * @param int $id
     * @return CountryInterface
     */
    public function getById($id)
    {
        $country = $this->countryFactory->create();
        $country->getResource()->load($country, $id);
//        if (!$country->getId()) {
//            throw new NoSuchEntityException(__('Unable to find country with ID "%1"', $id));
//        }
        return $country;
    }

    /**
     * @param CountryInterface $country
     * @return CountryInterface
     */
    public function save(CountryInterface $country)
    {
        $country->getResource()->save($country);
        return $country;
    }

    /**
     * @param CountryInterface $country
     */
    public function delete(CountryInterface $country)
    {
        $country->getResource()->delete($country);
    }

    /**
     * @param CountryInterface $country
     */
    public function truncate(CountryInterface $country)
    {
        $connection = $country->getResource()->getConnection();
        $tableName = $country->getResource()->getMainTable();
        $connection->truncateTable($tableName);
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return Data\CountrySearchResultInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->countryCollectionFactory->create();

        $this->addFiltersToCollection($searchCriteria, $collection);
        $this->addSortOrdersToCollection($searchCriteria, $collection);
        $this->addPagingToCollection($searchCriteria, $collection);

        $collection->load();

        return $this->buildSearchResult($searchCriteria, $collection);
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @param Collection $collection
     */
    private function addFiltersToCollection(SearchCriteriaInterface $searchCriteria, Collection $collection)
    {
        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            $fields = $conditions = [];
            foreach ($filterGroup->getFilters() as $filter) {
                $fields[] = $filter->getField();
                $conditions[] = [$filter->getConditionType() => $filter->getValue()];
            }
            $collection->addFieldToFilter($fields, $conditions);
        }
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @param Collection $collection
     */
    private function addSortOrdersToCollection(SearchCriteriaInterface $searchCriteria, Collection $collection)
    {
        foreach ((array) $searchCriteria->getSortOrders() as $sortOrder) {
            $direction = $sortOrder->getDirection() == SortOrder::SORT_ASC ? 'asc' : 'desc';
            $collection->addOrder($sortOrder->getField(), $direction);
        }
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @param Collection $collection
     */
    private function addPagingToCollection(SearchCriteriaInterface $searchCriteria, Collection $collection)
    {
        $collection->setPageSize($searchCriteria->getPageSize());
        $collection->setCurPage($searchCriteria->getCurrentPage());
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @param Collection $collection
     * @return mixed
     */
    private function buildSearchResult(SearchCriteriaInterface $searchCriteria, Collection $collection)
    {
        $searchResults = $this->searchResultFactory->create();

        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }
}
