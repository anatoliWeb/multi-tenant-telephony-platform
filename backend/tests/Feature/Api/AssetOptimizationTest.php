<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class AssetOptimizationTest extends TestCase
{
    public function test_vite_config_has_production_asset_optimization_policy(): void
    {
        $viteConfig = file_get_contents(base_path('vite.config.js'));

        $this->assertNotFalse($viteConfig);
        $this->assertStringContainsString("sourcemap: process.env.VITE_BUILD_SOURCEMAP === 'true'", $viteConfig);
        $this->assertStringContainsString("cssMinify: true", $viteConfig);
        $this->assertStringContainsString("manualChunks", $viteConfig);
    }

    public function test_angular_production_build_has_optimization_and_hashing_policy(): void
    {
        $angularJsonPath = dirname(base_path()).'/frontend/angular.json';
        if (! is_file($angularJsonPath)) {
            $this->markTestSkipped('Angular workspace is not mounted inside backend test container.');
        }
        $angularJson = file_get_contents($angularJsonPath);

        $this->assertNotFalse($angularJson);
        $this->assertStringContainsString('"optimization": true', $angularJson);
        $this->assertStringContainsString('"outputHashing": "all"', $angularJson);
        $this->assertStringContainsString('"sourceMap": false', $angularJson);
        $this->assertStringContainsString('"budgets"', $angularJson);
    }

    public function test_nginx_has_static_asset_cache_headers_and_html_no_cache_policy(): void
    {
        $nginxConfPath = dirname(base_path()).'/docker/nginx/default.conf';
        if (! is_file($nginxConfPath)) {
            $this->markTestSkipped('Docker nginx config is not mounted inside backend test container.');
        }
        $nginxConf = file_get_contents($nginxConfPath);

        $this->assertNotFalse($nginxConf);
        $this->assertStringContainsString('location ~* ^/build/assets/', $nginxConf);
        $this->assertStringContainsString('immutable', $nginxConf);
        $this->assertStringContainsString('location ~* \\.html$', $nginxConf);
        $this->assertStringContainsString('no-cache, no-store, must-revalidate', $nginxConf);
    }

    public function test_performance_docs_contain_asset_optimization_section(): void
    {
        $docs = file_get_contents(base_path('docs/performance.md'));

        $this->assertNotFalse($docs);
        $this->assertStringContainsString('## Asset Optimization', $docs);
        $this->assertStringContainsString('### Vue Admin (Laravel + Vite)', $docs);
        $this->assertStringContainsString('### Angular Dashboard', $docs);
    }
}
