<?php
/**
 * Thrown whenever an AI provider request cannot be completed.
 *
 * @package CF7AIC\AI
 */

namespace CF7AIC\AI;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProviderException
 *
 * Signals a recoverable AI provider failure — a network error, an
 * authentication failure, a rate limit, or an unexpected response shape.
 * Callers (the CF7 submission handler, in a later phase) are expected to
 * catch this, log it, and continue Contact Form 7's normal behavior
 * rather than let it propagate.
 *
 * Messages on this exception are written to be safe to display to an
 * administrator (e.g. in a log or admin notice) — never include the API
 * key or raw request body in a message.
 */
final class ProviderException extends \RuntimeException {

}
