<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HardwareDevice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'connection_type',
        'status',
        'manufacturer',
        'model',
        'serial_number',
        'ip_address',
        'port',
        'enabled',
        'last_connected',
        'error',
        'operations_count',
        'error_count',
        'config',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'last_connected' => 'datetime',
        'operations_count' => 'integer',
        'error_count' => 'integer',
        'config' => 'array',
    ];

    // Device types
    public const TYPE_PRINTER = 'PRINTER';
    public const TYPE_SCANNER = 'SCANNER';
    public const TYPE_CASH_DRAWER = 'CASH_DRAWER';
    public const TYPE_PAYMENT_TERMINAL = 'PAYMENT_TERMINAL';
    public const TYPE_CUSTOMER_DISPLAY = 'CUSTOMER_DISPLAY';
    public const TYPE_WEIGHT_SCALE = 'WEIGHT_SCALE';

    // Connection types
    public const CONNECTION_USB = 'USB';
    public const CONNECTION_NETWORK = 'NETWORK';
    public const CONNECTION_BLUETOOTH = 'BLUETOOTH';
    public const CONNECTION_SERIAL = 'SERIAL';
    public const CONNECTION_KEYBOARD_WEDGE = 'KEYBOARD_WEDGE';

    // Status types
    public const STATUS_CONNECTED = 'CONNECTED';
    public const STATUS_DISCONNECTED = 'DISCONNECTED';
    public const STATUS_ERROR = 'ERROR';
    public const STATUS_CONNECTING = 'CONNECTING';

    /**
     * Get devices by type
     */
    public static function getByType(string $type)
    {
        return static::where('type', $type)->get();
    }

    /**
     * Get active devices
     */
    public static function getActive()
    {
        return static::where('enabled', true)->get();
    }

    /**
     * Get connected devices
     */
    public static function getConnected()
    {
        return static::where('status', self::STATUS_CONNECTED)->get();
    }

    /**
     * Increment operations count
     */
    public function incrementOperations(): void
    {
        $this->increment('operations_count');
    }

    /**
     * Increment error count
     */
    public function incrementErrors(): void
    {
        $this->increment('error_count');
    }

    /**
     * Mark as connected
     */
    public function markAsConnected(): void
    {
        $this->update([
            'status' => self::STATUS_CONNECTED,
            'last_connected' => now(),
            'error' => null,
        ]);
    }

    /**
     * Mark as disconnected
     */
    public function markAsDisconnected(string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_DISCONNECTED,
            'error' => $reason,
        ]);
    }

    /**
     * Mark as error
     */
    public function markAsError(string $error): void
    {
        $this->update([
            'status' => self::STATUS_ERROR,
            'error' => $error,
        ]);
        $this->incrementErrors();
    }
}
