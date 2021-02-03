<?php declare(strict_types=1);
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Registry;

class EmailSendState
{
    /**
     * @var bool
     */
    private $state;

    const STATE_YES = true;
    const STATE_NO = false;

    public function set(bool $state)
    {
        $this->state = $state;
    }

    public function get()
    {
        return $this->state ? self::STATE_YES : self::STATE_NO;
    }
}
