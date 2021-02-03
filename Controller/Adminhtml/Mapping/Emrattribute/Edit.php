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

class Edit extends Action
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
     * Edit constructor.
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
        $attributeId = $requestParams['Id'];
        $code = $requestParams['code'];
        $label = $requestParams['label'];
        $field_type = $requestParams['field_type'];
        if ($attributeId) {
            $productAttribute = $this->emrattribute->load($attributeId);
            $productId = $productAttribute->getId();
            if ($productId) {
                if (stripos($code, 'c_') === false) {
                    $code = 'c_' . $code;
                }
                if (stripos($label, 'c_') === false) {
                    $label = 'c_' . $label;
                }
                $field_type = ucfirst($field_type);
                $productAttribute->setCode($code)
                    ->setLabel($label)
                    ->setFieldType($field_type)
                    ->save();
                $resultJson = $this->resultJsonFactory->create();
                $data['status'] = 'SUCCESS';
                return $resultJson->setData($data);
            }
        }
    }
}
