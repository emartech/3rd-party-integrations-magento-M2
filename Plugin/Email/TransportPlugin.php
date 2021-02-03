<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Plugin\Email;

use Magento\Framework\Mail\TransportInterface;
use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Emarsys\Emarsys\Registry\EmailSendState;

/**
 * SMTP mail transport.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class TransportPlugin
{
    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * @var EmailSendState
     */
    protected $state;

    /**
     * TransportPlugin constructor.
     *
     * @param EmarsysHelper $emarsysHelper
     * @param EmailSendState $state
     */
    public function __construct(
        EmarsysHelper $emarsysHelper,
        EmailSendState $state
    ) {
        $this->emarsysHelper = $emarsysHelper;
        $this->state = $state;
    }

    /**
     * @param TransportInterface $subject
     * @param callable $proceed
     * @throws \Exception
     *
     * @return null
     */
    public function aroundSendMessage(
        TransportInterface $subject,
        callable $proceed
    ) {
        if (!$this->emarsysHelper->isEmarsysEnabled() || !$this->state->get()) {
            return $proceed();
        }

        return true;
    }
}
