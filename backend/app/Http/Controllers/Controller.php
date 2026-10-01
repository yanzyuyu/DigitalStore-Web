<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

/**
 * Base controller.
 *
 * AuthorizesRequests provides `$this->authorize(...)`, which is what enforces
 * per-record policies on top of the coarse role middleware.
 */
abstract class Controller
{
    use AuthorizesRequests;
    use ValidatesRequests;
}
