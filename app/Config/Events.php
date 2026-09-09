<?php

namespace Config;

use CodeIgniter\Config\Config;
use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\FrameworkException;
// use CodeIgniter\Queue\Events\QueueEvent;
// use CodeIgniter\Queue\Events\QueueEventManager;

use Config\Email;
use MyHooks;



/*
 * --------------------------------------------------------------------
 * Application Events
 * --------------------------------------------------------------------
 * Events allow you to tap into the execution of the program without
 * modifying or extending core files. This file provides a central
 * location to define your events, though they can always be added
 * at run-time, also, if needed.
 *
 * You create code that can execute by subscribing to events with
 * the 'on()' method. This accepts any form of callable, including
 * Closures, that will be executed when the event is triggered.
 *
 * Example:
 *      Events::on('create', [$myInstance, 'myMethod']);
 */

$myclass = new MyHooks; // Create an instance of MyClass

// Binding class methods as event callbacks
helper('function_helper');
Events::on("pre_system", [$myclass, "my_hook"]);

Events::on('pre_system', function () {
	if (ENVIRONMENT !== 'testing') {
		if (ini_get('zlib.output_compression')) {
			throw FrameworkException::forEnabledZlibOutputCompression();
		}

		while (ob_get_level() > 0) {
			ob_end_flush();
		}

		ob_start(function ($buffer) {
			return $buffer;
		});
	}

	/*
	 * --------------------------------------------------------------------
	 * Debug Toolbar Listeners.
	 * --------------------------------------------------------------------
	 * If you delete, they will no longer be collected.
	 */
	set_language();
	if (CI_DEBUG && ! is_cli()) {
		Events::on('DBQuery', 'CodeIgniter\Debug\Toolbar\Collectors\Database::collect');
		Services::toolbar()->respond();
	}
});


Events::on('post_controller_constructor', function () {
	check_for_installer();
	set_system_timezone();
});

function check_for_installer()
{
	if (file_exists('install/index.php')) {
		header("location:install/");
		die();
	}
}
function set_language()
{
	$config = config('App');
	$config->supportedLocales  = ['en', 'hi'];
}
function set_system_timezone()
{
	$db  = \Config\Database::connect();
	$settings = get_settings('general_settings', true);
	/* Set database timezone */
	if (!empty($settings['system_timezone_gmt'])) {
		$db->query("SET time_zone='" . $settings['system_timezone_gmt'] . "'");
	} else {
		$db->query("SET time_zone='+05:30'");
	}


	/* Set PHP server timezone */
	if (!empty($settings['system_timezone'])) {
		date_default_timezone_set($settings['system_timezone']);
	} else {
		date_default_timezone_set('Asia/Kolkata');
	}
}

/*
 * --------------------------------------------------------------------
 * Queue event logging (writable/logs/queue_logs.log)
 * --------------------------------------------------------------------
 * Listener logs all queue events and full metadata for debugging.
 * Worker and connection events include config; job events include job/exception summary and queue payload.
 */
