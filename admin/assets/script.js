/**
 * AI Review Generator Admin JavaScript
 */
jQuery(document).ready(function($) {
    'use strict';
    
    const ARG = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.initTabs();
            this.initSettingsForm();
            this.initAjaxActions();
            this.initSliders();
            this.initTooltips();
            this.initTableActions();
        },
        
        /**
         * Initialize tab functionality
         */
        initTabs: function() {
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                
                const targetTab = $(this).attr('href');
                
                // Update active tab
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Show target content
                $('.tab-content').removeClass('active');
                $(targetTab).addClass('active');
                
                // Store active tab in localStorage
                localStorage.setItem('ai_review_generator_active_tab', targetTab);
            });
            
            // Restore active tab from localStorage
            const activeTab = localStorage.getItem('ai_review_generator_active_tab');
            if (activeTab && $(activeTab).length) {
                $('.nav-tab[href="' + activeTab + '"]').trigger('click');
            }
        },
        
        /**
         * Initialize settings form functionality
         */
        initSettingsForm: function() {
            // AI Model selection changes
            $('#ai-model-select').on('change', function() {
                const selectedModel = $(this).val();
                
                // Hide all API key rows
                $('.api-key-row').hide();
                
                // Show relevant API key row
                $('#' + selectedModel.replace('_', '-') + '-key').show();
                
                // Update model info if available
                this.updateModelInfo(selectedModel);
            }).trigger('change');
            
            // Randomness slider
            $('[name="randomness_degree"]').on('input', function() {
                $('.randomness-value').text($(this).val() + '%');
            });
            
            // Rating validation
            $('[name="min_rating"], [name="max_rating"]').on('change', function() {
                const minRating = parseInt($('[name="min_rating"]').val());
                const maxRating = parseInt($('[name="max_rating"]').val());
                
                if (minRating > maxRating) {
                    if ($(this).attr('name') === 'min_rating') {
                        $('[name="max_rating"]').val(minRating);
                    } else {
                        $('[name="min_rating"]').val(maxRating);
                    }
                }
            });
            
            // Review length validation
            $('[name="review_length_min"], [name="review_length_max"]').on('change', function() {
                const minLength = parseInt($('[name="review_length_min"]').val());
                const maxLength = parseInt($('[name="review_length_max"]').val());
                
                if (minLength > maxLength) {
                    if ($(this).attr('name') === 'review_length_min') {
                        $('[name="review_length_max"]').val(minLength);
                    } else {
                        $('[name="review_length_min"]').val(maxLength);
                    }
                }
            });
        },
        
        /**
         * Initialize AJAX actions
         */
        initAjaxActions: function() {
            // Test API connection
            $('#test-api').on('click', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const originalText = $button.text();
                const aiModel = $('#ai-model-select').val();
                
                // Get API key based on selected model
                let apiKeyField = '';
                switch(aiModel) {
                    case 'deepseek_r1':
                        apiKeyField = 'openrouter_api_key';
                        break;
                    case 'openai_gpt4':
                        apiKeyField = 'openai_api_key';
                        break;
                    case 'claude_3_5':
                        apiKeyField = 'claude_api_key';
                        break;
                    case 'gemini_pro':
                        apiKeyField = 'gemini_api_key';
                        break;
                }
                
                const apiKey = $('[name="' + apiKeyField + '"]').val();
                
                if (!apiKey) {
                    ARG.showNotice('Please enter an API key first.', 'error');
                    return;
                }
                
                $button.text(ai_review_generator_admin.strings.processing).prop('disabled', true);
                
                $.ajax({
                    url: ai_review_generator_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ai_review_generator_test_api',
                        nonce: ai_review_generator_admin.nonce,
                        ai_model: aiModel,
                        api_key: apiKey
                    },
                    success: function(response) {
                        if (response.success) {
                            ARG.showNotice(response.data.message, 'success');
                        } else {
                            ARG.showNotice(response.data.message, 'error');
                        }
                    },
                    error: function() {
                        ARG.showNotice(ai_review_generator_admin.strings.error, 'error');
                    },
                    complete: function() {
                        $button.text(originalText).prop('disabled', false);
                    }
                });
            });
            
            // Generate preview review
            $('#generate-preview').on('click', function(e) {
                e.preventDefault();
                ARG.showPreviewModal();
            });
            
            // Sync products
            $('#sync-products').on('click', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const originalText = $button.text();
                
                $button.text(ai_review_generator_admin.strings.processing).prop('disabled', true);
                
                $.ajax({
                    url: ai_review_generator_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ai_review_generator_sync_products',
                        nonce: ai_review_generator_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            ARG.showNotice(response.data.message, 'success');
                            // Refresh page to update stats
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            ARG.showNotice(response.data.message, 'error');
                        }
                    },
                    error: function() {
                        ARG.showNotice(ai_review_generator_admin.strings.error, 'error');
                    },
                    complete: function() {
                        $button.text(originalText).prop('disabled', false);
                    }
                });
            });
            
            // Process queue
            $('#process-queue').on('click', function(e) {
                e.preventDefault();
                ARG.processQueue();
            });
            
            // Clear failed items
            $('#clear-failed').on('click', function(e) {
                e.preventDefault();
                ARG.clearFailedItems();
            });
        },
        
        /**
         * Initialize sliders and range inputs
         */
        initSliders: function() {
            $('.randomness-slider').on('input', function() {
                const value = $(this).val();
                $(this).siblings('.randomness-value').text(value + '%');
                
                // Update background color based on value
                const percentage = (value - $(this).attr('min')) / ($(this).attr('max') - $(this).attr('min')) * 100;
                $(this).css('background', `linear-gradient(90deg, #2271b1 ${percentage}%, #ddd ${percentage}%)`);
            }).trigger('input');
        },
        
        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            // Add tooltip functionality if needed
            $('.tooltip').each(function() {
                const $this = $(this);
                const title = $this.attr('title');
                if (title) {
                    $this.attr('data-tooltip', title).removeAttr('title');
                }
            });
        },
        
        /**
         * Initialize table actions
         */
        initTableActions: function() {
            // Select all checkbox
            $('#cb-select-all').on('change', function() {
                $('input[name="queue_items[]"]').prop('checked', $(this).prop('checked'));
            });
            
            // Individual checkboxes
            $('input[name="queue_items[]"]').on('change', function() {
                const totalCheckboxes = $('input[name="queue_items[]"]').length;
                const checkedCheckboxes = $('input[name="queue_items[]"]:checked').length;
                
                $('#cb-select-all').prop({
                    'checked': checkedCheckboxes === totalCheckboxes,
                    'indeterminate': checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes
                });
            });
            
            // Preview review buttons
            $('.preview-review').on('click', function(e) {
                e.preventDefault();
                const reviewId = $(this).data('id');
                ARG.showReviewPreview(reviewId);
            });
        },
        
        /**
         * Show preview modal
         */
        showPreviewModal: function() {
            // Create modal HTML
            const modalHtml = `
                <div id="ai-review-preview-modal" class="ai-modal">
                    <div class="ai-modal-content">
                        <div class="ai-modal-header">
                            <h3>Generate Preview Review</h3>
                            <span class="ai-modal-close">&times;</span>
                        </div>
                        <div class="ai-modal-body">
                            <p>Select a product to generate a preview review:</p>
                            <select id="preview-product-select" class="widefat">
                                <option value="">Loading products...</option>
                            </select>
                            <div id="preview-result" style="display:none; margin-top:20px;">
                                <h4>Generated Review:</h4>
                                <div id="preview-content"></div>
                            </div>
                        </div>
                        <div class="ai-modal-footer">
                            <button type="button" id="generate-preview-btn" class="button button-primary" disabled>
                                Generate Preview
                            </button>
                            <button type="button" class="button ai-modal-close">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            // Add modal to page
            $('body').append(modalHtml);
            
            // Load products
            this.loadProducts();
            
            // Handle modal events
            $('.ai-modal-close').on('click', function() {
                $('#ai-review-preview-modal').remove();
            });
            
            $('#preview-product-select').on('change', function() {
                $('#generate-preview-btn').prop('disabled', !$(this).val());
            });
            
            $('#generate-preview-btn').on('click', function() {
                ARG.generatePreview();
            });
            
            // Show modal
            $('#ai-review-preview-modal').fadeIn();
        },
        
        /**
         * Load products for preview
         */
        loadProducts: function() {
            $.ajax({
                url: ai_review_generator_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_review_generator_get_products',
                    nonce: ai_review_generator_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        let options = '<option value="">Select a product...</option>';
                        response.data.forEach(function(product) {
                            options += `<option value="${product.id}">${product.name}</option>`;
                        });
                        $('#preview-product-select').html(options);
                    }
                },
                error: function() {
                    $('#preview-product-select').html('<option value="">Error loading products</option>');
                }
            });
        },
        
        /**
         * Generate preview review
         */
        generatePreview: function() {
            const productId = $('#preview-product-select').val();
            const $button = $('#generate-preview-btn');
            const originalText = $button.text();
            
            if (!productId) {
                return;
            }
            
            $button.text('Generating...').prop('disabled', true);
            $('#preview-result').hide();
            
            $.ajax({
                url: ai_review_generator_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_review_generator_generate_preview',
                    nonce: ai_review_generator_admin.nonce,
                    product_id: productId
                },
                success: function(response) {
                    if (response.success) {
                        $('#preview-content').html(
                            `<strong>Rating:</strong> ${response.data.rating} ⭐<br>` +
                            `<strong>Review:</strong><p>${response.data.review_text}</p>`
                        );
                        $('#preview-result').slideDown();
                    } else {
                        $('#preview-content').html(`<p class="error-message">${response.data.message}</p>`);
                        $('#preview-result').slideDown();
                    }
                },
                error: function() {
                    $('#preview-content').html(`<p class="error-message">${ai_review_generator_admin.strings.error}</p>`);
                    $('#preview-result').slideDown();
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Show a notice message
         */
        showNotice: function(message, type = 'info') {
            const noticeHtml = `
                <div class="notice notice-${type} is-dismissible ai-review-generator-notice">
                    <p>${message}</p>
                </div>
            `;
            $('.wrap h1').after(noticeHtml);
            
            setTimeout(function() {
                $('.ai-review-generator-notice').fadeOut();
            }, 5000);
        }
    };
    
    ARG.init();
});