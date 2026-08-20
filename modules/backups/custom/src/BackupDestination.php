<?php

namespace Modules\Backups;

use Illuminate\Database\Eloquent\Model;
use Modules\FileAdapters\FileAdapter;

class BackupDestination extends Model
{
    public $timestamps = false;

    protected $table = 'zz_backup_destinations';

    protected $guarded = ['id'];

    protected $casts = [
        'enabled' => 'boolean',
        'retention' => 'integer',
        'last_attempt_at' => 'datetime',
        'last_success_at' => 'datetime',
    ];

    public function adapter()
    {
        return $this->belongsTo(FileAdapter::class, 'id_adapter');
    }
}
