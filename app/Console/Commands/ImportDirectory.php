<?php

namespace App\Console\Commands;

use App\Models\DataSource;
use App\Models\User;
use App\Services\ImportService;
use App\Support\EntityRegistry;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use JsonException;

class ImportDirectory extends Command
{
    protected $signature = 'core:import {entity} {file : Path to a normalized JSON array} {--source= : Source ID} {--user= : Reviewing national administrator ID}';

    protected $description = 'Validate and stage normalized directory data for human review; never publishes automatically';

    public function handle(ImportService $imports): int
    {
        $entity = $this->argument('entity');
        $actor = User::find($this->option('user'));
        $source = DataSource::find($this->option('source'));
        $path = $this->argument('file');
        if (! $actor?->hasPermission('imports.manage') || ! $source || ! (EntityRegistry::ENTITIES[$entity]['public'] ?? false)) {
            $this->error('A public entity, existing source, and authorized national administrator are required.');

            return self::FAILURE;
        }
        if (! is_file($path) || ! is_readable($path) || filesize($path) > 2 * 1024 * 1024) {
            $this->error('The input must be a readable JSON file of at most 2 MiB.');

            return self::FAILURE;
        }
        try {
            $rows = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($rows) || ! array_is_list($rows) || count($rows) < 1 || count($rows) > 500 || count(array_filter($rows, 'is_array')) !== count($rows)) {
                $this->error('Provide a JSON array of 1 to 500 record objects.');

                return self::FAILURE;
            }
            // CLI authorization uses the persisted role; no bearer credential is stored or printed.
            $actor->withAccessToken(new \Laravel\Sanctum\TransientToken);
            $batch = $imports->stage($entity, $source->id, $rows, $actor);
            $this->line(json_encode(['id' => $batch->id, 'status' => $batch->status, 'report' => $batch->report], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $batch->status === 'invalid' ? self::FAILURE : self::SUCCESS;
        } catch (JsonException|ValidationException $exception) {
            $this->error($exception instanceof JsonException ? 'Invalid JSON input.' : 'The input does not meet the import requirements.');

            return self::FAILURE;
        }
    }
}
