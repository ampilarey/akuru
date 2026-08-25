<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Contracts\RequestTypeHandler;
use App\Domains\Academics\Enums\SchoolRequestType;
use App\Domains\Academics\Models\SchoolRequest;

class RequestHandlerRegistry
{
    /**
     * @var array<string, class-string<RequestTypeHandler>>
     */
    private array $handlers = [
        'teacher_leave' => HandleTeacherLeaveApprovalAction::class,
    ];

    public function handlerFor(SchoolRequest $request): ?RequestTypeHandler
    {
        $type = $request->type instanceof SchoolRequestType ? $request->type->value : (string) $request->type;
        $class = $this->handlers[$type] ?? null;

        return $class ? app($class) : null;
    }
}
