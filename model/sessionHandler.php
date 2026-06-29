<?php
/**
 * Database Session Handler
 *
 * Stores PHP sessions in MySQL database instead of filesystem to prevent
 * OS cron jobs from deleting long-lived sessions (e.g., "Stay Logged In" feature).
 *
 * @author Cloud Box 9
 * @version 1.0
 * @date 2025-12-31
 */

class DatabaseSessionHandler implements SessionHandlerInterface
{
    private $db;
    private $table = 'userSessions';
    private $lifetime = 86400; // 24 hours default

    /**
     * Constructor
     *
     * @param PDO $db Database connection
     * @param int $lifetime Session lifetime in seconds
     */
    public function __construct($db, $lifetime = 86400)
    {
        $this->db = $db;
        $this->lifetime = $lifetime;
    }

    /**
     * Open session storage
     *
     * @param string $savePath Session save path (not used in database storage)
     * @param string $sessionName Session name
     * @return bool Always returns true
     */
    public function open(string $savePath, string $sessionName): bool
    {
        // No action needed for database storage
        return true;
    }

    /**
     * Close session storage
     *
     * @return bool Always returns true
     */
    public function close(): bool
    {
        // No action needed for database storage
        return true;
    }

    /**
     * Read session data
     *
     * @param string $sessionId Session ID
     * @return string|false Session data or empty string if not found, false on error
     */
    public function read(string $sessionId): string|false
    {
        try {
            $stmt = $this->db->prepare("
                SELECT sessionData
                FROM {$this->table}
                WHERE sessionId = :sessionId
                AND sessionExpire > NOW()
            ");
            $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_STR);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                // Update last activity timestamp
                $updateStmt = $this->db->prepare("
                    UPDATE {$this->table}
                    SET lastActivity = NOW()
                    WHERE sessionId = :sessionId
                ");
                $updateStmt->bindParam(':sessionId', $sessionId, PDO::PARAM_STR);
                $updateStmt->execute();

                return $row['sessionData'];
            }

            return '';
        } catch (PDOException $e) {
            error_log("Session read error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Write session data
     *
     * @param string $sessionId Session ID
     * @param string $sessionData Serialized session data
     * @return bool True on success, false on failure
     */
    public function write(string $sessionId, string $sessionData): bool
    {
        try {
            // Calculate expiration time
            $expireTime = date('Y-m-d H:i:s', time() + $this->lifetime);

            // Check if session exists
            $stmt = $this->db->prepare("
                SELECT sessionId
                FROM {$this->table}
                WHERE sessionId = :sessionId
            ");
            $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_STR);
            $stmt->execute();

            if ($stmt->fetch()) {
                // Update existing session
                $updateStmt = $this->db->prepare("
                    UPDATE {$this->table}
                    SET sessionData = :sessionData,
                        sessionExpire = :sessionExpire,
                        lastActivity = NOW()
                    WHERE sessionId = :sessionId
                ");
                $updateStmt->bindParam(':sessionId', $sessionId, PDO::PARAM_STR);
                $updateStmt->bindParam(':sessionData', $sessionData, PDO::PARAM_STR);
                $updateStmt->bindParam(':sessionExpire', $expireTime, PDO::PARAM_STR);
                return $updateStmt->execute();
            } else {
                // Insert new session
                $insertStmt = $this->db->prepare("
                    INSERT INTO {$this->table} (
                        sessionId,
                        sessionData,
                        sessionExpire,
                        lastActivity
                    ) VALUES (
                        :sessionId,
                        :sessionData,
                        :sessionExpire,
                        NOW()
                    )
                ");
                $insertStmt->bindParam(':sessionId', $sessionId, PDO::PARAM_STR);
                $insertStmt->bindParam(':sessionData', $sessionData, PDO::PARAM_STR);
                $insertStmt->bindParam(':sessionExpire', $expireTime, PDO::PARAM_STR);
                return $insertStmt->execute();
            }
        } catch (PDOException $e) {
            error_log("Session write error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Destroy session
     *
     * @param string $sessionId Session ID
     * @return bool True on success, false on failure
     */
    public function destroy(string $sessionId): bool
    {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM {$this->table}
                WHERE sessionId = :sessionId
            ");
            $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Session destroy error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Garbage collection - Remove expired sessions
     *
     * @param int $maxlifetime Maximum session lifetime in seconds
     * @return int|false Number of deleted sessions or false on failure
     */
    public function gc(int $maxlifetime): int|false
    {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM {$this->table}
                WHERE sessionExpire < NOW()
            ");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Session GC error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update session lifetime (for "Stay Logged In" feature)
     *
     * @param int $lifetime New lifetime in seconds
     */
    public function setLifetime($lifetime)
    {
        $this->lifetime = $lifetime;
    }
}
?>
