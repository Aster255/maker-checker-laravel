<?php

namespace Aster255\MakerChecker\Events;

use Aster255\MakerChecker\Models\MakerCheckerRequest;
use Throwable;

class RequestFailed
{
    public MakerCheckerRequest $request;

    public Throwable $exception;

    public function __construct(MakerCheckerRequest $request, Throwable $exception)
    {
        $this->request = $request;
        $this->exception = $exception;
    }
}
