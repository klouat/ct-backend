<?php

namespace Tests\Unit;

use App\Models\Box;
use App\Models\BoxItem;
use App\Models\CountingResult;
use App\Models\Invoice;
use App\Models\Package;
use App\Support\VerificationService;
use Carbon\Carbon;
use Tests\TestCase;

class VerificationServiceTest extends TestCase
{
    public function test_invoice_summary_computes_over_status(): void
    {
        $firstPackage = new Package([
            'package_code' => 'PKG-001',
            'qty' => 5,
        ]);
        $firstPackage->setAttribute('package_id', 10);
        $firstPackage->setRelation('countingResults', collect([
            new CountingResult([
                'counted_qty' => 6,
                'counted_time' => Carbon::parse('2026-04-28 10:00:00'),
            ]),
        ]));

        $secondPackage = new Package([
            'package_code' => 'PKG-002',
            'qty' => 2,
        ]);
        $secondPackage->setAttribute('package_id', 11);
        $secondPackage->setRelation('countingResults', collect([
            new CountingResult([
                'counted_qty' => 4,
                'counted_time' => Carbon::parse('2026-04-28 09:00:00'),
            ]),
        ]));

        $box = new Box([
            'box_code' => 'BOX-001',
            'qr_text' => 'BOX:BOX-001',
        ]);
        $box->setAttribute('box_id', 5);
        $box->setRelation('items', collect([
            new BoxItem([
                'item_name' => 'Item A',
                'quantity' => 5,
            ]),
            new BoxItem([
                'item_name' => 'Item B',
                'quantity' => 2,
            ]),
        ]));
        $box->setRelation('packages', collect([$firstPackage, $secondPackage]));

        $invoice = new Invoice([
            'invoice_code' => 'INV-PO-001',
            'po_number' => 'PO-001',
            'status' => 'terverifikasi',
            'target_box_count' => 1,
        ]);
        $invoice->setAttribute('invoice_id', 1);
        $invoice->setRelation('boxes', collect([$box]));

        $summary = VerificationService::invoice($invoice);

        $this->assertSame([
            'invoice_id' => 1,
            'invoice_code' => 'INV-PO-001',
            'po_number' => 'PO-001',
            'status' => 'terverifikasi',
            'target_box_count' => 1,
            'total_boxes' => 1,
            'total_items' => 2,
            'total_packages' => 2,
            'total_qty' => 7,
            'total_counted' => 10,
            'status' => 'OVER',
        ], $summary);
    }
}
