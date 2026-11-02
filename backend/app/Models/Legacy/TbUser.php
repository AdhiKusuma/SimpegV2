<?php

namespace App\Models\Legacy;

use Illuminate\Foundation\Auth\User as Authenticatable;

class TbUser extends Authenticatable
{
    protected $table = 'tb_user';

    protected $primaryKey = 'id_user';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];
}
