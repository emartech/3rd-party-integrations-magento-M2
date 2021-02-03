<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\Mapping\Emrattribute;

use Emarsys\Emarsys\Model\Emrattribute;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;

class Delete extends Action
{
    /**
     * @var Emrattribute
     */
    protected $emrattribute;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * Delete constructor.
     *
     * @param Context $context
     * @param Emrattribute $emrattribute
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        Emrattribute $emrattribute,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->emrattribute = $emrattribute;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    /**
     * @return $this|ResponseInterface|ResultInterface
     * @throws \Exception
     */
    public function execute()
    {
        $requestParams = $this->getRequest()->getParams();
        $productId = $requestParams['Id'];
        $productAttribute = $this->emrattribute->load($productId);
        if ($productAttribute->getId()) {
            $productAttribute->delete();
        }
        $data['status'] = 'SUCCESS';
        $resultJson = $this->resultJsonFactory->create();
        $data['status'] = 'SUCCESS';
        return $resultJson->setData($data);
    }
}
