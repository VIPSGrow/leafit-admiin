<?php

if (!function_exists('destroy_session_files')) {
    /**
     * Manually destroy session files for complete logout
     * 
     * @param string|null $sessionId Specific session ID to destroy, or null for current session
     * @return bool True if successful, false otherwise
     */
    function destroy_session_files($sessionId = null)
    {
        try {
            $sessionPath = WRITEPATH . 'session/';

            // If no specific session ID provided, get current session ID
            if ($sessionId === null) {
                $sessionId = session_id();
            }

            // If we have a session ID, try to delete the specific session file
            if (!empty($sessionId)) {
                $sessionFile = $sessionPath . 'ci_session' . $sessionId;
                if (file_exists($sessionFile)) {
                    if (unlink($sessionFile)) {
                        // log_message('info', 'Session file deleted: ' . $sessionFile);
                        return true;
                    } else {
                        log_message('error', 'Failed to delete session file: ' . $sessionFile);
                    }
                }
            }

            // Also try to destroy all session files for the current user
            // This is a more aggressive approach to ensure complete logout
            $sessionFiles = glob($sessionPath . 'ci_session*');
            $deletedCount = 0;

            foreach ($sessionFiles as $file) {
                // Skip if we already deleted this file
                if (!file_exists($file)) {
                    continue;
                }

                try {
                    // Read session file to check if it belongs to current user
                    $content = file_get_contents($file);
                    if ($content !== false) {
                        $data = @unserialize($content);

                        // If we can't unserialize or if it's the current session, delete it
                        if (
                            $data === false ||
                            (isset($data['__ci_last_regenerate']) && $sessionId && strpos($file, $sessionId) !== false)
                        ) {

                            if (unlink($file)) {
                                $deletedCount++;
                                // log_message('info', 'Session file deleted: ' . basename($file));
                            }
                        }
                    }
                } catch (Exception $e) {
                    // If we can't read the file, try to delete it anyway
                    log_message('warning', 'Could not read session file, attempting to delete: ' . basename($file));
                    if (unlink($file)) {
                        $deletedCount++;
                        // log_message('info', 'Session file deleted: ' . basename($file));
                    }
                }
            }

            // log_message('info', 'Total session files deleted: ' . $deletedCount);
            return $deletedCount > 0;
        } catch (Exception $e) {
            log_message('error', 'Error destroying session files: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('clear_all_sessions')) {
    /**
     * Clear all session files (use with caution - affects all users)
     * 
     * @return int Number of session files deleted
     */
    function clear_all_sessions()
    {
        try {
            $sessionPath = WRITEPATH . 'session/';
            $sessionFiles = glob($sessionPath . 'ci_session*');
            $deletedCount = 0;

            foreach ($sessionFiles as $file) {
                if (unlink($file)) {
                    $deletedCount++;
                }
            }

            // log_message('info', 'All session files cleared. Count: ' . $deletedCount);
            return $deletedCount;
        } catch (Exception $e) {
            log_message('error', 'Error clearing all sessions: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('destroy_current_session')) {
    /**
     * Destroy current session completely
     * 
     * @return bool True if successful
     */
    function destroy_current_session()
    {
        try {
            // Check if session is started before trying to destroy it
            $sessionStarted = session_status() === PHP_SESSION_ACTIVE;
            $sessionId = null;

            if ($sessionStarted) {
                // Get current session ID before destroying
                $sessionId = session_id();

                // Clear all session variables first
                $_SESSION = array();

                // Destroy the session
                session_destroy();
            }

            // Manually delete session files
            $result = destroy_session_files($sessionId);

            // Clear session cookie if it exists
            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
            }

            // Also try to clear CodeIgniter session cookie
            if (isset($_COOKIE['ci_session'])) {
                setcookie('ci_session', '', time() - 3600, '/');
            }

            // log_message('info', 'Current session destroyed completely. Session was ' . ($sessionStarted ? 'active' : 'inactive'));
            return $result;
        } catch (Exception $e) {
            log_message('error', 'Error destroying current session: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('safe_destroy_session')) {
    /**
     * Safely destroy session without calling session_destroy()
     * This is a safer alternative that only deletes session files and clears cookies
     * 
     * @return bool True if successful
     */
    function safe_destroy_session()
    {
        try {
            // Get current session ID if available
            $sessionId = null;
            if (session_status() === PHP_SESSION_ACTIVE) {
                $sessionId = session_id();
            }

            // Manually delete session files
            $result = destroy_session_files($sessionId);

            // Clear session cookies
            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
            }

            // Clear CodeIgniter session cookie
            if (isset($_COOKIE['ci_session'])) {
                setcookie('ci_session', '', time() - 3600, '/');
            }

            // Clear session variables if session is active
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION = array();
            }

            // log_message('info', 'Session safely destroyed without session_destroy()');
            return $result;
        } catch (Exception $e) {
            log_message('error', 'Error in safe session destruction: ' . $e->getMessage());
            return false;
        }
    }
}
