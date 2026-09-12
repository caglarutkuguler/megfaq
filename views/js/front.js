/**
 * Product FAQ and Customer Questions - front office.
 *
 * The accordion is a <details> element, which opens and closes on its own, and
 * the FAQ page's search is a GET form the server answers on its own. This file
 * adds the three things the browser will not do by itself: open the row a link
 * points into, filter the page as the visitor types, and stop a double submit
 * of the ask form.
 *
 * The filter reads the same text and applies the same rule as the server -
 * case-insensitive, anywhere in the question, the answer or the product name -
 * so a page that arrived from ?q= and a page filtered here look the same.
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture & Consulting Ltd.
 * @license   https://opensource.org/licenses/MIT MIT License
 */

(function () {
    'use strict';

    /** Below this many characters nothing is filtered: one letter matches everything. */
    var MIN_CHARS = 2;

    /** Same number as the controller: up to this many matches, the answers open. */
    var OPEN_MATCHES_UP_TO = 10;

    function toArray(list) {
        return Array.prototype.slice.call(list || []);
    }

    function textOf(el) {
        return el ? (el.textContent || '') : '';
    }

    function escapeRegExp(value) {
        return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function closest(el, className) {
        while (el && el.nodeType === 1) {
            if (el.classList.contains(className)) {
                return el;
            }
            el = el.parentNode;
        }

        return null;
    }

    /**
     * Open the entry someone was linked to, and every row it sits inside.
     *
     * A link to #megfaq-12 scrolls to a collapsed <details> and shows the reader
     * a closed question - or, on the FAQ page, a closed product row with the
     * question somewhere inside it - which looks like the link is broken.
     */
    function openTarget() {
        var hash = window.location.hash;

        if (!hash || hash.length < 2) {
            return;
        }

        var target;
        try {
            target = document.getElementById(decodeURIComponent(hash.slice(1)));
        } catch (e) {
            return;
        }

        if (!target) {
            return;
        }

        var node = target;
        var opened = false;
        while (node && node.nodeType === 1) {
            if (node.tagName === 'DETAILS' && !node.open) {
                node.open = true;
                opened = true;
            }
            node = node.parentNode;
        }

        // Opening the rows above it moved the target; put it back in view.
        if (opened && target.scrollIntoView) {
            target.scrollIntoView();
        }
    }

    /**
     * Wrap every occurrence of the search inside an element's text in <mark>,
     * text node by text node, so the markup around it (the <br> in an answer)
     * is left exactly as the server sent it.
     */
    function highlight(el, re) {
        if (!el || !document.createTreeWalker) {
            return;
        }

        var walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null, false);
        var nodes = [];
        while (walker.nextNode()) {
            nodes.push(walker.currentNode);
        }

        nodes.forEach(function (node) {
            var text = node.nodeValue;
            re.lastIndex = 0;
            var found = re.exec(text);

            if (!found) {
                return;
            }

            var fragment = document.createDocumentFragment();
            var last = 0;

            while (found) {
                if (found.index > last) {
                    fragment.appendChild(document.createTextNode(text.slice(last, found.index)));
                }

                var mark = document.createElement('mark');
                mark.className = 'megfaq__mark';
                mark.textContent = found[0];
                fragment.appendChild(mark);

                last = found.index + found[0].length;
                found = re.exec(text);
            }

            if (last < text.length) {
                fragment.appendChild(document.createTextNode(text.slice(last)));
            }

            node.parentNode.replaceChild(fragment, node);
        });
    }

    function unhighlight(root) {
        toArray(root.querySelectorAll('mark.megfaq__mark')).forEach(function (mark) {
            var parent = mark.parentNode;
            parent.replaceChild(document.createTextNode(mark.textContent), mark);
            parent.normalize();
        });
    }

    /**
     * The FAQ page's search box.
     */
    function search() {
        var root = document.querySelector('.megfaq--page');
        var form = root ? root.querySelector('[data-megfaq-search]') : null;

        if (!form) {
            return;
        }

        var input = form.querySelector('input[type="search"]');
        var clear = form.querySelector('.megfaq__search-clear');
        var status = form.querySelector('[data-megfaq-status]');
        var frame = root.querySelector('[data-megfaq-groups]') || root.querySelector('.megfaq__list--flat');

        if (!input || !status) {
            return;
        }

        var groups = toArray(root.querySelectorAll('.megfaq__group')).map(function (el) {
            var name = el.querySelector('.megfaq__group-name');

            return {
                el: el,
                name: name,
                count: el.querySelector('[data-megfaq-count]'),
                text: textOf(name),
                titleHit: false,
                hits: 0
            };
        });

        var items = toArray(root.querySelectorAll('.megfaq__item')).map(function (el) {
            var groupEl = closest(el.parentNode, 'megfaq__group');
            var group = null;

            groups.forEach(function (candidate) {
                if (candidate.el === groupEl) {
                    group = candidate;
                }
            });

            return {
                el: el,
                q: el.querySelector('.megfaq__q'),
                a: el.querySelector('.megfaq__a'),
                text: textOf(el.querySelector('.megfaq__q')) + '\n' + textOf(el.querySelector('.megfaq__a')),
                group: group
            };
        });

        var rows = items.concat(groups);

        /** Open flags from before the search began, put back when it is cleared. */
        var remembered = null;

        /**
         * True until the first search on a page that arrived already filtered by
         * ?q=. Its open flags are the server's answer to that search, so the
         * page to go back to is the one described by data-open instead.
         */
        var landed = input.value.trim().length >= MIN_CHARS;

        function begin() {
            remembered = rows.map(function (row) {
                return {
                    el: row.el,
                    open: landed ? row.el.hasAttribute('data-open') : row.el.open
                };
            });
            landed = false;
        }

        function syncUrl(query) {
            if (!window.history || !window.history.replaceState || typeof URL !== 'function') {
                return;
            }

            try {
                var url = new URL(window.location.href);
                if (query === '') {
                    url.searchParams['delete']('q');
                } else {
                    url.searchParams.set('q', query);
                }
                window.history.replaceState(null, '', url.toString());
            } catch (e) {
                // No URL API: the address bar just keeps what it had.
            }
        }

        function restore() {
            remembered.forEach(function (row) {
                row.el.hidden = false;
                row.el.open = row.open;
            });
            remembered = null;

            unhighlight(root);

            groups.forEach(function (group) {
                if (group.count) {
                    group.count.textContent = group.count.getAttribute('data-megfaq-count');
                }
            });

            if (frame) {
                frame.hidden = false;
            }

            status.textContent = '';
            syncUrl('');
        }

        function apply() {
            var raw = input.value;
            var query = raw.trim();

            if (clear) {
                clear.hidden = raw === '';
            }

            if (query.length < MIN_CHARS) {
                if (remembered) {
                    restore();
                }

                return;
            }

            if (!remembered) {
                begin();
            }

            unhighlight(root);

            var find = new RegExp(escapeRegExp(query), 'i');
            var markAll = new RegExp(escapeRegExp(query), 'gi');
            var total = 0;

            groups.forEach(function (group) {
                group.hits = 0;
                group.titleHit = find.test(group.text);
            });

            items.forEach(function (item) {
                var hit = (item.group && item.group.titleHit) || find.test(item.text);

                item.el.hidden = !hit;

                if (hit) {
                    total += 1;
                    if (item.group) {
                        item.group.hits += 1;
                    }
                }
            });

            var openMatches = total <= OPEN_MATCHES_UP_TO;

            items.forEach(function (item) {
                if (item.el.hidden) {
                    return;
                }

                item.el.open = openMatches;
                highlight(item.q, markAll);
                highlight(item.a, markAll);
            });

            groups.forEach(function (group) {
                group.el.hidden = group.hits === 0;
                group.el.open = group.hits > 0;

                if (group.count) {
                    group.count.textContent = String(group.hits);
                }

                if (group.titleHit) {
                    highlight(group.name, markAll);
                }
            });

            if (frame) {
                frame.hidden = total === 0;
            }

            if (total === 0) {
                status.textContent = status.getAttribute('data-none') || '';
            } else if (total === 1) {
                status.textContent = status.getAttribute('data-one') || '';
            } else {
                status.textContent = (status.getAttribute('data-many') || '%d').replace('%d', String(total));
            }

            syncUrl(query);
        }

        var timer = null;
        function applySoon() {
            window.clearTimeout(timer);
            timer = window.setTimeout(apply, 60);
        }

        input.addEventListener('input', applySoon);
        input.addEventListener('search', applySoon);

        // Enter, or the keyboard's Search key: the page is already filtered, so
        // the only thing left to do is put the keyboard away.
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            window.clearTimeout(timer);
            apply();
            input.blur();
        });

        if (clear) {
            clear.addEventListener('click', function () {
                input.value = '';
                window.clearTimeout(timer);
                apply();
                input.focus();
            });
        }

        // A page that arrived from ?q= is filtered but not yet highlighted.
        if (landed) {
            apply();
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
        search();
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
