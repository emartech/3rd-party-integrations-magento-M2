<?php

namespace Emarsys\Emarsys\Controller\NewsletterSubscription;

class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;

    /**
     * Index constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }

    /**
     * Default customer account page
     *
     * @return void
     */
    public function execute()
    {
        /*
            External link for click to confirm Email subscription
            <h1>Newsletter Subscription</h1>
            <a href="http://192.168.10.131/emarsys1/emarsys/newslettersubscription/index"
            target="_blank">Click Here </a> to confirm your subscription.

            Email Link should have below fields in URL

            1. Customer Id / Email
            2. StoreId
            3. Campaign Id
            4. external event id to trigger

       */
    }
}
