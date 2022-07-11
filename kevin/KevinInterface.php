<?php

namespace Kevin\VirtueMart;

/**
 * Interface to provide kevin. constants.
 *
 * @since 1.0.0
 */
interface KevinInterface
{
    const VERSION = '1.1.2'; // kevin. VirtueMart plugin version

    const KEVIN_PAYMENT_STATUS_STARTED = 'started';
    const KEVIN_PAYMENT_STATUS_PENDING = 'pending';
    const KEVIN_PAYMENT_STATUS_COMPLETED = 'completed';
    const KEVIN_PAYMENT_STATUS_FAILED = 'failed';

    const ALERT_SUCCESS = 'success';
    const ALERT_INFO = 'info';
    const ALERT_ERROR = 'error';

    const SIGNATURE_TIMESTAMP_TIMEOUT = 300000;
}
