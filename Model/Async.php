<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;
use Zend_Json;

/**
 * Class Async
 */
class Async extends AbstractModel
{
    /**
     * @var DateTimeFactory
     */
    protected $dateFactory;

    /**
     * Async constructor.
     *
     * @param DateTimeFactory $dateFactory
     * @param Context $context
     * @param Registry $registry
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        DateTimeFactory $dateFactory,
        Context $context,
        Registry $registry,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->dateFactory = $dateFactory;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * constructor
     */
    public function _construct()
    {
        parent::_construct();
        $this->_init(\Emarsys\Emarsys\Model\ResourceModel\Async::class);
    }

    /**
     * @param int $websiteId
     * @param string $endpoint
     * @param string $email
     * @param null|int $customerId
     * @param null|int $subscriberId
     * @param array $requestBody
     * @throws \Exception
     */
    public function saveToAsyncQueue(
        $websiteId,
        $endpoint,
        $email,
        $customerId = null,
        $subscriberId = null,
        $requestBody
    ) {
        $this->setWebsiteId($websiteId)
            ->setEndpoint($endpoint)
            ->setEmail($email)
            ->setCustomerId($customerId)
            ->setSubscriberId($subscriberId)
            ->setRequestBody(Zend_Json::encode($requestBody))
            ->save();
    }

    /**
     * Processing object before save data
     *
     * @return $this
     */
    public function beforeSave()
    {
        $this->setUpdatedAt($this->dateFactory->create()->gmtDate());
        return parent::beforeSave();
    }

    /**
     * @return int
     */
    public function getWebsiteId()
    {
        return $this->getData('website_id');
    }
    /**
     * @param int $websiteId
     * @return Async
     */
    public function setWebsiteId($websiteId)
    {
        return $this->setData('website_id', $websiteId);
    }

    /**
     * @return string
     */
    public function getEndpoint()
    {
        return $this->getData('endpoint');
    }

    /**
     * @param string $endpoint
     * @return Async
     */
    public function setEndpoint($endpoint)
    {
        return $this->setData('endpoint', $endpoint);
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->getData('email');
    }

    /**
     * @param string $email
     * @return Async
     */
    public function setEmail($email)
    {
        return $this->setData('email', $email);
    }

    /**
     * @return  null|int
     */
    public function getCustomerId()
    {
        return $this->getData('customer_id');
    }

    /**
     * @param null|int $customerId
     * @return Async
     */
    public function setCustomerId($customerId = null)
    {
        return $this->setData('customer_id', $customerId);
    }

    /**
     * @return null
     */
    public function getSubscriberId()
    {
        return $this->getData('subscriber_id');
    }

    /**
     * @param null $subscriberId
     * @return Async
     */
    public function setSubscriberId($subscriberId = null)
    {
        return $this->setData('subscriber_id', $subscriberId);
    }

    /**
     * @return string json encoded
     */
    public function getRequestBody()
    {
        return $this->getData('request_body');
    }

    /**
     * @param string $requestBody
     * @return Async
     */
    public function setRequestBody($requestBody)
    {
        return $this->setData('request_body', $requestBody);
    }
}
