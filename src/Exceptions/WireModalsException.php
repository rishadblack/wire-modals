<?php

namespace Rishadblack\WireModals\Exceptions;

use RuntimeException;

/**
 * Base class for every refusal the package raises. The host and the trait catch it,
 * log the message and dispatch "wire-modals:rejected" instead of failing the request.
 */
abstract class WireModalsException extends RuntimeException {}