// $queueLogDir = WRITEPATH . 'logs';
// $queueLogFile = $queueLogDir . '/queue_logs.log';
// $queueEventLogger = function ($event) use ($queueLogFile, $queueLogDir) {
// 	if (! $event instanceof QueueEvent) {
// 		return;
// 	}
// 	if (! is_dir($queueLogDir)) {
// 		@mkdir($queueLogDir, 0755, true);
// 	}
// 	$ts = $event->getTimestamp()->toDateTimeString();
// 	$type = $event->getType();
// 	$handler = $event->getHandler();
// 	$queue = $event->getQueue() ?? '-';
// 	$line = sprintf("[%s] %s | handler=%s | queue=%s", $ts, $type, $handler, $queue);
// 	// Job-related fields
// 	if ($event->getJobId() !== null) {
// 		$line .= sprintf(" | job_id=%s", $event->getJobId());
// 	}
// 	if ($event->getJobClass() !== null) {
// 		$line .= sprintf(" | job_class=%s", $event->getJobClass());
// 	}
// 	if ($event->getPriority() !== null) {
// 		$line .= sprintf(" | priority=%s", $event->getPriority());
// 	}
// 	if ($event->getAttempts() !== null) {
// 		$line .= sprintf(" | attempts=%s", $event->getAttempts());
// 	}
// 	if ($event->getProcessingTime() > 0) {
// 		$line .= sprintf(" | processing_time=%.2fs", $event->getProcessingTime());
// 	}
// 	$exception = $event->getException();
// 	if ($exception !== null) {
// 		$line .= sprintf(" | error=%s", str_replace(["\r", "\n"], ' ', $exception->getMessage()));
// 		$line .= sprintf(" | file=%s:%s", $exception->getFile(), $exception->getLine());
// 	}
// 	// Worker event fields (WORKER_STARTED, WORKER_STOPPED)
// 	$meta = $event->getAllMetadata();
// 	if (isset($meta['priorities']) && is_array($meta['priorities'])) {
// 		$line .= ' | priorities=' . implode(',', $meta['priorities']);
// 	}
// 	if (isset($meta['uptime_seconds'])) {
// 		$line .= sprintf(" | uptime_seconds=%.1f", $meta['uptime_seconds']);
// 	}
// 	if (isset($meta['jobs_processed'])) {
// 		$line .= sprintf(" | jobs_processed=%s", $meta['jobs_processed']);
// 	}
// 	// Worker/connection config (WORKER_STARTED, HANDLER_CONNECTION_ESTABLISHED, HANDLER_CONNECTION_FAILED)
// 	if (isset($meta['config']) && is_array($meta['config'])) {
// 		$line .= ' | config=' . json_encode($meta['config'], JSON_UNESCAPED_SLASHES);
// 	}
// 	// Full metadata: serialize safely; include job payload for debugging
// 	$safeMeta = [];
// 	foreach ($meta as $k => $v) {
// 		if ($v instanceof \CodeIgniter\Queue\Entities\QueueJob) {
// 			$jobPayload = $v->payload ?? null;
// 			$safeMeta[$k] = ['_type' => 'job', 'id' => $v->id, 'queue' => $v->queue ?? null, 'priority' => $v->priority ?? null, 'attempts' => $v->attempts ?? null, 'payload' => $jobPayload];
// 			// Log payload on its own so it is visible even when metadata JSON is truncated
// 			if ($jobPayload !== null) {
// 				$payloadStr = is_array($jobPayload) ? json_encode($jobPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $jobPayload;
// 				if (strlen($payloadStr) > 1500) {
// 					$payloadStr = substr($payloadStr, 0, 1497) . '...';
// 				}
// 				$line .= ' | payload=' . $payloadStr;
// 			}
// 		} elseif ($v instanceof \Throwable) {
// 			$safeMeta[$k] = ['_type' => 'exception', 'message' => $v->getMessage(), 'file' => $v->getFile() . ':' . $v->getLine()];
// 		} elseif (is_array($v)) {
// 			$safeMeta[$k] = $v;
// 		} else {
// 			$safeMeta[$k] = $v;
// 		}
// 	}
// 	if ($safeMeta !== []) {
// 		$metaJson = json_encode($safeMeta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
// 		if (strlen($metaJson) > 2000) {
// 			$metaJson = substr($metaJson, 0, 1997) . '...';
// 		}
// 		$line .= ' | metadata=' . $metaJson;
// 	}
// 	$line .= PHP_EOL;
// 	@file_put_contents($queueLogFile, $line, FILE_APPEND | LOCK_EX);
// };

// Events::on(QueueEventManager::JOB_PUSHED, $queueEventLogger);
// Events::on(QueueEventManager::JOB_PUSH_FAILED, $queueEventLogger);
// Events::on(QueueEventManager::JOB_PROCESSING_STARTED, $queueEventLogger);
// Events::on(QueueEventManager::JOB_PROCESSING_COMPLETED, $queueEventLogger);
// Events::on(QueueEventManager::JOB_FAILED, $queueEventLogger);
// Events::on(QueueEventManager::QUEUE_CLEARED, $queueEventLogger);
// Events::on(QueueEventManager::WORKER_STARTED, $queueEventLogger);
// Events::on(QueueEventManager::WORKER_STOPPED, $queueEventLogger);
// Events::on(QueueEventManager::HANDLER_CONNECTION_ESTABLISHED, $queueEventLogger);
// Events::on(QueueEventManager::HANDLER_CONNECTION_FAILED, $queueEventLogger);
