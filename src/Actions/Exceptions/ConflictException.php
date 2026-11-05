<?php

declare(strict_types=1);

namespace Codeberry\Beads\Actions\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A text field changed in bd since the client loaded it.
 */
final class ConflictException extends RuntimeException
{
    public function __construct(public readonly string $field, public readonly string $theirs)
    {
        parent::__construct("The {$field} was changed elsewhere since you started editing.");
    }

    public function render(): JsonResponse
    {
        return new JsonResponse([
            'error' => ['code' => 'conflict', 'message' => $this->getMessage()],
            'field' => $this->field,
            'theirs' => $this->theirs,
        ], 409);
    }
}
