/**
 * Fast Single Product Theme - Main JavaScript
 * Vanilla JS, zero dependencies, optimized for performance
 * Handles: Pack selector, AJAX checkout, sticky CTA, accordions
 */

(function() {
	'use strict';

	// Configuration
	const config = {
		ajaxUrl: fspAjax?.url || '/wp-admin/admin-ajax.php',
		nonce: fspAjax?.nonce || '',
		mobile: window.innerWidth < 768,
		codeRefresh: false,
	};

	/**
	 * DOM Ready Initialization
	 */
	function init() {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', setup);
		} else {
			setup();
		}
	}

	function setup() {
		initPackSelector();
		initCheckoutButtons();
		initHeroCTA();
		initMobileMenu();
		initBackToTop();
		initStickyBottomCTA();
		initAccordions();
		initSmoothScroll();
		handleResponsive();
		window.addEventListener('resize', debounce(handleResponsive, 250));
	}

	/**
	 * Pack Selector - Handle variant option selection
	 */
	function initPackSelector() {
		const variantOptions = document.querySelectorAll('.variant-option');
		const priceDisplay = document.getElementById('js-price-display');
		const stickyPrice = document.getElementById('js-sticky-price');

		variantOptions.forEach(option => {
			option.addEventListener('change', function(e) {
				if (this.querySelector('input[type="radio"]').checked) {
					const variationId = this.dataset.variationId;
					const price = this.dataset.price;

					// Update selected state
					variantOptions.forEach(o => {
						o.classList.remove('selected');
					});
					this.classList.add('selected');

					// Fetch and update price
					if (variationId && config.nonce) {
						fetchVariantPrice(variationId, priceDisplay, stickyPrice);
					}

					// Store selected variation for checkout
					window.fspSelectedVariation = {
						id: variationId,
						price: price,
					};
				}
			});

			// Auto-select first variant
			const input = option.querySelector('input[type="radio"]');
			if (input && !window.fspSelectedVariation) {
				setTimeout(() => {
					input.checked = true;
					input.dispatchEvent(new Event('change', { bubbles: true }));
					option.classList.add('selected');
				}, 100);
			}
		});
	}

	/**
	 * Fetch variant price via AJAX
	 */
	function fetchVariantPrice(variationId, priceDisplay, stickyPrice) {
		const formData = new FormData();
		formData.append('action', 'fsp_get_variant_price');
		formData.append('nonce', config.nonce);
		formData.append('variation_id', variationId);

		fetch(config.ajaxUrl, {
			method: 'POST',
			body: formData,
		})
			.then(response => response.json())
			.then(data => {
				if (data.success && data.data.price) {
					if (priceDisplay) {
						priceDisplay.innerHTML = data.data.price;
					}
					if (stickyPrice) {
						stickyPrice.innerHTML = data.data.price;
					}
				}
			})
			.catch(error => console.warn('Price fetch error:', error));
	}

	/**
	 * Hero CTA - Handle the homepage hero buy button
	 */
	function initHeroCTA() {
		const heroCta = document.getElementById('js-hero-cta');
		const primaryBtn = document.getElementById('js-btn-instant-checkout');

		if (heroCta) {
			heroCta.addEventListener('click', handleCheckout);
		}
	}

	/**
	 * Mobile Menu - Handle burger toggle
	 */
	function initMobileMenu() {
		const burger = document.getElementById('navigation-burger');
		const mobileNav = document.getElementById('mobile-navigation-bar');

		if (burger && mobileNav) {
			burger.addEventListener('click', () => {
				document.body.classList.toggle('mobile-menu-open');
			});
		}
	}

	/**
	 * Back To Top - Show/hide on scroll
	 */
	function initBackToTop() {
		const backToTop = document.querySelector('.footer-back-to-top');
		if (!backToTop) return;

		backToTop.style.display = 'none';

		window.addEventListener('scroll', debounce(() => {
			if (window.scrollY > 400) {
				backToTop.style.display = 'flex';
			} else {
				backToTop.style.display = 'none';
			}
		}, 100));
	}

	/**
	 * Checkout Buttons - Handle Buy Now & Instant Checkout
	 */
	function initCheckoutButtons() {
		const primaryBtn = document.getElementById('js-btn-instant-checkout');
		const stickyBtn = document.getElementById('js-btn-sticky-checkout');

		if (primaryBtn) {
			primaryBtn.addEventListener('click', handleCheckout);
		}

		if (stickyBtn) {
			stickyBtn.addEventListener('click', handleCheckout);
		}
	}

	function handleCheckout(e) {
		e.preventDefault();

		const productId = this.dataset.productId;
		const quantity = 1;
		const variationId = window.fspSelectedVariation?.id || null;

		if (!productId) {
			console.error('Product ID missing');
			return;
		}

		// Show loading state
		const originalText = this.textContent;
		this.textContent = 'Processing...';
		this.disabled = true;

		const formData = new FormData();
		formData.append('action', 'fsp_add_to_cart');
		formData.append('nonce', config.nonce);
		formData.append('product_id', productId);
		formData.append('quantity', quantity);
		if (variationId) {
			formData.append('variation_id', variationId);
		}

		fetch(config.ajaxUrl, {
			method: 'POST',
			body: formData,
		})
			.then(response => response.json())
			.then(data => {
				if (data.success && data.data.redirect) {
					// Redirect to checkout
					window.location.href = data.data.redirect;
				} else {
					throw new Error(data.data || 'Checkout failed');
				}
			})
			.catch(error => {
				console.error('Checkout error:', error);
				this.textContent = originalText;
				this.disabled = false;
				alert('Error processing checkout. Please try again.');
			});
	}

	/**
	 * Sticky Bottom CTA - Show/hide on scroll
	 */
	function initStickyBottomCTA() {
		const bottomCta = document.querySelector('.bottom-cta');
		const heroFold = document.querySelector('.hero-fold');

		if (!bottomCta || !heroFold) return;

		// Initial state
		updateStickyState();

		window.addEventListener('scroll', debounce(updateStickyState, 50));

		function updateStickyState() {
			const heroRect = heroFold.getBoundingClientRect();
			// Show sticky CTA if hero fold is scrolled past
			if (heroRect.bottom < 0) {
				bottomCta.style.display = 'flex';
			} else {
				bottomCta.style.display = 'none';
			}
		}
	}

	/**
	 * Accordion - Use native HTML5 <details> element
	 * Add animation support for better UX
	 */
	function initAccordions() {
		const details = document.querySelectorAll('details');

		details.forEach(detail => {
			detail.addEventListener('toggle', function() {
				if (this.open) {
					// Smooth height animation
					const summary = this.querySelector('summary');
					const content = this.querySelector('[class*="answer"], [class*="content"]');

					if (summary && content) {
						// Small delay for visual smoothness
						setTimeout(() => {
							content.style.maxHeight = content.scrollHeight + 'px';
						}, 10);
					}
				}
			});
		});

		// Optional: Close other details when one opens
		details.forEach(detail => {
			detail.addEventListener('toggle', function() {
				if (this.open) {
					// Uncomment to close others:
					// details.forEach(d => {
					//   if (d !== this) d.open = false;
					// });
				}
			});
		});
	}

	/**
	 * Smooth Scroll - Enhanced user experience
	 */
	function initSmoothScroll() {
		document.addEventListener('click', function(e) {
			const link = e.target.closest('a[href^="#"]');
			if (!link) return;

			e.preventDefault();
			const target = document.querySelector(link.hash);
			if (target) {
				target.scrollIntoView({ behavior: 'smooth' });
			}
		});
	}

	/**
	 * Responsive Adjustments
	 */
	function handleResponsive() {
		config.mobile = window.innerWidth < 768;

		// Add mobile-specific class to body
		if (config.mobile) {
			document.body.classList.add('is-mobile');
		} else {
			document.body.classList.remove('is-mobile');
		}

		// Adjust sticky CTA visibility
		const bottomCta = document.querySelector('.bottom-cta');
		if (bottomCta) {
			if (config.mobile) {
				bottomCta.style.display = window.scrollY > 500 ? 'flex' : 'none';
			} else {
				bottomCta.style.display = 'none';
			}
		}
	}

	/**
	 * Utility: Debounce function for performance
	 */
	function debounce(func, wait) {
		let timeout;
		return function executedFunction(...args) {
			const later = () => {
				clearTimeout(timeout);
				func(...args);
			};
			clearTimeout(timeout);
			timeout = setTimeout(later, wait);
		};
	}

	/**
	 * Performance: Lazy load images
	 */
	function initLazyLoad() {
		if ('IntersectionObserver' in window) {
			const lazyImages = document.querySelectorAll('img[loading="lazy"]');
			const imageObserver = new IntersectionObserver((entries, observer) => {
				entries.forEach(entry => {
					if (entry.isIntersecting) {
						const img = entry.target;
						if (img.dataset.src) {
							img.src = img.dataset.src;
							img.removeAttribute('data-src');
						}
						observer.unobserve(img);
					}
				});
			});
			lazyImages.forEach(img => imageObserver.observe(img));
		}
	}

	/**
	 * Analytics: Track CTA clicks
	 */
	function trackCTAClicks() {
		if (!window.gtag) return;

		document.getElementById('js-btn-instant-checkout')?.addEventListener('click', () => {
			gtag('event', 'add_to_cart', {
				event_category: 'checkout',
				event_label: 'instant_checkout',
			});
		});

		document.getElementById('js-btn-sticky-checkout')?.addEventListener('click', () => {
			gtag('event', 'add_to_cart', {
				event_category: 'checkout',
				event_label: 'sticky_checkout',
			});
		});
	}

	/**
	 * Validation: Ensure essential elements exist
	 */
	function validateDOM() {
		const requiredElements = [
			'js-btn-instant-checkout',
			'js-price-display',
			'main',
		];

		requiredElements.forEach(id => {
			if (!document.getElementById(id) && id !== 'js-price-display') {
				console.warn(`Expected element #${id} not found`);
			}
		});
	}

	/**
	 * Storage: Handle cart state
	 */
	window.fspCart = {
		selectedVariation: null,

		addToCart(productId, variationId, quantity = 1) {
			this.selectedVariation = { productId, variationId, quantity };
			localStorage.setItem('fsp_cart', JSON.stringify(this.selectedVariation));
		},

		getCart() {
			const stored = localStorage.getItem('fsp_cart');
			return stored ? JSON.parse(stored) : null;
		},

		clear() {
			this.selectedVariation = null;
			localStorage.removeItem('fsp_cart');
		},
	};

	/**
	 * Accessibility: Enhance keyboard navigation
	 */
	function initA11y() {
		// Focus visible for keyboard navigation
		document.addEventListener('keydown', function(e) {
			if (e.key === 'Tab') {
				document.body.classList.add('keyboard-nav');
			}
		});

		document.addEventListener('mousedown', function() {
			document.body.classList.remove('keyboard-nav');
		});
	}

	/**
	 * Performance: Remove unused code after load
	 */
	window.addEventListener('load', () => {
		// Initialization complete
		console.log('FSP Theme loaded successfully');

		// Optional: Track performance metrics
		if (window.performance && window.performance.timing) {
			const perfData = window.performance.timing;
			const pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;
			console.log(`Page load time: ${pageLoadTime}ms`);

			// Send to analytics if available
			if (window.gtag) {
				gtag('event', 'page_load', {
					value: pageLoadTime,
					event_category: 'performance',
				});
			}
		}
	});

	// Start initialization
	init();

	// Expose utilities to global scope if needed
	window.fspTheme = {
		config,
		debounce,
		initCheckoutButtons,
	};
})();

/**
 * CSS-in-JS: Minimal dynamic styling for sticky CTA on scroll
 */
document.addEventListener('DOMContentLoaded', () => {
	const bottomCta = document.querySelector('.bottom-cta');
	if (!bottomCta) return;

	// Add padding to body when sticky CTA is visible
	window.addEventListener('scroll', function() {
		const mainElement = document.querySelector('main');
		if (!mainElement) return;

		if (bottomCta.style.display === 'flex') {
			mainElement.style.paddingBottom = bottomCta.offsetHeight + 'px';
		} else {
			mainElement.style.paddingBottom = '0';
		}
	});
});
