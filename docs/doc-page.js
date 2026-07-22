/**
 * Mail System by Katsarov Design - Documentation Subpage Scripts
 *
 * Enhancements for themed documentation pages:
 *   - Syntax highlighting via highlight.js (progressive; degrades gracefully)
 *   - Copy-to-clipboard buttons on code blocks
 *   - Scrollspy that highlights the active section in the "On this page" TOC
 *   - Collapsible TOC on small screens
 *
 * Shared header/footer behaviour (language switch, mobile menu) lives in script.js.
 */

(function () {
    'use strict';

    /**
     * Run highlight.js on all code blocks when the library is available.
     */
    function highlightCode() {
        if (window.hljs && typeof window.hljs.highlightElement === 'function') {
            document.querySelectorAll('.doc-content pre code').forEach(function (block) {
                window.hljs.highlightElement(block);
            });
        }
    }

    /**
     * Wrap each <pre> in a positioned container and add a copy button.
     */
    function addCopyButtons() {
        document.querySelectorAll('.doc-content pre').forEach(function (pre) {
            const code = pre.querySelector('code');
            if (!code) {
                return;
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'code-block';
            pre.parentNode.insertBefore(wrapper, pre);
            wrapper.appendChild(pre);

            const button = document.createElement('button');
            button.className = 'code-copy';
            button.type = 'button';
            button.textContent = 'Copy';
            button.setAttribute('aria-label', 'Copy code to clipboard');
            wrapper.appendChild(button);

            button.addEventListener('click', function () {
                const text = code.innerText;

                const done = function () {
                    button.textContent = 'Copied!';
                    button.classList.add('copied');
                    window.setTimeout(function () {
                        button.textContent = 'Copy';
                        button.classList.remove('copied');
                    }, 1800);
                };

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(done).catch(function () {
                        fallbackCopy(text, done);
                    });
                } else {
                    fallbackCopy(text, done);
                }
            });
        });
    }

    /**
     * Clipboard fallback for browsers without the async Clipboard API.
     * @param {string} text
     * @param {Function} done
     */
    function fallbackCopy(text, done) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'absolute';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            done();
        } catch (err) {
            /* no-op */
        }
        document.body.removeChild(textarea);
    }

    /**
     * Highlight the TOC entry for the section currently in view.
     */
    function initScrollSpy() {
        const links = Array.prototype.slice.call(
            document.querySelectorAll('.doc-toc a[href^="#"]')
        );
        if (!links.length || !('IntersectionObserver' in window)) {
            return;
        }

        const linkById = {};
        const sections = [];
        links.forEach(function (link) {
            const id = decodeURIComponent(link.getAttribute('href').slice(1));
            const section = document.getElementById(id);
            if (section) {
                linkById[id] = link;
                sections.push(section);
            }
        });

        let activeId = null;
        const setActive = function (id) {
            if (id === activeId || !linkById[id]) {
                return;
            }
            if (activeId && linkById[activeId]) {
                linkById[activeId].classList.remove('active');
            }
            linkById[id].classList.add('active');
            activeId = id;
        };

        const observer = new IntersectionObserver(function (entries) {
            const visible = entries
                .filter(function (e) { return e.isIntersecting; })
                .sort(function (a, b) { return a.boundingClientRect.top - b.boundingClientRect.top; });

            if (visible.length) {
                setActive(visible[0].target.id);
            }
        }, {
            rootMargin: '-100px 0px -70% 0px',
            threshold: 0
        });

        sections.forEach(function (section) { observer.observe(section); });

        // Reflect direct clicks immediately.
        links.forEach(function (link) {
            link.addEventListener('click', function () {
                setActive(decodeURIComponent(link.getAttribute('href').slice(1)));
                closeMobileToc();
            });
        });
    }

    /**
     * Collapsible "On this page" TOC on small screens.
     */
    let tocToggle = null;
    let tocNav = null;

    function closeMobileToc() {
        if (tocToggle && tocNav && window.matchMedia('(max-width: 960px)').matches) {
            tocNav.classList.remove('open');
            tocToggle.setAttribute('aria-expanded', 'false');
        }
    }

    function initTocToggle() {
        tocToggle = document.getElementById('docTocToggle');
        tocNav = document.getElementById('docToc');
        if (!tocToggle || !tocNav) {
            return;
        }

        tocToggle.addEventListener('click', function () {
            const isOpen = tocNav.classList.toggle('open');
            tocToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    function init() {
        highlightCode();
        addCopyButtons();
        initScrollSpy();
        initTocToggle();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
