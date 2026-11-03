<?php

namespace Tests\Unit\Models\Legacy;

use App\Models\Legacy\TbRecycleBin;
use Tests\TestCase;

class TbRecycleBinTest extends TestCase
{
    public function test_maps_the_legacy_recycle_bin_key_and_created_cast(): void
    {
        $model = new TbRecycleBin;

        $this->assertSame('tb_recycle_bin', $model->getTable());
        $this->assertSame('id_trash', $model->getKeyName());
        $this->assertSame('datetime', $model->getCasts()['created']);
        $this->assertFalse($model->usesTimestamps());
    }
}
