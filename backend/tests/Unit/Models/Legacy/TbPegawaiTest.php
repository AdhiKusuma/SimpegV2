<?php

namespace Tests\Unit\Models\Legacy;

use App\Models\Legacy\TbPegawai;
use Tests\TestCase;

class TbPegawaiTest extends TestCase
{
    public function test_maps_the_legacy_employee_table_and_string_primary_key(): void
    {
        $model = new TbPegawai;

        $this->assertSame('tb_pegawai', $model->getTable());
        $this->assertSame('id_peg', $model->getKeyName());
        $this->assertFalse($model->getIncrementing());
        $this->assertSame('string', $model->getKeyType());
        $this->assertFalse($model->usesTimestamps());
    }
}
