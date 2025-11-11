<?php

namespace App\Exceptions;

use Exception;

class GoogleAccountDisconnectedException extends Exception
{
    // This custom exception catches authentication failures
    // and show a friendly error message to the user instead of a generic 500 error.
}
