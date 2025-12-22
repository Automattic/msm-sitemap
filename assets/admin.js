/**
 * MSM Sitemap Admin JavaScript
 * Handles REST API loading of missing sitemaps count and UI interactions
 */

jQuery(document).ready(function($) {
    'use strict';

    // Generate missing sitemaps via REST API
    function generateMissingSitemaps(e) {
        e.preventDefault();

        const $button = $('#generate-missing-button');
        const $content = $('#missing-sitemaps-content');
        const originalText = $button.val();

        // Show loading state
        $button.prop('disabled', true).val(msmSitemapAdmin.generatingText || 'Generating...');
        $content.html('<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> ' +
            (msmSitemapAdmin.generatingText || 'Generating missing sitemaps...'));

        fetch(msmSitemapAdmin.restUrl + 'generate-missing', {
            method: 'POST',
            headers: {
                'X-WP-Nonce': msmSitemapAdmin.nonce,
                'Content-Type': 'application/json'
            }
        })
        .then(function(response) {
            return response.json().then(function(data) {
                return { ok: response.ok, data: data };
            });
        })
        .then(function(result) {
            if (result.ok && result.data.success) {
                $content.html('<span style="color: #46b450;">✅ ' +
                    (result.data.message || msmSitemapAdmin.generationSuccessText || 'Generation started successfully.') +
                    '</span>');
            } else {
                $content.html('<span style="color: #dc3232;">❌ ' +
                    (result.data.message || msmSitemapAdmin.generationErrorText || 'Failed to start generation.') +
                    '</span>');
            }

            // Refresh the missing sitemaps status after a short delay
            setTimeout(loadMissingSitemapsCount, 2000);
        })
        .catch(function(error) {
            console.error('Error generating missing sitemaps:', error);
            $content.html('<span style="color: #dc3232;">❌ ' +
                (msmSitemapAdmin.generationErrorText || 'Failed to start generation. Please try again.') +
                '</span>');
            $button.prop('disabled', false).val(originalText);
        });
    }

    // Load missing sitemaps count via REST API
    function loadMissingSitemapsCount() {
        const $content = $('#missing-sitemaps-content');

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
            const summary = data.summary;

            let html = '';

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

            // Update button state
            const $button = $('#generate-missing-button');
            if (summary.has_missing) {
                $button.removeClass('button-secondary').addClass('button-primary').prop('disabled', false);
                // Update button text based on cron status
                if (data.button_text) {
                    $button.val(data.button_text);
                }
            } else {
                $button.removeClass('button-primary').addClass('button-secondary').prop('disabled', true);
                // Reset button text to default when no missing sitemaps
                $button.val(msmSitemapAdmin.generateMissingText || 'Generate Missing Sitemaps');
            }
        })
        .catch(function(error) {
            console.error('Error loading missing sitemaps:', error);
            $content.html('<p style="margin: 0 0 15px 0; font-size: 13px; color: #dc3232;">Failed to load missing sitemaps data. Please refresh the page.</p>');
        });
    }

    // Toggle detailed statistics section
    function toggleDetailedStats() {
        const content = document.querySelector('.detailed-stats-content');
        const button = document.querySelector('.detailed-stats-toggle');
        
        if (!content || !button) {
            return;
        }
        
        const icon = button.querySelector('.dashicons');
        const text = button.querySelector('span:not(.dashicons)');
        
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

    // Toggle custom date range input fields
    function toggleCustomDateRange(value) {
        const customRange = document.getElementById('custom-date-range');
        if (!customRange) {
            return;
        }
        
        if (value === 'custom') {
            customRange.style.display = 'inline-block';
        } else {
            customRange.style.display = 'none';
            // Auto-submit for non-custom ranges
            const form = document.querySelector('#stats-date-range');
            if (form && form.form) {
                form.form.submit();
            }
        }
    }

    // Toggle danger zone section
    function toggleDangerZone() {
        const content = document.getElementById('danger-zone-content');
        const icon = document.getElementById('danger-zone-icon');
        const text = document.getElementById('danger-zone-toggle-text');
        
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

    // Confirm reset action
    function confirmReset() {
        return confirm(msmSitemapAdmin.confirmResetText || 'Are you sure you want to reset all sitemap data? This action cannot be undone and will delete all sitemaps, metadata, and statistics.');
    }

    // Toggle images settings visibility
    function toggleImagesSettings() {
        const imagesCheckbox = document.getElementById('images_provider_enabled');
        const imagesSettings = document.getElementById('images_settings');
        
        if (!imagesCheckbox || !imagesSettings) {
            return;
        }
        
        imagesSettings.style.display = imagesCheckbox.checked ? 'block' : 'none';
    }

    // Initialize event listeners
    function initializeEventListeners() {
        // Generate missing sitemaps button - intercept form submit
        const generateButton = document.getElementById('generate-missing-button');
        if (generateButton) {
            const form = generateButton.closest('form');
            if (form) {
                form.addEventListener('submit', generateMissingSitemaps);
            }
        }

        // Stats date range change
        const statsDateRange = document.getElementById('stats-date-range');
        if (statsDateRange) {
            statsDateRange.addEventListener('change', function() {
                toggleCustomDateRange(this.value);
            });
        }

        // Danger zone toggle
        const dangerZoneToggle = document.getElementById('danger-zone-toggle');
        if (dangerZoneToggle) {
            dangerZoneToggle.addEventListener('click', toggleDangerZone);
        }

        // Reset form confirmation
        const resetForm = document.querySelector('form[onsubmit="return confirmReset();"]');
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
        const imagesCheckbox = document.getElementById('images_provider_enabled');
        if (imagesCheckbox) {
            imagesCheckbox.addEventListener('change', toggleImagesSettings);
        }

        // Detailed stats toggle (if exists)
        const detailedStatsToggle = document.querySelector('button[onclick="toggleDetailedStats()"]');
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
