<?php

namespace Aster255\MakerChecker\Traits;

use Aster255\MakerChecker\Contracts\MakerCheckerRequestInterface;
use Aster255\MakerChecker\Facades\MakerChecker;

trait ChecksRequests
{
    public function approve(MakerCheckerRequestInterface $request, ?string $remarks = null): MakerCheckerRequestInterface
    {
        return MakerChecker::approve($request, $this, $remarks);
    }

    public function reject(MakerCheckerRequestInterface $request, ?string $remarks = null): MakerCheckerRequestInterface
    {
        return MakerChecker::reject($request, $this, $remarks);
    }
}
