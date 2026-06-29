<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Aipex_Client_OS
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        Aipex_Client_OS_Module_Loader::boot();

        if (is_admin()) {
            Aipex_Client_OS_Admin::boot();
        }
    }
}
