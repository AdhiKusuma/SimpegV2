<?php

namespace Tests\Unit\Models\Legacy;

use App\Models\Legacy\TbDiklat;
use Tests\TestCase;

class TbDiklatTest extends TestCase
{
    public function test_maps_the_legacy_training_table_and_numeric_cost_cast(): void
    {
        $model = new TbDiklat;

        $this->assertSame('tb_diklat', $model->getTable());
        $this->assertSame('id_diklat', $model->getKeyName());
        $this->assertSame('integer', $model->getCasts()['biaya']);
        $this->assertFalse($model->usesTimestamps());
    }
}
