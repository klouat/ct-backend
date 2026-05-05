<?php

namespace Tests\Feature;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_can_create_invoice_and_deduplicate_boxes_from_manual_entries(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'username' => 'vendor01',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('data.access_token');
        $headers = ['Authorization' => 'Bearer '.$token];

        $invoiceResponse = $this->withHeaders($headers)->postJson('/api/invoices', [
            'invoice_code' => 'INV-2026-001',
            'po_number' => 'PO-2026-001',
            'target_box_count' => 2,
            'estimated_arrival_date' => now()->addDays(3)->toDateString(),
            'manual_entries' => [
                [
                    'box_id' => 'BOX-NEW',
                    'item_name' => 'Item A',
                    'quantity' => 12,
                    'vendor' => 'Default Vendor',
                ],
                [
                    'box_id' => 'BOX-NEW',
                    'item_name' => 'Item B',
                    'quantity' => 8,
                    'vendor' => 'Default Vendor',
                ],
                [
                    'box_id' => 'BOX-SECOND',
                    'item_name' => 'Item C',
                    'quantity' => 4,
                    'vendor' => 'Default Vendor',
                ],
            ],
        ]);

        $invoiceResponse
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.invoice_code', 'INV-2026-001')
            ->assertJsonPath('data.status', 'terverifikasi')
            ->assertJsonCount(2, 'data.boxes')
            ->assertJsonPath('data.boxes.0.qr_text', 'BOX:BOX-NEW');

        $createdInvoice = Invoice::where('po_number', 'PO-2026-001')->firstOrFail();

        $this->assertCount(2, $createdInvoice->boxes);
        $this->assertCount(2, $createdInvoice->boxes()->where('box_code', 'BOX-NEW')->firstOrFail()->items);
    }

    public function test_operator_can_count_and_view_box_verification_under_invoice_flow(): void
    {
        $this->seed();

        $vendorLogin = $this->postJson('/api/auth/login', [
            'username' => 'vendor01',
            'password' => 'password123',
        ]);

        $vendorHeaders = ['Authorization' => 'Bearer '.$vendorLogin->json('data.access_token')];
        $invoice = Invoice::where('po_number', 'PO-001')->firstOrFail();
        $boxId = 1;
        $packageId = 1;

        $this->withHeaders($vendorHeaders)->getJson("/api/invoices/{$invoice->invoice_id}/verification")
            ->assertOk()
            ->assertJsonPath('data.po_number', 'PO-001');

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

        $this->withHeaders($vendorHeaders)->getJson("/api/boxes/{$boxId}/verification")
            ->assertOk()
            ->assertJsonPath('data.box_code', 'BOX-001');
    }
}
