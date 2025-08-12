/**
 * MSM Sitemap Admin JavaScript
 * Handles AJAX loading of missing sitemaps count
 */

jQuery(document).ready(function($) {
    'use strict';

    // Load missing sitemaps count via AJAX
    function loadMissingSitemapsCount() {
        const $content = $('#missing-sitemaps-content');
        
        if ($content.length === 0) {
            return;
        }

        $.ajax({
            url: msmSitemapAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'msm_get_missing_sitemaps',
                nonce: msmSitemapAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
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
                    }
                } else {
                    $content.html('<p style="margin: 0 0 15px 0; font-size: 13px; color: #dc3232;">Error loading missing sitemaps data.</p>');
                }
            },
            error: function() {
                $content.html('<p style="margin: 0 0 15px 0; font-size: 13px; color: #dc3232;">Failed to load missing sitemaps data. Please refresh the page.</p>');
            }
        });
    }

    // Load missing sitemaps count when page loads
    loadMissingSitemapsCount();
});
