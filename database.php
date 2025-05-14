<?php
// database.php - Database connection and operations

class Database {
    private $host;
    private $username;
    private $password;
    private $dbname;
    private $conn;

    public function __construct() {
        $this->host = DB_HOST;
        $this->username = DB_USERNAME;
        $this->password = DB_PASSWORD;
        $this->dbname = DB_NAME;
        
        $this->connect();
    }

    private function connect() {
        try {
            $this->conn = new PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->conn;
    }

    // Initialize database tables if they don't exist
    public function initializeTables() {
        // Create events table
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL,
                event_date DATE NOT NULL,
                event_time TIME NOT NULL,
                venue VARCHAR(255) NOT NULL,
                price DECIMAL(10, 2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Create registrations table
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS registrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                user_name VARCHAR(255) NOT NULL,
                phone_number VARCHAR(20) NOT NULL,
                payment_status VARCHAR(20) DEFAULT 'pending',
                payment_reference VARCHAR(100),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (event_id) REFERENCES events(id)
            )
        ");

        // Create sessions table for USSD sessions
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS ussd_sessions (
                session_id VARCHAR(100) PRIMARY KEY,
                phone_number VARCHAR(20) NOT NULL,
                user_name VARCHAR(255),
                selected_event_id INT,
                registration_step VARCHAR(50),
                filtered_events_json TEXT,
                last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    // Insert sample events if the events table is empty
    public function insertSampleEvents() {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM events");
        $stmt->execute();
        $count = $stmt->fetchColumn();

        if ($count == 0) {
            $events = [
                [
                    'name' => 'Jazz Festival 2025',
                    'type' => 'concert',
                    'event_date' => '2025-05-20',
                    'event_time' => '19:00:00',
                    'venue' => 'Kigali Convention Centre',
                    'price' => 25000.00
                ],
                [
                    'name' => 'Comedy Night with John Doe',
                    'type' => 'comedy',
                    'event_date' => '2025-05-25',
                    'event_time' => '20:30:00',
                    'venue' => 'Kigali Cultural Village',
                    'price' => 15000.00
                ],
                [
                    'name' => 'Summer Blockbuster Premiere',
                    'type' => 'movie',
                    'event_date' => '2025-06-01',
                    'event_time' => '18:00:00',
                    'venue' => 'Century Cinema Kigali',
                    'price' => 10000.00
                ],
                [
                    'name' => 'Rock Concert',
                    'type' => 'concert',
                    'event_date' => '2025-06-05',
                    'event_time' => '21:00:00',
                    'venue' => 'Amahoro Stadium',
                    'price' => 30000.00
                ]
            ];

            $stmt = $this->conn->prepare("
                INSERT INTO events (name, type, event_date, event_time, venue, price)
                VALUES (:name, :type, :event_date, :event_time, :venue, :price)
            ");

            foreach ($events as $event) {
                $stmt->execute($event);
            }
        }
    }
}