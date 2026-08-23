<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services;

use EstebanSmolak19\CrudServiceGenerator\Enums\BulkActions;

class BulkActionGuard {

    public function __construct(protected array $allowedActions = []) { }

    /**
     * Vérifie si une action bulk est autorisée, sinon lance une 404.
    */
    public function authorize(BulkActions $action): void
    {
        $isAllowed = in_array($action, $this->allowedActions, true);
        abort_if(!$isAllowed, 404, "Bulk action [{$action->value}] is disabled for this resource.");
    }
}