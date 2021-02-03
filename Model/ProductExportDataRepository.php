<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model;

use Emarsys\Emarsys\Model\ProductExportData;
use Emarsys\Emarsys\Model\ProductExportDataFactory;
use Emarsys\Emarsys\Model\ResourceModel\ProductExportData\Collection;
use Emarsys\Emarsys\Model\ResourceModel\ProductExportData\CollectionFactory as ProductExportDataCollectionFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SearchResultsInterfaceFactory;

class ProductExportDataRepository
{
    /**
     * @var ProductExportDataFactory
     */
    private $productExportDataFactory;

    /**
     * @var ProductExportDataCollectionFactory
     */
    private $productExportDataCollectionFactory;

    /**
     * @var SearchResultsInterfaceFactory
     */
    private $searchResultFactory;

    public function __construct(
        ProductExportDataFactory $productExportDataFactory,
        ProductExportDataCollectionFactory $productExportDataCollectionFactory,
        SearchResultsInterfaceFactory $searchResultInterfaceFactory
    ) {
        $this->productExportDataFactory = $productExportDataFactory;
        $this->productExportDataCollectionFactory = $productExportDataCollectionFactory;
        $this->searchResultFactory = $searchResultInterfaceFactory;
    }

    /**
     * @param int $id
     * @return ProductExportData
     */
    public function getById($id)
    {
        $productExportData = $this->productExportDataFactory->create();
        $productExportData->getResource()->load($productExportData, $id);

        return $productExportData;
    }

    /**
     * @param ProductExportData $productExportData
     * @return ProductExportData
     */
    public function save(ProductExportData $productExportData)
    {
        $productExportData->getResource()->save($productExportData);
        return $productExportData;
    }

    /**
     * @param ProductExportData $productExportData
     */
    public function delete(ProductExportData $productExportData)
    {
        $productExportData->getResource()->delete($productExportData);
    }

    /**
     * @param ProductExportData $productExportData
     */
    public function truncate(ProductExportData $productExportData)
    {
        $connection = $productExportData->getResource()->getConnection();
        $tableName = $productExportData->getResource()->getMainTable();
        $connection->truncateTable($tableName);
        unset($connection);
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return Data\SearchResultInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->productExportDataCollectionFactory->create();

        $this->addFiltersToCollection($searchCriteria, $collection);
        $this->addSortOrdersToCollection($searchCriteria, $collection);
        $this->addPagingToCollection($searchCriteria, $collection);

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
        foreach ((array)$searchCriteria->getSortOrders() as $sortOrder) {
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
        $collection->getSelect()->query()->closeCursor();
        unset($collection);

        return $searchResults;
    }
}
