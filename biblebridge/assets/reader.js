/* ============================================================
   BibleBridge Reader — reader.js
   ============================================================ */

(function () {
    'use strict';

    // -----------------------------------------------------------
    // Theme toggle (dark / light)
    // -----------------------------------------------------------
    var THEME_KEY = 'bb_theme';

    var themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            var next = isDark ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem(THEME_KEY, next);
        });
    }

    // -----------------------------------------------------------
    // Font size
    // -----------------------------------------------------------
    var FONT_KEY = 'bb_reader_font_size';
    var MIN_SIZE = 0.85;
    var MAX_SIZE = 1.6;
    var STEP     = 0.1;

    function getFontSize() {
        var stored = parseFloat(localStorage.getItem(FONT_KEY));
        return isNaN(stored) ? 1.1 : stored;
    }
    function applyFontSize(size) {
        document.documentElement.style.setProperty('--verse-font-size', size + 'rem');
    }
    function setFontSize(size) {
        size = Math.max(MIN_SIZE, Math.min(MAX_SIZE, parseFloat(size.toFixed(2))));
        localStorage.setItem(FONT_KEY, size);
        applyFontSize(size);
    }

    applyFontSize(getFontSize());

    var fontUp   = document.getElementById('fontUp');
    var fontDown = document.getElementById('fontDown');
    fontUp   && fontUp.addEventListener('click',   function () { setFontSize(getFontSize() + STEP); });
    fontDown && fontDown.addEventListener('click',  function () { setFontSize(getFontSize() - STEP); });

    // -----------------------------------------------------------
    // Version dropdown
    // -----------------------------------------------------------
    var versionBtn      = document.getElementById('versionBtn');
    var versionDropdown = document.getElementById('versionDropdown');

    if (versionBtn && versionDropdown) {
        versionBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = versionDropdown.classList.toggle('open');
            versionBtn.setAttribute('aria-expanded', isOpen);
        });
        document.addEventListener('click', function () {
            versionDropdown.classList.remove('open');
            versionBtn.setAttribute('aria-expanded', 'false');
        });
    }

    // -----------------------------------------------------------
    // Sidebar
    // fix: cache mobile state via resize listener — never re-check
    //      innerWidth at click time (causes mismatch during resize)
    // fix: persist desktop sidebar-closed state to localStorage
    // fix: debounce overlay click to prevent rapid re-fires
    // fix: suppress transitions during initial state restore so the
    //      sidebar never visibly opens-then-closes on page load
    // -----------------------------------------------------------
    var SIDEBAR_KEY    = 'bb_sidebar_closed';
    var sidebarToggle  = document.getElementById('sidebarToggle');
    var readerSidebar  = document.getElementById('readerSidebar');
    var readerLayout   = document.querySelector('.reader-layout');
    var sidebarOverlay = document.getElementById('sidebarOverlay');
    var _isMobile      = window.innerWidth <= 768;

    window.addEventListener('resize', function () { _isMobile = window.innerWidth <= 768; });

    // Restore sidebar-closed class to match what the inline <head> script already
    // applied visually — then remove the data attribute so CSS class takes over.
    if (!_isMobile && readerLayout && localStorage.getItem(SIDEBAR_KEY) === '1') {
        readerLayout.classList.add('sidebar-closed');
    }
    document.documentElement.removeAttribute('data-sidebar');

    function openSidebar() {
        readerSidebar  && readerSidebar.classList.add('open');
        sidebarOverlay && sidebarOverlay.classList.add('open');
    }
    function closeSidebar() {
        readerSidebar  && readerSidebar.classList.remove('open');
        sidebarOverlay && sidebarOverlay.classList.remove('open');
    }

    sidebarToggle && sidebarToggle.addEventListener('click', function () {
        if (_isMobile) {
            readerSidebar.classList.contains('open') ? closeSidebar() : openSidebar();
        } else {
            var closed = readerLayout.classList.toggle('sidebar-closed');
            localStorage.setItem(SIDEBAR_KEY, closed ? '1' : '0');
        }
    });

    var _overlayBusy = false;
    sidebarOverlay && sidebarOverlay.addEventListener('click', function () {
        if (_overlayBusy) return;
        _overlayBusy = true;
        closeSidebar();
        setTimeout(function () { _overlayBusy = false; }, 300);
    });

    // -----------------------------------------------------------
    // Sidebar: testament accordion
    // -----------------------------------------------------------
    document.querySelectorAll('.sidebar-testament-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var list = document.getElementById(btn.dataset.target);
            if (!list) return;
            var isOpen = list.classList.toggle('open');
            btn.classList.toggle('open', isOpen);
        });
    });

    // -----------------------------------------------------------
    // Sidebar: book → chapter 1
    // -----------------------------------------------------------
    document.querySelectorAll('.sidebar-book-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var bookSlug = btn.dataset.book;
            if (!bookSlug) return;
            var v = (typeof READER_VERSION !== 'undefined' && READER_VERSION !== 'kjv') ? '?v=' + READER_VERSION : '';
            window.location.href = '/read/' + bookSlug + '/1' + v;
        });
    });

    // -----------------------------------------------------------
    // Panel (cross-references only — topics removed)
    // fix: single _panelBusy flag prevents double-close
    // fix: aria-hidden toggled correctly on open/close
    // fix: clearSelection factored out to avoid repetition
    // -----------------------------------------------------------
    var xrefPanel       = document.getElementById('xrefPanel');
    var xrefBody        = document.getElementById('xrefBody');
    var xrefClose       = document.getElementById('xrefClose');
    var panelVerseLabel = document.getElementById('panelVerseLabel');
    var panelBackdrop   = document.getElementById('panelBackdrop');
    var selectedVerse   = null;
    var currentRef      = null;
    var _panelBusy      = false;

    function openPanel(verseNum) {
        if (!xrefPanel) return;
        currentRef = (typeof READER_BOOK_DISPLAY !== 'undefined' ? READER_BOOK_DISPLAY
            : bookSlugToTitle(typeof READER_BOOK !== 'undefined' ? READER_BOOK : '')
        ) + ' ' + (typeof READER_CHAPTER !== 'undefined' ? READER_CHAPTER : 1) + ':' + verseNum;

        // Build English reference for API lookup (cross-refs are language-agnostic)
        var engBook = typeof READER_BOOK !== 'undefined' ? READER_BOOK : '';
        var engRef = engBook.split('-').map(function (w) {
            return /^\d/.test(w) ? w : w.charAt(0).toUpperCase() + w.slice(1);
        }).join(' ') + ' ' + (typeof READER_CHAPTER !== 'undefined' ? READER_CHAPTER : 1) + ':' + verseNum;

        if (panelVerseLabel) panelVerseLabel.textContent = currentRef;
        xrefPanel.classList.add('open');
        xrefPanel.removeAttribute('aria-hidden');
        if (_isMobile) {
            document.body.style.overflow = 'hidden';
            panelBackdrop && panelBackdrop.classList.add('open');
        }
        loadXrefs(engRef);
    }

    function closePanel() {
        if (!xrefPanel || _panelBusy) return;
        _panelBusy = true;
        xrefPanel.classList.remove('open');
        xrefPanel.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        panelBackdrop && panelBackdrop.classList.remove('open');
        currentRef = null;
        setTimeout(function () { _panelBusy = false; }, 300);
    }

    function clearSelection() {
        if (selectedVerse) {
            selectedVerse.classList.remove('verse-selected');
            selectedVerse.classList.remove('verse-icons-hidden');
            cancelIconDismiss(selectedVerse);
            selectedVerse = null;
        }
    }

    panelBackdrop && panelBackdrop.addEventListener('click', function () {
        closePanel(); clearSelection();
    });
    xrefClose && xrefClose.addEventListener('click', function () {
        closePanel(); clearSelection();
    });

    // -----------------------------------------------------------
    // Verse actions: copy & share helpers
    // -----------------------------------------------------------
    function getVerseText(el) {
        var clone = el.cloneNode(true);
        clone.querySelectorAll('sup, .verse-actions, .verse-hint-label, .verse-hint-tooltip').forEach(function (n) { n.remove(); });
        return clone.textContent.trim();
    }

    function getVerseRef(verseNum) {
        var book = typeof READER_BOOK_DISPLAY !== 'undefined' ? READER_BOOK_DISPLAY
                 : bookSlugToTitle(typeof READER_BOOK !== 'undefined' ? READER_BOOK : '');
        var ch   = typeof READER_CHAPTER !== 'undefined' ? READER_CHAPTER : '';
        var v    = typeof READER_VERSION !== 'undefined' ? READER_VERSION.toUpperCase() : 'KJV';
        return book + ' ' + ch + ':' + verseNum + ' (' + v + ')';
    }

    function getVerseUrl(verseNum) {
        var v    = typeof READER_VERSION !== 'undefined' ? READER_VERSION : 'kjv';
        var base = typeof BASE_READ_URL  !== 'undefined' ? BASE_READ_URL  : window.location.pathname;
        var url  = window.location.origin + base + '?verse=' + verseNum;
        if (v !== 'kjv') url += '&v=' + v;
        return url;
    }

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).catch(function () { copyFallback(text); });
        } else {
            copyFallback(text);
        }
    }

    function copyFallback(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;opacity:0;pointer-events:none';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
    }

    var COPY_SVG  = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
    var SHARE_SVG = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';
    var IMAGE_SVG = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';
    var CHECK_SVG = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';

    // -----------------------------------------------------------
    // Verse image card generator (Canvas API)
    // -----------------------------------------------------------
    function generateVerseImage(verseText, reference, callback) {
        var W = 1080, H = 1080;
        var canvas = document.createElement('canvas');
        canvas.width = W; canvas.height = H;
        var ctx = canvas.getContext('2d');

        // Background — warm dark gradient
        var grad = ctx.createLinearGradient(0, 0, 0, H);
        grad.addColorStop(0, '#1a1512');
        grad.addColorStop(1, '#2a1f15');
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, W, H);

        // Subtle top accent line
        ctx.fillStyle = '#9e7640';
        ctx.fillRect(80, 80, 60, 4);

        // Verse text — word-wrapped
        ctx.fillStyle = '#e8e4de';
        ctx.font = 'italic 42px Georgia, serif';
        var maxW = W - 160;
        var lines = wrapText(ctx, '\u201C' + verseText + '\u201D', maxW);
        var lineH = 60;
        // Vertically center the text block
        var totalTextH = lines.length * lineH;
        var startY = Math.max(160, (H - totalTextH - 80) / 2);
        lines.forEach(function (line, i) {
            ctx.fillText(line, 80, startY + i * lineH);
        });

        // Reference
        ctx.font = '600 28px sans-serif';
        ctx.fillStyle = '#9e7640';
        ctx.fillText('\u2014 ' + reference, 80, startY + lines.length * lineH + 50);

        // Watermark
        ctx.font = '20px sans-serif';
        ctx.fillStyle = 'rgba(158,118,64,0.4)';
        ctx.textAlign = 'right';
        ctx.fillText(typeof SITE_DOMAIN !== 'undefined' ? SITE_DOMAIN : 'holybible.dev', W - 80, H - 60);

        canvas.toBlob(function (blob) {
            callback(blob);
        }, 'image/png');
    }

    function wrapText(ctx, text, maxWidth) {
        var words = text.split(' ');
        var lines = [];
        var line = '';
        for (var i = 0; i < words.length; i++) {
            var test = line + (line ? ' ' : '') + words[i];
            if (ctx.measureText(test).width > maxWidth && line) {
                lines.push(line);
                line = words[i];
            } else {
                line = test;
            }
        }
        if (line) lines.push(line);
        return lines;
    }

    function shareVerseImage(el) {
        var verseNum = el.dataset.verse;
        var text = getVerseText(el);
        var ref  = getVerseRef(verseNum);
        generateVerseImage(text, ref, function (blob) {
            var file = new File([blob], 'verse.png', { type: 'image/png' });
            if (navigator.canShare && navigator.canShare({ files: [file] })) {
                navigator.share({
                    files: [file],
                    title: ref,
                    text: ref + ' — ' + (typeof SITE_DOMAIN !== 'undefined' ? SITE_DOMAIN : 'holybible.dev')
                }).catch(function () {});
            } else {
                // Fallback: download
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = ref.replace(/[^a-zA-Z0-9 ]/g, '').replace(/\s+/g, '-') + '.png';
                a.click();
                URL.revokeObjectURL(a.href);
            }
        });
    }

    function flashDone(btn, originalSvg) {
        btn.classList.add('va-done');
        btn.innerHTML = CHECK_SVG;
        setTimeout(function () {
            btn.classList.remove('va-done');
            btn.innerHTML = originalSvg;
        }, 1500);
    }

    // -----------------------------------------------------------
    // Mobile long-press action sheet
    // -----------------------------------------------------------
    var vasBackdrop = document.createElement('div');
    var vasSheet    = document.createElement('div');
    vasBackdrop.className = 'vas-backdrop';
    vasSheet.className    = 'verse-action-sheet';
    vasSheet.setAttribute('aria-hidden', 'true');
    vasSheet.innerHTML =
        '<div class="vas-ref" id="vasRef"></div>' +
        '<button class="vas-action-btn" id="vasCopy">' + COPY_SVG  + ' Copy Verse</button>' +
        '<button class="vas-action-btn" id="vasShare">' + SHARE_SVG + ' Share Verse</button>' +
        '<button class="vas-action-btn" id="vasImage">' + IMAGE_SVG + ' Share as Image</button>' +
        '<button class="vas-action-btn vas-cancel" id="vasCancel">Cancel</button>';
    document.body.appendChild(vasBackdrop);
    document.body.appendChild(vasSheet);

    var vasRef      = document.getElementById('vasRef');
    var vasCopyBtn  = document.getElementById('vasCopy');
    var vasShareBtn = document.getElementById('vasShare');
    var vasImageBtn = document.getElementById('vasImage');
    var vasCancel   = document.getElementById('vasCancel');

    var _vasActiveVerse = null;

    function openVasSheet(el) {
        _vasActiveVerse = el;
        var verseNum = el.dataset.verse;
        if (vasRef) vasRef.textContent = getVerseRef(verseNum);
        vasCopyBtn.onclick = function () {
            copyText(getVerseText(el) + '\n— ' + getVerseRef(verseNum));
            closeVasSheet();
        };
        vasShareBtn.onclick = function () {
            var text = '"' + getVerseText(el) + '"\n— ' + getVerseRef(verseNum);
            var url  = getVerseUrl(verseNum);
            if (navigator.share) {
                navigator.share({ title: getVerseRef(verseNum), text: text, url: url }).catch(function(){});
                closeVasSheet();
            } else {
                copyText(text + '\n' + url);
                closeVasSheet();
            }
        };
        vasImageBtn.onclick = function () {
            shareVerseImage(el);
            closeVasSheet();
        };
        vasSheet.classList.add('open');
        vasSheet.removeAttribute('aria-hidden');
        vasBackdrop.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeVasSheet() {
        vasSheet.classList.remove('open');
        vasSheet.setAttribute('aria-hidden', 'true');
        vasBackdrop.classList.remove('open');
        document.body.style.overflow = '';
        _vasActiveVerse = null;
    }

    vasCancel   && vasCancel.addEventListener('click', closeVasSheet);
    vasBackdrop.addEventListener('click', closeVasSheet);

    // -----------------------------------------------------------
    // Verse selection + hover actions + long-press
    // -----------------------------------------------------------
    var _lpTimer      = null;
    var _lpHandled    = false;
    var _dismissTimer = null;

    function scheduleIconDismiss(el) {
        clearTimeout(_dismissTimer);
        _dismissTimer = setTimeout(function () {
            if (el && el.classList.contains('verse-selected')) {
                el.classList.add('verse-icons-hidden');
            }
        }, 1800);
    }

    function cancelIconDismiss(el) {
        clearTimeout(_dismissTimer);
        _dismissTimer = null;
        el && el.classList.remove('verse-icons-hidden');
    }

    document.querySelectorAll('.verse').forEach(function (el) {

        // Inject hover action buttons (CSS hides these on touch devices)
        var actions  = document.createElement('span');
        actions.className = 'verse-actions';
        actions.setAttribute('aria-hidden', 'true');

        var copyBtn  = document.createElement('button');
        copyBtn.className = 'va-btn va-copy';
        copyBtn.title = 'Copy verse';
        copyBtn.innerHTML = COPY_SVG;

        var shareBtn = document.createElement('button');
        shareBtn.className = 'va-btn va-share';
        shareBtn.title = 'Copy share link';
        shareBtn.innerHTML = SHARE_SVG;

        var imgBtn = document.createElement('button');
        imgBtn.className = 'va-btn va-image';
        imgBtn.title = 'Share as image';
        imgBtn.innerHTML = IMAGE_SVG;

        actions.appendChild(copyBtn);
        actions.appendChild(shareBtn);
        actions.appendChild(imgBtn);
        el.appendChild(actions);

        copyBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            copyText(getVerseText(el) + '\n— ' + getVerseRef(el.dataset.verse));
            flashDone(copyBtn, COPY_SVG);
        });

        shareBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var vn = el.dataset.verse;
            var text = '"' + getVerseText(el) + '"\n— ' + getVerseRef(vn);
            var url  = getVerseUrl(vn);
            if (navigator.share) {
                navigator.share({ title: getVerseRef(vn), text: text, url: url }).catch(function(){});
            } else {
                copyText(text + '\n' + url);
            }
            flashDone(shareBtn, SHARE_SVG);
        });

        imgBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            shareVerseImage(el);
            flashDone(imgBtn, IMAGE_SVG);
        });

        // Verse hover: re-show icons if they auto-dismissed, reset timer on leave
        el.addEventListener('mouseenter', function () {
            if (el.classList.contains('verse-selected')) {
                cancelIconDismiss(el);
                scheduleIconDismiss(el);
            }
        });
        el.addEventListener('mouseleave', function () {
            if (el.classList.contains('verse-selected')) {
                scheduleIconDismiss(el);
            }
        });

        // Pause dismiss timer while cursor is over the buttons themselves
        actions.addEventListener('mouseenter', function () { cancelIconDismiss(el); });
        actions.addEventListener('mouseleave', function () { scheduleIconDismiss(el); });

        // Long-press (mobile)
        el.addEventListener('touchstart', function () {
            _lpHandled = false;
            clearTimeout(_lpTimer);
            _lpTimer = setTimeout(function () {
                _lpHandled = true;
                openVasSheet(el);
            }, 500);
        }, { passive: true });

        el.addEventListener('touchmove', function () {
            clearTimeout(_lpTimer);
            _lpTimer = null;
        }, { passive: true });

        el.addEventListener('touchend', function () {
            clearTimeout(_lpTimer);
            _lpTimer = null;
        }, { passive: true });

        // Click → cross-refs
        el.addEventListener('click', function () {
            if (_lpHandled) { _lpHandled = false; return; }
            if (selectedVerse && selectedVerse !== el) {
                selectedVerse.classList.remove('verse-selected');
                selectedVerse.classList.remove('verse-icons-hidden');
                cancelIconDismiss(selectedVerse);
            }
            el.classList.toggle('verse-selected');
            el.classList.remove('verse-icons-hidden');
            selectedVerse = el.classList.contains('verse-selected') ? el : null;
            if (!selectedVerse) { closePanel(); cancelIconDismiss(el); return; }
            scheduleIconDismiss(el);
            var verseNum = el.dataset.verse;
            if (verseNum) openPanel(verseNum);
        });
    });

    // -----------------------------------------------------------
    // Bottom sheet: swipe down to close
    // fix: drag handle was drawn in CSS but JS had no touch support
    // -----------------------------------------------------------
    if (xrefPanel) {
        var _touchStartY = 0;
        var _touchDeltaY = 0;

        xrefPanel.addEventListener('touchstart', function (e) {
            _touchStartY = e.touches[0].clientY;
            _touchDeltaY = 0;
        }, { passive: true });

        xrefPanel.addEventListener('touchmove', function (e) {
            _touchDeltaY = e.touches[0].clientY - _touchStartY;
            if (_touchDeltaY > 0) {
                xrefPanel.style.transform = 'translateY(' + _touchDeltaY + 'px)';
            }
        }, { passive: true });

        xrefPanel.addEventListener('touchend', function () {
            if (_touchDeltaY > 80) {
                xrefPanel.style.transform = '';
                closePanel();
                clearSelection();
            } else {
                xrefPanel.style.transform = '';
            }
            _touchDeltaY = 0;
        });
    }

    // -----------------------------------------------------------
    // Cross-references
    // fix: scroll panel body to top on every load (cached or fresh)
    // fix: removed weight label ("Strength: very_high") — not useful to readers
    // fix: improved error message
    // -----------------------------------------------------------
    // -----------------------------------------------------------
    // Cross-references
    // -----------------------------------------------------------
    var xrefCache = {};
    var xrefCacheKeys = [];
    var XREF_CACHE_MAX = 50;

    function loadXrefs(reference) {
        if (!xrefBody) return;
        var v = typeof READER_VERSION !== 'undefined' ? READER_VERSION : 'kjv';
        var cacheKey = reference + ':' + v;
        if (xrefCache[cacheKey]) {
            xrefBody.innerHTML = xrefCache[cacheKey];
            xrefBody.scrollTop = 0;
            return;
        }
        xrefBody.innerHTML = '<p class="xref-loading"><span class="xref-spinner"></span>Loading…</p>';
        fetch('/reader/xref.php?reference=' + encodeURIComponent(reference) + '&v=' + encodeURIComponent(v))
            .then(function (r) {
                if (r.status === 429) {
                    xrefBody.innerHTML = '<p class="xref-hint">This reader is busy right now. Try again in a moment.</p>';
                    return null;
                }
                return r.json();
            })
            .then(function (data) {
                if (!data) return;
                var html = renderXrefs(data);
                if (xrefCacheKeys.length >= XREF_CACHE_MAX) {
                    delete xrefCache[xrefCacheKeys.shift()];
                }
                xrefCache[cacheKey] = html;
                xrefCacheKeys.push(cacheKey);
                xrefBody.innerHTML = html;
                xrefBody.scrollTop = 0;
            })
            .catch(function () {
                xrefBody.innerHTML = '<p class="xref-hint">Could not load. Check your connection.</p>';
            });
    }

    function renderXrefs(data) {
        if (!data || !data.cross_references || !data.cross_references.length) {
            return '<p class="xref-hint">No cross-references for this verse.</p>';
        }
        var html = '';

        // Source verse topics — show as theme header
        if (data.source_topics && data.source_topics.length) {
            html += '<div class="xref-themes">';
            data.source_topics.forEach(function (t) {
                html += '<a href="/topics/' + t.slug + '" class="xref-theme-chip">' + escHtml(t.name) + '</a>';
            });
            html += '</div>';
        }

        data.cross_references.forEach(function (xref) {
            html += '<div class="xref-item">';
            var xrefUrl = xref.url || refToUrl(xref.reference);
            html += '<a href="' + xrefUrl + '" class="xref-ref"'
                + ' target="_blank" rel="noopener">'
                + escHtml(xref.reference) + ' ↗</a>';
            if (xref.text) {
                html += '<p class="xref-text">' + escHtml(xref.text) + '</p>';
            }
            // Explained connection — shared topics or target topics
            if (xref.shared_topics && xref.shared_topics.length) {
                html += '<div class="xref-explain">';
                html += '<span class="xref-explain-label">Connected through</span> ';
                xref.shared_topics.forEach(function (t, i) {
                    if (i > 0) html += '<span class="xref-explain-sep"> · </span>';
                    html += '<a href="/topics/' + t.slug + '" class="xref-explain-topic">' + escHtml(t.name) + '</a>';
                });
                html += '</div>';
            } else if (xref.topics && xref.topics.length) {
                html += '<div class="xref-explain xref-explain-weak">';
                html += '<span class="xref-explain-label">About</span> ';
                xref.topics.forEach(function (t, i) {
                    if (i > 0) html += '<span class="xref-explain-sep"> · </span>';
                    html += '<a href="/topics/' + t.slug + '" class="xref-explain-topic">' + escHtml(t.name) + '</a>';
                });
                html += '</div>';
            }
            html += '</div>';
        });
        return html;
    }

    // -----------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------
    function bookSlugToTitle(slug) {
        // Use localized name map if available (set by server for non-English versions)
        if (typeof BOOK_NAMES !== 'undefined' && BOOK_NAMES[slug]) {
            return BOOK_NAMES[slug];
        }
        return slug.split('-').map(function (w) {
            return /^\d/.test(w) ? w : w.charAt(0).toUpperCase() + w.slice(1);
        }).join(' ');
    }

    function refToUrl(reference) {
        var m = reference.match(/^(.+?)\s+(\d+)(?::(\d+))?$/);
        if (!m) return '#';
        var book    = m[1].toLowerCase().replace(/\s+/g, '-');
        var chapter = m[2];
        var verse   = m[3];
        var v = (typeof READER_VERSION !== 'undefined' && READER_VERSION !== 'kjv') ? '&v=' + READER_VERSION : '';
        return '/read/' + book + '/' + chapter + (verse ? '?verse=' + verse + v : (v ? '?' + v.slice(1) : ''));
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // -----------------------------------------------------------
    // Hint system — one-time "Click any verse" label
    // -----------------------------------------------------------
    var HINT_KEY = 'bb_hints_seen';

    if (typeof READER_BOOK !== 'undefined' && !localStorage.getItem(HINT_KEY)) {
        var _hintVerses  = document.querySelectorAll('.verse');
        var _hintTarget  = _hintVerses[Math.min(2, _hintVerses.length - 1)];

        if (_hintTarget) {
            _hintTarget.classList.add('verse-hint-pulse');
            var _hintLabel = document.createElement('span');
            _hintLabel.className = 'verse-hint-label';
            _hintLabel.textContent = 'Click any verse to explore';
            _hintTarget.appendChild(_hintLabel);
        }

        document.addEventListener('click', function _hintHandler(e) {
            var verse = e.target.closest && e.target.closest('.verse');
            if (!verse) return;
            if (_hintTarget) _hintTarget.classList.remove('verse-hint-pulse');
            if (_hintLabel && _hintLabel.parentNode) _hintLabel.remove();
            localStorage.setItem(HINT_KEY, '1');
            document.removeEventListener('click', _hintHandler);
        });
    }

    // -----------------------------------------------------------
    // Reading progress bar
    // -----------------------------------------------------------
    var _progressBar = document.getElementById('readingProgressBar');
    if (_progressBar && typeof READER_BOOK !== 'undefined') {
        window.addEventListener('scroll', function () {
            var doc = document.documentElement;
            var scrollTop = doc.scrollTop || document.body.scrollTop;
            var scrollHeight = doc.scrollHeight - doc.clientHeight;
            var pct = scrollHeight > 0 ? scrollTop / scrollHeight : 0;
            _progressBar.style.transform = 'scaleX(' + pct + ')';
        }, { passive: true });
    }

    // -----------------------------------------------------------
    // Resume reading
    // -----------------------------------------------------------
    var LAST_READ_KEY = 'bb_last_read';

    if (typeof READER_BOOK !== 'undefined') {
        try {
            localStorage.setItem(LAST_READ_KEY, JSON.stringify({
                label: (typeof READER_BOOK_DISPLAY !== 'undefined' ? READER_BOOK_DISPLAY : bookSlugToTitle(READER_BOOK)) + ' ' + READER_CHAPTER,
                url: BASE_READ_URL + (READER_VERSION !== 'kjv' ? '?v=' + READER_VERSION : '')
            }));
        } catch (e) {}
    } else {
        // Index page — inject "Continue reading" banner
        try {
            var _lr = JSON.parse(localStorage.getItem(LAST_READ_KEY) || 'null');
            if (_lr && _lr.url && _lr.label) {
                var _resumeBanner = document.createElement('div');
                _resumeBanner.className = 'resume-banner';
                _resumeBanner.innerHTML =
                    '<span class="resume-label">Continue reading</span>' +
                    '<a href="' + escHtml(_lr.url) + '" class="resume-link">' + escHtml(_lr.label) + ' →</a>';
                var _hero = document.querySelector('.reader-index-hero');
                if (_hero) _hero.appendChild(_resumeBanner);
            }
        } catch (e) {}
    }

    // -----------------------------------------------------------
    // Bookmarks
    // -----------------------------------------------------------
    var BOOKMARKS_KEY  = 'bb_bookmarks';
    var BK_EMPTY_SVG   = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>';
    var BK_FILLED_SVG  = '<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>';

    function loadBookmarks() {
        try { return JSON.parse(localStorage.getItem(BOOKMARKS_KEY) || '[]'); } catch (e) { return []; }
    }
    function saveBookmarks(bms) {
        try { localStorage.setItem(BOOKMARKS_KEY, JSON.stringify(bms)); } catch (e) {}
    }
    function bkKey(verse) {
        return (typeof READER_BOOK !== 'undefined' ? READER_BOOK : '') +
               ':' + (typeof READER_CHAPTER !== 'undefined' ? READER_CHAPTER : '') +
               ':' + verse;
    }

    // Mark existing bookmarks on page load
    (function () {
        if (typeof READER_BOOK === 'undefined') return;
        var bms = loadBookmarks();
        var keys = {};
        bms.forEach(function (b) { keys[b.key] = true; });
        document.querySelectorAll('.verse').forEach(function (el) {
            if (keys[bkKey(el.dataset.verse)]) {
                el.classList.add('verse-bookmarked');
            }
        });
    }());

    // Inject bookmark panel
    var bkPanel    = document.createElement('aside');
    var bkBackdrop = document.createElement('div');
    bkPanel.id        = 'bkPanel';
    bkPanel.className = 'bk-panel';
    bkPanel.setAttribute('aria-hidden', 'true');
    bkPanel.innerHTML =
        '<div class="bk-panel-header">' +
            '<span class="bk-panel-title">Bookmarks</span>' +
            '<button class="bk-close" id="bkClose" aria-label="Close">×</button>' +
        '</div>' +
        '<div class="bk-panel-body" id="bkBody"></div>';
    bkBackdrop.className = 'bk-backdrop';
    document.body.appendChild(bkPanel);
    document.body.appendChild(bkBackdrop);

    var bkBody = document.getElementById('bkBody');

    function renderBookmarkList() {
        var bms = loadBookmarks();
        if (!bkBody) return;
        if (!bms.length) {
            bkBody.innerHTML = '<p class="bk-hint">No bookmarks yet.<br>Click the bookmark icon on any verse.</p>';
            return;
        }
        var html = '';
        bms.forEach(function (b) {
            html += '<div class="bk-item">' +
                '<a href="' + escHtml(b.url) + '" class="bk-ref">' + escHtml(b.ref) + '</a>';
            if (b.text) {
                html += '<p class="bk-text">' + escHtml(b.text.length > 110 ? b.text.slice(0, 110) + '…' : b.text) + '</p>';
            }
            html += '<button class="bk-remove" data-key="' + escHtml(b.key) + '" aria-label="Remove">×</button>' +
                '</div>';
        });
        bkBody.innerHTML = html;
        bkBody.querySelectorAll('.bk-remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var key = btn.dataset.key;
                saveBookmarks(loadBookmarks().filter(function (b) { return b.key !== key; }));
                // Unmark verse on page if present
                var verseEl = document.querySelector('[data-verse]');
                document.querySelectorAll('.verse-bookmarked').forEach(function (el) {
                    if (bkKey(el.dataset.verse) === key) {
                        el.classList.remove('verse-bookmarked');
                        var starBtn = el.querySelector('.va-bookmark');
                        if (starBtn) { starBtn.innerHTML = BK_EMPTY_SVG; starBtn.title = 'Bookmark verse'; }
                    }
                });
                updateBkHeaderBtn();
                renderBookmarkList();
            });
        });
    }

    function openBkPanel() {
        // Close xref panel if open
        if (xrefPanel && xrefPanel.classList.contains('open')) {
            closePanel(); clearSelection();
        }
        renderBookmarkList();
        bkPanel.classList.add('open');
        bkPanel.removeAttribute('aria-hidden');
        bkBackdrop.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeBkPanel() {
        bkPanel.classList.remove('open');
        bkPanel.setAttribute('aria-hidden', 'true');
        bkBackdrop.classList.remove('open');
        document.body.style.overflow = '';
    }

    document.getElementById('bkClose') && document.getElementById('bkClose').addEventListener('click', closeBkPanel);
    bkBackdrop.addEventListener('click', closeBkPanel);

    function updateBkHeaderBtn() {
        var btn = document.getElementById('bkHeaderBtn');
        if (!btn) return;
        btn.classList.toggle('has-bookmarks', loadBookmarks().length > 0);
    }

    // Inject header bookmark button (before theme toggle)
    (function () {
        var themeBtn = document.getElementById('themeToggle');
        if (!themeBtn) return;
        var btn = document.createElement('button');
        btn.id = 'bkHeaderBtn';
        btn.className = 'bk-header-btn';
        btn.setAttribute('aria-label', 'Bookmarks');
        btn.innerHTML = BK_EMPTY_SVG;
        themeBtn.parentNode.insertBefore(btn, themeBtn);
        btn.addEventListener('click', function () {
            bkPanel.classList.contains('open') ? closeBkPanel() : openBkPanel();
        });
        updateBkHeaderBtn();
    }());

    // Add bookmark button to each verse's action strip
    document.querySelectorAll('.verse').forEach(function (el) {
        var actions = el.querySelector('.verse-actions');
        if (!actions || typeof READER_BOOK === 'undefined') return;
        var btn = document.createElement('button');
        btn.className = 'va-btn va-bookmark';
        btn.title = 'Bookmark verse';
        var alreadySaved = el.classList.contains('verse-bookmarked');
        btn.innerHTML = alreadySaved ? BK_FILLED_SVG : BK_EMPTY_SVG;
        if (alreadySaved) btn.title = 'Remove bookmark';
        actions.appendChild(btn);

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var verseNum = el.dataset.verse;
            var key = bkKey(verseNum);
            var bms = loadBookmarks();
            var idx = bms.findIndex(function (b) { return b.key === key; });
            if (idx > -1) {
                bms.splice(idx, 1);
                el.classList.remove('verse-bookmarked');
                btn.innerHTML = BK_EMPTY_SVG;
                btn.title = 'Bookmark verse';
            } else {
                bms.unshift({
                    key: key,
                    ref: getVerseRef(verseNum),
                    text: getVerseText(el),
                    url: getVerseUrl(verseNum)
                });
                el.classList.add('verse-bookmarked');
                btn.innerHTML = BK_FILLED_SVG;
                btn.title = 'Remove bookmark';
            }
            saveBookmarks(bms);
            updateBkHeaderBtn();
        });
    });

    // -----------------------------------------------------------
    // Keyboard shortcuts
    // -----------------------------------------------------------
    document.addEventListener('keydown', function (e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        if (e.altKey || e.ctrlKey || e.metaKey) return;
        if (e.key === 'ArrowLeft') {
            var prev = document.getElementById('prevBtn');
            if (prev && prev.href) window.location.href = prev.href;
        } else if (e.key === 'ArrowRight') {
            var next = document.getElementById('nextBtn');
            if (next && next.href) window.location.href = next.href;
        } else if (e.key === 'Escape') {
            if (bkPanel.classList.contains('open')) { closeBkPanel(); return; }
            if (xrefPanel && xrefPanel.classList.contains('open')) { closePanel(); clearSelection(); }
        }
    });

    // -----------------------------------------------------------
    // Scroll on load
    // -----------------------------------------------------------
    // Scroll to a highlighted verse (?verse=N) — otherwise do nothing.
    // The layout's margin-top already clears the fixed header, so verse 1
    // is naturally visible at scroll position 0. No manual scroll needed.
    if (typeof HIGHLIGHT_VERSE !== 'undefined' && HIGHLIGHT_VERSE > 0) {
        var target = document.getElementById('v' + HIGHLIGHT_VERSE);
        if (target) {
            setTimeout(function () {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 150);
        }
    }

    // -----------------------------------------------------------
    // Verse highlights — persistent color marking
    // -----------------------------------------------------------
    var HL_KEY = 'bb_highlights';
    var HL_COLORS = ['yellow', 'green', 'blue', 'pink'];
    var HL_SVG = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>';

    function loadHighlights() {
        try { return JSON.parse(localStorage.getItem(HL_KEY) || '{}'); } catch (e) { return {}; }
    }
    function saveHighlights(h) {
        try { localStorage.setItem(HL_KEY, JSON.stringify(h)); } catch (e) {}
    }
    function hlKey(verse) {
        return (typeof READER_BOOK !== 'undefined' ? READER_BOOK : '') +
               ':' + (typeof READER_CHAPTER !== 'undefined' ? READER_CHAPTER : '') +
               ':' + verse;
    }

    function applyHighlight(el, color) {
        HL_COLORS.forEach(function (c) { el.classList.remove('verse-hl-' + c); });
        if (color) el.classList.add('verse-hl-' + color);
    }

    // Restore highlights on page load
    (function () {
        if (typeof READER_BOOK === 'undefined') return;
        var hls = loadHighlights();
        document.querySelectorAll('.verse').forEach(function (el) {
            var color = hls[hlKey(el.dataset.verse)];
            if (color) applyHighlight(el, color);
        });
    }());

    // Add highlight button to each verse's action strip
    document.querySelectorAll('.verse').forEach(function (el) {
        var actions = el.querySelector('.verse-actions');
        if (!actions || typeof READER_BOOK === 'undefined') return;

        var hlBtn = document.createElement('button');
        hlBtn.className = 'va-btn va-highlight';
        hlBtn.title = 'Highlight verse';
        hlBtn.innerHTML = HL_SVG;
        actions.appendChild(hlBtn);

        hlBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            // Remove any existing picker
            var existing = document.querySelector('.hl-color-picker');
            if (existing) existing.remove();

            var key = hlKey(el.dataset.verse);
            var hls = loadHighlights();
            var current = hls[key] || null;

            // If already highlighted, single click removes it
            if (current) {
                delete hls[key];
                applyHighlight(el, null);
                hlBtn.classList.remove('va-hl-active');
                saveHighlights(hls);
                return;
            }

            // Show color picker
            var picker = document.createElement('span');
            picker.className = 'hl-color-picker';
            picker.addEventListener('click', function (ev) { ev.stopPropagation(); });
            HL_COLORS.forEach(function (color) {
                var dot = document.createElement('button');
                dot.className = 'hl-dot hl-dot-' + color;
                dot.title = color.charAt(0).toUpperCase() + color.slice(1);
                dot.addEventListener('click', function (ev) {
                    ev.stopPropagation();
                    hls[key] = color;
                    applyHighlight(el, color);
                    hlBtn.classList.add('va-hl-active');
                    saveHighlights(hls);
                    picker.remove();
                });
                picker.appendChild(dot);
            });
            hlBtn.parentNode.appendChild(picker);

            // Dismiss on outside click
            setTimeout(function () {
                document.addEventListener('click', function _dismiss() {
                    picker.remove();
                    document.removeEventListener('click', _dismiss);
                }, { once: true });
            }, 0);
        });

        // Set initial state
        var hls = loadHighlights();
        if (hls[hlKey(el.dataset.verse)]) hlBtn.classList.add('va-hl-active');
    });

    // Add highlight color row to mobile action sheet
    (function () {
        var vasHlRow = document.createElement('div');
        vasHlRow.className = 'vas-hl-row';
        vasHlRow.innerHTML = '<span class="vas-hl-label">' + HL_SVG + ' Highlight</span>';

        var dotsWrap = document.createElement('span');
        dotsWrap.className = 'vas-hl-dots';

        HL_COLORS.forEach(function (color) {
            var dot = document.createElement('button');
            dot.className = 'vas-hl-dot vas-hl-dot-' + color;
            dot.dataset.color = color;
            dot.title = color.charAt(0).toUpperCase() + color.slice(1);
            dot.addEventListener('click', function () {
                var sel = _vasActiveVerse || document.querySelector('.verse-selected') || selectedVerse;
                if (!sel) { closeVasSheet(); return; }
                var key = hlKey(sel.dataset.verse);
                var hls = loadHighlights();
                if (hls[key] === color) {
                    delete hls[key];
                    applyHighlight(sel, null);
                } else {
                    hls[key] = color;
                    applyHighlight(sel, color);
                }
                saveHighlights(hls);
                closeVasSheet();
            });
            dotsWrap.appendChild(dot);
        });

        // Add remove button
        var removeDot = document.createElement('button');
        removeDot.className = 'vas-hl-dot vas-hl-dot-remove';
        removeDot.title = 'Remove highlight';
        removeDot.innerHTML = '&times;';
        removeDot.addEventListener('click', function () {
            var sel = _vasActiveVerse || document.querySelector('.verse-selected') || selectedVerse;
            if (!sel) { closeVasSheet(); return; }
            var key = hlKey(sel.dataset.verse);
            var hls = loadHighlights();
            delete hls[key];
            applyHighlight(sel, null);
            saveHighlights(hls);
            closeVasSheet();
        });
        dotsWrap.appendChild(removeDot);

        vasHlRow.appendChild(dotsWrap);
        var vasCancel = document.getElementById('vasCancel');
        if (vasCancel && vasCancel.parentNode) {
            vasCancel.parentNode.insertBefore(vasHlRow, vasCancel);
        }
    }());

    // -----------------------------------------------------------
    // Verse notes — text annotations per verse
    // -----------------------------------------------------------
    var NOTES_KEY = 'bb_notes';

    function loadNotes() {
        try { return JSON.parse(localStorage.getItem(NOTES_KEY) || '{}'); } catch (e) { return {}; }
    }
    function saveNotes(n) {
        try { localStorage.setItem(NOTES_KEY, JSON.stringify(n)); } catch (e) {}
        syncPushDebounced();
    }

    var NOTE_SVG = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>';

    // Show note indicator on verses that have notes
    (function () {
        if (typeof READER_BOOK === 'undefined') return;
        var notes = loadNotes();
        document.querySelectorAll('.verse').forEach(function (el) {
            var key = hlKey(el.dataset.verse);
            if (notes[key]) {
                var indicator = document.createElement('span');
                indicator.className = 'verse-note-indicator';
                indicator.title = notes[key];
                indicator.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
                var vnum = el.querySelector('.vnum');
                if (vnum) vnum.after(indicator);
            }
        });
    }());

    // Add note button to desktop action strip
    document.querySelectorAll('.verse').forEach(function (el) {
        var actions = el.querySelector('.verse-actions');
        if (!actions || typeof READER_BOOK === 'undefined') return;

        var noteBtn = document.createElement('button');
        noteBtn.className = 'va-btn va-note';
        noteBtn.title = 'Add note';
        noteBtn.innerHTML = NOTE_SVG;
        actions.appendChild(noteBtn);

        var notes = loadNotes();
        if (notes[hlKey(el.dataset.verse)]) noteBtn.classList.add('va-note-active');

        noteBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            openNoteEditor(el);
        });
    });

    // Add note to mobile action sheet
    (function () {
        var vasNote = document.createElement('button');
        vasNote.className = 'vas-action-btn';
        vasNote.id = 'vasNote';
        vasNote.innerHTML = NOTE_SVG + ' Add Note';
        var vasCancel = document.getElementById('vasCancel');
        if (vasCancel && vasCancel.parentNode) {
            vasCancel.parentNode.insertBefore(vasNote, vasCancel);
        }
        vasNote.addEventListener('click', function () {
            var sel = _vasActiveVerse || document.querySelector('.verse-selected') || selectedVerse;
            if (!sel) { closeVasSheet(); return; }
            closeVasSheet();
            setTimeout(function () { openNoteEditor(sel); }, 200);
        });
    }());

    function openNoteEditor(verseEl) {
        var existing = document.getElementById('noteEditor');
        if (existing) existing.remove();
        var existingBd = document.getElementById('noteEditorBd');
        if (existingBd) existingBd.remove();

        var key = hlKey(verseEl.dataset.verse);
        var notes = loadNotes();
        var current = notes[key] || '';
        var refLabel = (typeof READER_BOOK_DISPLAY !== 'undefined' ? READER_BOOK_DISPLAY
                       : (typeof READER_BOOK !== 'undefined' ? bookSlugToTitle(READER_BOOK) : '')) +
                       ' ' + (typeof READER_CHAPTER !== 'undefined' ? READER_CHAPTER : '') +
                       ':' + verseEl.dataset.verse;

        var bd = document.createElement('div');
        bd.id = 'noteEditorBd';
        bd.className = 'sync-modal-backdrop';

        var modal = document.createElement('div');
        modal.id = 'noteEditor';
        modal.className = 'sync-modal';
        modal.innerHTML =
            '<div class="sync-modal-header"><span>' + escH(refLabel) + '</span><button class="sync-modal-close" id="noteClose">&times;</button></div>' +
            '<div class="sync-modal-body">' +
            '<textarea class="note-textarea" id="noteText" placeholder="Write your note…" rows="5">' + escH(current) + '</textarea>' +
            '<button class="sync-btn sync-btn-primary" id="noteSave">Save Note</button>' +
            (current ? '<button class="sync-btn sync-btn-danger" id="noteDelete">Delete Note</button>' : '') +
            '</div>';

        document.body.appendChild(bd);
        document.body.appendChild(modal);
        document.getElementById('noteText').focus();

        bd.addEventListener('click', function () { modal.remove(); bd.remove(); });
        document.getElementById('noteClose').addEventListener('click', function () { modal.remove(); bd.remove(); });

        document.getElementById('noteSave').addEventListener('click', function () {
            var text = document.getElementById('noteText').value.trim();
            var notes = loadNotes();
            if (text) {
                notes[key] = text;
                // Add/update indicator
                var ind = verseEl.querySelector('.verse-note-indicator');
                if (!ind) {
                    ind = document.createElement('span');
                    ind.className = 'verse-note-indicator';
                    var vnum = verseEl.querySelector('.vnum');
                    if (vnum) vnum.after(ind);
                }
                ind.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
                ind.title = text;
            } else {
                delete notes[key];
                var ind = verseEl.querySelector('.verse-note-indicator');
                if (ind) ind.remove();
            }
            saveNotes(notes);
            modal.remove(); bd.remove();
        });

        var delBtn = document.getElementById('noteDelete');
        if (delBtn) {
            delBtn.addEventListener('click', function () {
                var notes = loadNotes();
                delete notes[key];
                saveNotes(notes);
                var ind = verseEl.querySelector('.verse-note-indicator');
                if (ind) ind.remove();
                modal.remove(); bd.remove();
            });
        }
    }

    // -----------------------------------------------------------
    // Highlight & Notes Browser
    // -----------------------------------------------------------
    function openHighlightBrowser() {
        var existing = document.getElementById('hlBrowser');
        if (existing) existing.remove();
        var existingBd = document.getElementById('hlBrowserBd');
        if (existingBd) existingBd.remove();

        var hls = loadHighlights();
        var notes = loadNotes();

        // Merge keys
        var allKeys = {};
        Object.keys(hls).forEach(function (k) { allKeys[k] = true; });
        Object.keys(notes).forEach(function (k) { allKeys[k] = true; });

        // Parse and group by book
        var byBook = {};
        Object.keys(allKeys).forEach(function (k) {
            var parts = k.split(':');
            if (parts.length < 3) return;
            var bookSlug = parts[0], ch = parts[1], vs = parts[2];
            var bookTitle = bookSlugToTitle(bookSlug);
            if (!byBook[bookSlug]) byBook[bookSlug] = { title: bookTitle, items: [] };
            byBook[bookSlug].items.push({
                key: k,
                ref: bookTitle + ' ' + ch + ':' + vs,
                url: '/read/' + bookSlug + '/' + ch + '/' + vs,
                color: hls[k] || null,
                note: notes[k] || null,
                ch: parseInt(ch),
                vs: parseInt(vs)
            });
        });

        // Sort items within each book
        Object.keys(byBook).forEach(function (b) {
            byBook[b].items.sort(function (a, b) { return a.ch === b.ch ? a.vs - b.vs : a.ch - b.ch; });
        });

        var totalItems = Object.keys(allKeys).length;

        var bd = document.createElement('div');
        bd.id = 'hlBrowserBd';
        bd.className = 'sync-modal-backdrop';

        var modal = document.createElement('div');
        modal.id = 'hlBrowser';
        modal.className = 'sync-modal hl-browser-modal';

        var html = '<div class="sync-modal-header"><span>My Highlights & Notes</span><button class="sync-modal-close" id="hlBrowserClose">&times;</button></div>';
        html += '<div class="sync-modal-body hl-browser-body">';

        if (totalItems === 0) {
            html += '<p class="sync-hint">No highlights or notes yet. Highlight verses or add notes while reading.</p>';
        } else {
            var bookSlugs = Object.keys(byBook).sort();
            for (var bi = 0; bi < bookSlugs.length; bi++) {
                var book = byBook[bookSlugs[bi]];
                html += '<div class="hl-browser-book">' + escH(book.title) + '</div>';
                for (var ii = 0; ii < book.items.length; ii++) {
                    var item = book.items[ii];
                    html += '<a href="' + item.url + '" class="hl-browser-item">';
                    if (item.color) html += '<span class="hl-browser-dot verse-hl-' + item.color + '"></span>';
                    html += '<span class="hl-browser-ref">' + escH(item.ref) + '</span>';
                    if (item.note) html += '<span class="hl-browser-note">' + escH(item.note.length > 60 ? item.note.substring(0, 60) + '…' : item.note) + '</span>';
                    html += '</a>';
                }
            }
        }

        html += '</div>';
        modal.innerHTML = html;

        document.body.appendChild(bd);
        document.body.appendChild(modal);

        bd.addEventListener('click', function () { modal.remove(); bd.remove(); });
        document.getElementById('hlBrowserClose').addEventListener('click', function () { modal.remove(); bd.remove(); });
    }

    // Inject highlight browser button into header (before sync button)
    (function () {
        var syncBtn = document.getElementById('syncHeaderBtn');
        var themeBtn = document.getElementById('themeToggle');
        var anchor = syncBtn || themeBtn;
        if (!anchor) return;

        var btn = document.createElement('button');
        btn.id = 'hlBrowserBtn';
        btn.className = 'sync-header-btn';
        btn.setAttribute('aria-label', 'My Highlights & Notes');
        btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l-6 6v3h9l3-3"/><path d="M22 12l-4.6 4.6a2 2 0 0 1-2.8 0l-5.2-5.2a2 2 0 0 1 0-2.8L14 4"/></svg>';
        anchor.parentNode.insertBefore(btn, anchor);
        btn.addEventListener('click', openHighlightBrowser);
    }());

    // Scroll active chapter into sidebar view
    var activeChapter = document.querySelector('.sidebar-chapter.active');
    if (activeChapter) activeChapter.scrollIntoView({ block: 'nearest' });

    // -----------------------------------------------------------
    // Search-as-you-type (typeahead)
    // -----------------------------------------------------------
    document.querySelectorAll('.reader-search-form').forEach(function (form) {
        var input = form.querySelector('.reader-search-input');
        if (!input) return;

        var dropdown = document.createElement('div');
        dropdown.className = 'search-suggest-dropdown';
        dropdown.style.display = 'none';
        form.style.position = 'relative';
        form.appendChild(dropdown);

        var debounceTimer = null;
        var activeIdx = -1;

        input.addEventListener('input', function () {
            var q = input.value.trim();
            clearTimeout(debounceTimer);
            if (q.length < 2) { dropdown.style.display = 'none'; return; }
            debounceTimer = setTimeout(function () {
                var v = form.querySelector('input[name="v"]');
                var url = '/reader/search-suggest.php?q=' + encodeURIComponent(q);
                if (v && v.value) url += '&v=' + encodeURIComponent(v.value);
                if (typeof READER_BOOK !== 'undefined') url += '&book=' + encodeURIComponent(READER_BOOK);
                fetch(url).then(function (r) {
                    if (r.status === 429) { dropdown.style.display = 'none'; return null; }
                    return r.json();
                }).then(function (items) {
                    if (!items) return;
                    if (!items.length) { dropdown.style.display = 'none'; return; }
                    activeIdx = -1;
                    dropdown.innerHTML = '';
                    items.forEach(function (item, i) {
                        var a = document.createElement('a');
                        a.href = item.url;
                        a.className = 'search-suggest-item' + (item.type === 'ref' ? ' search-suggest-ref' : '');
                        a.dataset.idx = i;
                        var label = '<span class="ssi-label">' + escHtml(item.label) + '</span>';
                        if (item.type === 'ref') label = '<span class="ssi-go">Go to</span> ' + label;
                        if (item.text) label += '<span class="ssi-text">' + escHtml(item.text) + '</span>';
                        a.innerHTML = label;
                        dropdown.appendChild(a);
                    });
                    dropdown.style.display = 'block';
                }).catch(function () { dropdown.style.display = 'none'; });
            }, 200);
        });

        // Keyboard navigation
        input.addEventListener('keydown', function (e) {
            var items = dropdown.querySelectorAll('.search-suggest-item');
            if (!items.length || dropdown.style.display === 'none') return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIdx = Math.min(activeIdx + 1, items.length - 1);
                items.forEach(function (it, i) { it.classList.toggle('active', i === activeIdx); });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIdx = Math.max(activeIdx - 1, 0);
                items.forEach(function (it, i) { it.classList.toggle('active', i === activeIdx); });
            } else if (e.key === 'Enter' && activeIdx >= 0) {
                e.preventDefault();
                items[activeIdx].click();
            } else if (e.key === 'Escape') {
                dropdown.style.display = 'none';
            }
        });

        // Close on click outside
        document.addEventListener('click', function (e) {
            if (!form.contains(e.target)) dropdown.style.display = 'none';
        });
    });

    // -----------------------------------------------------------
    // Mobile search expand/collapse
    // -----------------------------------------------------------
    var mobileSearchToggle = document.getElementById('mobileSearchToggle');
    var mobileSearchClose = document.getElementById('mobileSearchClose');
    if (mobileSearchToggle) {
        var headerCenter = mobileSearchToggle.closest('.reader-header-center');
        mobileSearchToggle.addEventListener('click', function () {
            headerCenter.classList.add('search-expanded');
            var inp = headerCenter.querySelector('.reader-search-input');
            if (inp) inp.focus();
        });
        if (mobileSearchClose) {
            mobileSearchClose.addEventListener('click', function () {
                headerCenter.classList.remove('search-expanded');
            });
        }
        // Also close on Escape
        headerCenter.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && headerCenter.classList.contains('search-expanded')) {
                headerCenter.classList.remove('search-expanded');
            }
        });
    }

    // -----------------------------------------------------------
    // Cloud Sync
    // -----------------------------------------------------------
    var SYNC_KEY = 'bb_sync_code';
    var SYNC_TOKEN_KEY = 'bb_sync_token';
    var SYNC_WORDS = [
        'GRACE','FAITH','PEACE','LIGHT','GLORY','TRUTH','MERCY',
        'BREAD','CROSS','CROWN','PSALM','HEART','HOPE',
        'LAMB','WORD','VINE','ROCK','RISEN','SAVED','AMEN'
    ];

    function generateSyncCode() {
        var w = SYNC_WORDS[Math.floor(Math.random() * SYNC_WORDS.length)];
        return w + '-' + (1000 + Math.floor(Math.random() * 9000));
    }
    function getSyncCode() { return localStorage.getItem(SYNC_KEY); }
    function setSyncCode(c) { localStorage.setItem(SYNC_KEY, c); }
    function getSyncToken() { return localStorage.getItem(SYNC_TOKEN_KEY); }
    function setSyncToken(t) { if (t) localStorage.setItem(SYNC_TOKEN_KEY, t); }

    function gatherSyncData() {
        var d = {};
        try { d.highlights = JSON.parse(localStorage.getItem('bb_highlights') || '{}'); } catch(e) { d.highlights = {}; }
        try { d.notes = JSON.parse(localStorage.getItem('bb_notes') || '{}'); } catch(e) { d.notes = {}; }
        try { d.last_read = JSON.parse(localStorage.getItem('bb_last_read') || 'null'); } catch(e) { d.last_read = null; }
        d.plans = {};
        for (var i = 0; i < localStorage.length; i++) {
            var k = localStorage.key(i);
            if (k && k.indexOf('bb_plan_') === 0) {
                try { d.plans[k] = JSON.parse(localStorage.getItem(k)); } catch(e) {}
            }
        }
        d.theme = localStorage.getItem('bb_theme') || null;
        return d;
    }

    function applySyncData(d) {
        if (!d) return;
        if (d.highlights) {
            var local = {};
            try { local = JSON.parse(localStorage.getItem('bb_highlights') || '{}'); } catch(e) {}
            localStorage.setItem('bb_highlights', JSON.stringify(Object.assign({}, local, d.highlights)));
        }
        if (d.notes) {
            var localNotes = {};
            try { localNotes = JSON.parse(localStorage.getItem('bb_notes') || '{}'); } catch(e) {}
            localStorage.setItem('bb_notes', JSON.stringify(Object.assign({}, localNotes, d.notes)));
        }
        if (d.last_read) localStorage.setItem('bb_last_read', JSON.stringify(d.last_read));
        if (d.plans) {
            Object.keys(d.plans).forEach(function(k) {
                localStorage.setItem(k, JSON.stringify(d.plans[k]));
            });
        }
        if (d.theme && !localStorage.getItem('bb_theme')) localStorage.setItem('bb_theme', d.theme);
    }

    function syncPull(cb) {
        var code = getSyncCode();
        if (!code) return;
        fetch('/reader/sync.php?code=' + encodeURIComponent(code))
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.status === 'ok' && res.data) applySyncData(res.data);
                if (cb) cb(res);
            }).catch(function() { if (cb) cb(null); });
    }

    function syncPush() {
        var code = getSyncCode();
        if (!code) return;
        var payload = {code: code, data: gatherSyncData()};
        var token = getSyncToken();
        if (token) payload.token = token;
        fetch('/reader/sync.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        }).then(function(r) {
            if (r.status === 403) {
                // Code claimed by someone else — generate a new one
                var newCode = generateSyncCode();
                localStorage.removeItem(SYNC_TOKEN_KEY);
                setSyncCode(newCode);
                syncPush();
                return;
            }
            return r.json();
        }).then(function(res) {
            if (res && res.token) setSyncToken(res.token);
        }).catch(function(){});
    }

    var _syncTimer = null;
    function syncPushDebounced() {
        if (!getSyncCode()) return;
        clearTimeout(_syncTimer);
        _syncTimer = setTimeout(syncPush, 3000);
    }

    // Hook into highlight saves
    var _origSaveHL = saveHighlights;
    saveHighlights = function(h) { _origSaveHL(h); syncPushDebounced(); };

    // Push on read page (last_read just changed)
    if (typeof READER_BOOK !== 'undefined' && getSyncCode()) syncPushDebounced();

    // Pull on page load, then push to ensure we have a claim token
    if (getSyncCode()) {
        syncPull();
        if (!getSyncToken()) syncPush();
    }

    // --- Sync UI ---
    function escH(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function openSyncModal() {
        var old = document.getElementById('syncModal');
        if (old) old.remove();
        var oldB = document.getElementById('syncBackdrop');
        if (oldB) oldB.remove();

        var bd = document.createElement('div');
        bd.id = 'syncBackdrop';
        bd.className = 'sync-modal-backdrop';

        var m = document.createElement('div');
        m.id = 'syncModal';
        m.className = 'sync-modal';

        var code = getSyncCode();

        var token = getSyncToken();
        if (code) {
            m.innerHTML =
                '<div class="sync-modal-header"><span>Cloud Sync</span><button class="sync-modal-close" id="syncClose">&times;</button></div>' +
                '<div class="sync-modal-body">' +
                '<p class="sync-status-label">Your sync code</p>' +
                '<div class="sync-code-display">' + escH(code) + '</div>' +
                (token ? '<p class="sync-status-label" style="margin-top:8px">Your sync key</p><div class="sync-code-display" style="font-size:1.1em;letter-spacing:3px">' + escH(token) + '</div>' : '') +
                '<p class="sync-hint">Enter the code and key on another device to sync your highlights and reading progress.</p>' +
                '<button class="sync-btn sync-btn-primary" id="syncNowBtn">Sync Now</button>' +
                '<button class="sync-btn sync-btn-secondary" id="syncCopyBtn">Copy Code & Key</button>' +
                '<button class="sync-btn sync-btn-danger" id="syncDisconnectBtn">Disconnect</button>' +
                '</div>';
        } else {
            m.innerHTML =
                '<div class="sync-modal-header"><span>Cloud Sync</span><button class="sync-modal-close" id="syncClose">&times;</button></div>' +
                '<div class="sync-modal-body">' +
                '<p class="sync-hint">Sync highlights and reading progress across devices. No account needed — just a code.</p>' +
                '<button class="sync-btn sync-btn-primary" id="syncGenBtn">Generate New Code</button>' +
                '<div class="sync-divider"><span>or connect with existing code</span></div>' +
                '<input class="sync-code-input" id="syncCodeInput" placeholder="Code (e.g. GRACE-4821)" maxlength="15">' +
                '<input class="sync-code-input" id="syncKeyInput" placeholder="Key (e.g. A7F3BC)" maxlength="6" style="margin-top:6px;text-transform:uppercase">' +
                '<button class="sync-btn sync-btn-secondary" id="syncConnBtn">Connect</button>' +
                '</div>';
        }

        document.body.appendChild(bd);
        document.body.appendChild(m);

        bd.addEventListener('click', closeSyncModal);
        document.getElementById('syncClose').addEventListener('click', closeSyncModal);

        if (code) {
            document.getElementById('syncNowBtn').addEventListener('click', function() {
                this.textContent = 'Syncing…';
                var btn = this;
                syncPull(function() { syncPush(); btn.textContent = 'Done!'; setTimeout(function() { closeSyncModal(); openSyncModal(); }, 600); });
            });
            document.getElementById('syncCopyBtn').addEventListener('click', function() {
                var text = code;
                if (token) text += '\nKey: ' + token;
                navigator.clipboard.writeText(text).catch(function(){});
                this.textContent = 'Copied!';
            });
            document.getElementById('syncDisconnectBtn').addEventListener('click', function() {
                localStorage.removeItem(SYNC_KEY);
                localStorage.removeItem(SYNC_TOKEN_KEY);
                var hb = document.getElementById('syncHeaderBtn');
                if (hb) hb.classList.remove('sync-active');
                closeSyncModal();
            });
        } else {
            document.getElementById('syncGenBtn').addEventListener('click', function() {
                var newCode = generateSyncCode();
                var btn = this;
                btn.textContent = 'Creating…';
                localStorage.removeItem(SYNC_TOKEN_KEY);
                setSyncCode(newCode);
                var payload = {code: newCode, data: gatherSyncData()};
                fetch('/reader/sync.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                }).then(function(r) {
                    if (r.status === 403) {
                        // Code already claimed — try again with a different code
                        newCode = generateSyncCode();
                        setSyncCode(newCode);
                        return fetch('/reader/sync.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({code: newCode, data: gatherSyncData()})
                        }).then(function(r2) { return r2.json(); });
                    }
                    return r.json();
                }).then(function(res) {
                    if (res && res.token) setSyncToken(res.token);
                    var hb = document.getElementById('syncHeaderBtn');
                    if (hb) hb.classList.add('sync-active');
                    closeSyncModal();
                    openSyncModal();
                }).catch(function() {
                    closeSyncModal();
                    openSyncModal();
                });
            });
            document.getElementById('syncConnBtn').addEventListener('click', function() {
                var inp = document.getElementById('syncCodeInput');
                var keyInp = document.getElementById('syncKeyInput');
                var val = (inp.value || '').trim().toUpperCase();
                var key = (keyInp.value || '').trim().toUpperCase();
                if (!/^[A-Z]{3,10}-\d{4}$/.test(val)) { inp.classList.add('sync-input-error'); return; }
                if (!/^[A-F0-9]{6}$/.test(key)) { keyInp.classList.add('sync-input-error'); return; }
                setSyncCode(val);
                setSyncToken(key);
                syncPull(function() {
                    var hb = document.getElementById('syncHeaderBtn');
                    if (hb) hb.classList.add('sync-active');
                    closeSyncModal();
                    openSyncModal();
                });
            });
        }
    }

    function closeSyncModal() {
        var m = document.getElementById('syncModal');
        var b = document.getElementById('syncBackdrop');
        if (m) m.remove();
        if (b) b.remove();
    }

    // Inject sync button into header
    (function() {
        var themeBtn = document.getElementById('themeToggle');
        if (!themeBtn) return;
        var btn = document.createElement('button');
        btn.id = 'syncHeaderBtn';
        btn.className = 'sync-header-btn';
        btn.setAttribute('aria-label', 'Cloud Sync');
        btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6"/><path d="M2.5 22v-6h6"/><path d="M2 11.5a10 10 0 0 1 18.8-4.3"/><path d="M22 12.5a10 10 0 0 1-18.8 4.2"/></svg>';
        if (getSyncCode()) btn.classList.add('sync-active');
        themeBtn.parentNode.insertBefore(btn, themeBtn);
        btn.addEventListener('click', openSyncModal);
    })();

    // -----------------------------------------------------------
    // Service Worker registration (offline support)
    // -----------------------------------------------------------
    if ('serviceWorker' in navigator) {
        // Derive base path from this script's URL (assets/reader.min.js → parent dir)
        var scripts = document.querySelectorAll('script[src*="reader"]');
        var swRoot = '';
        for (var si = 0; si < scripts.length; si++) {
            var m = scripts[si].src.match(/(.*)\/assets\/reader/);
            if (m) { swRoot = m[1]; break; }
        }
        navigator.serviceWorker.register(swRoot + '/sw.js', { scope: swRoot + '/' }).catch(function () {});
    }

})();
