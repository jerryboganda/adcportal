<?php

namespace App\Services\Dictation;

use RuntimeException;

/**
 * A dictation attempt the radiologist needs told about, in plain language.
 *
 * Its message is deliberately safe to show in the editor: it never contains
 * audio, a transcript, or the service's raw response body.
 */
class TranscriptionException extends RuntimeException
{
}
