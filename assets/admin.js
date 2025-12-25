/**
 * MSM Sitemap Admin JavaScript
 * Handles REST API loading of missing sitemaps count and UI interactions
 */

jQuery(document).ready(function($) {
    'use strict';

    // Track background generation polling interval
    var backgroundProgressInterval = null;

    /**
     * Generate missing sitemaps via REST API
     *
     * @param {boolean} background Whether to use background generation
     */
    function generateMissingSitemaps(background) {
        var $directButton = $('#generate-missing-direct-button');
        var $backgroundButton = $('#generate-missing-background-button');
        var $content = $('#missing-sitemaps-content');

        // Show loading state on both buttons
        $directButton.prop('disabled', true);
        if ($backgroundButton.length) {
            $backgroundButton.prop('disabled', true);
        }

        var loadingText = background
            ? (msmSitemapAdmin.schedulingText || 'Scheduling...')
            : (msmSitemapAdmin.generatingText || 'Generating...');

        $content.html('<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> ' + loadingText);

        fetch(msmSitemapAdmin.restUrl + 'generate-missing', {
            method: 'POST',
            headers: {
                'X-WP-Nonce': msmSitemapAdmin.nonce,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ background: background })
        })
        .then(function(response) {
            return response.json().then(function(data) {
                return { ok: response.ok, data: data };
            });
        })
        .then(function(result) {
            if (result.ok && result.data.success) {
                var successMessage = result.data.message || msmSitemapAdmin.generationSuccessText || 'Operation completed successfully.';
                $content.html('<span style="color: #46b450;">✅ ' + successMessage + '</span>');

                // If background generation was started, begin polling for progress
                if (background && result.data.method === 'background') {
                    startBackgroundProgressPolling();
                }
            } else {
                $content.html('<span style="color: #dc3232;">❌ ' +
                    (result.data.message || msmSitemapAdmin.generationErrorText || 'Operation failed.') +
                    '</span>');
                // Re-enable buttons on error
                $directButton.prop('disabled', false);
                if ($backgroundButton.length) {
                    $backgroundButton.prop('disabled', false);
                }
            }

            // Refresh the missing sitemaps status after a short delay
            setTimeout(loadMissingSitemapsCount, 2000);
        })
        .catch(function(error) {
            console.error('Error generating missing sitemaps:', error);
            $content.html('<span style="color: #dc3232;">❌ ' +
                (msmSitemapAdmin.generationErrorText || 'Failed to start generation. Please try again.') +
                '</span>');
            // Re-enable buttons on error
            $directButton.prop('disabled', false);
            if ($backgroundButton.length) {
                $backgroundButton.prop('disabled', false);
            }
        });
    }

    /**
     * Start polling for background generation progress
     */
    function startBackgroundProgressPolling() {
        var $progressArea = $('#background-generation-progress');
        var $progressText = $('#background-progress-text');
        var $progressCount = $('#background-progress-count');

        $progressArea.show();

        // Clear any existing interval
        if (backgroundProgressInterval) {
            clearInterval(backgroundProgressInterval);
        }

        // Poll every 5 seconds
        backgroundProgressInterval = setInterval(function() {
            checkBackgroundProgress();
        }, 5000);

        // Also check immediately
        checkBackgroundProgress();
    }

    /**
     * Check background generation progress
     */
    function checkBackgroundProgress() {
        var $progressArea = $('#background-generation-progress');
        var $progressText = $('#background-progress-text');
        var $progressCount = $('#background-progress-count');

        fetch(msmSitemapAdmin.restUrl + 'background-progress', {
            method: 'GET',
            headers: {
                'X-WP-Nonce': msmSitemapAdmin.nonce,
                'Content-Type': 'application/json'
            }
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.in_progress) {
                var completed = data.completed || 0;
                var total = data.total || 0;
                var remaining = data.remaining || 0;

                $progressText.text(msmSitemapAdmin.backgroundProgressText || 'Background generation in progress...');
                $progressCount.text('(' + completed + ' / ' + total + ' completed)');
                $progressArea.show();
            } else {
                // Generation complete
                $progressArea.hide();
                if (backgroundProgressInterval) {
                    clearInterval(backgroundProgressInterval);
                    backgroundProgressInterval = null;
                }
                // Refresh the missing sitemaps count and summary
                loadMissingSitemapsCount();
                refreshSitemapSummary();
            }
        })
        .catch(function(error) {
            console.error('Error checking background progress:', error);
        });
    }

    /**
     * Refresh the sitemap summary counts
     */
    function refreshSitemapSummary() {
        var $summary = $('#sitemap-summary-counts');

        if ($summary.length === 0) {
            return;
        }

        fetch(msmSitemapAdmin.restUrl + 'sitemap-summary', {
            method: 'GET',
            headers: {
                'X-WP-Nonce': msmSitemapAdmin.nonce,
                'Content-Type': 'application/json'
            }
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(function(data) {
            if (data.has_any) {
                $summary.text(data.summary_text).show();
            } else {
                $summary.hide();
            }
        })
        .catch(function(error) {
            console.error('Error refreshing sitemap summary:', error);
        });
    }

    /**
     * Load missing sitemaps count via REST API
     */
    function loadMissingSitemapsCount() {
        var $content = $('#missing-sitemaps-content');

        if ($content.length === 0) {
            return;
        }

        fetch(msmSitemapAdmin.restUrl + 'missing', {
            method: 'GET',
            headers: {
                'X-WP-Nonce': msmSitemapAdmin.nonce,
                'Content-Type': 'application/json'
            }
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(function(data) {
            var summary = data.summary;

            var html = '';

            if (summary.settings_changed) {
                // Settings have changed - show warning with dashicon
                html += '<span style="color: #996800; font-weight: bold;">';
                html += '<span class="dashicons dashicons-warning"></span> ';
                html += summary.message;
                html += '</span>';
            } else if (summary.has_missing) {
                // Missing sitemaps - show red warning
                html += '<span style="color: #dc3232; font-weight: bold;">';
                html += '🔍 ' + summary.message;
                html += '</span>';
            } else {
                // All up to date - show green checkmark
                html += '<span style="color: #46b450;">';
                html += '✅ ' + summary.message;
                html += '</span>';
            }

            $content.html(html);

            // Update button states - hide generate missing buttons when settings changed
            // (the Regenerate All Sitemaps button is shown instead)
            var $directButton = $('#generate-missing-direct-button');
            var $backgroundButton = $('#generate-missing-background-button');

            if (summary.settings_changed) {
                // Settings changed - hide the generate missing buttons
                $directButton.hide();
                if ($backgroundButton.length) {
                    $backgroundButton.hide();
                }
            } else if (summary.has_missing) {
                $directButton.show().removeClass('button-secondary').addClass('button-primary').prop('disabled', false);
                if ($backgroundButton.length) {
                    $backgroundButton.show().removeClass('button-secondary').addClass('button-primary').prop('disabled', false);
                }
            } else {
                $directButton.show().removeClass('button-primary').addClass('button-secondary').prop('disabled', true);
                if ($backgroundButton.length) {
                    $backgroundButton.show().removeClass('button-primary').addClass('button-secondary').prop('disabled', true);
                }
            }

            // Also check if background generation is in progress
            checkBackgroundProgressOnLoad();
        })
        .catch(function(error) {
            console.error('Error loading missing sitemaps:', error);
            $content.html('<p style="margin: 0 0 15px 0; font-size: 13px; color: #dc3232;">Failed to load missing sitemaps data. Please refresh the page.</p>');
        });
    }

    /**
     * Check if background generation is in progress on page load
     */
    function checkBackgroundProgressOnLoad() {
        fetch(msmSitemapAdmin.restUrl + 'background-progress', {
            method: 'GET',
            headers: {
                'X-WP-Nonce': msmSitemapAdmin.nonce,
                'Content-Type': 'application/json'
            }
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.in_progress) {
                // Start polling if generation is in progress
                startBackgroundProgressPolling();
            }
        })
        .catch(function(error) {
            console.error('Error checking background progress:', error);
        });
    }

    /**
     * Toggle detailed statistics section
     */
    function toggleDetailedStats() {
        var content = document.querySelector('.detailed-stats-content');
        var button = document.querySelector('.detailed-stats-toggle');

        if (!content || !button) {
            return;
        }

        var icon = button.querySelector('.dashicons');
        var text = button.querySelector('span:not(.dashicons)');

        if (content.style.display === 'none') {
            content.style.display = 'block';
            icon.className = 'dashicons dashicons-arrow-up-alt2';
            text.textContent = msmSitemapAdmin.hideDetailedStatsText || 'Hide Detailed Statistics';
        } else {
            content.style.display = 'none';
            icon.className = 'dashicons dashicons-arrow-down-alt2';
            text.textContent = msmSitemapAdmin.showDetailedStatsText || 'Show Detailed Statistics';
        }
    }

    /**
     * Toggle custom date range input fields
     */
    function toggleCustomDateRange(value) {
        var customRange = document.getElementById('custom-date-range');
        if (!customRange) {
            return;
        }

        if (value === 'custom') {
            customRange.style.display = 'inline-block';
        } else {
            customRange.style.display = 'none';
            // Auto-submit for non-custom ranges
            var form = document.querySelector('#stats-date-range');
            if (form && form.form) {
                form.form.submit();
            }
        }
    }

    /**
     * Toggle danger zone section
     */
    function toggleDangerZone() {
        var content = document.getElementById('danger-zone-content');
        var icon = document.getElementById('danger-zone-icon');
        var text = document.getElementById('danger-zone-toggle-text');

        if (!content || !icon || !text) {
            return;
        }

        if (content.style.display === 'none') {
            content.style.display = 'grid';
            icon.className = 'dashicons dashicons-arrow-up-alt2';
            text.textContent = msmSitemapAdmin.hideText || 'Hide';
        } else {
            content.style.display = 'none';
            icon.className = 'dashicons dashicons-arrow-down-alt2';
            text.textContent = msmSitemapAdmin.showText || 'Show';
        }
    }

    /**
     * Confirm reset action
     */
    function confirmReset() {
        return confirm(msmSitemapAdmin.confirmResetText || 'Are you sure you want to reset all sitemap data? This action cannot be undone and will delete all sitemaps, metadata, and statistics.');
    }

    /**
     * Toggle images settings visibility
     */
    function toggleImagesSettings() {
        var imagesCheckbox = document.getElementById('images_provider_enabled');
        var imagesSettings = document.getElementById('images_settings');

        if (!imagesCheckbox || !imagesSettings) {
            return;
        }

        imagesSettings.style.display = imagesCheckbox.checked ? 'block' : 'none';
    }

    /**
     * Toggle automatic updates settings visibility
     */
    function toggleAutomaticUpdatesSettings() {
        var automaticUpdatesCheckbox = document.getElementById('automatic_updates_enabled');
        var automaticUpdatesSettings = document.getElementById('automatic_updates_settings');

        if (!automaticUpdatesCheckbox || !automaticUpdatesSettings) {
            return;
        }

        automaticUpdatesSettings.style.display = automaticUpdatesCheckbox.checked ? 'block' : 'none';
    }

    /**
     * Toggle taxonomy cache settings visibility based on selected taxonomies
     */
    function toggleTaxonomyCacheSettings() {
        var taxonomyCheckboxes = document.querySelectorAll('.msm-taxonomy-checkbox');
        var cacheSettings = document.getElementById('taxonomy_cache_settings');

        if (!cacheSettings) {
            return;
        }

        var anyChecked = Array.from(taxonomyCheckboxes).some(function(cb) {
            return cb.checked;
        });

        cacheSettings.style.display = anyChecked ? 'block' : 'none';
    }

    /**
     * Toggle authors settings visibility
     */
    function toggleAuthorsSettings() {
        var authorsCheckbox = document.getElementById('authors_provider_enabled');
        var authorsSettings = document.getElementById('authors_settings');

        if (!authorsCheckbox || !authorsSettings) {
            return;
        }

        authorsSettings.style.display = authorsCheckbox.checked ? 'block' : 'none';
    }

    /**
     * Toggle page cache settings visibility based on selected page types
     */
    function togglePageCacheSettings() {
        var pageCheckboxes = document.querySelectorAll('.msm-page-type-checkbox');
        var cacheSettings = document.getElementById('page_cache_settings');

        if (!cacheSettings) {
            return;
        }

        var anyChecked = Array.from(pageCheckboxes).some(function(cb) {
            return cb.checked;
        });

        cacheSettings.style.display = anyChecked ? 'block' : 'none';
    }

    /**
     * Toggle post images wrapper visibility based on any post type checkbox
     */
    function togglePostImagesWrapper() {
        var postCheckboxes = document.querySelectorAll('.msm-post-type-checkbox');
        var imagesWrapper = document.getElementById('post_images_wrapper');

        if (!imagesWrapper) {
            return;
        }

        var anyChecked = Array.from(postCheckboxes).some(function(cb) {
            return cb.checked;
        });

        imagesWrapper.style.display = anyChecked ? 'block' : 'none';
    }

    /**
     * Initialize form change detection for save button
     */
    function initializeFormChangeDetection() {
        var form = document.getElementById('msm-provider-settings-form');
        var saveButton = document.getElementById('msm-save-provider-settings');

        if (!form || !saveButton) {
            return;
        }

        // Track changes on form inputs
        form.addEventListener('change', function() {
            saveButton.disabled = false;
        });

        // Also track input events for text/number fields
        form.addEventListener('input', function(e) {
            if (e.target.matches('input[type="text"], input[type="number"], textarea')) {
                saveButton.disabled = false;
            }
        });
    }

    /**
     * Initialize provider tab switching (progressive enhancement)
     */
    function initializeProviderTabs() {
        var tabWrapper = document.getElementById('msm-provider-tabs');
        if (!tabWrapper) {
            return;
        }

        var tabs = tabWrapper.querySelectorAll('.nav-tab[data-tab]');
        var panels = document.querySelectorAll('.msm-tab-panel[data-tab-panel]');
        var hiddenInput = document.querySelector('input[name="provider_tab"]');

        tabs.forEach(function(tab) {
            tab.addEventListener('click', function(e) {
                e.preventDefault();

                var targetTab = this.getAttribute('data-tab');

                // Update active tab
                tabs.forEach(function(t) {
                    t.classList.remove('nav-tab-active');
                });
                this.classList.add('nav-tab-active');

                // Show/hide panels
                panels.forEach(function(panel) {
                    if (panel.getAttribute('data-tab-panel') === targetTab) {
                        panel.style.display = '';
                    } else {
                        panel.style.display = 'none';
                    }
                });

                // Update hidden input for form submission
                if (hiddenInput) {
                    hiddenInput.value = targetTab;
                }

                // Update URL without reload (for bookmarking/refresh)
                var url = new URL(window.location.href);
                url.searchParams.set('provider_tab', targetTab);
                window.history.replaceState({}, '', url);
            });
        });
    }

    /**
     * Initialize event listeners
     */
    function initializeEventListeners() {
        // Provider tabs (progressive enhancement)
        initializeProviderTabs();

        // Form change detection for save button
        initializeFormChangeDetection();

        // Direct generate button click
        var directButton = document.getElementById('generate-missing-direct-button');
        if (directButton) {
            directButton.addEventListener('click', function(e) {
                e.preventDefault();
                generateMissingSitemaps(false);
            });
        }

        // Background generate button click
        var backgroundButton = document.getElementById('generate-missing-background-button');
        if (backgroundButton) {
            backgroundButton.addEventListener('click', function(e) {
                e.preventDefault();
                generateMissingSitemaps(true);
            });
        }

        // Stats date range change
        var statsDateRange = document.getElementById('stats-date-range');
        if (statsDateRange) {
            statsDateRange.addEventListener('change', function() {
                toggleCustomDateRange(this.value);
            });
        }

        // Danger zone toggle
        var dangerZoneToggle = document.getElementById('danger-zone-toggle');
        if (dangerZoneToggle) {
            dangerZoneToggle.addEventListener('click', toggleDangerZone);
        }

        // Reset form confirmation
        var resetForm = document.querySelector('form[onsubmit="return confirmReset();"]');
        if (resetForm) {
            // Remove the inline onsubmit attribute
            resetForm.removeAttribute('onsubmit');
            resetForm.addEventListener('submit', function(e) {
                if (!confirmReset()) {
                    e.preventDefault();
                }
            });
        }

        // Images provider checkbox
        var imagesCheckbox = document.getElementById('images_provider_enabled');
        if (imagesCheckbox) {
            imagesCheckbox.addEventListener('change', toggleImagesSettings);
        }

        // Automatic updates checkbox
        var automaticUpdatesCheckbox = document.getElementById('automatic_updates_enabled');
        if (automaticUpdatesCheckbox) {
            automaticUpdatesCheckbox.addEventListener('change', toggleAutomaticUpdatesSettings);
        }

        // Post type checkboxes - toggle images wrapper visibility
        var postTypeCheckboxes = document.querySelectorAll('.msm-post-type-checkbox');
        postTypeCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', togglePostImagesWrapper);
        });

        // Taxonomy checkboxes - toggle cache settings visibility
        var taxonomyCheckboxes = document.querySelectorAll('.msm-taxonomy-checkbox');
        taxonomyCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', toggleTaxonomyCacheSettings);
        });

        // Authors provider checkbox
        var authorsCheckbox = document.getElementById('authors_provider_enabled');
        if (authorsCheckbox) {
            authorsCheckbox.addEventListener('change', toggleAuthorsSettings);
        }

        // Page type checkboxes - toggle cache settings visibility
        var pageCheckboxes = document.querySelectorAll('.msm-page-type-checkbox');
        pageCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', togglePageCacheSettings);
        });

        // Detailed stats toggle (if exists)
        var detailedStatsToggle = document.querySelector('button[onclick="toggleDetailedStats()"]');
        if (detailedStatsToggle) {
            // Remove the inline onclick attribute
            detailedStatsToggle.removeAttribute('onclick');
            detailedStatsToggle.addEventListener('click', toggleDetailedStats);
        }
    }

    // Load missing sitemaps count when page loads
    loadMissingSitemapsCount();

    // Initialize event listeners when DOM is ready
    initializeEventListeners();
});
