<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClinicGenerateFilamentPolicies extends Command
{
    protected $signature = 'clinic:generate-policies
        {--force-write : Actually write policy files (otherwise dry-run)}
        {--only=* : Generate only for specific model classes (repeatable)}
        {--list : List mapping and exit}';

    protected $description = 'Generate clinic policies extending BaseClinicFilamentPolicy (dry-run by default).';

    public function handle(): int
    {
        $this->info('Clinic Filament Policy Generator');

        if (! class_exists(\App\Policies\Clinic\BaseClinicFilamentPolicy::class)) {
            $this->error('Missing: App\\Policies\\Clinic\\BaseClinicFilamentPolicy');

            return self::FAILURE;
        }

        $mapping = (array) config('clinic-filament-policies.mapping', []);

        if (empty($mapping)) {
            $this->warn('No mapping found in config/clinic-filament-policies.php');

            return self::SUCCESS;
        }

        if ($this->option('list')) {
            $rows = [];
            foreach ($mapping as $model => $key) {
                $rows[] = [$model, $key];
            }
            $this->table(['Model', 'Permission Key'], $rows);

            return self::SUCCESS;
        }

        $only = array_map(fn ($v) => ltrim((string) $v, '\\'), (array) $this->option('only'));
        if (! empty($only)) {
            $mapping = array_filter($mapping, function ($key, $modelClass) use ($only) {
                $modelClass = ltrim($modelClass, '\\');

                return in_array($modelClass, $only, true) || in_array(class_basename($modelClass), $only, true);
            }, ARRAY_FILTER_USE_BOTH);
        }

        $isDryRun = ! $this->option('force-write');
        $this->line($isDryRun ? 'Mode: DRY-RUN' : 'Mode: WRITE');

        $created = 0;
        $skipped = 0;
        $missing = 0;

        foreach ($mapping as $modelClass => $resourceKey) {
            $result = $this->generatePolicy($modelClass, $resourceKey, $isDryRun);
            if ($result === 'created') {
                $created++;
            }
            if ($result === 'skipped') {
                $skipped++;
            }
            if ($result === 'missing') {
                $missing++;
            }
        }

        $this->newLine();
        $this->info('Done.');
        $this->line("Created: {$created}");
        $this->line("Skipped: {$skipped}");
        $this->line("Missing models: {$missing}");

        return self::SUCCESS;
    }

    protected function generatePolicy(string $modelClass, string $resourceKey, bool $dryRun): string
    {
        $modelClass = ltrim($modelClass, '\\');

        if (! class_exists($modelClass)) {
            $this->warn("Missing model: {$modelClass}");

            return 'missing';
        }

        $modelName = class_basename($modelClass);
        $policyName = "{$modelName}Policy";
        $dir = app_path('Policies/Clinic');
        $path = "{$dir}/{$policyName}.php";

        if (File::exists($path)) {
            $this->comment("Skipping: {$policyName} exists.");

            return 'skipped';
        }

        $stub = <<<PHP
<?php

namespace App\Policies\Clinic;

use {$modelClass};

class {$policyName} extends BaseClinicFilamentPolicy
{
    protected static string \$resourceKey = '{$resourceKey}';
}

PHP;

        if ($dryRun) {
            $this->line("Would create: {$path} ({$resourceKey})");

            return 'created';
        }

        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($path, $stub);
        $this->info("Created: {$policyName}");

        return 'created';
    }
}
