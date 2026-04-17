<?php

namespace App\Models\Concerns;

use App\Support\SplitMultiModelConnections;

trait ResolvesSplitMultiDatabaseConnection
{
    /**
     * Use the connection for this model’s table from the split map.
     * Tables on auth_db use the default `mysql` connection (entry database).
     */
    public function getConnectionName(): ?string
    {
        $conn = SplitMultiModelConnections::connectionForTable($this->getTable());
        if ($conn === null) {
            return parent::getConnectionName();
        }

        return $conn === 'auth_db' ? 'mysql' : $conn;
    }
}
