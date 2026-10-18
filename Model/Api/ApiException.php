<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model\Api;

class ApiException extends \RuntimeException
{
    /** @var string */
    private $apiCode;

    /** @var int|null */
    private $httpStatus;

    /**
     * Retain safe local diagnostics and the optional remote HTTP status.
     *
     * @param string $apiCode
     * @param string $message
     * @param int|null $httpStatus
     */
    public function __construct(string $apiCode, string $message, ?int $httpStatus = null)
    {
        parent::__construct($message);
        $this->apiCode = $apiCode;
        $this->httpStatus = $httpStatus;
    }

    /**
     * Expose the safe machine-readable error code used by local state transitions.
     *
     * @return string
     */
    public function getApiCode(): string
    {
        return $this->apiCode;
    }

    /**
     * Expose the response status when a remote response was received.
     *
     * @return int|null
     */
    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    /**
     * Distinguish authentication rejection from connectivity and response-validation failures.
     *
     * @return bool
     */
    public function isCredentialRejected(): bool
    {
        return $this->httpStatus === 401;
    }
}
