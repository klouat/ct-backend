<?php

namespace Tests\Feature;

use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_can_create_shipment_box_package_and_generate_qr(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'username' => 'vendor01',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('data.access_token');
        $headers = ['Authorization' => 'Bearer '.$token];

        $shipmentResponse = $this->withHeaders($headers)->postJson('/api/shipments', [
            'shipment_code' => 'SHIP-NEW',
            'vendor_id' => 1,
            'shipment_date' => now()->toDateString(),
            'status' => 'PENDING',
        ]);

        $shipmentResponse->assertCreated();

        $shipmentId = $shipmentResponse->json('data.shipment_id');

        $boxResponse = $this->withHeaders($headers)->postJson('/api/boxes', [
            'box_code' => 'BOX-NEW',
            'shipment_id' => $shipmentId,
        ]);

        $boxResponse->assertCreated();
        $boxId = $boxResponse->json('data.box_id');

        $packageResponse = $this->withHeaders($headers)->postJson('/api/packages', [
            'package_code' => 'PKG-NEW',
            'box_id' => $boxId,
            'qty' => 12,
        ]);

        $packageResponse->assertCreated();
        $packageId = $packageResponse->json('data.package_id');

        $qrResponse = $this->withHeaders($headers)->postJson("/api/packages/{$packageId}/generate-qr");

        $qrResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.qr_text', 'SHIP:SHIP-NEW|BOX:BOX-NEW|PKG:PKG-NEW');
    }

    public function test_operator_can_count_and_view_shipment_verification(): void
    {
        $this->seed();

        $vendorLogin = $this->postJson('/api/auth/login', [
            'username' => 'vendor01',
            'password' => 'password123',
        ]);

        $vendorHeaders = ['Authorization' => 'Bearer '.$vendorLogin->json('data.access_token')];
        $shipment = Shipment::where('shipment_code', 'SHIP-001')->firstOrFail();
        $packageId = 1;

        $this->withHeaders($vendorHeaders)->getJson("/api/shipments/{$shipment->shipment_id}/verification")
            ->assertOk();

        $operatorLogin = $this->postJson('/api/auth/login', [
            'username' => 'operator01',
            'password' => 'password123',
        ]);

        $operatorHeaders = ['Authorization' => 'Bearer '.$operatorLogin->json('data.access_token')];

        $countResponse = $this->withHeaders($operatorHeaders)->postJson("/api/packages/{$packageId}/count", [
            'counted_qty' => 10,
        ]);

        $countResponse
            ->assertCreated()
            ->assertJsonPath('data.verification.status', 'MATCH');
    }
}
