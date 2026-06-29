<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Aipex_Client_OS_Module_Loader
{
    public static function boot(): void
    {
        $modules = [
            'crm' => AIPEX_CLIENT_OS_DIR . 'modules/crm/module.php',
            'client-portal' => AIPEX_CLIENT_OS_DIR . 'modules/client-portal/module.php',
            'workflow-engine' => AIPEX_CLIENT_OS_DIR . 'modules/workflow-engine/module.php',
            'secure-vault' => AIPEX_CLIENT_OS_DIR . 'modules/secure-vault/module.php',
            'securime' => AIPEX_CLIENT_OS_DIR . 'modules/securime/module.php',
        ];

        foreach ($modules as $key => $path) {
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }
}
