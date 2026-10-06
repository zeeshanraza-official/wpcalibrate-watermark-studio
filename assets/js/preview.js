/**
 * WPCalibrate Watermark Studio - Live Interactive Preview Engine
 * Synchronizes form controls in real-time with visual canvas approximation.
 */
(function ($) {
	'use strict';

	const PreviewEngine = {
		init: function () {
			this.cacheElements();
			if (!this.$stage.length) {
				return;
			}

			this.bindEvents();
			this.render();
		},

		cacheElements: function () {
			this.$stage        = $('#wpcalibrate-preview-stage');
			this.$imgLayer     = $('#preview-layer-image');
			this.$imgElement   = $('#preview-layer-img-tag');
			this.$textLayer    = $('#preview-layer-text');

			// Inputs
			this.$mode         = $('input[name="wpcalibrate_watermark_studio_settings[watermark_mode]"]');
			this.$imgUrl       = $('#input-image-url');
			this.$imgRelWidth  = $('#input-image-relative-width');
			this.$imgMaxWidth  = $('#input-image-max-width');
			this.$imgOpacity   = $('#input-image-opacity');
			this.$imgPos       = $('#input-image-position');
			this.$imgOffsetX   = $('#input-image-offset-x');
			this.$imgOffsetY   = $('#input-image-offset-y');

			this.$textContent  = $('#input-text-content');
			this.$textStrategy = $('#input-text-strategy');
			this.$textSize     = $('#input-text-size');
			this.$textScale    = $('#input-text-scale');
			this.$textColor    = $('#input-text-color');
			this.$textOpacity  = $('#input-text-opacity');
			this.$textPos      = $('#input-text-position');
			this.$textOffsetX  = $('#input-text-offset-x');
			this.$textOffsetY  = $('#input-text-offset-y');
			this.$textShadow   = $('#input-text-shadow');
			this.$textShadowCol= $('#input-text-shadow-color');
		},

		bindEvents: function () {
			const self = this;
			const inputSelectors = [
				'input[name*="watermark_mode"]',
				'#input-image-url',
				'#input-image-relative-width',
				'#input-image-max-width',
				'#input-image-opacity',
				'#input-image-position',
				'#input-image-offset-x',
				'#input-image-offset-y',
				'#input-text-content',
				'#input-text-strategy',
				'#input-text-size',
				'#input-text-scale',
				'#input-text-color',
				'#input-text-opacity',
				'#input-text-position',
				'#input-text-offset-x',
				'#input-text-offset-y',
				'#input-text-shadow',
				'#input-text-shadow-color'
			].join(', ');

			$(document).on('input change', inputSelectors, function () {
				self.render();
			});

			// Re-render on window resize
			$(window).on('resize', function () {
				self.render();
			});
		},

		render: function () {
			if (!this.$stage.length) return;

			const stageW = this.$stage.width();
			const stageH = this.$stage.height();
			const mode   = this.$mode.filter(':checked').val() || 'image';

			// 1. Render Image Watermark
			const showImg = (mode === 'image' || mode === 'both');
			const imgUrl  = this.$imgUrl.val();

			if (showImg && imgUrl) {
				this.$imgElement.attr('src', imgUrl);
				this.$imgLayer.show();

				const relPct  = parseInt(this.$imgRelWidth.val(), 10) || 25;
				let targetW   = Math.max(20, Math.round((stageW * relPct) / 100));
				const maxW    = parseInt(this.$imgMaxWidth.val(), 10) || 0;
				if (maxW > 0 && targetW > maxW) {
					targetW = maxW;
				}
				const opacity = (parseInt(this.$imgOpacity.val(), 10) || 80) / 100;
				const pos     = this.$imgPos.val() || 'bottom_right';
				const offX    = parseInt(this.$imgOffsetX.val(), 10) || 20;
				const offY    = parseInt(this.$imgOffsetY.val(), 10) || 20;

				this.$imgLayer.css({
					width: targetW + 'px',
					opacity: opacity
				});

				const targetH = this.$imgLayer.outerHeight() || Math.round(targetW * 0.4);
				const coords  = this.calculateCoordinates(stageW, stageH, targetW, targetH, pos, offX, offY);

				this.$imgLayer.css({
					left: coords.x + 'px',
					top: coords.y + 'px'
				});
			} else {
				this.$imgLayer.hide();
			}

			// 2. Render Text Watermark
			const showText = (mode === 'text' || mode === 'both');
			const textVal  = this.$textContent.val();

			if (showText && textVal) {
				this.$textLayer.text(textVal).show();

				const strategy = this.$textStrategy.val() || 'proportional';
				let fontSize   = parseInt(this.$textSize.val(), 10) || 24;

				if (strategy === 'proportional') {
					const scalePct = parseInt(this.$textScale.val(), 10) || 5;
					fontSize = Math.max(10, Math.round((stageW * scalePct) / 100));
				}

				const color    = this.$textColor.val() || '#ffffff';
				const opacity  = (parseInt(this.$textOpacity.val(), 10) || 80) / 100;
				const pos      = this.$textPos.val() || 'bottom_left';
				const offX     = parseInt(this.$textOffsetX.val(), 10) || 20;
				const offY     = parseInt(this.$textOffsetY.val(), 10) || 20;
				const shadow   = this.$textShadow.is(':checked');
				const shadowCol= this.$textShadowCol.val() || '#000000';

				this.$textLayer.css({
					fontSize: fontSize + 'px',
					color: color,
					opacity: opacity,
					textShadow: shadow ? `2px 2px 3px ${shadowCol}` : 'none'
				});

				const textW  = this.$textLayer.outerWidth();
				const textH  = this.$textLayer.outerHeight();
				const coords = this.calculateCoordinates(stageW, stageH, textW, textH, pos, offX, offY);

				this.$textLayer.css({
					left: coords.x + 'px',
					top: coords.y + 'px'
				});
			} else {
				this.$textLayer.hide();
			}
		},

		calculateCoordinates: function (stageW, stageH, elemW, elemH, pos, offX, offY) {
			let x = 0;
			let y = 0;

			switch (pos) {
				case 'top_left':
					x = offX;
					y = offY;
					break;
				case 'top_center':
					x = Math.round((stageW - elemW) / 2) + offX;
					y = offY;
					break;
				case 'top_right':
					x = stageW - elemW - offX;
					y = offY;
					break;
				case 'center_left':
					x = offX;
					y = Math.round((stageH - elemH) / 2) + offY;
					break;
				case 'center':
					x = Math.round((stageW - elemW) / 2) + offX;
					y = Math.round((stageH - elemH) / 2) + offY;
					break;
				case 'center_right':
					x = stageW - elemW - offX;
					y = Math.round((stageH - elemH) / 2) + offY;
					break;
				case 'bottom_left':
					x = offX;
					y = stageH - elemH - offY;
					break;
				case 'bottom_center':
					x = Math.round((stageW - elemW) / 2) + offX;
					y = stageH - elemH - offY;
					break;
				case 'bottom_right':
				default:
					x = stageW - elemW - offX;
					y = stageH - elemH - offY;
					break;
			}

			return { x: x, y: y };
		}
	};

	$(document).ready(function () {
		PreviewEngine.init();
	});

	window.WPCalibratePreviewEngine = PreviewEngine;
})(jQuery);
