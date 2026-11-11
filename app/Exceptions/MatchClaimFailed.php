<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown inside the match transaction when another request claimed one of the
 * two guests first. It rolls the transaction back; ChatService then tries the
 * next candidate.
 */
class MatchClaimFailed extends RuntimeException
{
}
