<?php

use RedBeanPHP\R as R;

function fetchSettings(): array
{
    try {
        $settings = R::findAll('setting');
    } catch (\Throwable $e) {
        return [];
    }

    $result = [];

    foreach ($settings as $setting) {
        $result[$setting->key] = $setting->value;
    }

    return $result;
}

function getSettingsWithDefaults(): array
{
    global $config;

    $defaults = [
        'site_name' => $config['site_name'] ?? 'Webappka CMS',
        'base_url'  => $config['base_url'] ?? '/',
    ];

    return array_merge($defaults, fetchSettings());
}
