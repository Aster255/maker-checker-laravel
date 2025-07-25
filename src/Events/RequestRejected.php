<?php

namespace Aster255\MakerChecker\Events;

use Aster255\MakerChecker\Models\MakerCheckerRequest;

class RequestRejected
{
    public MakerCheckerRequest $request;

    public function __construct(MakerCheckerRequest $request)
    {
        $this->request = $request;
    }
}
