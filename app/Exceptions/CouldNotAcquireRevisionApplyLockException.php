<?php

namespace App\Exceptions;

use Exception;

class CouldNotAcquireRevisionApplyLockException extends Exception
{
    public function __construct()
    {
        parent::__construct('目前有其他修訂正在套用，請稍後再試');
    }
}
