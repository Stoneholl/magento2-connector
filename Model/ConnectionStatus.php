<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model;

abstract class ConnectionStatus
{
    public const UNPAIRED = 'unpaired';
    public const PAIRING_CONFIRMATION_PENDING = 'pairing_confirmation_pending';
    public const CONNECTED = 'connected';
    public const OFFLINE = 'offline';
    public const STALE = 'stale';
    public const CREDENTIAL_REJECTED = 'credential_rejected';
}
