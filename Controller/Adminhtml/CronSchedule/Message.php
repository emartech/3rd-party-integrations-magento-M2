<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\CronSchedule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Cron\Model\ScheduleFactory;

class Message extends Action
{
    /**
     * @var ScheduleFactory
     */
    protected $scheduleFactory;

    /**
     * Message constructor.
     *
     * @param Context $context
     * @param ScheduleFactory $scheduleFactory
     */
    public function __construct(
        Context $context,
        ScheduleFactory $scheduleFactory
    ) {
        $this->scheduleFactory = $scheduleFactory;
        parent::__construct($context);
    }

    /**
     * @return void|$this
     * @throws \RuntimeException
     */
    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        if ($id) {
            $cronDetails = $this->scheduleFactory->create()->load($id);
            if ($cronDetails) {
                printf('<pre>' . $cronDetails->getMessages() . '</pre>');
            } else {
                printf('No Data Available');
            }
        } else {
            printf('No Data Available');
        }
    }
}
