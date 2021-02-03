<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\CronSchedule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Emarsys\Emarsys\Model\EmarsysCronDetailsFactory;

class Params extends Action
{
    /**
     * @var EmarsysCronDetailsFactory
     */
    protected $cronDetailsFactory;

    /**
     * Params constructor.
     *
     * @param Context $context
     * @param EmarsysCronDetailsFactory $cronDetailsFactory
     */
    public function __construct(
        Context $context,
        EmarsysCronDetailsFactory $cronDetailsFactory
    ) {
        $this->cronDetailsFactory = $cronDetailsFactory;
        parent::__construct($context);
    }

    /**
     * @return void|$this
     * @throws \Zend_Json_Exception
     */
    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        if ($id) {
            $cronDetails = $this->cronDetailsFactory->create()->load($id);
            if ($cronDetails) {
                $params = \Zend_Json::decode($cronDetails->getParams());
                if ($params) {
                    printf('<pre>' . (\Zend_Json::encode($params)) . '</pre>');
                } else {
                    printf('No Data Available');
                }
            } else {
                printf('No Data Available');
            }
        } else {
            printf('No Data Available');
        }
    }
}
