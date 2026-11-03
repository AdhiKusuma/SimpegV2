<?php

namespace Tests\Unit;

use App\Models\Legacy\TbEditPending;
use PHPUnit\Framework\TestCase;

class TbEditPendingStatusTest extends TestCase
{
    public function test_status_labels_map_to_legacy_enum_values_and_back(): void
    {
        $pending = new TbEditPending;
        $pending->status_otorisasi = 'Menunggu';
        $this->assertSame('Menunggu', $pending->getAttributes()['status_otorisasi']);
        $this->assertSame('Menunggu', $pending->status_otorisasi);

        $pending->status_otorisasi = 'Disetujui';
        $this->assertSame('Disetujui', $pending->getAttributes()['status_otorisasi']);
        $this->assertSame('Disetujui', $pending->status_otorisasi);

        $pending->status_otorisasi = 'Ditolak';
        $this->assertSame('Ditolak', $pending->getAttributes()['status_otorisasi']);
        $this->assertSame('Ditolak', $pending->status_otorisasi);
    }

    public function test_empty_legacy_status_is_presented_as_waiting(): void
    {
        $pending = new TbEditPending;
        $pending->setRawAttributes(['status_otorisasi' => '']);

        $this->assertSame('Menunggu', $pending->status_otorisasi);
    }
}
