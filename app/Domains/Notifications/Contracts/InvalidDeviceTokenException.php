<?php

namespace App\Domains\Notifications\Contracts;

use RuntimeException;

/**
 * The provider says this token no longer names a device — the app was
 * uninstalled, or the token was rotated. The caller deactivates the device
 * rather than trying it again tomorrow.
 */
class InvalidDeviceTokenException extends RuntimeException {}
