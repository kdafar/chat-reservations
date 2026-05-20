<?php

namespace App\Exceptions;

use Filament\Support\Exceptions\Halt;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    protected $dontReport = [
        Halt::class,
    ];
}
