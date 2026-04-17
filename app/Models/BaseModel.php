<?php

namespace App\Models;

use App\Models\Concerns\ResolvesSplitMultiDatabaseConnection;
use Illuminate\Database\Eloquent\Model as EloquentModel;

abstract class BaseModel extends EloquentModel
{
    use ResolvesSplitMultiDatabaseConnection;
}
