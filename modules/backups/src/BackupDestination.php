<?php

/*
 * OpenSTAManager: il software gestionale open source per l'assistenza tecnica e la fatturazione
 * Copyright (C) DevCode s.r.l.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace Modules\Backups;

use Illuminate\Database\Eloquent\Model;
use Modules\FileAdapters\FileAdapter;

class BackupDestination extends Model
{
    public $timestamps = false;

    protected $table = 'zz_backup_destinations';

    protected $guarded = ['id'];

    public function adapter()
    {
        return $this->belongsTo(FileAdapter::class, 'id_adapter');
    }
}
