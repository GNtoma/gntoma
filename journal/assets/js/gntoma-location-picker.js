/**
 * Sélecteur de lieu GeoNames (proxy backend, pas de username côté client).
 */
(function () {
    'use strict';

    function debounce(fn, ms) {
        var t;
        return function () {
            var ctx = this;
            var args = arguments;
            clearTimeout(t);
            t = setTimeout(function () {
                fn.apply(ctx, args);
            }, ms);
        };
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function GntomaLocationPicker(root) {
        this.root = root;
        this.input = root.querySelector('[data-gntoma-loc-input]');
        this.list = root.querySelector('[data-gntoma-loc-list]');
        this.hidden = root.querySelector('[data-gntoma-loc-id]');
        this.selectedBox = root.querySelector('[data-gntoma-loc-selected]');
        this.loader = root.querySelector('[data-gntoma-loc-loader]');
        this.searchUrl = root.getAttribute('data-search-url') || 'geonames/search.php';
        this.country = (root.getAttribute('data-country') || '').trim().toUpperCase();
        this.minLen = parseInt(root.getAttribute('data-min-len') || '2', 10);
        this.labels = {
            empty: root.getAttribute('data-msg-empty') || 'No results',
            error: root.getAttribute('data-msg-error') || 'Search unavailable',
            network: root.getAttribute('data-msg-network') || 'Network error',
            selected: root.getAttribute('data-msg-selected') || 'Selected',
            hint: root.getAttribute('data-msg-hint') || 'Choose a place from the list',
        };
        this.activeIndex = -1;
        this.results = [];
        this.abort = null;
        this.bind();
    }

    GntomaLocationPicker.prototype.bind = function () {
        var self = this;
        if (!this.input || !this.list || !this.hidden) {
            return;
        }

        var initialId = parseInt(
            this.root.getAttribute('data-initial-geoname-id') || this.hidden.value || '0',
            10
        );
        var initialLabel = (this.input.getAttribute('data-initial-label') || '').trim();
        if (initialId > 0 && initialLabel !== '') {
            this.setSelection(initialId, initialLabel, false);
            this.input.value = initialLabel;
        }

        var form = this.root.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                var q = (self.input.value || '').trim();
                var lastLabel = (self.input.getAttribute('data-last-label') || '').trim();
                var hiddenVal = parseInt(self.hidden.value || '0', 10);
                if (
                    initialId > 0 &&
                    hiddenVal < 1 &&
                    (q === '' || q === initialLabel || (lastLabel !== '' && q === lastLabel))
                ) {
                    self.hidden.value = String(initialId);
                }
            });
        }

        this.input.addEventListener(
            'input',
            debounce(function () {
                self.onQueryChange();
            }, 320)
        );

        this.input.addEventListener('keydown', function (e) {
            self.onKeydown(e);
        });

        this.input.addEventListener('focus', function () {
            if (self.results.length) {
                self.root.classList.add('is-open');
            }
        });

        document.addEventListener('click', function (e) {
            if (!self.root.contains(e.target)) {
                self.close();
            }
        });

        this.list.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-geoname-id]');
            if (!btn) {
                return;
            }
            var id = parseInt(btn.getAttribute('data-geoname-id') || '0', 10);
            var label = btn.getAttribute('data-label') || '';
            self.setSelection(id, label, true);
            self.close();
        });
    };

    GntomaLocationPicker.prototype.onQueryChange = function () {
        var q = (this.input.value || '').trim();
        if (this.hidden.value && q !== (this.input.getAttribute('data-last-label') || '')) {
            this.hidden.value = '';
            this.root.classList.remove('has-selection');
            if (this.selectedBox) {
                this.selectedBox.textContent = '';
            }
        }

        if (q.length < this.minLen) {
            this.close();
            this.renderMessage('');
            return;
        }

        this.search(q);
    };

    GntomaLocationPicker.prototype.search = function (q) {
        var self = this;
        if (this.abort) {
            this.abort.abort();
        }
        this.abort = new AbortController();
        this.root.classList.add('is-loading');

        var url =
            this.searchUrl +
            '?q=' +
            encodeURIComponent(q) +
            (this.country.length === 2 ? '&country=' + encodeURIComponent(this.country) : '');

        fetch(url, {
            credentials: 'same-origin',
            signal: this.abort.signal,
            headers: { Accept: 'application/json' },
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, status: res.status, data: data };
                });
            })
            .then(function (pack) {
                self.root.classList.remove('is-loading');
                if (!pack.ok || !pack.data || !pack.data.ok) {
                    var msg =
                        (pack.data && pack.data.message) ||
                        (pack.status === 429 ? self.labels.error : self.labels.error);
                    self.results = [];
                    self.renderMessage(msg, true);
                    self.open();
                    return;
                }
                self.results = pack.data.results || [];
                self.activeIndex = -1;
                if (!self.results.length) {
                    self.renderMessage(self.labels.empty);
                } else {
                    self.renderResults();
                }
                self.open();
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') {
                    return;
                }
                self.root.classList.remove('is-loading');
                self.results = [];
                self.renderMessage(self.labels.network, true);
                self.open();
            });
    };

    GntomaLocationPicker.prototype.renderResults = function () {
        var html = '';
        for (var i = 0; i < this.results.length; i++) {
            var r = this.results[i];
            var id = r.geoname_id || r.geonameId;
            var label = r.label || r.name || '';
            var meta = [];
            if (r.admin1) {
                meta.push(r.admin1);
            }
            if (r.country_name || r.countryName) {
                meta.push(r.country_name || r.countryName);
            }
            if (r.population) {
                meta.push(
                    new Intl.NumberFormat(undefined, { notation: 'compact' }).format(r.population)
                );
            }
            html +=
                '<button type="button" class="gntoma-loc__option' +
                (i === this.activeIndex ? ' is-active' : '') +
                '" data-geoname-id="' +
                escapeHtml(String(id)) +
                '" data-label="' +
                escapeHtml(label) +
                '">' +
                '<div class="gntoma-loc__option-title">' +
                escapeHtml(r.name || label) +
                '</div>' +
                (meta.length
                    ? '<div class="gntoma-loc__option-meta">' + escapeHtml(meta.join(' · ')) + '</div>'
                    : '') +
                '</button>';
        }
        this.list.innerHTML = html;
    };

    GntomaLocationPicker.prototype.renderMessage = function (text, isError) {
        if (!text) {
            this.list.innerHTML = '';
            return;
        }
        var cls = isError ? 'gntoma-loc__error' : 'gntoma-loc__empty';
        this.list.innerHTML = '<div class="' + cls + '">' + escapeHtml(text) + '</div>';
    };

    GntomaLocationPicker.prototype.onKeydown = function (e) {
        if (!this.results.length && e.key !== 'Escape') {
            return;
        }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            this.activeIndex = Math.min(this.activeIndex + 1, this.results.length - 1);
            this.renderResults();
            this.highlightActive();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            this.activeIndex = Math.max(this.activeIndex - 1, 0);
            this.renderResults();
            this.highlightActive();
        } else if (e.key === 'Enter' && this.activeIndex >= 0) {
            e.preventDefault();
            var r = this.results[this.activeIndex];
            var id = r.geoname_id || r.geonameId;
            var label = r.label || r.name || '';
            this.setSelection(id, label, true);
            this.close();
        } else if (e.key === 'Escape') {
            this.close();
        }
    };

    GntomaLocationPicker.prototype.highlightActive = function () {
        var opts = this.list.querySelectorAll('.gntoma-loc__option');
        for (var i = 0; i < opts.length; i++) {
            opts[i].classList.toggle('is-active', i === this.activeIndex);
            if (i === this.activeIndex) {
                opts[i].scrollIntoView({ block: 'nearest' });
            }
        }
    };

    GntomaLocationPicker.prototype.setSelection = function (id, label, syncInput) {
        this.hidden.value = String(id);
        this.input.setAttribute('data-last-label', label);
        if (syncInput) {
            this.input.value = label;
        }
        this.root.classList.add('has-selection');
        if (this.selectedBox) {
            this.selectedBox.textContent = this.labels.selected + ' : ' + label;
        }
    };

    GntomaLocationPicker.prototype.open = function () {
        this.root.classList.add('is-open');
    };

    GntomaLocationPicker.prototype.close = function () {
        this.root.classList.remove('is-open');
    };

    function initAll() {
        var nodes = document.querySelectorAll('[data-gntoma-location-picker]');
        for (var i = 0; i < nodes.length; i++) {
            if (!nodes[i].__gntomaLoc) {
                nodes[i].__gntomaLoc = new GntomaLocationPicker(nodes[i]);
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    window.GntomaLocationPicker = GntomaLocationPicker;
    window.gntomaInitLocationPickers = initAll;
})();
