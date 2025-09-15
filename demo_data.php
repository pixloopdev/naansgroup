<?php
function insert_demo_data($db) {
    try {
        // Add demo properties with diverse locations and configurations
        $bedspaces1 = json_encode([
            ['name' => 'Bed-101', 'type' => 'bedspace'],
            ['name' => 'Bed-102', 'type' => 'bedspace'],
            ['name' => 'Bed-103', 'type' => 'bedspace'],
            ['name' => 'Room-201', 'type' => 'room']
        ]);
        $bedspaces2 = json_encode([
            ['name' => 'Room-101', 'type' => 'room'],
            ['name' => 'Room-102', 'type' => 'room']
        ]);
        
        // Insert demo properties
        $stmt = $db->prepare("INSERT INTO properties (name, address, email, ownerFullName, mobile, buildingYearlyRent, totalCharges, bedspaces) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        // Property 1: Al Nahda Building
        $stmt->execute([
            'Al Nahda Building',
            'Al Nahda 2, Dubai',
            'alnahda@example.com',
            'Mohammed Ahmed',
            '971501234567',
            120000,
            48000,
            $bedspaces1
        ]);
        $property1_id = $db->lastInsertId();

        // Property 2: Deira Apartments
        $stmt->execute([
            'Deira Apartments',
            'Deira, Dubai',
            'deira@example.com',
            'Sarah Khan',
            '971502345678',
            90000,
            36000,
            $bedspaces2
        ]);
        $property2_id = $db->lastInsertId();

        // Insert demo tenants
        $stmt = $db->prepare("INSERT INTO tenants (name, email, phone, idNumber, propertyId, bedspace, rentAmount, rentDueDate, securityDeposit, contractStartDate, contractEndDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // Current date for demo data
        $current_date = date('Y-m-d');
        $next_year = date('Y-m-d', strtotime('+1 year'));

        // Tenants for Property 1
        $stmt->execute([
            'John Smith',
            'john@example.com',
            '971503456789',
            'EID-123456',
            $property1_id,
            'Bed-101',
            1200,
            5,
            1200,
            $current_date,
            $next_year
        ]);

        $stmt->execute([
            'Ali Hassan',
            'ali@example.com',
            '971504567890',
            'EID-234567',
            $property1_id,
            'Room-201',
            2500,
            10,
            2500,
            $current_date,
            $next_year
        ]);

        // Insert demo payments
        $stmt = $db->prepare("INSERT INTO payments (tenantId, propertyId, amount, paymentDate, paymentMethod, notes) VALUES (?, ?, ?, ?, ?, ?)");
        
        // Get tenant IDs for payments
        $tenant_ids = $db->query("SELECT id, propertyId FROM tenants")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tenant_ids as $tenant) {
            // Add last month's payment
            $stmt->execute([
                $tenant['id'],
                $tenant['propertyId'],
                1200,
                date('Y-m-d', strtotime('-1 month')),
                'cash',
                'Monthly rent payment'
            ]);
        }

        // Insert demo expense categories
        $categories = ['DEWA', 'Maintenance', 'Cleaning', 'Security'];
        $stmt = $db->prepare("INSERT INTO expense_categories (name, description) VALUES (?, ?)");
        foreach ($categories as $category) {
            $stmt->execute([$category, $category . ' related expenses']);
        }

        // Insert demo expenses
        $stmt = $db->prepare("INSERT INTO expenses (description, amount, expenseDate, category, propertyId, vendorName, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        // Example expenses for each property
        $properties = [$property1_id, $property2_id];
        foreach ($properties as $prop_id) {
            $stmt->execute([
                'Monthly DEWA Bill',
                500,
                date('Y-m-d', strtotime('-15 days')),
                'DEWA',
                $prop_id,
                'DEWA Authority',
                'Regular monthly utility payment'
            ]);

            $stmt->execute([
                'AC Maintenance',
                300,
                date('Y-m-d', strtotime('-7 days')),
                'Maintenance',
                $prop_id,
                'Cool Air Services',
                'Regular AC maintenance'
            ]);
        }

        return true;
    } catch (Exception $e) {
        error_log("Error inserting demo data: " . $e->getMessage());
        return false;
    }
}
?>
