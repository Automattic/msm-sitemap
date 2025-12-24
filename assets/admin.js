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
                // Refresh the missing sitemaps count
                loadMissingSitemapsCount();
            }
        })
        .catch(function(error) {
            console.error('Error checking background progress:', error);
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

            if (summary.has_missing) {
                html += '<span style="color: #dc3232; font-weight: bold;">';
                html += '🔍 ' + summary.message;
                html += '</span>';
            } else {
                html += '<span style="color: #46b450;">';
                html += '✅ ' + summary.message;
                html += '</span>';
            }

            $content.html(html);

            // Update button states
            var $directButton = $('#generate-missing-direct-button');
            var $backgroundButton = $('#generate-missing-background-button');

            if (summary.has_missing) {
                $directButton.removeClass('button-secondary').addClass('button-primary').prop('disabled', false);
                if ($backgroundButton.length) {
                    $backgroundButton.removeClass('button-secondary').addClass('button-primary').prop('disabled', false);
                }
            } else {
                $directButton.removeClass('button-primary').addClass('button-secondary').prop('disabled', true);
                if ($backgroundButton.length) {
                    $backgroundButton.removeClass('button-primary').addClass('button-secondary').prop('disabled', true);
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
     * Toggle taxonomy settings visibility
     */
    function toggleTaxonomySettings() {
        var taxonomiesCheckbox = document.getElementById('taxonomies_provider_enabled');
        var taxonomiesSettings = document.getElementById('taxonomies_settings');

        if (!taxonomiesCheckbox || !taxonomiesSettings) {
            return;
        }

        taxonomiesSettings.style.display = taxonomiesCheckbox.checked ? 'block' : 'none';
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
     * Initialize event listeners
     */
    function initializeEventListeners() {
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

        // Taxonomies provider checkbox
        var taxonomiesCheckbox = document.getElementById('taxonomies_provider_enabled');
        if (taxonomiesCheckbox) {
            taxonomiesCheckbox.addEventListener('change', toggleTaxonomySettings);
        }

        // Authors provider checkbox
        var authorsCheckbox = document.getElementById('authors_provider_enabled');
        if (authorsCheckbox) {
            authorsCheckbox.addEventListener('change', toggleAuthorsSettings);
        }

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
