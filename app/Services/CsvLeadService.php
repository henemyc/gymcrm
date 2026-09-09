<?php

namespace App\Services;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvLeadService
{
    public function __construct(
        private DuplicateGuard $duplicates,
        private ActivityLogger $logger
    ) {
    }

    public function import(UploadedFile $file, User $actor, ?int $assignTo = null): array
    {
        $max = Setting::getInt('max_import_rows', 2000);

        if (! in_array(strtolower($file->getClientOriginalExtension()), ['csv', 'txt'], true)) {
            throw ValidationException::withMessages(['file' => 'Only CSV files are allowed.']);
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'CSV must be 5 MB or smaller.']);
        }

        $path = $file->store('imports');
        $full = Storage::path($path);
        $handle = fopen($full, 'r');
        if (! $handle) {
            throw ValidationException::withMessages(['file' => 'Could not read the uploaded file.']);
        }

        $header = null;
        $created = 0;
        $duplicates = 0;
        $invalid = 0;
        $rowNum = 0;
        $errors = [];

        try {
            while (($row = fgetcsv($handle)) !== false) {
                if ($header === null) {
                    $header = array_map(fn ($h) => strtolower(trim((string) $h)), $row);
                    continue;
                }

                $rowNum++;
                if ($rowNum > $max) {
                    $errors[] = "Stopped at {$max} data rows (limit).";
                    break;
                }

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $data = $this->mapRow($header, $row);
                if ($data['name'] === '') {
                    $invalid++;
                    $errors[] = "Row {$rowNum}: name is required.";
                    continue;
                }

                $check = $this->duplicates->check($data);
                if ($check['duplicate']) {
                    $duplicates++;
                    continue;
                }

                Lead::query()->create([
                    'name' => mb_substr($data['name'], 0, 191),
                    'phone' => $data['phone'] ?: null,
                    'email' => $data['email'] ?: null,
                    'website' => $data['website'] ?: null,
                    'address' => $data['address'] ?: null,
                    'city' => $data['city'] ?: null,
                    'state' => $data['state'] ?: null,
                    'country' => $data['country'] ?: null,
                    'category' => $data['category'] ?: null,
                    'notes' => $data['notes'] ?: null,
                    'source' => LeadSource::Import->value,
                    'status' => LeadStatus::New->value,
                    'assigned_to' => $assignTo,
                    'assigned_by' => $assignTo ? $actor->id : null,
                    'assigned_at' => $assignTo ? now() : null,
                    'created_by' => $actor->id,
                ]);
                $created++;
            }
        } finally {
            fclose($handle);
        }

        $this->logger->log(
            'leads.imported',
            "Imported {$created} leads from CSV ({$duplicates} duplicates skipped).",
            null,
            ['created' => $created, 'duplicates' => $duplicates, 'invalid' => $invalid]
        );

        return compact('created', 'duplicates', 'invalid', 'errors');
    }

    public function export($query): StreamedResponse
    {
        $filename = 'leads-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id', 'name', 'phone', 'email', 'website', 'address', 'city', 'state', 'country',
                'category', 'status', 'source', 'assigned_to', 'rating', 'google_maps_url', 'created_at',
            ]);

            $query->with('assignee')->orderBy('id')->chunk(200, function ($leads) use ($out) {
                foreach ($leads as $lead) {
                    fputcsv($out, [
                        $lead->id,
                        $lead->name,
                        $lead->phone,
                        $lead->email,
                        $lead->website,
                        $lead->address,
                        $lead->city,
                        $lead->state,
                        $lead->country,
                        $lead->category,
                        $lead->status,
                        $lead->source,
                        $lead->assignee?->name,
                        $lead->rating,
                        $lead->google_maps_url,
                        $lead->created_at,
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['name', 'phone', 'email', 'website', 'address', 'city', 'state', 'country', 'category', 'notes']);
            fputcsv($out, ['Iron Temple Gym', '9876543210', 'info@example.com', 'https://example.com', 'Mall Road', 'Kanpur', 'Uttar Pradesh', 'India', 'gym', 'Called reception']);
            fclose($out);
        }, 'leads-import-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function mapRow(array $header, array $row): array
    {
        $assoc = [];
        foreach ($header as $i => $key) {
            $assoc[$key] = isset($row[$i]) ? trim((string) $row[$i]) : '';
        }

        return [
            'name' => $assoc['name'] ?? ($assoc['business'] ?? ($assoc['title'] ?? '')),
            'phone' => $assoc['phone'] ?? ($assoc['mobile'] ?? ($assoc['contact'] ?? '')),
            'email' => $assoc['email'] ?? '',
            'website' => $assoc['website'] ?? ($assoc['url'] ?? ''),
            'address' => $assoc['address'] ?? '',
            'city' => $assoc['city'] ?? '',
            'state' => $assoc['state'] ?? '',
            'country' => $assoc['country'] ?? '',
            'category' => $assoc['category'] ?? ($assoc['type'] ?? ''),
            'notes' => $assoc['notes'] ?? ($assoc['note'] ?? ''),
        ];
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
