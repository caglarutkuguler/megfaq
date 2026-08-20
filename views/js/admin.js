/**
 * Product FAQ and Customer Questions - back office.
 *
 * Two small jobs: the language tabs on the entry form, and a confirmation before
 * a delete. Nothing else on this screen depends on script - the list, the
 * filters and the settings are plain forms that work without it.
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture & Consulting Ltd.
 * @license   https://opensource.org/licenses/MIT MIT License
 */

(function () {
    'use strict';

    function languageTabs(root) {
        var tabs = root.querySelectorAll('[data-mf-lang]');

        if (!tabs.length) {
            return;
        }

        Array.prototype.forEach.call(tabs, function (tab) {
            tab.addEventListener('click', function (event) {
                event.preventDefault();

                var id = tab.getAttribute('href');
                var pane = root.querySelector(id);

                if (!pane) {
                    return;
                }

                Array.prototype.forEach.call(
                    root.querySelectorAll('.mf-lang-pane'),
                    function (node) {
                        node.classList.remove('mf-lang-pane--active');
                    }
                );
                Array.prototype.forEach.call(
                    root.querySelectorAll('.mf-lang-tabs > li'),
                    function (node) {
                        node.classList.remove('active');
                    }
                );

                pane.classList.add('mf-lang-pane--active');

                if (tab.parentNode) {
                    tab.parentNode.classList.add('active');
                }
            });
        });
    }

    /**
     * Delete is the one action here that cannot be undone, so it asks. Publish
     * and unpublish do not: both are one click away from being put back.
     */
    function confirmDelete(root) {
        Array.prototype.forEach.call(
            root.querySelectorAll('[data-mf-confirm]'),
            function (button) {
                button.addEventListener('click', function (event) {
                    if (!window.confirm(button.getAttribute('data-mf-confirm'))) {
                        event.preventDefault();
                    }
                });
            }
        );
    }

    function run() {
        var root = document.querySelector('[data-mf-admin]');

        if (!root) {
            return;
        }

        languageTabs(root);
        confirmDelete(root);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
}());
