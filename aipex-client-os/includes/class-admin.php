<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Aipex_Client_OS_Admin
{
    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'register_menu']);
    }

    public static function register_menu(): void
    {
        add_menu_page(
            'Aipex Client OS',
            'Aipex Client OS',
            'manage_options',
            'aipex-client-os',
            [self::class, 'render_dashboard'],
            'dashicons-networking',
            56
        );
    }

    public static function render_dashboard(): void
    {
        echo '<div class="wrap">';
        echo '<h1>Aipex Client OS</h1>';
        echo '<p>CRM, client portal, workflow engine, secure vault, and industry-pack foundation.</p>';
        echo '<h2>Foundation Modules</h2>';
        echo '<ul>';
        echo '<li>CRM Core</li>';
        echo '<li>Client Portal</li>';
        echo '<li>Workflow Engine</li>';
        echo '<li>Secure Vault</li>';
        echo '<li>Securime Emergency Release</li>';
        echo '<li>Express Divorce Industry Pack</li>';
        echo '</ul>';
        echo '</div>';
    }
}
