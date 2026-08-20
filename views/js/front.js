/**
 * Product FAQ and Customer Questions - front office.
 *
 * There is deliberately very little here. The accordion is a <details> element,
 * which opens and closes on its own; this file only adds the two things the
 * browser will not do by itself.
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture & Consulting Ltd.
 * @license   https://opensource.org/licenses/MIT MIT License
 */

(function () {
    'use strict';

    /**
     * Open the entry someone was linked to.
     *
     * A link to #megfaq-12 scrolls to a collapsed <details> and shows the reader
     * a closed question, which looks like the link is broken.
     */
    function openTarget() {
        if (!window.location.hash) {
            return;
        }

        var target = document.querySelector(window.location.hash);

        if (target && target.tagName === 'DETAILS') {
            target.open = true;
        }
    }

    /**
     * Stop a second submit while the first is in flight. The server refuses
     * duplicates by flood control anyway, but a shopper who double-clicks should
     * not have to find that out from an error message.
     */
    function guardForm() {
        var form = document.querySelector('.megfaq__form');

        if (!form) {
            return;
        }

        form.addEventListener('submit', function () {
            var button = form.querySelector('button[type="submit"]');

            if (button) {
                button.disabled = true;
            }
        });
    }

    function run() {
        openTarget();
        guardForm();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }

    window.addEventListener('hashchange', openTarget);
}());
