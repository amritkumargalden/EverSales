<?php
/**
 * Database Connection Utility
 * Handles MySQL database connection and queries for EverSales
 */

require_once __DIR__ . '/../config.php';

class Database {
    private $host;
    private $db_name;
    private $db_user;
    private $db_pass;
    private $connection;

    /**
     * Constructor - Initialize with config values
     */
    public function __construct() {
        $this->host = DB_HOST;
        $this->db_name = DB_NAME;
        $this->db_user = DB_USER;
        $this->db_pass = DB_PASSWORD;
    }

    /**
     * Connect to MySQL database
     */
    public function connect() {
        if (function_exists('mysqli_report')) {
            mysqli_report(MYSQLI_REPORT_OFF);
        }

        $this->connection = new mysqli(
            $this->host,
            $this->db_user,
            $this->db_pass,
            $this->db_name
        );

        // Check connection
        if ($this->connection->connect_error) {
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
            }

            die(json_encode([
                'success' => false,
                'message' => 'Database connection failed. Please check that MySQL is running and the database settings are correct.'
            ]));
        }

        // Set charset
        $this->connection->set_charset("utf8mb4");
        return $this->connection;
    }

    /**
     * Execute a SELECT query
     */
    public function query($sql) {
        $result = $this->connection->query($sql);
        if (!$result) {
            return ['error' => $this->connection->error];
        }
        return $result;
    }

    /**
     * Execute an INSERT/UPDATE/DELETE query
     */
    public function execute($sql) {
        if ($this->connection->query($sql)) {
            return [
                'success' => true,
                'insert_id' => $this->connection->insert_id,
                'affected_rows' => $this->connection->affected_rows
            ];
        } else {
            return [
                'success' => false,
                'error' => $this->connection->error
            ];
        }
    }

    /**
     * Get a single row as associative array
     */
    public function getRow($sql) {
        $result = $this->connection->query($sql);
        if (!$result) {
            return null;
        }
        return $result->fetch_assoc();
    }

    /**
     * Get all rows as array
     */
    public function getResults($sql) {
        $result = $this->connection->query($sql);
        if (!$result) {
            return [];
        }
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Escape string to prevent SQL injection
     */
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }

    /**
     * Prepare statement for safer queries
     */
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }

    /**
     * Close database connection
     */
    public function close() {
        if ($this->connection) {
            $this->connection->close();
        }
    }

    /**
     * Begin transaction
     */
    public function beginTransaction() {
        $this->connection->begin_transaction();
    }

    /**
     * Commit transaction
     */
    public function commit() {
        $this->connection->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback() {
        $this->connection->rollback();
    }
}
