<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Tournament\Models\TournamentTemplate;

class DeleteTournamentTemplateAction
{
    /**
     * Delete (soft delete) a tournament template.
     */
    public function execute(TournamentTemplate $template): bool
    {
        return (bool) $template->delete();
    }
}
