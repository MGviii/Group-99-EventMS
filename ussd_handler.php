<?php
// ussd_handler.php - Handles USSD requests and responses

class UssdHandler {
    private $conn;
    private $sessionData;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function handleRequest($sessionId, $serviceCode, $phoneNumber, $text) {
        // Load or create session
        $this->loadSession($sessionId, $phoneNumber);

        // Process the USSD text input
        return $this->processUssdInput($sessionId, $text);
    }

    private function loadSession($sessionId, $phoneNumber) {
        // Check if session exists
        $stmt = $this->conn->prepare("SELECT * FROM ussd_sessions WHERE session_id = :session_id");
        $stmt->execute(['session_id' => $sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($session) {
            // Update last activity timestamp
            $stmt = $this->conn->prepare("
                UPDATE ussd_sessions 
                SET last_activity = CURRENT_TIMESTAMP 
                WHERE session_id = :session_id
            ");
            $stmt->execute(['session_id' => $sessionId]);
            $this->sessionData = $session;
        } else {
            // Create new session
            $stmt = $this->conn->prepare("
                INSERT INTO ussd_sessions (session_id, phone_number) 
                VALUES (:session_id, :phone_number)
            ");
            $stmt->execute([
                'session_id' => $sessionId,
                'phone_number' => $phoneNumber
            ]);

            $this->sessionData = [
                'session_id' => $sessionId,
                'phone_number' => $phoneNumber,
                'user_name' => null,
                'selected_event_id' => null,
                'registration_step' => null,
                'filtered_events_json' => null
            ];
        }
    }

    private function updateSessionData($sessionId, $data) {
        $allowedFields = [
            'user_name', 
            'selected_event_id', 
            'registration_step', 
            'filtered_events_json'
        ];
        
        $updates = [];
        $params = ['session_id' => $sessionId];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updates[] = "$key = :$key";
                $params[$key] = $value;
            }
        }
        
        if (count($updates) > 0) {
            $sql = "UPDATE ussd_sessions SET " . implode(", ", $updates) . " WHERE session_id = :session_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            // Update local session data
            foreach ($data as $key => $value) {
                if (in_array($key, $allowedFields)) {
                    $this->sessionData[$key] = $value;
                }
            }
        }
    }

    private function processUssdInput($sessionId, $text) {
        // Main menu
        if ($text == "") {
            return "CON Welcome to Event Registration\n1. Browse Events\n2. My Registrations\n3. Exit";
        }
        
        // Split the text by * to determine the menu level
        $textParts = explode("*", $text);
        
        // Main menu options
        if (count($textParts) == 1) {
            $selectedOption = $textParts[0];
            
            if ($selectedOption == "1") {
                // Browse events
                return "CON Select Event Category\n1. Concerts\n2. Comedy Shows\n3. Movie Premieres\n4. All Events";
            }
            
            else if ($selectedOption == "2") {
                // My registrations - Fetch from database
                return $this->getUserRegistrations($this->sessionData['phone_number']);
            }
            
            else if ($selectedOption == "3") {
                // Exit
                return "END Thank you for using Event Registration. Goodbye!";
            }
            
            else {
                return "CON Invalid option. Please try again.\n\n0. Back to Main Menu";
            }
        }
        
        // Event category selection
        else if (count($textParts) == 2 && $textParts[0] == "1") {
            $category = $textParts[1];
            return $this->getEventsByCategory($category);
        }
        
        // Event selection
        else if (count($textParts) == 3 && $textParts[0] == "1") {
            if ($textParts[2] == "0") {
                return "CON Welcome to Event Registration\n1. Browse Events\n2. My Registrations\n3. Exit";
            }
            
            return $this->handleEventSelection($textParts[2]);
        }
        
        // Register for event or go back
        else if (count($textParts) == 4 && $textParts[0] == "1") {
            $choice = $textParts[3];
            
            if ($choice == "1") {
                // Start registration process
                $this->updateSessionData($sessionId, ['registration_step' => 'name']);
                return "CON Please enter your full name:";
            } 
            else if ($choice == "0") {
                // Go back to event category
                return "CON Select Event Category\n1. Concerts\n2. Comedy Shows\n3. Movie Premieres\n4. All Events";
            }
            else {
                return "CON Invalid option. Please try again.\n\n0. Back to Main Menu";
            }
        }
        
        // Handle registration steps
        else if ($this->sessionData['registration_step'] == 'name' && count($textParts) == 5) {
            // Save name and ask for confirmation
            $name = $textParts[4];
            $this->updateSessionData($sessionId, ['user_name' => $name, 'registration_step' => 'confirm']);
            
            return $this->getRegistrationSummary($name);
        }
        
        // Handle payment confirmation
        else if ($this->sessionData['registration_step'] == 'confirm' && count($textParts) == 6) {
            $choice = $textParts[5];
            
            if ($choice == "1") {
                // Process payment and registration
                return $this->processRegistration();
            }
            
            else if ($choice == "2") {
                // Cancel registration
                return "END Registration cancelled. Thank you for using our service.";
            }
            
            else {
                return "CON Invalid option. Please try again.\n\n0. Back to Main Menu";
            }
        }
        
        // Handle any other case
        else {
            return "CON Invalid input. Please try again.\n\n0. Back to Main Menu";
        }
    }

    private function getEventsByCategory($category) {
        $eventList = "CON Select an Event\n";
        $sql = "SELECT * FROM events";
        $params = [];
        
        // Filter events based on category
        if ($category == "1") {  // Concerts
            $sql .= " WHERE type = 'concert'";
        } 
        else if ($category == "2") {  // Comedy Shows
            $sql .= " WHERE type = 'comedy'";
        } 
        else if ($category == "3") {  // Movie Premieres
            $sql .= " WHERE type = 'movie'";
        } 
        else if ($category == "4") {  // All Events
            // No filter needed
        } 
        else {
            return "CON Invalid option. Please try again.\n\n0. Back to Main Menu";
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($events) == 0) {
            return "CON No events found in this category.\n\n0. Back to Main Menu";
        }
        
        // Build the event list and store the filtered events
        $filteredEvents = [];
        $counter = 1;
        
        foreach ($events as $event) {
            $eventList .= $counter . ". " . $event['name'] . " - " . date('M d, Y', strtotime($event['event_date'])) . "\n";
            $filteredEvents[$counter] = $event;
            $counter++;
        }
        
        $eventList .= "0. Back to Main Menu";
        $this->updateSessionData($this->sessionData['session_id'], ['filtered_events_json' => json_encode($filteredEvents)]);
        
        return $eventList;
    }

    private function handleEventSelection($selection) {
        if (!is_numeric($selection)) {
            return "CON Invalid selection. Please try again.\n\n0. Back to Main Menu";
        }
        
        $filteredEvents = json_decode($this->sessionData['filtered_events_json'], true);
        
        if (isset($filteredEvents[$selection])) {
            $selectedEvent = $filteredEvents[$selection];
            
            // Store the selected event ID
            $this->updateSessionData($this->sessionData['session_id'], ['selected_event_id' => $selectedEvent['id']]);
            
            // Format the event details
            $eventDate = date('M d, Y', strtotime($selectedEvent['event_date']));
            $eventTime = date('h:i A', strtotime($selectedEvent['event_time']));
            
            // Show event details
            return "CON {$selectedEvent['name']}\nDate: {$eventDate}\nTime: {$eventTime}\nVenue: {$selectedEvent['venue']}\nPrice: " . number_format($selectedEvent['price'], 2) . " " . DEFAULT_CURRENCY . "\n\n1. Register for this event\n0. Back to Events";
        } 
        else {
            return "CON Invalid selection. Please try again.\n\n0. Back to Main Menu";
        }
    }

    private function getRegistrationSummary($name) {
        // Get the selected event details
        $stmt = $this->conn->prepare("SELECT * FROM events WHERE id = :id");
        $stmt->execute(['id' => $this->sessionData['selected_event_id']]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$event) {
            return "CON Error retrieving event details. Please try again.\n\n0. Back to Main Menu";
        }
        
        // Format event date and time
        $eventDate = date('M d, Y', strtotime($event['event_date']));
        $eventTime = date('h:i A', strtotime($event['event_time']));
        
        // Display registration summary
        return "CON Registration Summary:\nName: {$name}\nPhone: {$this->sessionData['phone_number']}\nEvent: {$event['name']}\nDate: {$eventDate}\nPrice: " . number_format($event['price'], 2) . " " . DEFAULT_CURRENCY . "\n\n1. Confirm and Pay\n2. Cancel";
    }

    private function processRegistration() {
        try {
            // Get the selected event details
            $stmt = $this->conn->prepare("SELECT * FROM events WHERE id = :id");
            $stmt->execute(['id' => $this->sessionData['selected_event_id']]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$event) {
                return "END Error retrieving event details. Please try again later.";
            }
            
            // Insert registration into database
            $stmt = $this->conn->prepare("
                INSERT INTO registrations (event_id, user_name, phone_number, payment_status)
                VALUES (:event_id, :user_name, :phone_number, 'pending')
            ");
            
            $stmt->execute([
                'event_id' => $this->sessionData['selected_event_id'],
                'user_name' => $this->sessionData['user_name'],
                'phone_number' => $this->sessionData['phone_number']
            ]);
            
            $registrationId = $this->conn->lastInsertId();
            
            // Process payment (in a real scenario, integrate with payment gateway)
            // For this example, we'll simulate a successful payment
            $paymentHandler = new PaymentHandler();
            $paymentResult = $paymentHandler->processPayment(
                $this->sessionData['phone_number'],
                $event['price'],
                "Payment for {$event['name']}",
                $registrationId
            );
            
            // Send confirmation SMS
            $smsHandler = new SmsHandler();
            $smsHandler->sendConfirmationSms(
                $this->sessionData['phone_number'],
                $this->sessionData['user_name'],
                $event['name'],
                $event['event_date'],
                $event['event_time'],
                $event['venue']
            );
            
            return "END Thank you for registering! A confirmation SMS has been sent to your phone.";
            
        } catch (Exception $e) {
            // Handle errors
            if (DEBUG_MODE) {
                return "END Registration failed: " . $e->getMessage();
            } else {
                return "END Registration failed. Please try again later.";
            }
        }
    }

    private function getUserRegistrations($phoneNumber) {
        // Get user registrations from the database
        $stmt = $this->conn->prepare("
            SELECT r.id, e.name, e.event_date, e.event_time, e.venue, r.payment_status
            FROM registrations r
            JOIN events e ON r.event_id = e.id
            WHERE r.phone_number = :phone_number
            ORDER BY e.event_date ASC
        ");
        
        $stmt->execute(['phone_number' => $phoneNumber]);
        $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($registrations) == 0) {
            return "CON Your Registrations\nYou have no active registrations.\n\n0. Back to Main Menu";
        }
        
        $response = "CON Your Registrations\n";
        $counter = 1;
        
        foreach ($registrations as $reg) {
            $eventDate = date('M d', strtotime($reg['event_date']));
            $status = ucfirst($reg['payment_status']);
            $response .= "{$counter}. {$reg['name']} ({$eventDate}) - {$status}\n";
            $counter++;
        }
        
        $response .= "\n0. Back to Main Menu";
        return $response;
    }
}