<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

interface CountryInterface extends ExtensibleDataInterface
{
    /**
     * @return string
     */
    public function getChoice();

    /**
     * @param string $choice
     * @return void
     */
    public function setChoice($choice);

    /**
     * @return string|null
     */
    public function getBitPosition();

    /**
     * @param string $bitPosition
     * @return void
     */
    public function setBitPosition($bitPosition);

    /**
     * @return int
     */
    public function getWebsiteId();

    /**
     * @param int $websiteId
     * @return void
     */
    public function setWebsiteId($websiteId);

    /**
     * @return string
     */
    public function getMagentoId();

    /**
     * @param string $magentoId
     * @return void
     */
    public function setMagentoId($magentoId);
}
