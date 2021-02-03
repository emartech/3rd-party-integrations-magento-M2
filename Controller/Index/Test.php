<?php

namespace Emarsys\Emarsys\Controller\Index;

use Magento\Framework\App\Action\Context;

use Magento\Framework\App\Action\Action;
use Emarsys\Emarsys\Helper\Event;

class Test extends Action
{
    /**
     * Test constructor.
     *
     * @param Context $context
     * @param Event $eventHelper
     */
    public function __construct(
        Context $context,
        Event $eventHelper
    ) {
        parent::__construct($context);
        $this->eventHelper = $eventHelper;
    }

    /**
     * Test Action
     */
    public function execute()
    {
        $emarsysApiIds = [];
        $eventSchema = $this->eventHelper->getEventSchema();
        foreach ($eventSchema['data'] as $_eventSchema) {
            $emarsysApiIds[] = $_eventSchema['id'];
        }
        $emarsysLocalIds = $this->eventHelper->getLocalEmarsysEvents();
        $result = array_diff($emarsysApiIds, $emarsysLocalIds);
        if (!empty($result)) {
            $this->eventHelper->saveEmarsysEventSchemaNotification();
        }
    }
}
