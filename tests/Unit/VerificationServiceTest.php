<?php

namespace Tests\Unit;

use App\Models\Box;
use App\Models\CountingResult;
use App\Models\Package;
use App\Models\Shipment;
use App\Support\VerificationService;
use Carbon\Carbon;
use Tests\TestCase;

class VerificationServiceTest extends TestCase
{
    public function test_shipment_summary_computes_over_status(): void
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
        ]);
        $box->setAttribute('box_id', 5);
        $box->setRelation('packages', collect([$firstPackage, $secondPackage]));

        $shipment = new Shipment([
            'shipment_code' => 'SHP-001',
        ]);
        $shipment->setAttribute('shipment_id', 1);
        $shipment->setRelation('boxes', collect([$box]));

        $summary = VerificationService::shipment($shipment);

        $this->assertSame([
            'shipment_id' => 1,
            'shipment_code' => 'SHP-001',
            'total_boxes' => 1,
            'total_packages' => 2,
            'total_qty' => 7,
            'total_counted' => 10,
            'status' => 'OVER',
        ], $summary);
    }
}
