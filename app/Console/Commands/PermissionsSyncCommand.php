<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

class PermissionsSyncCommand extends Command
{
    protected $signature = 'permissions:sync {--prune : Delete DB permissions not present in config}';

    protected $description = 'Sync permissions from config/permissions.php to DB.';

    public function handle(): int
    {
        $registry = config('permissions', []);
        if (! is_array($registry) || empty($registry)) {
            $this->error('config/permissions.php is empty or invalid.');

            return self::FAILURE;
        }

        $all = [];
        foreach ($registry as $group => $perms) {
            foreach ($perms as $name) {
                $all[$name] = true;
                Permission::firstOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    []
                );
            }
        }

        $this->info('Synced '.count($all).' permissions.');

        if ($this->option('prune')) {
            $dbNames = Permission::query()->pluck('name')->all();
            $toDelete = array_diff($dbNames, array_keys($all));
            if ($toDelete && $this->confirm('Delete '.count($toDelete).' permissions not in config?')) {
                Permission::query()->whereIn('name', $toDelete)->delete();
                $this->warn('Pruned: '.count($toDelete));
            }
        }

        return self::SUCCESS;
    }
}
