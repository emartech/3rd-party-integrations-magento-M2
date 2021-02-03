<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface CountrySearchResultInterface extends SearchResultsInterface
{
    /**
     * @return \Emarsys\Emarsys\Api\Data\CountryInterface[]
     */
    public function getItems();

    /**
     * @param \Emarsys\Emarsys\Api\Data\CountryInterface[] $items
     * @return void
     */
    public function setItems(array $items);
}