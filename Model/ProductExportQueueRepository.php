<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model;

use Emarsys\Emarsys\Model\ProductExportQueue;
use Emarsys\Emarsys\Model\ProductExportQueueFactory;
use Emarsys\Emarsys\Model\ResourceModel\ProductExportQueue\Collection;
use Emarsys\Emarsys\Model\ResourceModel\ProductExportQueue\CollectionFactory as ProductExportQueueCollectionFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SearchResultsInterfaceFactory;

class ProductExportQueueRepository
{
    /**
     * @var ProductExportQueueFactory
     */
    private $productExportQueueFactory;

    /**
     * @var ProductExportQueueCollectionFactory
     */
    private $productExportQueueCollectionFactory;

    /**
     * @var SearchResultsInterfaceFactory
     */
    private $searchResultFactory;

    public function __construct(
        ProductExportQueueFactory $productExportQueueFactory,
        ProductExportQueueCollectionFactory $productExportQueueCollectionFactory,
        SearchResultsInterfaceFactory $searchResultInterfaceFactory
    ) {
        $this->productExportQueueFactory = $productExportQueueFactory;
        $this->productExportQueueCollectionFactory = $productExportQueueCollectionFactory;
        $this->searchResultFactory = $searchResultInterfaceFactory;
    }

    /**
     * @param int $id
     * @return ProductExportQueue
     */
    public function getById($id)
    {
        $productExportQueue = $this->productExportQueueFactory->create();
        $productExportQueue->getResource()->load($productExportQueue, $id);

        return $productExportQueue;
    }

    /**
     * @param ProductExportQueue $productExportQueue
     * @return ProductExportQueue
     */
    public function save(ProductExportQueue $productExportQueue)
    {
        $productExportQueue->getResource()->save($productExportQueue);
        return $productExportQueue;
    }

    /**
     * @param ProductExportQueue $productExportQueue
     */
    public function delete(ProductExportQueue $productExportQueue)
    {
        $productExportQueue->getResource()->delete($productExportQueue);
    }

    /**
     * @param ProductExportQueue $productExportQueue
     */
    public function truncate(ProductExportQueue $productExportQueue)
    {
        $connection = $productExportQueue->getResource()->getConnection();
        $tableName = $productExportQueue->getResource()->getMainTable();
        $connection->truncateTable($tableName);
        unset($connection);
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return Data\SearchResultInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->productExportQueueCollectionFactory->create();

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
