<?php

namespace Dzava\GlobalSearch\Tests\Fixtures;

class DenyPolicy
{
    public function search($user)
    {
        return false;
    }
}
