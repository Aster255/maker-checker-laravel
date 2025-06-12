<?php

namespace Aster255\MakerChecker\Facades;

use Illuminate\Support\Facades\Facade;
use Aster255\MakerChecker\MakerCheckerRequestManager;

/**
 * @method static \Aster255\MakerChecker\RequestBuilder request()
 * @method static void afterInitiating(\Closure $callback)
 * @method static void afterApproving(\Closure $callback)
 * @method static void afterRejecting(\Closure $callback)
 * @method static void onFailure(\Closure $callback)
 * @method static \Aster255\MakerChecker\Contracts\MakerCheckerRequestInterface approve(\Aster255\MakerChecker\Contracts\MakerCheckerRequestInterface $request, \Illuminate\Database\Eloquent\Model $approver, string|null $remarks)
 * @method static \Aster255\MakerChecker\Contracts\MakerCheckerRequestInterface reject(\Aster255\MakerChecker\Contracts\MakerCheckerRequestInterface $request, \Illuminate\Database\Eloquent\Model $rejector, string|null $remarks)
 *
 * @see \Aster255\MakerChecker\MakerCheckerRequestManager
 */
class MakerChecker extends Facade
{
    protected static function getFacadeAccessor()
    {
        return MakerCheckerRequestManager::class;
    }
}
