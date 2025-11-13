<?php

namespace FluentMail\App\Hooks\Handlers;

use FluentMail\Includes\Core\Application;

class SettingsAccessBlocker
{
    /**
     * Application instance.
     *
     * @var Application
     */
    protected $app;

    /**
     * Map of hashes that should be hidden/blocked.
     *
     * @var array
     */
    protected $blockedHashes = [
        '#/alert',
        '#/alerts',
        '#/about',
        '#/documentation',
    ];

    /**
     * Constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Register hooks for blocking settings sections.
     *
     * @return void
     */
    public function register()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueBlockingAssets'], 20);
    }

    /**
     * Enqueue inline assets that hide disallowed menu entries and
     * prevent navigating to their routes.
     *
     * @return void
     */
    public function enqueueBlockingAssets()
    {
        if (!$this->isSettingsScreen()) {
            return;
        }

        $blocked = $this->getBlockedHashes();

        if (wp_script_is('fluent_mail_admin_app_boot', 'enqueued')) {
            $script = $this->generateInlineScript($blocked);
            wp_add_inline_script('fluent_mail_admin_app_boot', $script, 'after');
        } else {
            add_action('admin_print_footer_scripts', function () use ($blocked) {
                echo '<script>' . $this->generateInlineScript($blocked) . '</script>';
            }, 100);
        }

        if (wp_style_is('fluent_mail_admin_app', 'enqueued')) {
            $style = $this->generateInlineStyle($blocked);
            if ($style) {
                wp_add_inline_style('fluent_mail_admin_app', $style);
            }
        }
    }

    /**
     * Determine if current request is for FluentSMTP settings screen.
     *
     * @return bool
     */
    protected function isSettingsScreen()
    {
        return is_admin() && isset($_GET['page']) && $_GET['page'] === 'fluent-mail';
    }

    /**
     * Retrieve the hashes that should be blocked, normalised to lowercase.
     *
     * @return array
     */
    protected function getBlockedHashes()
    {
        return array_values(array_unique(array_map(function ($hash) {
            return strtolower(trim($hash));
        }, $this->blockedHashes)));
    }

    /**
     * Generate inline script that hides menu entries and redirects
     * blocked routes back to the dashboard.
     *
     * @param string $blockedJson
     * @return string
     */
    protected function generateInlineScript($blocked)
    {
        $blockedJson = wp_json_encode(array_values($blocked), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        $script = <<<JS
(function () {
    if (!window || !document) {
        return;
    }

    var blocked = [];
    try {
        blocked = JSON.parse('$blockedJson') || [];
    } catch (e) {
        blocked = [];
    }

    if (!blocked.length) {
        return;
    }

    var normalisedBlocked = blocked.map(function (hash) {
        return (hash || '').toLowerCase().replace(/\/+$/, '');
    });

    var blockedSet = normalisedBlocked.reduce(function (acc, hash) {
        if (hash) {
            acc[hash] = true;
        }
        return acc;
    }, {});

    var safeHash = '#/';

    function normaliseHash(hash) {
        if (!hash) {
            return '';
        }
        return hash.toLowerCase().replace(/\/+$/, '');
    }

    function isBlocked(hash) {
        var normalised = normaliseHash(hash);
        if (!normalised) {
            return false;
        }

        if (blockedSet[normalised]) {
            return true;
        }

        // Allow matching hashes that might have trailing slashes removed.
        if (blockedSet[normalised.replace(/\/+$/, '')]) {
            return true;
        }

        return false;
    }

    function redirectIfBlocked() {
        if (isBlocked(window.location.hash)) {
            var base = window.location.href.split('#')[0];
            window.location.replace(base + safeHash);
        }
    }

    function hideMenuItems(context) {
        var scope = context || document;
        normalisedBlocked.forEach(function (hash) {
            if (!hash) {
                return;
            }
            var selectors = [
                'a[href$="' + hash + '"]',
                'a[href$="' + hash + '/"]',
                'a[href="' + hash + '"]',
                'a[href="' + hash.replace('#/', '#') + '"]'
            ];

            selectors.forEach(function (selector) {
                var nodes = scope.querySelectorAll(selector);
                if (!nodes.length) {
                    return;
                }
                nodes.forEach(function (node) {
                    var item = node.closest('li, .el-menu-item, .el-submenu__title, .el-menu-item-group, .fs_nav_item');
                    if (item) {
                        item.style.setProperty('display', 'none', 'important');
                    }
                    node.style.setProperty('display', 'none', 'important');
                    node.setAttribute('aria-hidden', 'true');
                    node.setAttribute('tabindex', '-1');
                });
            });
        });
    }

    function observeMenu() {
        if (!document.body) {
            return;
        }

        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType === 1) {
                        hideMenuItems(node);
                    }
                });
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    function boot() {
        hideMenuItems();
        redirectIfBlocked();
        observeMenu();
    }

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        boot();
    } else {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    }

    window.addEventListener('hashchange', redirectIfBlocked);
})();
JS;

        return $script;
    }

    /**
     * Generate CSS that hides the blocked menu anchors by default.
     *
     * @param array $blocked
     * @return string
     */
    protected function generateInlineStyle($blocked)
    {
        if (empty($blocked)) {
            return '';
        }

        $selectors = [];
        foreach ($blocked as $hash) {
            if (!$hash) {
                continue;
            }
            $sanitised = addcslashes($hash, "\\\"");
            $selectors[] = 'a[href$="' . $sanitised . '"]';
            $selectors[] = 'a[href$="' . $sanitised . '/"]';
            $selectors[] = 'a[href="' . $sanitised . '"]';
        }

        $selectors = array_unique($selectors);

        if (empty($selectors)) {
            return '';
        }

        return implode(',', $selectors) . '{display:none !important;}';
    }
}
