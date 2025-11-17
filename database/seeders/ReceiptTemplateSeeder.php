<?php

namespace Database\Seeders;

use App\Models\ReceiptTemplate;
use Illuminate\Database\Seeder;

class ReceiptTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default receipt template
        ReceiptTemplate::create([
            'name' => 'Standard Receipt',
            'description' => 'Default receipt template with all standard sections',
            'paper_width' => 80,
            'is_default' => true,
            'sections' => [
                'header' => [
                    'enabled' => true,
                    'logo' => [
                        'enabled' => false,
                        'imageData' => null,
                        'width' => 200,
                        'height' => 80,
                        'alignment' => 'center',
                    ],
                    'businessName' => [
                        'enabled' => true,
                        'fontSize' => 'large',
                        'bold' => true,
                    ],
                    'address' => [
                        'enabled' => true,
                    ],
                    'contact' => [
                        'enabled' => true,
                        'showPhone' => true,
                        'showEmail' => true,
                    ],
                    'alignment' => 'center',
                ],
                'items' => [
                    'enabled' => true,
                    'showSKU' => false,
                    'showQuantity' => true,
                    'showUnitPrice' => true,
                    'showItemTotal' => true,
                    'showDiscount' => true,
                ],
                'totals' => [
                    'enabled' => true,
                    'showSubtotal' => true,
                    'showDiscount' => true,
                    'showTax' => true,
                    'showTotal' => true,
                    'showPaid' => true,
                    'showChange' => true,
                    'boldTotal' => true,
                ],
                'footer' => [
                    'enabled' => true,
                    'showTransactionId' => true,
                    'showCashier' => true,
                    'showDateTime' => true,
                    'thankYouMessage' => 'Thank you for your business!',
                ],
            ],
            'styles' => [
                'font' => 'monospace',
                'borderStyle' => 'single',
                'sectionSpacing' => 1,
            ],
        ]);

        // Create minimal receipt template
        ReceiptTemplate::create([
            'name' => 'Minimal Receipt',
            'description' => 'Compact receipt with essential information only',
            'paper_width' => 58,
            'is_default' => false,
            'sections' => [
                'header' => [
                    'enabled' => true,
                    'logo' => [
                        'enabled' => false,
                        'imageData' => null,
                        'width' => 200,
                        'height' => 80,
                        'alignment' => 'center',
                    ],
                    'businessName' => [
                        'enabled' => true,
                        'fontSize' => 'medium',
                        'bold' => true,
                    ],
                    'address' => [
                        'enabled' => false,
                    ],
                    'contact' => [
                        'enabled' => false,
                        'showPhone' => false,
                        'showEmail' => false,
                    ],
                    'alignment' => 'center',
                ],
                'items' => [
                    'enabled' => true,
                    'showSKU' => false,
                    'showQuantity' => true,
                    'showUnitPrice' => false,
                    'showItemTotal' => true,
                    'showDiscount' => false,
                ],
                'totals' => [
                    'enabled' => true,
                    'showSubtotal' => false,
                    'showDiscount' => false,
                    'showTax' => true,
                    'showTotal' => true,
                    'showPaid' => false,
                    'showChange' => false,
                    'boldTotal' => true,
                ],
                'footer' => [
                    'enabled' => true,
                    'showTransactionId' => true,
                    'showCashier' => false,
                    'showDateTime' => true,
                    'thankYouMessage' => 'Thank you!',
                ],
            ],
            'styles' => [
                'font' => 'monospace',
                'borderStyle' => 'none',
                'sectionSpacing' => 1,
            ],
        ]);

        // Create detailed receipt template
        ReceiptTemplate::create([
            'name' => 'Detailed Receipt',
            'description' => 'Comprehensive receipt with all available information',
            'paper_width' => 80,
            'is_default' => false,
            'sections' => [
                'header' => [
                    'enabled' => true,
                    'logo' => [
                        'enabled' => false,
                        'imageData' => null,
                        'width' => 200,
                        'height' => 80,
                        'alignment' => 'center',
                    ],
                    'businessName' => [
                        'enabled' => true,
                        'fontSize' => 'xlarge',
                        'bold' => true,
                    ],
                    'address' => [
                        'enabled' => true,
                    ],
                    'contact' => [
                        'enabled' => true,
                        'showPhone' => true,
                        'showEmail' => true,
                    ],
                    'alignment' => 'center',
                ],
                'items' => [
                    'enabled' => true,
                    'showSKU' => true,
                    'showQuantity' => true,
                    'showUnitPrice' => true,
                    'showItemTotal' => true,
                    'showDiscount' => true,
                ],
                'totals' => [
                    'enabled' => true,
                    'showSubtotal' => true,
                    'showDiscount' => true,
                    'showTax' => true,
                    'showTotal' => true,
                    'showPaid' => true,
                    'showChange' => true,
                    'boldTotal' => true,
                ],
                'footer' => [
                    'enabled' => true,
                    'showTransactionId' => true,
                    'showCashier' => true,
                    'showDateTime' => true,
                    'thankYouMessage' => 'Thank you for shopping with us! We appreciate your business and hope to see you again soon.',
                ],
            ],
            'styles' => [
                'font' => 'monospace',
                'borderStyle' => 'double',
                'sectionSpacing' => 2,
            ],
        ]);
    }
}
