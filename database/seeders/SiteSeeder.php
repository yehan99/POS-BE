<?php

namespace Database\Seeders;

use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SiteSeeder extends Seeder
{
    /**
     * Seed default site records and align existing users.
     */
    public function run(): void
    {
        $tenant = Tenant::query()->first();

        if (! $tenant) {
            return;
        }

        $catalog = [
            ['slug' => 'colombo-hq', 'name' => 'Colombo'],
            ['slug' => 'piliyandala', 'name' => 'Piliyandala'],
        ];

        $sites = collect();

        foreach ($catalog as $definition) {
            $site = Site::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $definition['name'],
                    'description' => Str::headline($definition['name']).' Branch',
                    'is_active' => true,
                ]
            );

            $sites->push($site);
        }

        $primarySite = $sites->first();

        if (! $primarySite) {
            return;
        }

        User::query()
            ->where('tenant_id', $tenant->id)
            ->whereNull('site_id')
            ->update(['site_id' => $primarySite->id]);
    }
}
