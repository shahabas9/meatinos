(function ($) {
    'use strict';

    var root = document.documentElement;
    var storedTheme = window.localStorage.getItem('meatinos_theme');
    if (storedTheme === 'dark') {
        root.setAttribute('data-theme', 'dark');
    }

    function updateThemeIcon() {
        var dark = root.getAttribute('data-theme') === 'dark';
        $('#themeToggle i').attr('class', dark ? 'bi bi-sun' : 'bi bi-moon-stars');
    }
    updateThemeIcon();

    $('#themeToggle').on('click', function () {
        var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        if (next === 'dark') {
            root.setAttribute('data-theme', 'dark');
        } else {
            root.removeAttribute('data-theme');
        }
        window.localStorage.setItem('meatinos_theme', next);
        updateThemeIcon();
    });

    $('.password-toggle').on('click', function () {
        var input = $(this).siblings('input');
        var show = input.attr('type') === 'password';
        input.attr('type', show ? 'text' : 'password');
        $(this).find('i').attr('class', show ? 'bi bi-eye-slash' : 'bi bi-eye');
    });

    $('form.needs-validation').on('submit', function (event) {
        if (!this.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        $(this).addClass('was-validated');
    });

    var autoModal = document.querySelector('.modal[data-auto-open="true"]') || document.getElementById('recordModal') || document.getElementById('userModal') || document.getElementById('documentModal');
    if (autoModal && autoModal.dataset.autoOpen === 'true' && window.bootstrap) {
        window.bootstrap.Modal.getOrCreateInstance(autoModal).show();
    }

    $(document).on('keydown', function (event) {
        if (event.key === '/' && !/input|textarea|select/i.test(document.activeElement.tagName)) {
            event.preventDefault();
            $('.global-search input, .module-search input').first().trigger('focus');
        }
        if (event.key === 'Escape' && document.activeElement && /input|textarea/i.test(document.activeElement.tagName)) {
            document.activeElement.blur();
        }
    });

    $('.alert').not('.alert-danger').each(function () {
        var element = this;
        window.setTimeout(function () {
            if (window.bootstrap) {
                window.bootstrap.Alert.getOrCreateInstance(element).close();
            }
        }, 7000);
    });

    $('[data-ai-prompt]').on('click', function () {
        $('#aiQuestion').val($(this).data('ai-prompt')).trigger('focus');
    });

    function updateStageMeasurementFields() {
        var form = $('[data-module-form="production_stage_measurements"]');
        if (!form.length) return;
        var stage = form.find('[name="stage"]').val() || '';
        form.find('[data-stage-values]').each(function () {
            var field = $(this);
            var allowed = String(field.data('stage-values') || '').split(',');
            var visible = stage !== '' && allowed.indexOf(stage) !== -1;
            field.toggleClass('d-none', !visible);
            field.find('input,select,textarea').prop('disabled', !visible);
        });
        form.find('[data-stage-section]').each(function () {
            var section = $(this);
            var name = String(section.data('stage-section') || '');
            var fields = form.find('[data-field-section="' + name.replace(/"/g, '\\"') + '"]');
            section.toggleClass('d-none', fields.length > 0 && fields.filter(':not(.d-none)').length === 0);
        });
    }
    $(document).on('change', '[data-module-form="production_stage_measurements"] [name="stage"]', updateStageMeasurementFields);
    $('#recordModal').on('shown.bs.modal', updateStageMeasurementFields);
    updateStageMeasurementFields();

    function recalculateBulkRates() {
        var form = $('[data-bulk-rate-form]');
        if (!form.length) return;
        var base = parseFloat($('#baseWholeBirdPrice').val()) || 0;
        var defaultMargin = parseFloat($('#defaultRateMargin').val()) || 0;
        form.find('[data-rate-row]').each(function () {
            var row = $(this);
            var factor = parseFloat(row.find('[data-rate-factor]').val()) || 0;
            var marginInput = row.find('[data-rate-margin]').val();
            var margin = marginInput === '' ? defaultMargin : (parseFloat(marginInput) || 0);
            var fixed = parseFloat(row.find('[data-rate-fixed]').val()) || 0;
            var calculated = Math.round((base * factor * (1 + margin / 100) + fixed) * 100) / 100;
            row.find('[data-rate-calculated]').text('₹' + calculated.toFixed(2));
            var finalInput = row.find('[data-rate-final]');
            if (finalInput.attr('data-rate-manual') !== '1') finalInput.val(calculated.toFixed(2));
        });
    }
    $(document).on('input', '#baseWholeBirdPrice,#defaultRateMargin,[data-rate-factor],[data-rate-margin],[data-rate-fixed]', recalculateBulkRates);
    $(document).on('input', '[data-rate-final]', function () { $(this).attr('data-rate-manual', '1'); });

    function writeLocation(form, position) {
        form.find('[name="latitude"]').val(position.coords.latitude);
        form.find('[name="longitude"]').val(position.coords.longitude);
        form.find('[name="accuracy_meters"]').val(position.coords.accuracy || '');
        form.find('[data-location-status]').text('Location captured (accuracy ' + Math.round(position.coords.accuracy || 0) + ' m).');
    }
    $(document).on('click', '[data-capture-location]', function () {
        var form = $(this).closest('[data-location-form]');
        var status = form.find('[data-location-status]');
        if (!navigator.geolocation) { status.text('Location is not supported by this device.'); return; }
        status.text('Capturing secure GPS location…');
        navigator.geolocation.getCurrentPosition(function (position) { writeLocation(form, position); }, function (error) {
            status.text(error.code === 1 ? 'Location permission is required.' : 'Unable to capture location. Move outdoors and retry.');
        }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 15000 });
    });

    var tracker = $('[data-sales-tracker]');
    if (tracker.length && navigator.geolocation) {
        var trackingStatus = tracker.find('[data-tracking-status]');
        var pingInFlight = false;
        var pingController = null;
        var ping = function () {
            if (pingInFlight) return;
            pingInFlight = true;
            navigator.geolocation.getCurrentPosition(function (position) {
                var body = new URLSearchParams();
                body.set('_token', String(tracker.data('token')));
                body.set('latitude', position.coords.latitude);
                body.set('longitude', position.coords.longitude);
                body.set('accuracy_meters', position.coords.accuracy || '');
                pingController = new AbortController();
                fetch(String(tracker.data('ping-url')), { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' }, credentials: 'same-origin', body: body.toString(), signal: pingController.signal })
                    .then(function (response) {
                        if (!response.ok) throw new Error('Location update failed.');
                        return response.json();
                    })
                    .then(function (result) { trackingStatus.text(result.ok ? 'Location updated at ' + new Date().toLocaleTimeString() + '.' : result.message); })
                    .catch(function (error) { if (error.name !== 'AbortError') trackingStatus.text('Location update will retry automatically.'); })
                    .finally(function () { pingInFlight = false; pingController = null; });
            }, function () { pingInFlight = false; trackingStatus.text('GPS permission is required for live tracking.'); }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 15000 });
        };
        ping();
        var pingTimer = window.setInterval(ping, Math.max(30, parseInt(tracker.data('ping-seconds'), 10) || 60) * 1000);
        window.addEventListener('beforeunload', function () {
            window.clearInterval(pingTimer);
            if (pingController) pingController.abort();
        }, { once: true });
    }

    $(document).on('submit', 'form[data-prevent-double-submit]', function (event) {
        var form = $(this);
        if (form.data('submitting')) {
            event.preventDefault();
            return;
        }
        form.data('submitting', true);
        window.setTimeout(function () {
            form.find('[type="submit"]').prop('disabled', true).attr('aria-busy', 'true');
        }, 0);
    });

    $(document).on('click', '[data-clear-search]', function () {
        var form = $(this).closest('form');
        var input = form.find('input[type="search"]').first();
        input.val('').trigger('input').focus();
        form.removeClass('has-query');
        if (form.is('[data-submit-on-clear]')) form.get(0).requestSubmit();
    });
    $(document).on('input', '.module-search input[type="search"], .global-search input[type="search"]', function () {
        $(this).closest('form').toggleClass('has-query', this.value.length > 0);
    });

    var pendingDeleteForm = null;
    var deleteTrigger = null;
    $(document).on('submit', 'form[data-confirm-delete]', function (event) {
        if ($(this).data('confirmed')) return;
        event.preventDefault();
        pendingDeleteForm = this;
        deleteTrigger = event.originalEvent && event.originalEvent.submitter ? event.originalEvent.submitter : null;
        window.bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteConfirmModal')).show();
    });
    $(document).on('click', '[data-confirm-delete-action]', function () {
        if (!pendingDeleteForm) return;
        var form = pendingDeleteForm;
        pendingDeleteForm = null;
        $(form).data('confirmed', true);
        $(this).prop('disabled', true).attr('aria-busy', 'true');
        form.requestSubmit();
    });
    $('#deleteConfirmModal').on('hidden.bs.modal', function () {
        $(this).find('[data-confirm-delete-action]').prop('disabled', false).removeAttr('aria-busy');
        pendingDeleteForm = null;
        if (deleteTrigger) deleteTrigger.focus();
        deleteTrigger = null;
    });

    var currentSalesChart = null;
    function initProductionSalesChart() {
        var canvas = document.getElementById('productionSalesChart');
        if (!canvas || !window.Chart) return;
        if (currentSalesChart) {
            try { currentSalesChart.destroy(); } catch (e) {}
            currentSalesChart = null;
        }
        var styles = getComputedStyle(document.documentElement);
        var labels = JSON.parse(canvas.dataset.labels || '[]');
        var production = JSON.parse(canvas.dataset.production || '[]');
        var sales = JSON.parse(canvas.dataset.sales || '[]');
        currentSalesChart = new window.Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Production (kg)',
                        data: production,
                        borderColor: styles.getPropertyValue('--meatin-red').trim(),
                        backgroundColor: 'rgba(215,25,56,.08)',
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        tension: .35,
                        fill: true,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Sales (INR)',
                        data: sales,
                        borderColor: styles.getPropertyValue('--blue').trim(),
                        backgroundColor: 'rgba(33,104,220,.04)',
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        tension: .35,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false }, tooltip: { padding: 10, cornerRadius: 8 } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: styles.getPropertyValue('--muted').trim(), font: { size: 9 } } },
                    y: { beginAtZero: true, position: 'left', grid: { color: styles.getPropertyValue('--line').trim() }, ticks: { color: styles.getPropertyValue('--muted').trim(), font: { size: 9 } } },
                    y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { color: styles.getPropertyValue('--muted').trim(), font: { size: 9 } } }
                }
            }
        });
    }
    initProductionSalesChart();

    // ==========================================
    // Sidebar Scroll Position Preservation
    // ==========================================
    function initSidebarScroll() {
        var sidebars = document.querySelectorAll('.sidebar-nav');
        if (!sidebars.length) return;

        var savedScroll = sessionStorage.getItem('meatinos_sidebar_scroll');
        sidebars.forEach(function (sidebarNav) {
            if (savedScroll !== null) {
                sidebarNav.scrollTop = parseInt(savedScroll, 10);
            }
            var activeLink = sidebarNav.querySelector('.nav-link.active');
            if (activeLink) {
                var rect = activeLink.getBoundingClientRect();
                var navRect = sidebarNav.getBoundingClientRect();
                if (rect.top < navRect.top || rect.bottom > navRect.bottom) {
                    activeLink.scrollIntoView({ block: 'nearest', behavior: 'instant' });
                }
            }
            sidebarNav.addEventListener('scroll', function () {
                sessionStorage.setItem('meatinos_sidebar_scroll', sidebarNav.scrollTop);
            }, { passive: true });
        });
    }
    initSidebarScroll();

    // ==========================================
    // Seamless Navigation Engine (PJAX)
    // Prevents sidebar / menu bar refresh on module click
    // ==========================================
    var loadingBar = document.createElement('div');
    loadingBar.className = 'nav-loading-bar';
    document.body.appendChild(loadingBar);

    var loadingProgress = 0;
    var loadingTimer = null;

    function startLoading() {
        if (loadingTimer) clearInterval(loadingTimer);
        loadingProgress = 20;
        loadingBar.classList.add('is-loading');
        loadingBar.style.width = loadingProgress + '%';
        loadingTimer = setInterval(function () {
            if (loadingProgress < 85) {
                loadingProgress += Math.floor(Math.random() * 12) + 5;
                loadingBar.style.width = loadingProgress + '%';
            }
        }, 100);
    }

    function finishLoading() {
        if (loadingTimer) clearInterval(loadingTimer);
        loadingBar.style.width = '100%';
        setTimeout(function () {
            loadingBar.classList.remove('is-loading');
            setTimeout(function () {
                loadingBar.style.width = '0%';
            }, 250);
        }, 120);
    }

    function reinitPageComponents() {
        // 1. Auto-open modal if requested
        var autoModal = document.querySelector('.modal[data-auto-open="true"]') || document.getElementById('recordModal') || document.getElementById('userModal') || document.getElementById('documentModal');
        if (autoModal && autoModal.dataset.autoOpen === 'true' && window.bootstrap) {
            window.bootstrap.Modal.getOrCreateInstance(autoModal).show();
        }

        // 2. Alert auto-dismiss timer
        $('.alert').not('.alert-danger').each(function () {
            var element = this;
            window.setTimeout(function () {
                if (window.bootstrap) {
                    window.bootstrap.Alert.getOrCreateInstance(element).close();
                }
            }, 7000);
        });

        // 3. Stage measurement dynamic form sections
        updateStageMeasurementFields();

        // 4. Bulk rates live calculations
        recalculateBulkRates();

        // 5. Production & Sales chart
        initProductionSalesChart();

        // 6. Close mobile sidebar offcanvas if open
        var mobileSidebarEl = document.getElementById('mobileSidebar');
        if (mobileSidebarEl && window.bootstrap) {
            var offcanvasInstance = window.bootstrap.Offcanvas.getInstance(mobileSidebarEl);
            if (offcanvasInstance) {
                offcanvasInstance.hide();
            }
        }

        // 7. Search form states
        $('.module-search input[type="search"], .global-search input[type="search"]').each(function () {
            $(this).closest('form').toggleClass('has-query', this.value.length > 0);
        });

        // 8. Restore or scroll sidebar to active item
        initSidebarScroll();

        // 9. Scroll content viewport to top smoothly
        window.scrollTo({ top: 0, behavior: 'instant' });
    }

    var inFlightController = null;

    function navigateTo(url, pushState) {
        if (typeof pushState === 'undefined') pushState = true;

        if (inFlightController) {
            inFlightController.abort();
        }
        inFlightController = new AbortController();

        startLoading();

        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            signal: inFlightController.signal
        })
        .then(function (response) {
            if (!response.ok) {
                window.location.href = url;
                return null;
            }
            if (response.redirected && response.url && response.url !== url) {
                window.location.href = response.url;
                return null;
            }
            return response.text();
        })
        .then(function (html) {
            if (!html) return;
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, 'text/html');

            // Update title
            document.title = doc.title;

            // Update topbar title & subtitle
            var newTitle = doc.querySelector('.topbar .page-title');
            var currTitle = document.querySelector('.topbar .page-title');
            if (newTitle && currTitle) {
                currTitle.innerHTML = newTitle.innerHTML;
            }
            var newSub = doc.querySelector('.topbar .page-subtitle');
            var currSub = document.querySelector('.topbar .page-subtitle');
            if (newSub && currSub) {
                currSub.innerHTML = newSub.innerHTML;
            }

            // Update main content area
            var newContent = doc.querySelector('.content-area');
            var currContent = document.querySelector('.content-area');
            if (newContent && currContent) {
                currContent.innerHTML = newContent.innerHTML;

                // Execute scripts within newly loaded content
                var scripts = currContent.querySelectorAll('script');
                scripts.forEach(function (s) {
                    var scriptEl = document.createElement('script');
                    if (s.src) {
                        scriptEl.src = s.src;
                    } else {
                        scriptEl.textContent = s.textContent;
                    }
                    document.body.appendChild(scriptEl);
                    setTimeout(function () { scriptEl.remove(); }, 100);
                });
            }

            // Update active state across all sidebar navigations without reloading the menu bar
            var parsedUrl = new URL(url, window.location.origin);
            var targetRoute = parsedUrl.searchParams.get('route') || 'dashboard';
            var targetName = parsedUrl.searchParams.get('name') || '';

            document.querySelectorAll('.sidebar-nav').forEach(function (nav) {
                nav.querySelectorAll('.nav-link').forEach(function (link) {
                    var linkHref = link.getAttribute('href');
                    if (!linkHref) return;
                    var linkParsed = new URL(linkHref, window.location.origin);
                    var linkRoute = linkParsed.searchParams.get('route') || 'dashboard';
                    var linkName = linkParsed.searchParams.get('name') || '';

                    var isActive = (linkRoute === targetRoute) && (linkRoute !== 'module' || linkName === targetName);
                    link.classList.toggle('active', isActive);

                    var arrow = link.querySelector('.nav-arrow');
                    if (isActive && !arrow) {
                        var newArrow = document.createElement('i');
                        newArrow.className = 'bi bi-chevron-right ms-auto nav-arrow';
                        link.appendChild(newArrow);
                    } else if (!isActive && arrow) {
                        arrow.remove();
                    }
                });
            });

            // Push history state
            if (pushState) {
                window.history.pushState({ url: url }, doc.title, url);
            }

            reinitPageComponents();
            finishLoading();
        })
        .catch(function (err) {
            finishLoading();
            if (err.name !== 'AbortError') {
                window.location.href = url;
            }
        });
    }

    // Intercept clicks on sidebar menu links
    $(document).on('click', '.sidebar-nav .nav-link, [data-seamless-nav]', function (event) {
        if (event.which > 1 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        var href = $(this).attr('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) {
            return;
        }
        var parsed = new URL(href, window.location.origin);
        if (parsed.origin !== window.location.origin) {
            return;
        }
        if (href.indexOf('download') !== -1 || href.indexOf('export') !== -1) {
            return;
        }

        event.preventDefault();
        navigateTo(href, true);
    });

    // Handle browser back and forward navigation
    window.addEventListener('popstate', function () {
        navigateTo(window.location.href, false);
    });

    // Global navigation API
    window.MeatinOSNav = {
        navigate: function (url) {
            navigateTo(url, true);
        }
    };
})(window.jQuery);

