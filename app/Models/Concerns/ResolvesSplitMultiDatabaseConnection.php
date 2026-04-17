<?php

namespace App\Models\Concerns;

use App\Support\SplitMultiModelConnections;
use Illuminate\Database\Eloquent\Model;

trait ResolvesSplitMultiDatabaseConnection
{
    /**
     * When DB_TOPOLOGY=multi, use the connection for this model’s table from the split map.
     * Tables on auth_db use the default `mysql` connection (multi entry database).
     */
    public function getConnectionName(): ?string
    {
        if (config('database.split_multi.topology') !== 'multi') {
            /** @var Model $this */
            return parent::getConnectionName();
        }

        $conn = SplitMultiModelConnections::connectionForTable($this->getTable());
        if ($conn === null) {
            return parent::getConnectionName();
        }

        return $conn === 'auth_db' ? 'mysql' : $conn;
    }
}
