/**
 * WordPress CDN Integration Admin JavaScript - Enhanced Version
 */
(function($) {
    'use strict';

    // Document ready
    $(function() {
        // Initialize the main tabs
        initMainTabs();
        
        // Initialize analyzer tabs
        initAnalyzerTabs();
        
        // Test connection button
        $('#test-connection-button, #dashboard-test-connection').on('click', function() {
            testGitHubConnection($(this));
        });
        
        // Purge CDN cache button
        $('#purge-cdn-button, #dashboard-purge-cdn, #quick-purge-cdn').on('click', function() {
            if ($(this).hasClass('disabled')) {
                return;
            }
            
            if (!confirm(wpCdnAdmin.i18n.confirmPurge)) {
                return;
            }
            
            purgeCdnCache($(this));
        });
        
        // Quick analysis button
        $('#quick-analyze-button').on('click', function() {
            analyzeUrls($(this), false);
        });
        
        // Deep analysis button
        $('#deep-analyze-button').on('click', function() {
            var startUrl = $('#start-url').val();
            var maxPages = $('#max-pages').val();
            
            if (!startUrl) {
                alert('Please enter a Start URL');
                return;
            }
            
            analyzeUrls($(this), true, startUrl, maxPages);
        });
        
        // Analyze pasted URLs button
        $('#analyze-pasted-urls-button').on('click', function() {
            var pastedUrls = $('#pasted-urls').val();
            
            if (!pastedUrls) {
                alert('Please paste URLs to analyze');
                return;
            }
            
            analyzePastedUrls($(this), pastedUrls);
        });
        
        // Select All button
        $(document).on('click', '#select-all-urls', function() {
            var $visibleCheckboxes = $('.url-item:visible input[type="checkbox"]');
            var isAllSelected = $visibleCheckboxes.filter(':checked').length === $visibleCheckboxes.length;
            
            $visibleCheckboxes.prop('checked', !isAllSelected);
            
            $(this).text(isAllSelected ? wpCdnAdmin.i18n.selectAll : wpCdnAdmin.i18n.deselectAll);
        });
        
        // Add Selected URLs button
        $(document).on('click', '#add-selected-urls', function() {
            addSelectedUrlsToCustomList();
        });
        
        // Upload to GitHub button
        $(document).on('click', '#upload-to-github-button', function() {
            uploadSelectedToGithub($(this));
        });
        
        // Log view functionality
        $('#view-log-button, #refresh-log-button').on('click', function() {
            viewLog($(this));
        });
        
        // Validate URLs button
        $('#validate-urls-button').on('click', function() {
            validateCustomUrls($(this));
        });
        
        // Filter functionality
        $('#url-filter, #url-type-filter').on('input change', function() {
            filterUrls();
        });
        
        // Tab linking functionality
        $('.cdn-tab-link').on('click', function() {
            var tabId = $(this).data('tab');
            showMainTab(tabId);
        });
        
        // Check for CDN enabled warning
        if ($('#wp-cdn-enabled').length) {
            var $enableCheckbox = $('#wp-cdn-enabled');
            var originalChecked = $enableCheckbox.prop('checked');
            
            $enableCheckbox.on('change', function() {
                if (!originalChecked && $(this).prop('checked')) {
                    var customUrlsContent = $('#wp-cdn-custom-urls').val();
                    if (!customUrlsContent || customUrlsContent.trim() === '') {
                        if (!confirm(wpCdnAdmin.i18n.enableWarning)) {
                            $(this).prop('checked', false);
                        }
                    }
                }
            });
        }
    });
	/**
     * Initialize the main tabs system.
     */
    function initMainTabs() {
        $('.cdn-tab-button').on('click', function() {
            var tabId = $(this).data('tab');
            showMainTab(tabId);
        });
        
        // Check for hash in URL to show specific tab
        var hash = window.location.hash;
        if (hash) {
            var tabId = hash.substring(1);
            if (tabId.indexOf('-tab') === -1) {
                tabId = tabId + '-tab';
            }
            if ($('#' + tabId).length) {
                showMainTab(tabId.replace('-tab', ''));
            }
        }
    }
    
    /**
     * Show a specific main tab.
     * 
     * @param {string} tabId The tab ID to show.
     */
    function showMainTab(tabId) {
        // Update active tab
        $('.cdn-tab-button').removeClass('active');
        $('.cdn-tab-button[data-tab="' + tabId + '"]').addClass('active');
        
        // Show corresponding content
        $('.cdn-tab-pane').removeClass('active');
        $('#' + tabId + '-tab').addClass('active');
        
        // Update URL hash
        window.location.hash = tabId;
    }
    
    /**
     * Initialize the analyzer tabs.
     */
    function initAnalyzerTabs() {
        $('.cdn-analyzer-tab').on('click', function() {
            var tabId = $(this).data('tab');
            
            // Update active tab
            $('.cdn-analyzer-tab').removeClass('active');
            $(this).addClass('active');
            
            // Show corresponding content
            $('.cdn-analyzer-tab-content').removeClass('active');
            $('#' + tabId).addClass('active');
        });
    }
    
    /**
     * Test GitHub connection.
     * 
     * @param {jQuery} $button The button that was clicked.
     */
    function testGitHubConnection($button) {
        var $status = $button.siblings('.connection-status');
        if (!$status.length) {
            $status = $('<span class="connection-status"></span>');
            $button.after($status);
        }
        
        $button.prop('disabled', true);
        $button.text(wpCdnAdmin.i18n.testingConnection);
        $status.removeClass('success error').text('');
        
        $.ajax({
            url: wpCdnAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'cdn_test_connection',
                nonce: wpCdnAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.addClass('success').text(response.data.message);
                    
                    // Refresh the page if on dashboard to update workflow
                    if ($('.workflow-steps').length) {
                        location.reload();
                    } else {
                        alert(wpCdnAdmin.i18n.success + ': ' + response.data.message);
                    }
                } else {
                    $status.addClass('error').text(response.data.message);
                    alert(wpCdnAdmin.i18n.error + ': ' + response.data.message);
                }
            },
            error: function() {
                $status.addClass('error').text('Failed to connect to server');
                alert(wpCdnAdmin.i18n.error + ': ' + 'Failed to connect to server');
            },
            complete: function() {
                $button.prop('disabled', false);
                $button.text('Test Connection');
            }
        });
    }
    
    /**
     * Purge CDN cache.
     * 
     * @param {jQuery} $button The button that was clicked.
     */
    function purgeCdnCache($button) {
        var $status = $button.siblings('.purge-status');
        if (!$status.length) {
            $status = $('<span class="purge-status"></span>');
            $button.after($status);
        }
        
        $button.prop('disabled', true);
        $button.text(wpCdnAdmin.i18n.purgingCache);
        $status.removeClass('success error').text('');
        
        $.ajax({
            url: wpCdnAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'cdn_purge_cache',
                nonce: wpCdnAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.addClass('success').text(response.data.message);
                    alert(wpCdnAdmin.i18n.success + ': ' + response.data.message);
                } else {
                    $status.addClass('error').text(response.data.message);
                    alert(wpCdnAdmin.i18n.error + ': ' + response.data.message);
                }
            },
            error: function() {
                $status.addClass('error').text('Failed to connect to server');
                alert(wpCdnAdmin.i18n.error + ': ' + 'Failed to connect to server');
            },
            complete: function() {
                $button.prop('disabled', false);
                $button.text('Purge Cache');
            }
        });
    }
	/**
     * Analyze URLs from the site.
     * 
     * @param {jQuery} $button    The button that was clicked.
     * @param {bool}   deepAnalyze Whether to perform deep analysis.
     * @param {string} startUrl   Optional. Starting URL for deep analysis.
     * @param {number} maxPages   Optional. Maximum pages for deep analysis.
     */
    function analyzeUrls($button, deepAnalyze, startUrl, maxPages) {
        $button.prop('disabled', true);
        $button.text(wpCdnAdmin.i18n.analyzing);
        
        // Reset results
        $('.cdn-analyzer-results').hide();
        $('.cdn-analyzer-url-list').empty();
        $('#url-count').text('');
        $('.filter-status').text('');
        $('#url-filter').val('');
        $('#url-type-filter').val('all');
        
        var data = {
            action: 'cdn_analyze_urls',
            nonce: wpCdnAdmin.nonce,
            deep_analyze: deepAnalyze
        };
        
        if (deepAnalyze) {
            data.start_url = startUrl;
            data.max_pages = maxPages;
        }
        
        $.ajax({
            url: wpCdnAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: data,
            success: function(response) {
                if (response.success) {
                    displayAnalysisResults(response.data);
                } else {
                    alert(wpCdnAdmin.i18n.error + ': ' + response.data.message);
                }
            },
            error: function() {
                alert(wpCdnAdmin.i18n.error + ': ' + 'Failed to connect to server');
            },
            complete: function() {
                $button.prop('disabled', false);
                $button.text(deepAnalyze ? 'Start Deep Analysis' : 'Analyze Homepage');
            }
        });
    }
    
    /**
     * Analyze pasted URLs.
     * 
     * @param {jQuery} $button    The button that was clicked.
     * @param {string} pastedUrls URLs pasted by the user.
     */
    function analyzePastedUrls($button, pastedUrls) {
        $button.prop('disabled', true);
        $button.text(wpCdnAdmin.i18n.analyzing);
        
        // Reset results
        $('.cdn-analyzer-results').hide();
        $('.cdn-analyzer-url-list').empty();
        $('#url-count').text('');
        $('.filter-status').text('');
        $('#url-filter').val('');
        $('#url-type-filter').val('all');
        
        $.ajax({
            url: wpCdnAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'cdn_direct_analyze_urls',
                nonce: wpCdnAdmin.nonce,
                pasted_urls: pastedUrls
            },
            success: function(response) {
                if (response.success) {
                    displayAnalysisResults(response.data);
                } else {
                    alert(wpCdnAdmin.i18n.error + ': ' + response.data.message);
                }
            },
            error: function() {
                alert(wpCdnAdmin.i18n.error + ': ' + 'Failed to connect to server');
            },
            complete: function() {
                $button.prop('disabled', false);
                $button.text('Analyze Pasted URLs');
            }
        });
    }
    
    /**
     * Display analysis results.
     * 
     * @param {Object} data Response data from the server.
     */
    function displayAnalysisResults(data) {
        if (!data.urls || !data.urls.length) {
            alert(wpCdnAdmin.i18n.noUrlsFound);
            return;
        }
        
        // Group URLs by type for better organization
        var urlsByType = categorizeUrls(data.urls);
        
        // Update count
        $('#url-count').text(data.count + ' URLs found');
        
        // Clear URL list
        var $urlList = $('.cdn-analyzer-url-list');
        $urlList.empty();
        
        // Create markup for each type
        Object.keys(urlsByType).forEach(function(type) {
            var urls = urlsByType[type];
            if (urls.length > 0) {
                var typeLabel = getTypeLabel(type);
                var typeIcon = getTypeIcon(type);
                
                var $typeHeader = $('<div class="url-type-header" data-type="' + type + '">' + 
                                  typeIcon + ' <strong>' + typeLabel + '</strong> <span class="count">(' + urls.length + ')</span>' +
                                  '<span class="toggle-indicator">▼</span>' +
                                  '</div>');
                
                var $typeItems = $('<div class="url-type-items" data-type="' + type + '"></div>');
                
                urls.forEach(function(url, index) {
                    var $item = $('<div class="url-item" data-type="' + type + '">' +
                                 '<label>' +
                                 '<input type="checkbox" class="url-checkbox" value="' + url + '"> ' +
                                 '<span class="url-path">' + url + '</span>' +
                                 '</label>' +
                                 '</div>');
                    
                    $typeItems.append($item);
                });
                
                $urlList.append($typeHeader);
                $urlList.append($typeItems);
                
                // Make type headers collapsible
                $typeHeader.on('click', function() {
                    var $items = $('.url-type-items[data-type="' + type + '"]');
                    $items.toggle();
                    var $indicator = $(this).find('.toggle-indicator');
                    $indicator.text($items.is(':visible') ? '▼' : '►');
                });
            }
        });
        
        // Initialize filter functionality
        filterUrls();
        
        // Show results
        $('.cdn-analyzer-results').show();
    }
    
    /**
     * Filter displayed URLs.
     */
    function filterUrls() {
        var searchText = $('#url-filter').val().toLowerCase();
        var selectedType = $('#url-type-filter').val();
        
        var visibleCount = 0;
        var totalCount = $('.url-item').length;
        
        // Show/hide URL items based on filters
        $('.url-item').each(function() {
            var $item = $(this);
            var urlText = $item.find('.url-path').text().toLowerCase();
            var urlType = $item.data('type');
            
            var matchesSearch = searchText === '' || urlText.indexOf(searchText) !== -1;
            var matchesType = selectedType === 'all' || (
                selectedType === 'images' && (urlType === 'png' || urlType === 'jpg' || urlType === 'jpeg' || urlType === 'gif' || urlType === 'svg' || urlType === 'webp' || urlType === 'ico') ||
                selectedType === 'fonts' && (urlType === 'woff' || urlType === 'woff2' || urlType === 'ttf' || urlType === 'eot') ||
                selectedType === urlType
            );
            
            if (matchesSearch && matchesType) {
                $item.show();
                visibleCount++;
            } else {
                $item.hide();
            }
        });
        
        // Update headers visibility based on their children
        $('.url-type-header').each(function() {
            var type = $(this).data('type');
            var $items = $('.url-item[data-type="' + type + '"]:visible');
            
            if ($items.length > 0) {
                $(this).show();
                $(this).find('.count').text('(' + $items.length + ')');
            } else {
                $(this).hide();
            }
        });
        
        // Update filter status
        $('.filter-status').text('Showing ' + visibleCount + ' of ' + totalCount + ' URLs');
    }
	/**
     * Add selected URLs to the custom URL list.
     */
    function addSelectedUrlsToCustomList() {
        var selectedUrls = [];
        $('.url-item input[type="checkbox"]:checked').each(function() {
            selectedUrls.push($(this).val());
        });
        
        if (selectedUrls.length === 0) {
            alert('Please select at least one URL.');
            return;
        }
        
        // Get current textarea content
        var customUrlsTextarea = $('#wp-cdn-custom-urls');
        var currentUrls = '';
        
        if (customUrlsTextarea.length) {
            // Direct update if we're on the same page
            currentUrls = customUrlsTextarea.val();
            
            // Add new URLs (ensure no duplicates)
            var existingUrls = currentUrls ? currentUrls.split("\n") : [];
            var newUrls = [];
            
            selectedUrls.forEach(function(url) {
                if (existingUrls.indexOf(url) === -1) {
                    newUrls.push(url);
                }
            });
            
            if (newUrls.length === 0) {
                alert('All selected URLs are already in the custom URL list.');
                return;
            }
            
            var updatedUrls = currentUrls ? currentUrls + "\n" + newUrls.join("\n") : newUrls.join("\n");
            
            // Update textarea
            customUrlsTextarea.val(updatedUrls);
            
            // Notify user
            alert('Added ' + newUrls.length + ' URLs to the custom URL list.');
        } else {
            // We're on a different tab/page, need to use AJAX
            $.ajax({
                url: wpCdnAdmin.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'cdn_update_custom_urls',
                    nonce: wpCdnAdmin.nonce,
                    urls: JSON.stringify(selectedUrls)
                },
                success: function(response) {
                    if (response.success) {
                        alert('Added ' + selectedUrls.length + ' URLs to the custom URL list.');
                        
                        // Switch to custom URLs tab
                        showMainTab('custom');
                    } else {
                        alert(wpCdnAdmin.i18n.error + ': ' + response.data.message);
                    }
                },
                error: function() {
                    alert(wpCdnAdmin.i18n.error + ': ' + 'Failed to connect to server');
                }
            });
        }
    }
    
    /**
     * Upload selected URLs to GitHub.
     * 
     * @param {jQuery} $button The button that was clicked.
     */
    function uploadSelectedToGithub($button) {
        var selectedUrls = [];
        $('.url-item input[type="checkbox"]:checked').each(function() {
            selectedUrls.push($(this).val());
        });
        
        if (selectedUrls.length === 0) {
            alert('Please select at least one URL to upload.');
            return;
        }
        
        if (!confirm(wpCdnAdmin.i18n.confirmUpload)) {
            return;
        }
        
        var $progress = $('.cdn-upload-progress');
        var $result = $('.cdn-upload-result');
        
        $button.prop('disabled', true);
        $button.text(wpCdnAdmin.i18n.uploadingFiles);
        $progress.show();
        $progress.find('.progress-bar').css('width', '0%');
        $progress.find('.progress-status').text('Preparing upload...');
        $result.hide();
        
        // Simulate progress while uploading
        var progressInterval = setInterval(function() {
            var currentWidth = parseInt($progress.find('.progress-bar').css('width'));
            var containerWidth = $progress.find('.progress-bar-container').width();
            var percentage = (currentWidth / containerWidth) * 100;
            
            if (percentage < 90) {
                $progress.find('.progress-bar').css('width', (percentage + 1) + '%');
            }
        }, 500);
        
        $.ajax({
            url: wpCdnAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'cdn_upload_to_github',
                nonce: wpCdnAdmin.nonce,
                urls: JSON.stringify(selectedUrls)
            },
            success: function(response) {
                clearInterval(progressInterval);
                $progress.find('.progress-bar').css('width', '100%');
                
                if (response.success) {
                    var results = response.data.results;
                    
                    // Show result message
                    var resultHtml = '<h3>' + wpCdnAdmin.i18n.uploadComplete + '</h3>';
                    resultHtml += '<p><strong>' + response.data.message + '</strong></p>';
                    
                    if (results.details && results.details.length) {
                        resultHtml += '<button type="button" class="button toggle-details">' + wpCdnAdmin.i18n.viewDetails + '</button>';
                        resultHtml += '<div class="upload-details" style="display:none; margin-top:10px">';
                        resultHtml += '<table class="widefat">';
                        resultHtml += '<thead><tr><th>URL</th><th>Status</th><th>Message</th></tr></thead>';
                        resultHtml += '<tbody>';
                        
                        results.details.forEach(function(detail) {
                            var statusClass = detail.success ? 'success' : 'error';
                            var statusText = detail.success ? 'Success' : 'Failed';
                            
                            resultHtml += '<tr class="' + statusClass + '">';
                            resultHtml += '<td>' + detail.url + '</td>';
                            resultHtml += '<td>' + statusText + '</td>';
                            resultHtml += '<td>' + detail.message + '</td>';
                            resultHtml += '</tr>';
                        });
                        
                        resultHtml += '</tbody></table></div>';
                    }
                    
                    $result.html(resultHtml).show();
                    
                    // Toggle details
                    $result.find('.toggle-details').on('click', function() {
                        var $details = $result.find('.upload-details');
                        $details.toggle();
                        $(this).text($details.is(':visible') ? wpCdnAdmin.i18n.hideDetails : wpCdnAdmin.i18n.viewDetails);
                    });
                    
                    // Ask user if they want to add URLs to custom list
                    if (results.success > 0 && confirm(wpCdnAdmin.i18n.addToCustomUrls)) {
                        // Get successful URLs
                        var successfulUrls = [];
                        results.details.forEach(function(detail) {
                            if (detail.success) {
                                successfulUrls.push(detail.url);
                            }
                        });
                        
                        // Get current textarea content
                        var customUrlsTextarea = $('#wp-cdn-custom-urls');
                        
                        if (customUrlsTextarea.length) {
                            // Direct update
                            var currentUrls = customUrlsTextarea.val();
                            
                            // Add new URLs (ensure no duplicates)
                            var existingUrls = currentUrls ? currentUrls.split("\n") : [];
                            var newUrls = [];
                            
                            successfulUrls.forEach(function(url) {
                                if (existingUrls.indexOf(url) === -1) {
                                    newUrls.push(url);
                                }
                            });
                            
                            if (newUrls.length > 0) {
                                var updatedUrls = currentUrls ? currentUrls + "\n" + newUrls.join("\n") : newUrls.join("\n");
                                
                                // Update textarea
                                customUrlsTextarea.val(updatedUrls);
                                
                                alert(wpCdnAdmin.i18n.urlsAdded);
                            }
                        } else {
                            // AJAX update
                            $.ajax({
                                url: wpCdnAdmin.ajaxUrl,
                                type: 'POST',
                                dataType: 'json',
                                data: {
                                    action: 'cdn_update_custom_urls',
                                    nonce: wpCdnAdmin.nonce,
                                    urls: JSON.stringify(successfulUrls)
                                },
                                success: function(response) {
                                    if (response.success) {
                                        alert(wpCdnAdmin.i18n.urlsAdded);
                                    }
                                }
                            });
                        }
                    }
                } else {
                    $result.html('<div class="error"><p>' + response.data.message + '</p></div>').show();
                }
            },
            error: function() {
                clearInterval(progressInterval);
                $progress.find('.progress-bar').css('width', '100%').addClass('error');
                $progress.find('.progress-status').text('Upload failed: server error');
                $result.html('<div class="error"><p>Failed to connect to server</p></div>').show();
            },
            complete: function() {
                $button.prop('disabled', false);
                $button.text('Upload to GitHub');
            }
        });
    }
	/**
     * View log file.
     * 
     * @param {jQuery} $button The button that was clicked.
     */
    function viewLog($button) {
        var $content = $('#log-content');
        
        $button.prop('disabled', true);
        $content.html('Loading log data...');
        
        $.ajax({
            url: wpCdnAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'cdn_view_log',
                nonce: wpCdnAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $content.html(response.data.content);
                } else {
                    $content.html('Error: ' + response.data.message);
                }
            },
            error: function() {
                $content.html('Failed to load log data. Server error.');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    }
    
    /**
     * Validate custom URLs.
     * 
     * @param {jQuery} $button The button that was clicked.
     */
    function validateCustomUrls($button) {
        var $status = $('.validate-status');
        var $progress = $('#validation-progress');
        var $results = $('#validation-results');
        
        $button.prop('disabled', true);
        $button.text(wpCdnAdmin.i18n.validatingUrls);
        $status.removeClass('success error').text('');
        $progress.show();
        $progress.find('.progress-bar').css('width', '0%');
        $progress.find('.progress-status').text('Preparing validation...');
        $results.empty().hide();
        
        // Start the validation process
        $.ajax({
            url: wpCdnAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'cdn_validate_urls',
                nonce: wpCdnAdmin.nonce,
                batch_mode: false
            },
            success: function(response) {
                if (response.success) {
                    processUrlBatches(response.data.batches, response.data.total_urls);
                } else {
                    $status.addClass('error').text(response.data.message);
                    $progress.hide();
                    alert(wpCdnAdmin.i18n.error + ': ' + response.data.message);
                    $button.prop('disabled', false);
                    $button.text('Validate & Auto-Upload');
                }
            },
            error: function() {
                $status.addClass('error').text('Failed to connect to server');
                $progress.hide();
                alert(wpCdnAdmin.i18n.error + ': ' + 'Failed to connect to server');
                $button.prop('disabled', false);
                $button.text('Validate & Auto-Upload');
            }
        });
        
        /**
         * Process URL batches for validation.
         * 
         * @param {number} totalBatches Total number of batches.
         * @param {number} totalUrls    Total number of URLs.
         */
        function processUrlBatches(totalBatches, totalUrls) {
            var validationResults = {
                processed: 0,
                success: 0,
                failed: 0,
                exists: 0,
                details: []
            };
            
            // Get all custom URLs
            var customUrls = $('#wp-cdn-custom-urls').val().split('\n').filter(function(url) {
                return url.trim() !== '';
            });
            
            // Process in batches
            var batchSize = 5;
            var batches = [];
            
            for (var i = 0; i < customUrls.length; i += batchSize) {
                batches.push(customUrls.slice(i, i + batchSize));
            }
            
            processBatch(0, batches);
            
            function processBatch(batchIndex, batches) {
                if (batchIndex >= batches.length) {
                    // All batches complete
                    completeValidation(validationResults, totalUrls);
                    return;
                }
                
                // Update progress
                var progress = Math.round((batchIndex / batches.length) * 100);
                $('#validation-progress .progress-bar').css('width', progress + '%');
                $('#validation-progress .progress-status').text(
                    'Validating batch ' + (batchIndex + 1) + ' of ' + batches.length
                );
                
                $.ajax({
                    url: wpCdnAdmin.ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'cdn_validate_urls',
                        nonce: wpCdnAdmin.nonce,
                        batch_mode: true,
                        batch_urls: JSON.stringify(batches[batchIndex]),
                        batch_index: batchIndex,
                        total_batches: batches.length
                    },
                    success: function(response) {
                        if (response.success) {
                            var batchResults = response.data.batch_results;
                            
                            // Merge batch results
                            validationResults.processed += batchResults.processed;
                            validationResults.success += batchResults.success;
                            validationResults.failed += batchResults.failed;
                            validationResults.exists += batchResults.exists;
                            
                            if (batchResults.details && batchResults.details.length) {
                                validationResults.details = validationResults.details.concat(batchResults.details);
                            }
                            
                            // Process next batch
                            processBatch(batchIndex + 1, batches);
                        } else {
                            // Error in batch
                            $('#validation-progress .progress-status').text('Error: ' + response.data.message);
                            $('#validation-progress .progress-bar').css('width', '100%').addClass('error');
                            
                            alert(wpCdnAdmin.i18n.error + ': ' + response.data.message);
                            $('#validate-urls-button').prop('disabled', false).text('Validate & Auto-Upload');
                        }
                    },
                    error: function() {
                        $('#validation-progress .progress-status').text('Error: Failed to connect to server');
                        $('#validation-progress .progress-bar').css('width', '100%').addClass('error');
                        
                        alert(wpCdnAdmin.i18n.error + ': ' + 'Failed to connect to server');
                        $('#validate-urls-button').prop('disabled', false).text('Validate & Auto-Upload');
                    }
                });
            }
        }
        
        /**
         * Complete validation process.
         * 
         * @param {Object} results   Validation results.
         * @param {number} totalUrls Total number of URLs.
         */
        function completeValidation(results, totalUrls) {
            var $button = $('#validate-urls-button');
            var $status = $('.validate-status');
            var $progress = $('#validation-progress');
            var $results = $('#validation-results');
            
            // Update progress to 100%
            $progress.find('.progress-bar').css('width', '100%').addClass('success');
            $progress.find('.progress-status').text('Validation completed');
            
            // Create result message
            var resultMessage = 'Processed ' + results.processed + ' URLs: ' + 
                                results.success + ' uploaded, ' + 
                                results.exists + ' already on GitHub, ' + 
                                results.failed + ' failed.';
            
            $status.addClass(results.failed > 0 ? 'warning' : 'success').text(resultMessage);
            
            // Show detailed results
            var resultHtml = '<h3>Validation Results</h3>';
            resultHtml += '<p><strong>' + resultMessage + '</strong></p>';
            
            if (results.details && results.details.length) {
                resultHtml += '<div class="validation-details">';
                resultHtml += '<table class="widefat">';
                resultHtml += '<thead><tr><th>URL</th><th>Status</th><th>Message</th></tr></thead>';
                resultHtml += '<tbody>';
                
                results.details.forEach(function(detail) {
                    var statusClass = '';
                    switch(detail.status) {
                        case 'uploaded': statusClass = 'status-success'; break;
                        case 'failed': statusClass = 'status-error'; break;
                        case 'exists': statusClass = 'status-exists'; break;
                    }
                    
                    resultHtml += '<tr>';
                    resultHtml += '<td>' + detail.url + '</td>';
                    resultHtml += '<td class="' + statusClass + '">' + detail.status + '</td>';
                    resultHtml += '<td>' + detail.message + '</td>';
                    resultHtml += '</tr>';
                });
                
                resultHtml += '</tbody></table></div>';
            }
            
            $results.html(resultHtml).show();
            
            // Re-enable button
            $button.prop('disabled', false).text('Validate & Auto-Upload');
        }
    }
    
    /**
     * Categorize URLs by file type.
     * 
     * @param {Array} urls Array of URLs.
     * @return {Object} URLs categorized by file type.
     */
    function categorizeUrls(urls) {
        var result = {
            'js': [],
            'css': [],
            'png': [],
            'jpg': [],
            'jpeg': [],
            'gif': [],
            'svg': [],
            'webp': [],
            'ico': [],
            'woff': [],
            'woff2': [],
            'ttf': [],
            'eot': [],
            'other': []
        };
        
        urls.forEach(function(url) {
            var extension = url.split('.').pop().split('?')[0].toLowerCase();
            
            if (result[extension] !== undefined) {
                result[extension].push(url);
            } else {
                result.other.push(url);
            }
        });
        
        return result;
    }
    
    /**
     * Get user-friendly type label.
     * 
     * @param {string} type File type.
     * @return {string} User-friendly label.
     */
    function getTypeLabel(type) {
        switch (type) {
            case 'js': return 'JavaScript Files';
            case 'css': return 'CSS Files';
            case 'png': return 'PNG Images';
            case 'jpg': return 'JPG Images';
            case 'jpeg': return 'JPEG Images';
            case 'gif': return 'GIF Images';
            case 'svg': return 'SVG Images';
            case 'webp': return 'WebP Images';
            case 'ico': return 'Icon Files';
            case 'woff': return 'WOFF Fonts';
            case 'woff2': return 'WOFF2 Fonts';
            case 'ttf': return 'TTF Fonts';
            case 'eot': return 'EOT Fonts';
            case 'other': return 'Other Files';
            default: return type.toUpperCase() + ' Files';
        }
    }
    
    /**
     * Get icon for file type.
     * 
     * @param {string} type File type.
     * @return {string} HTML icon.
     */
    function getTypeIcon(type) {
        var icon = '';
        switch (type) {
            case 'js': 
                icon = '<span class="dashicons dashicons-media-code" style="color: #f0db4f;"></span>';
                break;
            case 'css': 
                icon = '<span class="dashicons dashicons-editor-code" style="color: #264de4;"></span>';
                break;
            case 'png': 
            case 'jpg': 
            case 'jpeg': 
            case 'gif': 
            case 'svg':
            case 'webp':
            case 'ico':
                icon = '<span class="dashicons dashicons-format-image" style="color: #ff9800;"></span>';
                break;
            case 'woff':
            case 'woff2':
            case 'ttf':
            case 'eot':
                icon = '<span class="dashicons dashicons-editor-textcolor" style="color: #607d8b;"></span>';
                break;
            case 'other': 
                icon = '<span class="dashicons dashicons-media-default" style="color: #9e9e9e;"></span>';
                break;
            default:
                icon = '<span class="dashicons dashicons-media-default" style="color: #9e9e9e;"></span>';
        }
        return icon;
    }
})(jQuery);