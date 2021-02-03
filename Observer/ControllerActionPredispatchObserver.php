<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */
namespace Emarsys\Emarsys\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Customer\Model\Session;

/**
 * Class ControllerActionPredispatchObserver
 */
class ControllerActionPredispatchObserver implements ObserverInterface
{
    /**
     * @var Session
     */
    protected $customerSession;

    /**
     * ControllerActionPredispatchObserver constructor.
     * @param Session $customerSession
     */
    public function __construct(
        Session $customerSession
    ) {
        $this->customerSession = $customerSession;
    }

    /**
     * Handle controller_action_predispatch event
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $controllerAction = $observer->getEvent()->getControllerAction();
        $request = $controllerAction->getRequest();

        if ($request->getParams() && $request->getParam('email')) {
            $this->customerSession->setWebExtendCustomerEmail($request->getParam('email'));
        }

        if ($request->getPost() && $request->getPost('email')) {
            $this->customerSession->setWebExtendCustomerEmail($request->getPost('email'));
        }
    }
}
