<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Plugin;

use Magento\Checkout\CustomerData\Cart;
use Magento\Quote\Model\Quote\Item;
use Magento\Catalog\Model\Product;

class CustomerDataCart
{
    /**
     * @var Item
     */
    public $item;

    /**
     * @var Product
     */
    public $product;

    /**
     * CustomerDataCart constructor.
     *
     * @param Item $item
     * @param Product $product
     */
    public function __construct(
        Item $item,
        Product $product
    ) {
        $this->item = $item;
        $this->product = $product;
    }

    /**
     * @param OriginalCustomerDataCart $subject
     * @param array $result
     *
     * @return array
     */
    public function afterGetSectionData(Cart $subject, $result)
    {
        if (isset($result['items']) && !empty($result['items'])) {
            foreach ($result['items'] as &$item) {
                $productId = $this->product->getIdBySku($item['product_sku']);
                $item['product_real_id'] = $productId ? $productId : $item['product_id'];

                /** @var \Magento\Quote\Model\Quote\Item $itemModel */
                $itemModel = $this->item->load($item['item_id']);
                $item['base_product_price_value'] = round($itemModel->getBasePrice(), 2);
            }
        }

        return $result;
    }
}
