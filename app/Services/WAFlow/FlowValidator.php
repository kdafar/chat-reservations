<?php

namespace App\Services\WAFlow;

use Illuminate\Support\Arr;

class FlowValidator
{
    private const DATE_RE = '/^\d{4}-\d{2}-\d{2}$/';

    private const TIME_RE = '/^\d{2}:\d{2}$/'; // 24h HH:MM

    private const B64_RE = '/^[A-Za-z0-9+\/=]+$/';

    private array $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    public function __construct(private FlowAssets $assets) {}

    /** Validate by screen name */
    public function assert(string $screen, array $data): FlowValidationResult
    {
        return match ($screen) {
            'APPOINTMENT' => $this->validateAppointment($data),
            'DETAILS' => $this->validateDetails($data),
            'SUMMARY' => $this->validateSummary($data),
            'CONFIRMATION' => new FlowValidationResult(true, [], $data), // usually simple
            default => new FlowValidationResult(true, [], $data),
        };
    }

    public function safeAppointmentPayload(): array
    {
        return [
            'party_size' => [
                ['id' => '2', 'title' => '2 people'],
                ['id' => '4', 'title' => '4 people'],
                ['id' => '6', 'title' => '6 people'],
            ],
            'party_size_prefill' => '',
            'is_date_enabled' => true,
            'min_date' => now(config('app.timezone', 'Asia/Kuwait'))->toDateString(),
            'max_date' => now(config('app.timezone', 'Asia/Kuwait'))->addDays(60)->toDateString(),
            'unavailable_dates' => [],
            'include_days' => $this->days,
            'time' => [],
            'is_time_enabled' => true,
            'show_time' => false,
            'confirmation_message' => '',
        ];
    }

    private function validateAppointment(array $d): FlowValidationResult
    {
        $errors = [];
        $norm = $d;

        // --- party_size
        $ps = Arr::get($d, 'party_size');
        if (! is_array($ps)) {
            $errors[] = 'data.party_size must be an array of {id,title[,image]}';
            $ps = [];
        }
        $norm['party_size'] = array_values(array_map(function ($row) use (&$errors) {
            $id = isset($row['id']) ? (string) $row['id'] : '';
            $title = isset($row['title']) ? (string) $row['title'] : '';
            $img = isset($row['image']) ? (string) $row['image'] : '';
            if ($img !== '') {
                if (str_starts_with($img, 'data:image')) {
                    $errors[] = 'party_size.image must be RAW base64, not data URL';
                    $img = explode(',', $img, 2)[1] ?? '';
                }
                if ($img !== '' && ! preg_match(self::B64_RE, $img)) {
                    $errors[] = 'party_size.image must be base64 (A–Z/a–z/0–9+/=)';
                    $img = $this->assets->tinyPixel();
                }
            }

            return ['id' => $id, 'title' => $title] + ($img !== '' ? ['image' => $img] : []);
        }, $ps));

        // --- booleans
        foreach (['is_date_enabled', 'is_time_enabled', 'show_time'] as $b) {
            $v = Arr::get($d, $b, false);
            if (is_string($v)) {
                $v = strtolower($v) === 'true';
            }
            $norm[$b] = (bool) $v;
        }

        // --- dates
        foreach (['min_date', 'max_date'] as $k) {
            $v = (string) Arr::get($d, $k, '');
            if ($v === '' || ! preg_match(self::DATE_RE, $v)) {
                $errors[] = "{$k} must be YYYY-MM-DD";
            }
            $norm[$k] = $v;
        }

        $unavail = Arr::get($d, 'unavailable_dates', []);
        if (! is_array($unavail)) {
            $unavail = [];
        }
        $norm['unavailable_dates'] = array_values(array_filter($unavail, fn ($v) => is_string($v) && preg_match(self::DATE_RE, $v)));

        $days = Arr::get($d, 'include_days', []);
        if (! is_array($days) || empty($days)) {
            $days = $this->days;
        }
        $norm['include_days'] = array_values(array_intersect($days, $this->days));

        // --- time list
        $time = Arr::get($d, 'time', []);
        if (! is_array($time)) {
            $time = [];
        }
        if (count($time) > 10) {
            $errors[] = 'time list must be ≤ 10 items';
            $time = array_slice($time, 0, 10);
        }
        $norm['time'] = array_values(array_map(function ($row) use (&$errors) {
            $id = isset($row['id']) ? (string) $row['id'] : '';
            $title = isset($row['title']) ? (string) $row['title'] : '';
            $en = (bool) ($row['enabled'] ?? true);
            $img = isset($row['image']) ? (string) $row['image'] : '';
            if ($id !== '' && ! preg_match(self::TIME_RE, $id)) {
                $errors[] = 'time[].id must be HH:MM 24h';
            }
            if ($img !== '') {
                if (str_starts_with($img, 'data:image')) {
                    $img = explode(',', $img, 2)[1] ?? '';
                }
                if ($img !== '' && ! preg_match(self::B64_RE, $img)) {
                    $errors[] = 'time[].image must be base64';
                    $img = $this->assets->tinyPixel();
                }
            }
            $out = ['id' => $id, 'title' => $title, 'enabled' => (bool) $en];
            if ($img !== '') {
                $out['image'] = $img;
            }

            return $out;
        }, $time));

        // --- confirmation_message
        $norm['confirmation_message'] = (string) Arr::get($d, 'confirmation_message', '');

        return new FlowValidationResult(empty($errors), $errors, $norm);
    }

    private function validateDetails(array $d): FlowValidationResult
    {
        $errors = [];
        $norm = $d;
        foreach (['name', 'phone', 'email', 'notes', 'branch', 'party_size', 'date', 'time'] as $k) {
            if (isset($norm[$k]) && ! is_string($norm[$k])) {
                $norm[$k] = (string) $norm[$k];
            }
        }
        if (isset($norm['date']) && $norm['date'] !== '' && ! preg_match(self::DATE_RE, $norm['date'])) {
            $errors[] = 'DETAILS.date must be YYYY-MM-DD';
        }
        if (isset($norm['time']) && $norm['time'] !== '' && ! preg_match(self::TIME_RE, $norm['time'])) {
            $errors[] = 'DETAILS.time must be HH:MM 24h';
        }

        return new FlowValidationResult(empty($errors), $errors, $norm);
    }

    private function validateSummary(array $d): FlowValidationResult
    {
        $errors = [];
        $norm = $d;
        foreach (['appointment', 'details', 'party_size', 'date', 'time', 'name', 'phone', 'email', 'notes', 'slot_key'] as $k) {
            if (isset($norm[$k]) && ! is_string($norm[$k])) {
                $norm[$k] = (string) $norm[$k];
            }
        }
        if (isset($norm['date']) && $norm['date'] !== '' && ! preg_match(self::DATE_RE, $norm['date'])) {
            $errors[] = 'SUMMARY.date must be YYYY-MM-DD';
        }
        if (isset($norm['time']) && $norm['time'] !== '' && ! preg_match(self::TIME_RE, $norm['time'])) {
            $errors[] = 'SUMMARY.time must be HH:MM 24h';
        }

        return new FlowValidationResult(empty($errors), $errors, $norm);
    }
}
