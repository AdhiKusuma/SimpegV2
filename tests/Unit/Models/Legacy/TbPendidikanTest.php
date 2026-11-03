<?php

namespace Tests\Unit\Models\Legacy;

use App\Models\Legacy\TbPendidikan;
use Tests\TestCase;

class TbPendidikanTest extends TestCase
{
    public function test_maps_the_legacy_education_table_primary_key_and_casts(): void
    {
        $model = new TbPendidikan;

        $this->assertSame('tb_pendidikan', $model->getTable());
        $this->assertSame('id_pendidikan', $model->getKeyName());
        $this->assertTrue($model->getIncrementing());
        $this->assertSame('int', $model->getKeyType());
        $this->assertFalse($model->usesTimestamps());
        $this->assertSame('date', $model->getCasts()['tgl_ijazah']);
    }
}
