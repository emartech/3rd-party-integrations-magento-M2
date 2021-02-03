<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Api;

use Emarsys\Emarsys\Api\Data\CountryInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

interface CountryRepositoryInterface
{
    /**
     * @param int $id
     * @return \Emarsys\Emarsys\Api\Data\CountryInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById($id);

    /**
     * @param \Emarsys\Emarsys\Api\Data\CountryInterface $country
     * @return \Emarsys\Emarsys\Api\Data\CountryInterface
     */
    public function save(CountryInterface $country);

    /**
     * @param \Emarsys\Emarsys\Api\Data\CountryInterface $country
     * @return void
     */
    public function delete(CountryInterface $country);

    /**
     * @param \Emarsys\Emarsys\Api\Data\CountryInterface $country
     * @return void
     */
    public function truncate(CountryInterface $country);

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Emarsys\Emarsys\Api\Data\CountrySearchResultInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);
}
