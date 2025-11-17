<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReceiptTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'paper_width',
        'is_default',
        'sections',
        'styles',
    ];

    protected $casts = [
        'paper_width' => 'integer',
        'is_default' => 'boolean',
        'sections' => 'array',
        'styles' => 'array',
    ];

    /**
     * Boot method to handle default template logic
     */
    protected static function boot()
    {
        parent::boot();

        // When setting a template as default, unset all others
        static::saving(function ($template) {
            if ($template->is_default) {
                static::where('id', '!=', $template->id)
                    ->update(['is_default' => false]);
            }
        });
    }

    /**
     * Get the default template
     */
    public static function getDefault()
    {
        return static::where('is_default', true)->first();
    }

    /**
     * Scope to get only default templates
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Get default sections structure
     */
    public static function getDefaultSections(): array
    {
        return [
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
        ];
    }

    /**
     * Get default styles structure
     */
    public static function getDefaultStyles(): array
    {
        return [
            'font' => 'monospace',
            'borderStyle' => 'single',
            'sectionSpacing' => 1,
        ];
    }
}
