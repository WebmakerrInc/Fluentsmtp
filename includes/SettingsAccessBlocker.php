<?php

namespace FluentMail\Includes;

class SettingsAccessBlocker
{
    /**
     * Register the hooks for hiding FluentSMTP SPA menu items.
     *
     * @return void
     */
    public static function register()
    {
        $instance = new static();
        add_action('current_screen', [$instance, 'maybeBoot']);
    }

    /**
     * Boot the blocker only on the FluentSMTP settings screen.
     *
     * @return void
     */
    public function maybeBoot($currentScreen)
    {
        if (!$currentScreen || !isset($currentScreen->id) || $currentScreen->id !== 'settings_page_fluent-mail') {
            return;
        }

        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets'], 20);
        add_action('admin_print_footer_scripts', [$this, 'printFooterScript'], 20);
    }

    /**
     * Enqueue inline CSS to hide the target menu items.
     *
     * @return void
     */
    public function enqueueAssets()
    {
        $css = <<<CSS
        a[href='#/notification-settings'],
        a[href='#/support'],
        a[href='#/documentation'] {
            display: none !important;
        }
        CSS;

        wp_add_inline_style('fluent_mail_admin_app', trim($css));
    }

    /**
     * Print the JavaScript needed to keep the links hidden and redirect blocked hashes.
     *
     * @return void
     */
    public function printFooterScript()
    {
        ?>
        <script type="text/javascript">
            (function() {
                const blocked = [
                    '#/notification-settings',
                    '#/support',
                    '#/documentation'
                ];
                const fallbackHash = '#/settings';

                const removeBlockedLinks = () => {
                    blocked.forEach((hash) => {
                        const selector = `a[href='${hash}']`;
                        document.querySelectorAll(selector).forEach((link) => {
                            if (link.parentNode) {
                                link.parentNode.removeChild(link);
                            } else {
                                link.remove();
                            }
                        });
                    });
                };

                const observer = new MutationObserver(() => {
                    removeBlockedLinks();
                });

                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });

                removeBlockedLinks();

                const enforceAllowedHash = () => {
                    if (blocked.includes(window.location.hash)) {
                        window.location.hash = fallbackHash;
                    }
                };

                enforceAllowedHash();
                window.addEventListener('hashchange', enforceAllowedHash);
            })();
        </script>
        <?php
    }
}
