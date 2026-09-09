<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\Honeypot;

class Filters extends BaseConfig
{
	/**
	 * Configures aliases for Filter classes to
	 * make reading things nicer and simpler.
	 *
	 * @var array
	 */
	public $aliases = [
		'csrf' => CSRF::class,
		'toolbar' => DebugToolbar::class,
		'honeypot' => Honeypot::class,
		'admin_sanitizer' => \App\Filters\AdminPanelSanitizer::class, // Legacy - kept for backward compatibility
		'global_sanitizer' => \App\Filters\GlobalSanitizer::class, // New robust global sanitizer
		'auth' => \App\Filters\AuthFilter::class,
		'protected' => \App\Filters\ProtectedRouteFilter::class,
		'admin_auth' => \App\Filters\AdminAuthFilter::class, // Admin-role gate (layered on top of `protected`)
		'handyman_auth' => \App\Filters\HandymanAuthFilter::class, // Handyman-role gate
		'permission' => \App\Filters\PermissionFilter::class, // Route-arg authorization gate (PermissionService::can)
		'csrf_response' => \App\Filters\CsrfResponseFilter::class, // Auto-injects csrfName/csrfHash into JSON responses (Phase 7)
		'ImageFallback' => \App\Filters\ImageFallback::class,
		'language' => \App\Filters\LanguageFilter::class,
		'handyman_api' => \App\Filters\HandymanApiFilter::class,
		'output_escaper' => \App\Filters\OutputEscaper::class,
		'cors' => \App\Filters\Cors::class,

	];

	/**
	 * List of filter aliases that are always
	 * applied before and after every request.
	 *
	 * @var array
	 */
	public $globals = [
		'before' => [

			// 'csrf' =>[
			// 	'except' =>[
			// 		"api/*",
			// 		"partner/api/*",
			// 	]
			// ],
			// Global sanitizer - applies to all routes for XSS protection
			// Sanitizes all GET and POST inputs before controllers receive them
			'global_sanitizer' => [
				'except' => [
					'login/*',
					'logout/*',
					'api/*', // Exclude API routes if they handle their own sanitization
					'partner/api/*', // Exclude partner API routes
				]
			],
			'cors',
		],
		'after' => [
			'ImageFallback',
			// OutputEscaper filter disabled - it's too aggressive and breaks legitimate HTML/JavaScript
			// Input sanitization is already handled by AdminPanelSanitizer and ProtectedRouteFilter
			// 'output_escaper' => [
			// 	'except' => [
			// 		'/api/*',
			// 		'/partner/api/*',
			// 		'/payment/*',
			// 		'/update_subscription_status',
			// 	]
			// ],
			'toolbar' => [
				'except' => [
					"/api/webhooks/*",
					// "/partner/api/[a-z0-9_-]+/[a-z0-9_-]+",
				]
			],
			'cors',

		],
	];

	/**
	 * List of filter aliases that works on a
	 * particular HTTP method (GET, POST, etc.).
	 *
	 * Example:
	 * 'post' => ['csrf', 'throttle']
	 *
	 * @var array
	 */
	public $methods = [
		// 'post' => ['throttle'],
		// 'get' => ['throttle'],

	];

	/**
	 * List of filter aliases that should run on any
	 * before or after URI patterns.
	 *
	 * Example:
	 * 'isLoggedIn' => ['before' => ['account/*', 'profiles/*']]
	 *
	 * @var array
	 */
	public $filters = [
		// Admin-role gate across ALL admin functionality. Layered on top of
		// the existing `protected` route-group filter (which still owns the
		// logged-in check + input sanitization). Public admin pages
		// (admin/login, admin/forgot-password) are skipped inside the filter.
		'admin_auth' => [
			'before' => [
				'admin',
				'admin/*',
			],
		],

		'handyman_auth' => [
			'before' => [
				'handyman',
				'handyman/*',
			],
		],

		// Phase 7 — auto-inject csrfName/csrfHash into JSON responses on admin routes.
		// Replaces the per-call csrf_token()/csrf_hash() args in successResponse()/
		// ErrorResponse()/NoPermission()/JsonResponse::*. Skips non-JSON bodies and
		// payloads that already carry the keys (legacy helper calls during rolling
		// migration).
		'csrf_response' => [
			'after' => [
				'admin',
				'admin/*',
			],
		],

		// Authorization gates extracted from inline is_permitted()+NoPermission()
		// guards in the partner (Provider*) controllers. Branching permission
		// checks remain inline (PermissionService::can) and are not listed here.
		// NOTE: duplicate() and delete_partner() are deliberately NOT here.
		// duplicate() denies with a redirect+toast (not NoPermission JSON);
		// delete_partner() runs the demo-mode guard BEFORE the permission
		// check, so moving the permission pre-controller would reorder the
		// demo-vs-permission denial. Both keep an inline check (swapped to
		// PermissionService::can) to preserve exact behaviour.

	];

	public function __construct()
	{
		parent::__construct();

		foreach (glob(APPPATH . 'Config/AccessControl/*.php') as $file) {
			$this->filters = array_replace_recursive($this->filters, require $file);
		}
	}
}
