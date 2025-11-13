<?php

namespace FluentMail\Includes;

class SettingsAccessBlocker
{
    public static function register()
    {
        if (!is_admin()) {
            return;
        }

        add_action('admin_enqueue_scripts-settings_page_fluent-mail', [static::class, 'blockAccess']);
    }

    public static function blockAccess()
    {
        $css = implode("\n", [
            "#fluent_mail_app .fm_top_nav a[href='#/notification-settings'] {",
            "    display: none !important;",
            "}",
            "#fluent_mail_app .fm_top_nav a[href='#/support'] {",
            "    display: none !important;",
            "}",
            "#fluent_mail_app .fm_top_nav a[href='#/documentation'] {",
            "    display: none !important;",
            "}"
        ]);

        wp_register_style('fluentmail-settings-access-blocker', false);
        wp_enqueue_style('fluentmail-settings-access-blocker');
        wp_add_inline_style('fluentmail-settings-access-blocker', $css);

        $script = <<<'JS'
(function() {
    const blocked = [
        '#/notification-settings',
        '#/support',
        '#/documentation'
    ];

    function cleanMenu() {
        const selectors = [
            "a[href='#/notification-settings']",
            "a[href='#/support']",
            "a[href='#/documentation']"
        ];

        selectors.forEach(sel => {
            document.querySelectorAll(sel).forEach(el => el.remove());
        });

        if (blocked.includes(window.location.hash)) {
            window.location.hash = '#/settings';
        }
    }

    // Run once in case SPA is already rendered
    cleanMenu();

    // MutationObserver — SPA safe
    const observer = new MutationObserver(cleanMenu);
    observer.observe(document.body, { childList: true, subtree: true });
})();
JS;

        wp_register_script('fluentmail-settings-access-blocker', false, [], false, true);
        wp_enqueue_script('fluentmail-settings-access-blocker');
        wp_add_inline_script('fluentmail-settings-access-blocker', $script);
    }
}

class_alias(__NAMESPACE__ . '\\SettingsAccessBlocker', 'SettingsAccessBlocker');
