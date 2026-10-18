<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Setting extends Model
{
    protected $fillable = [
        'dairy_name',
        'phone',
        'address',
        'currency',
        'invoice_region',
        'ollama_api_key',
    ];

    /**
     * Never include the API key when the model is turned into an array/JSON
     * (for example by @json($settings) or toArray()). Reading
     * $setting->ollama_api_key in PHP code still works.
     */
    protected $hidden = [
        'ollama_api_key',
    ];

    protected function casts(): array
    {
        return [
            'ollama_api_key' => 'encrypted',
        ];
    }

    /**
     * Maps the friendly "Invoice Timing / Region" label chosen in Settings
     * to a real IANA timezone identifier so receipts and the POS clock can
     * show the correct local time no matter what the server's own clock is
     * set to.
     */
    public const REGION_TIMEZONES = [
        'Pakistan (PKT)'              => 'Asia/Karachi',
        'USA - Eastern (EST)'         => 'America/New_York',
        'USA - Pacific (PST)'         => 'America/Los_Angeles',
        'United Kingdom (GMT)'        => 'Europe/London',
        'United Arab Emirates (GST)'  => 'Asia/Dubai',
        'India (IST)'                 => 'Asia/Kolkata',
    ];

    /**
     * The one settings row. The migration creates it, so this normally just
     * reads it. The create() below is only a safety net.
     */
    public static function current(): self
    {
        return static::query()->orderBy('id')->first() ?? static::create([
            'dairy_name' => 'DairyPro',
            'currency' => 'Rs.',
            'invoice_region' => 'Pakistan (PKT)',
        ]);
    }

    /** IANA timezone identifier for this business, e.g. "Asia/Karachi". */
    public function timezone(): string
    {
        return self::REGION_TIMEZONES[$this->invoice_region] ?? 'Asia/Karachi';
    }

    /** Convert a stored (server-timezone) timestamp to this business's local time for display. */
    public function localize(?Carbon $dateTime): ?Carbon
    {
        return $dateTime?->copy()->setTimezone($this->timezone());
    }

    /** "Right now" in this business's configured timezone. */
    public function now(): Carbon
    {
        return Carbon::now($this->timezone());
    }

    /** Convert a business-local day to the timezone used by database timestamps. */
    public function businessDayBounds(?Carbon $date = null): array
    {
        $businessDate = ($date ?: $this->now())->copy()->setTimezone($this->timezone());
        $storageTimezone = config('app.timezone');

        return [
            $businessDate->copy()->startOfDay()->setTimezone($storageTimezone),
            $businessDate->copy()->endOfDay()->setTimezone($storageTimezone),
        ];
    }

    /** Convert a business-local month to the timezone used by database timestamps. */
    public function businessMonthBounds(?Carbon $date = null): array
    {
        $businessDate = ($date ?: $this->now())->copy()->setTimezone($this->timezone());
        $storageTimezone = config('app.timezone');

        return [
            $businessDate->copy()->startOfMonth()->startOfDay()->setTimezone($storageTimezone),
            $businessDate->copy()->endOfMonth()->endOfDay()->setTimezone($storageTimezone),
        ];
    }
}
