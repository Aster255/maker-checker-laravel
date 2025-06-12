<?php

namespace Aster255\MakerChecker\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Aster255\MakerChecker\Traits\ChecksRequests;
use Aster255\MakerChecker\Traits\MakesRequests;

class User extends Model
{
    use MakesRequests, ChecksRequests;

    protected $guarded = [];
}
