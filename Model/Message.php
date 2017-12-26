<?php
/**
 * Mail Message
 *
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Emarsys\Emarsys\Model;

class Message extends \Magento\Framework\Mail\Message
{
    protected $emarsysData = [];

    public function setEmarsysData($emarsysData)
    {
        return $this->emarsysData = $emarsysData;
    }

    public function getEmarsysData()
    {
        return $this->emarsysData;
    }
}
