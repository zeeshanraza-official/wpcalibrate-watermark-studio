/**
 * WPCalibrate Watermark Studio - Admin Interactions & Bulk Worker
 */
(function ($) {
	'use strict';

	// 1. Tab Navigation
	function initTabs() {
		const $tabs   = $('.wpcalibrate-tab-link');
		const $panels = $('.wpcalibrate-tab-panel');

		// Handle hash on load
		let activeTab = window.location.hash ? window.location.hash.substring(1) : 'general';
		if (!$('#tab-' + activeTab).length) {
			activeTab = 'general';
		}

		activateTab(activeTab);

		$tabs.on('click', function (e) {
			e.preventDefault();
			const target = $(this).data('tab');
			activateTab(target);
			window.location.hash = target;
		});

		function activateTab(tabKey) {
			$tabs.removeClass('active');
			$panels.removeClass('active');

			$tabs.filter('[data-tab="' + tabKey + '"]').addClass('active');
			$('#tab-' + tabKey).addClass('active');

			if (window.WPCalibratePreviewEngine) {
				window.WPCalibratePreviewEngine.render();
			}
		}
	}

	// 2. Media Library Picker for Watermark Image
	function initMediaPicker() {
		let mediaFrame;

		$('#btn-select-watermark-image').on('click', function (e) {
			e.preventDefault();

			if (mediaFrame) {
				mediaFrame.open();
				return;
			}

			mediaFrame = wp.media({
				title: wpcalibrateWsL10n.selectWatermark || 'Select Watermark Image',
				button: {
					text: wpcalibrateWsL10n.useAsWatermark || 'Use as Watermark'
				},
				multiple: false,
				library: {
					type: 'image'
				}
			});

			mediaFrame.on('select', function () {
				const attachment = mediaFrame.state().get('selection').first().toJSON();
				$('#input-image-id').val(attachment.id);
				$('#input-image-url').val(attachment.url).trigger('change');
				$('#watermark-image-preview-thumb').attr('src', attachment.url).show();
				$('#btn-remove-watermark-image').show();
			});

			mediaFrame.open();
		});

		$('#btn-remove-watermark-image').on('click', function (e) {
			e.preventDefault();
			$('#input-image-id').val(0);
			$('#input-image-url').val('').trigger('change');
			$('#watermark-image-preview-thumb').attr('src', '').hide();
			$(this).hide();
		});
	}

	// 3. Color Pickers Initialization
	function initColorPickers() {
		if (!$.fn.wpColorPicker) return;

		$('.color-picker-input').wpColorPicker({
			change: function (event, ui) {
				$(this).val(ui.color.toString()).trigger('change');
			},
			clear: function () {
				$(this).val('').trigger('change');
			}
		});
	}

	// 4. Settings Form Dynamic Conditionals
	function initSettingsConditionals() {
		// Font Size Strategy Toggle
		function updateFontStrategy() {
			const strategy = $('#input-text-strategy').val();
			if (strategy === 'fixed') {
				$('#wrapper-text-scale').hide();
				$('#wrapper-text-size').show();
			} else {
				$('#wrapper-text-scale').show();
				$('#wrapper-text-size').hide();
			}
		}
		$('#input-text-strategy').on('change', updateFontStrategy);
		updateFontStrategy();

		// Text Drop Shadow Toggle
		function updateTextShadow() {
			const enabled = $('#input-text-shadow').is(':checked');
			if (enabled) {
				$('#wrapper-text-shadow-color').show();
			} else {
				$('#wrapper-text-shadow-color').hide();
			}
		}
		$('#input-text-shadow').on('change', updateTextShadow);
		updateTextShadow();

		// Automation Context Scope Toggle
		function updateAutoContext() {
			const context = $('input[name*="auto_target_context"]:checked').val();
			if (context === 'post_types') {
				$('#row-auto-post-types').show();
			} else {
				$('#row-auto-post-types').hide();
			}
		}
		$('input[name*="auto_target_context"]').on('change', updateAutoContext);
		updateAutoContext();
	}

	// 5. Bulk Queue Interactive Worker & Scope Filtering
	function initBulkQueue() {
		const $console = $('#wpcalibrate-bulk-console');
		if (!$console.length) return;

		const nonce = $console.data('nonce');
		let isRunning = false;

		const $opSelect   = $('#bulk-operation');
		const $targetSelect = $('#bulk-target');
		const $startBtn   = $('#btn-start-bulk');
		const $cancelBtn  = $('#btn-cancel-bulk');
		const $panel      = $('#bulk-progress-panel');
		const $barFill    = $('#bulk-progress-fill');
		const $barText    = $('#bulk-progress-text');
		const $barPercent = $('#bulk-progress-percent');
		const $completed  = $('#stat-completed');
		const $skipped    = $('#stat-skipped');
		const $failed     = $('#stat-failed');
		const $remaining  = $('#stat-remaining');
		const $errTable   = $('#bulk-errors-table tbody');
		const $errContainer = $('#bulk-errors-container');

		// Sync Target Scope dropdown according to chosen Operation
		function syncBulkTargetOptions() {
			const op = $opSelect.val();
			if (op === 'restore') {
				$targetSelect.find('option[value="unwatermarked"]').hide().prop('disabled', true);
				$targetSelect.find('option[value="failed"]').hide().prop('disabled', true);
				$targetSelect.find('option[value="backed_up"]').show().prop('disabled', false);
				$targetSelect.find('option[value="watermarked"]').show().prop('disabled', false);
				$targetSelect.find('option[value="all"]').show().prop('disabled', false);

				if ($targetSelect.val() === 'unwatermarked' || $targetSelect.val() === 'failed' || !$targetSelect.val()) {
					$targetSelect.val('backed_up');
				}
			} else {
				$targetSelect.find('option[value="unwatermarked"]').show().prop('disabled', false);
				$targetSelect.find('option[value="failed"]').show().prop('disabled', false);
				$targetSelect.find('option[value="backed_up"]').hide().prop('disabled', true);
				$targetSelect.find('option[value="watermarked"]').show().prop('disabled', false);
				$targetSelect.find('option[value="all"]').show().prop('disabled', false);

				if ($targetSelect.val() === 'backed_up' || !$targetSelect.val()) {
					$targetSelect.val('unwatermarked');
				}
			}
		}

		$opSelect.on('change', syncBulkTargetOptions);
		syncBulkTargetOptions();

		$startBtn.on('click', function () {
			const operation = $opSelect.val();
			const target    = $targetSelect.val();

			$startBtn.prop('disabled', true).text(wpcalibrateWsL10n.starting || 'Starting...');
			$cancelBtn.show();
			$panel.slideDown();
			$errTable.empty();
			$errContainer.hide();

			$.post(ajaxurl, {
				action: 'wpcalibrate_ws_bulk_start',
				nonce: nonce,
				operation: operation,
				target: target
			}, function (res) {
				if (res.success && res.data.state) {
					isRunning = true;
					updateUI(res.data.state, res.data.counts);
					runNextBatch();
				} else {
					alert(res.data ? res.data.message : 'Error starting queue');
					$startBtn.prop('disabled', false).text(wpcalibrateWsL10n.startQueue || 'Start Bulk Queue');
					$cancelBtn.hide();
				}
			}).fail(function () {
				alert('Server communication error.');
				$startBtn.prop('disabled', false).text(wpcalibrateWsL10n.startQueue || 'Start Bulk Queue');
				$cancelBtn.hide();
			});
		});

		$cancelBtn.on('click', function () {
			if (!confirm(wpcalibrateWsL10n.confirmCancel || 'Are you sure you want to cancel the queue?')) return;

			isRunning = false;
			$cancelBtn.prop('disabled', true).text(wpcalibrateWsL10n.cancelling || 'Cancelling...');

			$.post(ajaxurl, {
				action: 'wpcalibrate_ws_bulk_cancel',
				nonce: nonce
			}, function (res) {
				if (res.success && res.data.state) {
					updateUI(res.data.state, res.data.counts);
				}
				$cancelBtn.hide().prop('disabled', false).text(wpcalibrateWsL10n.cancelQueue || 'Cancel Queue');
				$startBtn.prop('disabled', false).text(wpcalibrateWsL10n.startQueue || 'Start Bulk Queue');
			});
		});

		function runNextBatch() {
			if (!isRunning) return;

			$.post(ajaxurl, {
				action: 'wpcalibrate_ws_bulk_work',
				nonce: nonce
			}, function (res) {
				if (res.success && res.data.state) {
					updateUI(res.data.state, res.data.counts);

					if (res.data.state.status === 'running') {
						setTimeout(runNextBatch, 500); // Trigger next batch
					} else {
						// Finished or stopped
						isRunning = false;
						$startBtn.prop('disabled', false).text(wpcalibrateWsL10n.startQueue || 'Start Bulk Queue');
						$cancelBtn.hide();
					}
				}
			}).fail(function () {
				// Retry conservatively after brief delay
				if (isRunning) {
					setTimeout(runNextBatch, 3000);
				}
			});
		}

		function updateUI(state, counts) {
			const total     = state.total_items || 0;
			const processed = state.processed_items || 0;
			const percent   = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;

			$barFill.css('width', percent + '%');
			$barPercent.text(percent + '%');
			$barText.text(`Processed ${processed} of ${total} (${state.status.toUpperCase()})`);

			$completed.text(state.completed_count || 0);
			$skipped.text(state.skipped_count || 0);
			$failed.text(state.failed_count || 0);
			$remaining.text(state.remaining_ids ? state.remaining_ids.length : 0);

			if (counts) {
				$('#metric-total').text(counts.total);
				$('#metric-watermarked').text(counts.watermarked);
				$('#metric-unwatermarked').text(counts.unwatermarked);
				$('#metric-backed-up').text(counts.backed_up);
				$('#metric-failed').text(counts.failed);
			}

			if (state.errors && state.errors.length > 0) {
				$errTable.empty();
				state.errors.forEach(function (err) {
					$errTable.append(`<tr><td>#${err.attachment_id}</td><td><code>${err.code}</code></td><td>${err.message}</td></tr>`);
				});
				$errContainer.show();
			}
		}
	}

	$(document).ready(function () {
		initTabs();
		initMediaPicker();
		initColorPickers();
		initSettingsConditionals();
		initBulkQueue();
	});
})(jQuery);
