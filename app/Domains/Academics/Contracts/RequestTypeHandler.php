<?php

namespace App\Domains\Academics\Contracts;

use App\Domains\Academics\Models\SchoolRequest;

interface RequestTypeHandler
{
    public function onApproved(SchoolRequest $request): void;
}
