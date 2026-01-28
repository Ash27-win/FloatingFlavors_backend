<?php
require_once "config.php";

try {
    // Read POST values safely
    $user_id = $_POST['user_id'] ?? null;
    $booking_type = $_POST['booking_type'] ?? null;

    if (!$user_id || !$booking_type) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Required fields missing"
        ]);
        exit;
    }

    // ✅ CHECK IF USER ALREADY HAS ACTIVE/PENDING BOOKING
    $checkStmt = $pdo->prepare("
        SELECT booking_id, status 
        FROM event_bookings 
        WHERE user_id = ? 
        AND status IN ('PENDING', 'CONFIRMED')
        LIMIT 1
    ");
    $checkStmt->execute([$user_id]);
    $existingBooking = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingBooking) {
        echo json_encode([
            "success" => false,
            "message" => "You already have an active booking. Status: " . $existingBooking['status']
        ]);
        exit;
    }

    // Event booking fields
    $event_type = $_POST['event_type'] ?? null;
    $event_name = $_POST['event_name'] ?? null;
    $people_count = $_POST['people_count'] ?? null;
    $event_date = $_POST['event_date'] ?? null;
    $event_time = $_POST['event_time'] ?? null;

    // Company contract fields
    $company_name = $_POST['company_name'] ?? null;
    $contact_person = $_POST['contact_person'] ?? null;
    $employee_count = $_POST['employee_count'] ?? null;
    $contract_duration = $_POST['contract_duration'] ?? null;
    $service_frequency = $_POST['service_frequency'] ?? null;
    $notes = $_POST['notes'] ?? null;

    $sql = "
        INSERT INTO event_bookings (
            user_id,
            booking_type,
            event_type,
            event_name,
            people_count,
            event_date,
            event_time,
            company_name,
            contact_person,
            employee_count,
            contract_duration,
            service_frequency,
            notes,
            status,
            created_at
        ) VALUES (
            :user_id,
            :booking_type,
            :event_type,
            :event_name,
            :people_count,
            :event_date,
            :event_time,
            :company_name,
            :contact_person,
            :employee_count,
            :contract_duration,
            :service_frequency,
            :notes,
            'PENDING',
            NOW()
        )
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ":user_id" => $user_id,
        ":booking_type" => $booking_type,
        ":event_type" => $event_type,
        ":event_name" => $event_name,
        ":people_count" => $people_count,
        ":event_date" => $event_date,
        ":event_time" => $event_time,
        ":company_name" => $company_name,
        ":contact_person" => $contact_person,
        ":employee_count" => $employee_count,
        ":contract_duration" => $contract_duration,
        ":service_frequency" => $service_frequency,
        ":notes" => $notes
    ]);

    $bookingId = $pdo->lastInsertId();

    // Return full booking details
    $fetchStmt = $pdo->prepare("SELECT * FROM event_bookings WHERE booking_id = ?");
    $fetchStmt->execute([$bookingId]);
    $bookingDetails = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "booking_id" => $bookingId,
        "message" => "Booking created successfully",
        "booking" => $bookingDetails
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Booking failed",
        "error" => $e->getMessage()
    ]);
}