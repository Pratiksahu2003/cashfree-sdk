<?php

namespace CashfreePayment\Exceptions;

use Exception;

class CashfreeException extends Exception
{
    /**
     * Raw error response payload from Cashfree.
     */
    protected ?array $errorResponse = null;

    /**
     * CashfreeException constructor.
     */
    public function __construct(string $message, int $code = 0, ?array $errorResponse = null, ?Exception $previous = null)
    {
        $this->errorResponse = $errorResponse;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the raw error response array.
     */
    public function getErrorResponse(): ?array
    {
        return $this->errorResponse;
    }
}
