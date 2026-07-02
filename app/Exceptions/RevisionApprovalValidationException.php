<?php

namespace App\Exceptions;

use App\Services\Revision\RevisionValidationResult;
use Exception;

class RevisionApprovalValidationException extends Exception
{
    public function __construct(private RevisionValidationResult $validationResult)
    {
        parent::__construct('修訂驗證未通過，無法接受');
    }

    public function validationResult(): RevisionValidationResult
    {
        return $this->validationResult;
    }
}
