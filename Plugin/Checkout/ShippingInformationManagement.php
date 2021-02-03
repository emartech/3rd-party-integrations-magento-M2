<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Plugin\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Checkout\Model\ShippingInformationManagement as SIM;
use Magento\Checkout\Api\Data\ShippingInformationInterface;

/**
 * Class ShippingInformationManagement
 */
class ShippingInformationManagement
{
    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * ShippingInformationManagement constructor.
     *
     * @param Session $checkoutSession
     */
    public function __construct(Session $checkoutSession)
    {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @param SIM $subject
     * @param $cartId
     * @param ShippingInformationInterface $addressInformation
     */
    public function beforeSaveAddressInformation(
        SIM $subject,
        $cartId,
        ShippingInformationInterface $addressInformation
    ) {
        $extAttributes = $addressInformation->getShippingAddress()->getExtensionAttributes();
        if ($extAttributes) {
            $this->checkoutSession->setNewsletterSubCheckout(
                $extAttributes->getEmarsysSubscriber() ? 1 : 0
            );
        }
    }
}
