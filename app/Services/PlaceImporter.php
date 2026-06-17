<?php

namespace App\Services;

use App\Models\Place;
use Illuminate\Support\Facades\File;

class PlaceImporter
{
    public function import(?string $provider = null, ?string $file = null): array
    {
        $dataPath = rtrim(config('services.crawler.data_path', '/var/www/data'), '/');
        $files = $this->resolveFiles($dataPath, $provider, $file);

        if (empty($files)) {
            return [
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0,
                'files' => [],
                'message' => 'فایلی برای import یافت نشد.',
            ];
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $processedFiles = [];

        foreach ($files as $filePath) {
            $result = $this->importFile($filePath);
            $imported += $result['imported'];
            $updated += $result['updated'];
            $skipped += $result['skipped'];
            $processedFiles[] = basename($filePath);
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'files' => $processedFiles,
            'message' => 'Import با موفقیت انجام شد.',
        ];
    }

    private function resolveFiles(string $dataPath, ?string $provider, ?string $file): array
    {
        if ($file) {
            $path = str_starts_with($file, '/') ? $file : $dataPath . '/' . ltrim($file, '/');

            return File::exists($path) ? [$path] : [];
        }

        if ($provider) {
            $path = $dataPath . '/' . $provider . '-places.ndjson';

            return File::exists($path) ? [$path] : [];
        }

        return File::exists($dataPath)
            ? File::glob($dataPath . '/*-places.ndjson') ?: []
            : [];
    }

    private function importFile(string $filePath): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;

        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            return compact('imported', 'updated', 'skipped');
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $payload = json_decode($line, true);

            if (!is_array($payload)) {
                $skipped++;
                continue;
            }

            $attributes = $this->mapPayloadToAttributes($payload);

            if (empty($attributes['provider'])) {
                $skipped++;
                continue;
            }

            $place = null;

            if (!empty($attributes['external_id'])) {
                $place = Place::query()
                    ->where('provider', $attributes['provider'])
                    ->where('external_id', $attributes['external_id'])
                    ->first();
            }

            if ($place) {
                $place->fill($attributes);
                $place->save();
                $updated++;
            } else {
                Place::create($attributes);
                $imported++;
            }
        }

        fclose($handle);

        return compact('imported', 'updated', 'skipped');
    }

    private function mapPayloadToAttributes(array $payload): array
    {
        $externalId = $payload['id'] ?? $payload['external_id'] ?? null;

        return [
            'provider' => $payload['provider'] ?? null,
            'external_id' => $externalId !== null ? (string) $externalId : null,
            'name' => $payload['name'] ?? null,
            'address' => $payload['address'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'lat' => $payload['lat'] ?? null,
            'lng' => $payload['lng'] ?? null,
            'url' => $payload['url'] ?? null,
            'keyword' => $payload['keyword'] ?? null,
            'raw_data' => $payload,
        ];
    }
}
