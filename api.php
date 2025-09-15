<?php
session_start();
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

// Include demo data functions
require_once 'demo_data.php';

// --- Error Reporting (for debugging) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Database Setup ---
$db_file = 'propertymanagement.sqlite';
$is_new_db = !file_exists($db_file);
$db = new PDO('sqlite:' . $db_file);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys = ON;');

// --- Create/Update Tables ---
function create_tables($db) {
    $db->exec("CREATE TABLE IF NOT EXISTS properties (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        address TEXT NOT NULL,
        email TEXT,
        whatsapp TEXT,
        bedspaces TEXT, -- Storing as JSON string of objects for bedspaces
        rooms TEXT, -- Storing as JSON string of objects for rooms
        yearlyRent REAL DEFAULT 0, -- Annual rent amount
        totalCharges REAL DEFAULT 0, -- Total quarterly charges (12000 x 4)
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Migration: add new columns if missing
    try {
        $cols = $db->query("PRAGMA table_info(properties)")->fetchAll(PDO::FETCH_ASSOC);
        $colNames = array_map(function($c){ return $c['name']; }, $cols);
        if (!in_array('mobile', $colNames)) { $db->exec("ALTER TABLE properties ADD COLUMN mobile TEXT"); }
        if (!in_array('ownerFullName', $colNames)) { $db->exec("ALTER TABLE properties ADD COLUMN ownerFullName TEXT"); }
        if (!in_array('buildingYearlyRent', $colNames)) { $db->exec("ALTER TABLE properties ADD COLUMN buildingYearlyRent REAL DEFAULT 0"); }
        if (!in_array('documentPath', $colNames)) { $db->exec("ALTER TABLE properties ADD COLUMN documentPath TEXT"); }
    } catch (Exception $e) {
        // ignore migration errors
    }

    $db->exec("CREATE TABLE IF NOT EXISTS tenants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        phone TEXT, -- Mobile number
        idNumber TEXT, -- ID/Passport number
        propertyId INTEGER NOT NULL,
        bedspace TEXT NOT NULL,
        rentAmount REAL DEFAULT 0,
        rentDueDate INTEGER DEFAULT 1, -- Day of the month (1-31)
        securityDeposit REAL DEFAULT 0, -- Security deposit amount
        contractStartDate TEXT, -- Contract start date
        contractEndDate TEXT, -- Contract end date
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (propertyId) REFERENCES properties(id) ON DELETE CASCADE
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS payments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tenantId INTEGER NOT NULL,
        propertyId INTEGER NOT NULL,
        amount REAL NOT NULL,
        paymentDate TEXT NOT NULL,
        paymentMethod TEXT DEFAULT 'cash', -- cash, cheque, bank_transfer
        chequeNumber TEXT, -- For cheque payments
        chequeDate TEXT, -- Post-dated cheque date
        bankDetails TEXT, -- Bank transfer details
        notes TEXT, -- Additional notes
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (tenantId) REFERENCES tenants(id) ON DELETE CASCADE,
        FOREIGN KEY (propertyId) REFERENCES properties(id) ON DELETE CASCADE
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS expenses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        description TEXT NOT NULL,
        amount REAL NOT NULL,
        expenseDate TEXT NOT NULL,
        category TEXT DEFAULT 'general', -- dewa, maintenance, commission, purchasing, general
        propertyId INTEGER, -- Optional: link expense to a property
        vendorName TEXT, -- Vendor/supplier name
        invoiceNumber TEXT, -- Invoice/bill number
        notes TEXT, -- Additional notes
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (propertyId) REFERENCES properties(id) ON DELETE SET NULL
    )");

    // Create common expenses table (separate from property-related expenses)
    $db->exec("CREATE TABLE IF NOT EXISTS common_expenses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        description TEXT NOT NULL,
        amount REAL NOT NULL,
        expenseDate TEXT NOT NULL,
        category TEXT DEFAULT 'general',
        vendorName TEXT,
        invoiceNumber TEXT,
        notes TEXT,
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Create expense categories table for better organization
    $db->exec("CREATE TABLE IF NOT EXISTS expense_categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        description TEXT,
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Insert default expense categories
    $db->exec("INSERT OR IGNORE INTO expense_categories (name, description) VALUES 
        ('dewa', 'DEWA utility bills'),
        ('maintenance', 'Property maintenance and repairs'),
        ('commission', 'Agent commissions and fees'),
        ('purchasing', 'Equipment and supplies purchasing'),
        ('general', 'General expenses')");

    // Create payment schedules for cheque management
    $db->exec("CREATE TABLE IF NOT EXISTS payment_schedules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tenantId INTEGER, -- Made nullable since tenant is no longer required
        propertyId INTEGER NOT NULL,
        scheduledAmount REAL NOT NULL,
        scheduledDate TEXT NOT NULL,
        chequeNumber TEXT,
        status TEXT DEFAULT 'pending', -- pending, received, cleared, bounced
        actualPaymentId INTEGER, -- Links to actual payment when received
        notes TEXT,
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (tenantId) REFERENCES tenants(id) ON DELETE SET NULL,
        FOREIGN KEY (propertyId) REFERENCES properties(id) ON DELETE CASCADE,
        FOREIGN KEY (actualPaymentId) REFERENCES payments(id) ON DELETE SET NULL
    )");
}

function handle_backup_db($db) {
    // Close the current database connection
    $db = null;
    
    // Set headers for file download
    header('Content-Type: application/x-sqlite3');
    header('Content-Disposition: attachment; filename="naansprop_backup_' . date('Y-m-d') . '.sqlite"');
    header('Content-Length: ' . filesize('propertymanagement.sqlite'));
    
    // Output the database file
    readfile('propertymanagement.sqlite');
    exit;
}

function handle_import_demo($db) {
    try {
        // Clear existing data first
        $db->exec("DELETE FROM payments");
        $db->exec("DELETE FROM expenses");
        $db->exec("DELETE FROM expense_categories");
        $db->exec("DELETE FROM tenants");
        $db->exec("DELETE FROM properties");
        
        // Reset auto-increment counters
        $db->exec("DELETE FROM sqlite_sequence WHERE name IN ('payments', 'expenses', 'expense_categories', 'tenants', 'properties')");
        
        // Import demo data using the function from demo_data.php
        if (insert_demo_data($db)) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception('Failed to import demo data');
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to import demo data: ' . $e->getMessage()]);
    }
}

function handle_reset_data($db) {
    try {
        // Close any existing connection
        $db = null;
        
        // Delete the existing database file
        if (file_exists('propertymanagement.sqlite')) {
            unlink('propertymanagement.sqlite');
        }
        
        // Create new database connection
        $db = new PDO('sqlite:propertymanagement.sqlite');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create fresh tables
        create_tables($db);
        
        // Clear any uploaded files in the documents folder
        $documents_dir = __DIR__ . '/uploads/documents';
        if (is_dir($documents_dir)) {
            $files = glob($documents_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        // Log the error (this will go to PHP error log)
        error_log("Reset database error: " . $e->getMessage());
        
        // Send error response
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database reset failed. Please try again.']);
    }
}

// Include demo data functions
require_once 'demo_data.php';

create_tables($db);
if ($is_new_db) {
    insert_demo_data($db);
}


// --- API Endpoint Router ---
header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$method = $_SERVER['REQUEST_METHOD'];

// CSRF check for mutating requests
if ($method === 'POST') {
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$csrfHeader || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
}

try {
    switch ($action) {
        case 'get_all_data':
            handle_get_all_data($db);
            break;
        case 'add_property':
            if ($method === 'POST') handle_add_property($db);
            break;
        case 'delete_property':
            if ($method === 'POST') handle_delete_property($db);
            break;
        case 'add_bedspace':
            if ($method === 'POST') handle_add_bedspace($db);
            break;
        case 'edit_bedspace':
            if ($method === 'POST') handle_edit_bedspace($db);
            break;
        case 'delete_bedspace':
            if ($method === 'POST') handle_delete_bedspace($db);
            break;
        case 'add_tenant':
            if ($method === 'POST') handle_add_tenant($db);
            break;
        case 'delete_tenant':
            if ($method === 'POST') handle_delete_tenant($db);
            break;
        case 'add_payment':
            if ($method === 'POST') handle_add_payment($db);
            break;
        case 'update_payment':
            if ($method === 'POST') handle_update_payment($db);
            break;
        case 'delete_payment':
            if ($method === 'POST') handle_delete_payment($db);
            break;
        case 'add_expense':
            if ($method === 'POST') handle_add_expense($db);
            break;
        case 'get_report':
            if ($method === 'POST') handle_get_report($db);
            break;
        case 'reset_dummy_data':
            if ($method === 'POST') handle_reset_dummy_data($db);
            break;
        case 'reset_data':
            if ($method === 'POST') handle_reset_data($db);
            break;
        case 'backup_db':
            if ($method === 'GET') handle_backup_db($db);
            break;
        case 'import_demo':
            if ($method === 'POST') handle_import_demo($db);
            break;
        case 'update_property':
            if ($method === 'POST') handle_update_property($db);
            break;
        case 'get_cheques':
            if ($method === 'GET') handle_get_cheques($db);
            break;
        case 'add_cheque':
            if ($method === 'POST') handle_add_cheque($db);
            break;
        case 'update_cheque_status':
            if ($method === 'POST') handle_update_cheque_status($db);
            break;
        case 'get_settings':
            if ($method === 'GET') handle_get_settings($db);
            break;
        case 'save_settings':
            if ($method === 'POST') handle_save_settings($db);
            break;
        case 'update_tenant':
            if ($method === 'POST') handle_update_tenant($db);
            break;
        case 'update_expense':
            if ($method === 'POST') handle_update_expense($db);
            break;
        case 'delete_expense':
            if ($method === 'POST') handle_delete_expense($db);
            break;
        case 'add_common_expense':
            if ($method === 'POST') handle_add_common_expense($db);
            break;
        case 'update_common_expense':
            if ($method === 'POST') handle_update_common_expense($db);
            break;
        case 'delete_common_expense':
            if ($method === 'POST') handle_delete_common_expense($db);
            break;
        default:
            echo json_encode(['error' => 'Invalid action']);
            http_response_code(400);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    http_response_code(500);
}

// --- Handler Functions ---

function get_rent_reminders($db) {
    $reminders = [];
    $today = new DateTime();
    $current_month_year = $today->format('Y-m');
    $current_day = (int) $today->format('d');
    
    // Get all tenants who haven't paid this month
    $payments_stmt = $db->prepare("SELECT tenantId FROM payments WHERE strftime('%Y-%m', paymentDate) = ?");
    $payments_stmt->execute([$current_month_year]);
    $paid_tenant_ids = $payments_stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    // Get all active tenants with property information
    $tenants_stmt = $db->query("
        SELECT t.*, p.name as propertyName, p.address as propertyAddress 
        FROM tenants t 
        LEFT JOIN properties p ON t.propertyId = p.id 
        WHERE t.rentAmount > 0
    ");
    $tenants = $tenants_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tenants as $tenant) {
        // Skip tenants who have already paid this month
        if (in_array($tenant['id'], $paid_tenant_ids)) continue;
        
        $due_date = (int)$tenant['rentDueDate'];
        $days_difference = $current_day - $due_date;
        
        // Determine status
        if ($days_difference > 0) {
            $status = 'Overdue';
            $days_info = $days_difference . ' days overdue';
        } elseif ($days_difference == 0) {
            $status = 'Due Today';
            $days_info = 'Due today';
        } else {
            $days_until_due = abs($days_difference);
            if ($days_until_due <= 7) {
                $status = 'Due Soon';
                $days_info = $days_until_due . ' days until due';
            } else {
                continue; // Skip if more than 7 days away
            }
        }
        
        $tenant['status'] = $status;
        $tenant['days_info'] = $days_info;
        $tenant['days_until_due'] = $days_difference; // For sorting
        $tenant['monthlyRent'] = $tenant['rentAmount']; // Add alias for frontend compatibility
        $reminders[] = $tenant;
    }
    
    // Sort by urgency: Due Today first, then overdue, then upcoming
    usort($reminders, function($a, $b) {
        // Priority order: Due Today (0) > Overdue (positive) > Due Soon (negative)
        if ($a['status'] === 'Due Today' && $b['status'] !== 'Due Today') return -1;
        if ($b['status'] === 'Due Today' && $a['status'] !== 'Due Today') return 1;
        
        // If both are Due Today, sort by tenant name
        if ($a['status'] === 'Due Today' && $b['status'] === 'Due Today') {
            return strcmp($a['name'], $b['name']);
        }
        
        // For others, sort by days difference (overdue first, then upcoming)
        return $a['days_until_due'] <=> $b['days_until_due'];
    });
    
    return $reminders;
}


function handle_get_all_data($db) {
    $properties_stmt = $db->query("SELECT * FROM properties ORDER BY createdAt DESC");
    $properties = $properties_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($properties as &$prop) {
        $prop['bedspaces'] = $prop['bedspaces'] ? json_decode($prop['bedspaces']) : [];
    }

    $tenants_stmt = $db->query("SELECT * FROM tenants ORDER BY createdAt DESC");
    $tenants = $tenants_stmt->fetchAll(PDO::FETCH_ASSOC);

    $payments_stmt = $db->query("SELECT * FROM payments ORDER BY createdAt DESC");
    $payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);

    $expenses_stmt = $db->query("SELECT * FROM expenses ORDER BY createdAt DESC");
    $common_expenses_stmt = $db->query("SELECT * FROM common_expenses ORDER BY createdAt DESC");
    $common_expenses = $common_expenses_stmt->fetchAll(PDO::FETCH_ASSOC);
    $expenses = $expenses_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cheque schedules (for Cheques module)
    $cheques_stmt = $db->query("SELECT ps.*, t.name as tenantName, p.name as propertyName
        FROM payment_schedules ps
        LEFT JOIN tenants t ON ps.tenantId = t.id
        LEFT JOIN properties p ON ps.propertyId = p.id
        ORDER BY ps.scheduledDate DESC");
    $cheques = $cheques_stmt->fetchAll(PDO::FETCH_ASSOC);
     
    $rent_reminders = get_rent_reminders($db);

    echo json_encode([
        'properties' => $properties,
        'tenants' => $tenants,
        'payments' => $payments,
        'expenses' => $expenses,
        'common_expenses' => $common_expenses,
        'cheques' => $cheques,
        'rent_reminders' => $rent_reminders
    ]);
}

function handle_get_report($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input data
    if (!$data || !isset($data['startDate']) || !isset($data['endDate'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Start date and end date are required']);
        return;
    }
    
    $startDate = $data['startDate'];
    $endDate = $data['endDate'];
    $propertyId = (isset($data['propertyId']) && $data['propertyId'] !== '' && $data['propertyId'] !== null)
        ? intval($data['propertyId'])
        : null;
    
    // Validate date format
    if (!$startDate || !$endDate) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid date format']);
        return;
    }

    // Build queries conditionally by property
    if ($propertyId !== null) {
        $income_stmt = $db->prepare("SELECT pay.*, t.name AS tenantName, p.name AS propertyName
            FROM payments pay
            LEFT JOIN tenants t ON pay.tenantId = t.id
            LEFT JOIN properties p ON pay.propertyId = p.id
            WHERE pay.paymentDate BETWEEN ? AND ? AND pay.propertyId = ?
            ORDER BY pay.paymentDate DESC");
        $income_stmt->execute([$startDate, $endDate, $propertyId]);
    } else {
        $income_stmt = $db->prepare("SELECT pay.*, t.name AS tenantName, p.name AS propertyName
            FROM payments pay
            LEFT JOIN tenants t ON pay.tenantId = t.id
            LEFT JOIN properties p ON pay.propertyId = p.id
            WHERE pay.paymentDate BETWEEN ? AND ?
            ORDER BY pay.paymentDate DESC");
        $income_stmt->execute([$startDate, $endDate]);
    }
    $income = $income_stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($propertyId !== null) {
        $expenses_stmt = $db->prepare("SELECT e.*, p.name AS propertyName
            FROM expenses e
            LEFT JOIN properties p ON e.propertyId = p.id
            WHERE e.expenseDate BETWEEN ? AND ? AND e.propertyId = ?
            ORDER BY e.expenseDate DESC");
        $expenses_stmt->execute([$startDate, $endDate, $propertyId]);
    } else {
        $expenses_stmt = $db->prepare("SELECT e.*, p.name AS propertyName
            FROM expenses e
            LEFT JOIN properties p ON e.propertyId = p.id
            WHERE e.expenseDate BETWEEN ? AND ?
            ORDER BY e.expenseDate DESC");
        $expenses_stmt->execute([$startDate, $endDate]);
    }
    $expenses = $expenses_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get common expenses only for "All properties" reports
    $common_expenses = [];
    if ($propertyId === null) {
        $common_expenses_stmt = $db->prepare("SELECT ce.*, NULL AS propertyName, 'common' AS expenseType
            FROM common_expenses ce
            WHERE ce.expenseDate BETWEEN ? AND ?
            ORDER BY ce.expenseDate DESC");
        $common_expenses_stmt->execute([$startDate, $endDate]);
        $common_expenses = $common_expenses_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Add expense type to regular expenses for differentiation
    foreach ($expenses as &$expense) {
        $expense['expenseType'] = 'property';
    }

    // Combine all expenses for the total calculation (exclude common when a single property is selected)
    $all_expenses = array_merge($expenses, $common_expenses);

    // --- Net Profit Calculation ---
    $env = env_read_assoc(__DIR__ . '/.env');
    // Opening balance applies only to the consolidated report (all properties)
    $openingBalance = 0.0;
    if ($propertyId === null) {
        $openingBalance = isset($env['OPENING_BALANCE']) ? floatval($env['OPENING_BALANCE']) : 0.0;
    }

    $totalRevenue = array_sum(array_column($income, 'amount'));
    $totalPropertyExpenses = array_sum(array_column($expenses, 'amount'));
    $totalCommonExpenses = array_sum(array_column($common_expenses, 'amount'));

    $netProfit = $openingBalance + $totalRevenue - $totalPropertyExpenses - $totalCommonExpenses;

    echo json_encode([
        'income' => $income,
        'expenses' => $all_expenses,
        'property_expenses' => $expenses,
        'common_expenses' => $common_expenses,
        'openingBalance' => $openingBalance,
        'totalRevenue' => $totalRevenue,
        'totalPropertyExpenses' => $totalPropertyExpenses,
        'totalCommonExpenses' => $totalCommonExpenses,
        'netProfit' => $netProfit
    ]);
}

function handle_add_expense($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input data
    if (!$data || !isset($data['description']) || !isset($data['amount']) || !isset($data['date'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Description, amount, and date are required']);
        return;
    }
    
    $stmt = $db->prepare("INSERT INTO expenses (description, amount, expenseDate, category, propertyId, vendorName, invoiceNumber, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['description'],
        $data['amount'],
        $data['date'],
        isset($data['category']) ? $data['category'] : 'general',
        isset($data['propertyId']) ? $data['propertyId'] : null,
        isset($data['vendorName']) ? $data['vendorName'] : null,
        isset($data['invoiceNumber']) ? $data['invoiceNumber'] : null,
        isset($data['notes']) ? $data['notes'] : null
    ]);
    echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
}

function handle_add_property($db) {
    // Support both JSON and multipart (for PDF upload)
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    $isMultipart = stripos($contentType, 'multipart/form-data') !== false;

    $name = null; $address = null;
    $email = null; $whatsapp = null; $mobile = null; $ownerFullName = null;
    $buildingYearlyRent = 0; $totalCharges = 0; $documentPath = null;

    if ($isMultipart) {
        // Validate required fields
        $name = $_POST['name'] ?? null;
        $address = $_POST['address'] ?? null;
        if (!$name || !$address) {
            http_response_code(400);
            echo json_encode(['error' => 'Name and address are required']);
            return;
        }

        // Optional fields
        $email = $_POST['email'] ?? null;
        $whatsapp = $_POST['whatsapp'] ?? null;
        $mobile = $_POST['mobile'] ?? null;
        $ownerFullName = $_POST['ownerFullName'] ?? null;
        $buildingYearlyRent = isset($_POST['buildingYearlyRent']) ? floatval($_POST['buildingYearlyRent']) : 0;
        $totalCharges = isset($_POST['totalCharges']) ? floatval($_POST['totalCharges']) : 0;

        // Handle file upload (PDF only)
        if (isset($_FILES['document']) && $_FILES['document']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['document']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['error' => 'File upload failed']);
                return;
            }
            $tmpPath = $_FILES['document']['tmp_name'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
            $ext = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
            if ($mime !== 'application/pdf' || $ext !== 'pdf') {
                http_response_code(400);
                echo json_encode(['error' => 'Only PDF files are allowed']);
                return;
            }
            // Save file
            $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'documents';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }
            $safeBase = preg_replace('/[^a-zA-Z0-9-_]/', '_', pathinfo($_FILES['document']['name'], PATHINFO_FILENAME));
            $filename = $safeBase . '_' . time() . '.pdf';
            $destPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
            if (!move_uploaded_file($tmpPath, $destPath)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to store uploaded file']);
                return;
            }
            // Store relative path for serving
            $documentPath = 'uploads/documents/' . $filename;
        }
    } else {
        // JSON payload fallback (no file upload)
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['name']) || !isset($data['address'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Name and address are required']);
            return;
        }
        $name = $data['name'];
        $address = $data['address'];
        $email = $data['email'] ?? null;
        $whatsapp = $data['whatsapp'] ?? null;
        $mobile = $data['mobile'] ?? null;
        $ownerFullName = $data['ownerFullName'] ?? null;
        // Prefer new field; fallback to legacy yearlyRent if provided
        $buildingYearlyRent = isset($data['buildingYearlyRent']) ? floatval($data['buildingYearlyRent']) : (isset($data['yearlyRent']) ? floatval($data['yearlyRent']) : 0);
        $totalCharges = isset($data['totalCharges']) ? floatval($data['totalCharges']) : 0;
        $documentPath = $data['documentPath'] ?? null;
    }

    // Insert with new columns
    $stmt = $db->prepare("INSERT INTO properties (name, address, email, whatsapp, mobile, ownerFullName, bedspaces, buildingYearlyRent, totalCharges, documentPath) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $name,
        $address,
        $email,
        $whatsapp,
        $mobile,
        $ownerFullName,
        json_encode([]),
        $buildingYearlyRent,
        $totalCharges,
        $documentPath
    ]);
    echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
}

function handle_delete_property($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input data
    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Property ID is required']);
        return;
    }
    
    $db->exec('PRAGMA foreign_keys = ON;');
    $stmt = $db->prepare("DELETE FROM properties WHERE id = ?");
    $stmt->execute([$data['id']]);
    echo json_encode(['success' => $stmt->rowCount() > 0]);
}

function handle_add_bedspace($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input data
    if (!$data || !isset($data['propertyId']) || !isset($data['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Property ID and bedspace name are required']);
        return;
    }
    
    $stmt_get = $db->prepare("SELECT bedspaces FROM properties WHERE id = ?");
    $stmt_get->execute([$data['propertyId']]);
    $property = $stmt_get->fetch(PDO::FETCH_ASSOC);
    if ($property) {
        $bedspaces = $property['bedspaces'] ? json_decode($property['bedspaces'], true) : [];
        $new_bedspace = [
            'name' => $data['name'],
            'type' => isset($data['type']) ? $data['type'] : 'bedspace'
        ];
        $bedspaces[] = $new_bedspace;
        $stmt_update = $db->prepare("UPDATE properties SET bedspaces = ? WHERE id = ?");
        $stmt_update->execute([json_encode($bedspaces), $data['propertyId']]);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Property not found']);
    }
}

function handle_edit_bedspace($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input data
    if (!$data || !isset($data['propertyId']) || !isset($data['bedspaceIndex']) || !isset($data['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Property ID, bedspace index, and name are required']);
        return;
    }
    
    $stmt_get = $db->prepare("SELECT bedspaces FROM properties WHERE id = ?");
    $stmt_get->execute([$data['propertyId']]);
    $property = $stmt_get->fetch(PDO::FETCH_ASSOC);
    if ($property) {
        $bedspaces = $property['bedspaces'] ? json_decode($property['bedspaces'], true) : [];
        if (isset($bedspaces[$data['bedspaceIndex']])) {
            $bedspaces[$data['bedspaceIndex']]['name'] = $data['name'];
            if (isset($data['type'])) {
                $bedspaces[$data['bedspaceIndex']]['type'] = $data['type'];
            }
            $stmt_update = $db->prepare("UPDATE properties SET bedspaces = ? WHERE id = ?");
            $stmt_update->execute([json_encode($bedspaces), $data['propertyId']]);
            echo json_encode(['success' => true]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Bedspace not found']);
        }
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Property not found']);
    }
}

function handle_delete_bedspace($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input data
    if (!$data || !isset($data['propertyId']) || !isset($data['bedspaceIndex'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Property ID and bedspace index are required']);
        return;
    }
    
    $stmt_get = $db->prepare("SELECT bedspaces FROM properties WHERE id = ?");
    $stmt_get->execute([$data['propertyId']]);
    $property = $stmt_get->fetch(PDO::FETCH_ASSOC);
    if ($property) {
        $bedspaces = $property['bedspaces'] ? json_decode($property['bedspaces'], true) : [];
        if (isset($bedspaces[$data['bedspaceIndex']])) {
            // Check if bedspace is occupied by any tenant
            $stmt_check = $db->prepare("SELECT COUNT(*) FROM tenants WHERE propertyId = ? AND bedspace = ?");
            $stmt_check->execute([$data['propertyId'], $bedspaces[$data['bedspaceIndex']]['name']]);
            if ($stmt_check->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Cannot delete bedspace: it is currently occupied by a tenant']);
                return;
            }
            
            array_splice($bedspaces, $data['bedspaceIndex'], 1);
            $stmt_update = $db->prepare("UPDATE properties SET bedspaces = ? WHERE id = ?");
            $stmt_update->execute([json_encode($bedspaces), $data['propertyId']]);
            echo json_encode(['success' => true]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Bedspace not found']);
        }
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Property not found']);
    }
}

function handle_add_tenant($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input data
    if (!$data || !isset($data['name']) || !isset($data['email']) || !isset($data['propertyId']) || !isset($data['bedspace'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Name, email, property ID, and bedspace are required']);
        return;
    }
    
    $stmt = $db->prepare("INSERT INTO tenants (name, email, phone, idNumber, propertyId, bedspace, rentAmount, rentDueDate, securityDeposit, contractStartDate, contractEndDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['name'], 
        $data['email'], 
        isset($data['phone']) ? $data['phone'] : null,
        isset($data['idNumber']) ? $data['idNumber'] : null,
        $data['propertyId'], 
        $data['bedspace'], 
        isset($data['rentAmount']) ? $data['rentAmount'] : 0, 
        isset($data['rentDueDate']) ? $data['rentDueDate'] : 1,
        isset($data['securityDeposit']) ? $data['securityDeposit'] : 0,
        isset($data['contractStartDate']) ? $data['contractStartDate'] : null,
        isset($data['contractEndDate']) ? $data['contractEndDate'] : null
    ]);
    echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
}

function handle_delete_tenant($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input data
    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Tenant ID is required']);
        return;
    }
    
    try {
        $db->exec('PRAGMA foreign_keys = ON;');
        $stmt = $db->prepare("DELETE FROM tenants WHERE id = ?");
        $stmt->execute([$data['id']]);
        echo json_encode(['success' => $stmt->rowCount() > 0]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete tenant: ' . $e->getMessage()]);
    }
}

function handle_add_payment($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input data
    if (!$data || !isset($data['tenantId']) || !isset($data['amount']) || !isset($data['date'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Tenant ID, amount, and date are required']);
        return;
    }
    
    $stmt_get = $db->prepare("SELECT propertyId FROM tenants WHERE id = ?");
    $stmt_get->execute([$data['tenantId']]);
    $tenant = $stmt_get->fetch(PDO::FETCH_ASSOC);
    if ($tenant) {
        $stmt = $db->prepare("INSERT INTO payments (tenantId, propertyId, amount, paymentDate, paymentMethod, chequeNumber, chequeDate, bankDetails, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['tenantId'], 
            $tenant['propertyId'], 
            $data['amount'], 
            $data['date'],
            isset($data['paymentMethod']) ? $data['paymentMethod'] : 'cash',
            isset($data['chequeNumber']) ? $data['chequeNumber'] : null,
            isset($data['chequeDate']) ? $data['chequeDate'] : null,
            isset($data['bankDetails']) ? $data['bankDetails'] : null,
            isset($data['notes']) ? $data['notes'] : null
        ]);
        echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Tenant not found']);
    }
}

function handle_update_payment($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Payment ID is required']);
        return;
    }

    // Load existing payment
    $stmt = $db->prepare("SELECT * FROM payments WHERE id = ?");
    $stmt->execute([$data['id']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['error' => 'Payment not found']);
        return;
    }

    // Determine updated values
    $tenantId = isset($data['tenantId']) ? intval($data['tenantId']) : intval($existing['tenantId']);
    $amount = isset($data['amount']) ? floatval($data['amount']) : floatval($existing['amount']);
    $paymentDate = isset($data['date']) ? $data['date'] : $existing['paymentDate'];
    $paymentMethod = isset($data['paymentMethod']) ? $data['paymentMethod'] : $existing['paymentMethod'];
    $chequeNumber = array_key_exists('chequeNumber', $data) ? $data['chequeNumber'] : $existing['chequeNumber'];
    $chequeDate = array_key_exists('chequeDate', $data) ? $data['chequeDate'] : $existing['chequeDate'];
    $bankDetails = array_key_exists('bankDetails', $data) ? $data['bankDetails'] : $existing['bankDetails'];
    $notes = array_key_exists('notes', $data) ? $data['notes'] : $existing['notes'];

    // If tenant changed, align propertyId
    $propertyId = intval($existing['propertyId']);
    if ($tenantId !== intval($existing['tenantId'])) {
        $stmtT = $db->prepare("SELECT propertyId FROM tenants WHERE id = ?");
        $stmtT->execute([$tenantId]);
        $tenant = $stmtT->fetch(PDO::FETCH_ASSOC);
        if (!$tenant) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid tenantId']);
            return;
        }
        $propertyId = intval($tenant['propertyId']);
    }

    $stmtUp = $db->prepare("UPDATE payments SET tenantId = ?, propertyId = ?, amount = ?, paymentDate = ?, paymentMethod = ?, chequeNumber = ?, chequeDate = ?, bankDetails = ?, notes = ? WHERE id = ?");
    $stmtUp->execute([$tenantId, $propertyId, $amount, $paymentDate, $paymentMethod, $chequeNumber, $chequeDate, $bankDetails, $notes, intval($data['id'])]);

    echo json_encode(['success' => true]);
}

function handle_delete_payment($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Payment ID is required']);
        return;
    }
    try {
        $stmt = $db->prepare("DELETE FROM payments WHERE id = ?");
        $stmt->execute([intval($data['id'])]);
        echo json_encode(['success' => $stmt->rowCount() > 0]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete payment: ' . $e->getMessage()]);
    }
}

function handle_reset_dummy_data($db) {
    try {
        // Clear existing data
        $db->exec("DELETE FROM payment_schedules");
        $db->exec("DELETE FROM payments");
        $db->exec("DELETE FROM expenses");
        $db->exec("DELETE FROM tenants");
        $db->exec("DELETE FROM properties");
        
        // Reset auto-increment counters
        $db->exec("DELETE FROM sqlite_sequence WHERE name IN ('properties', 'tenants', 'payments', 'expenses', 'payment_schedules')");
        
        // Add fresh dummy data
        add_dummy_data($db);
        
        echo json_encode(['success' => true, 'message' => 'Dummy data has been reset successfully']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to reset dummy data: ' . $e->getMessage()]);
    }
}
function handle_update_property($db) {
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    $isMultipart = stripos($contentType, 'multipart/form-data') !== false;

    $id = null;
    $name = null; $address = null;
    $email = null; $whatsapp = null; $mobile = null; $ownerFullName = null;
    $buildingYearlyRent = null; $totalCharges = null; $documentPath = null;

    if ($isMultipart) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Property ID is required']);
            return;
        }

        // Load existing property
        $stmt = $db->prepare("SELECT * FROM properties WHERE id = ?");
        $stmt->execute([$id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['error' => 'Property not found']);
            return;
        }

        // Fields (fallback to existing if not provided)
        $name = $_POST['name'] ?? $existing['name'];
        $address = $_POST['address'] ?? $existing['address'];
        $email = $_POST['email'] ?? $existing['email'];
        $whatsapp = $_POST['whatsapp'] ?? $existing['whatsapp'];
        $mobile = $_POST['mobile'] ?? (isset($existing['mobile']) ? $existing['mobile'] : null);
        $ownerFullName = $_POST['ownerFullName'] ?? (isset($existing['ownerFullName']) ? $existing['ownerFullName'] : null);
        $buildingYearlyRent = isset($_POST['buildingYearlyRent']) ? floatval($_POST['buildingYearlyRent']) : (isset($existing['buildingYearlyRent']) ? floatval($existing['buildingYearlyRent']) : 0);
        $totalCharges = isset($_POST['totalCharges']) ? floatval($_POST['totalCharges']) : (isset($existing['totalCharges']) ? floatval($existing['totalCharges']) : 0);
        $documentPath = isset($existing['documentPath']) ? $existing['documentPath'] : null;

        // Handle optional new document
        if (isset($_FILES['document']) && $_FILES['document']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['document']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['error' => 'File upload failed']);
                return;
            }
            $tmpPath = $_FILES['document']['tmp_name'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
            $ext = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
            if ($mime !== 'application/pdf' || $ext !== 'pdf') {
                http_response_code(400);
                echo json_encode(['error' => 'Only PDF files are allowed']);
                return;
            }
            $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'documents';
            if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
            $safeBase = preg_replace('/[^a-zA-Z0-9-_]/', '_', pathinfo($_FILES['document']['name'], PATHINFO_FILENAME));
            $filename = $safeBase . '_' . time() . '.pdf';
            $destPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
            if (!move_uploaded_file($tmpPath, $destPath)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to store uploaded file']);
                return;
            }
            $documentPath = 'uploads/documents/' . $filename;
        }
    } else {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Property ID is required']);
            return;
        }

        $id = intval($data['id']);

        // Load existing
        $stmt = $db->prepare("SELECT * FROM properties WHERE id = ?");
        $stmt->execute([$id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['error' => 'Property not found']);
            return;
        }

        // Use provided values or fallback
        $name = $data['name'] ?? $existing['name'];
        $address = $data['address'] ?? $existing['address'];
        $email = $data['email'] ?? $existing['email'];
        $whatsapp = $data['whatsapp'] ?? $existing['whatsapp'];
        $mobile = $data['mobile'] ?? (isset($existing['mobile']) ? $existing['mobile'] : null);
        $ownerFullName = $data['ownerFullName'] ?? (isset($existing['ownerFullName']) ? $existing['ownerFullName'] : null);
        $buildingYearlyRent = isset($data['buildingYearlyRent']) ? floatval($data['buildingYearlyRent']) : (isset($existing['buildingYearlyRent']) ? floatval($existing['buildingYearlyRent']) : 0);
        $totalCharges = isset($data['totalCharges']) ? floatval($data['totalCharges']) : (isset($existing['totalCharges']) ? floatval($existing['totalCharges']) : 0);
        $documentPath = $data['documentPath'] ?? (isset($existing['documentPath']) ? $existing['documentPath'] : null);
    }

    $stmtUp = $db->prepare("UPDATE properties SET name = ?, address = ?, email = ?, whatsapp = ?, mobile = ?, ownerFullName = ?, buildingYearlyRent = ?, totalCharges = ?, documentPath = ? WHERE id = ?");
    $stmtUp->execute([
        $name,
        $address,
        $email,
        $whatsapp,
        $mobile,
        $ownerFullName,
        $buildingYearlyRent,
        $totalCharges,
        $documentPath,
        $id
    ]);

    echo json_encode(['success' => true]);
}

/**
 * --- Settings helpers and handlers ---
 */
function env_read_assoc($path) {
    if (!file_exists($path)) return [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '' || strpos($trim, '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $env[$parts[0]] = $parts[1];
        }
    }
    return $env;
}

function env_write_assoc($path, $updates) {
    $existing = file_exists($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];
    $used = [];
    $out = [];
    foreach ($existing as $line) {
        $orig = $line;
        $trim = trim($line);
        if ($trim === '' || strpos($trim, '#') === 0 || strpos($trim, '=') === false) {
            $out[] = $orig;
            continue;
        }
        list($k, $v) = explode('=', $line, 2);
        if (array_key_exists($k, $updates)) {
            $out[] = $k . '=' . $updates[$k];
            $used[$k] = true;
        } else {
            $out[] = $orig;
        }
    }
    foreach ($updates as $k => $v) {
        if (!isset($used[$k])) {
            $out[] = $k . '=' . $v;
        }
    }
    $content = implode(PHP_EOL, $out);
    if (substr($content, -1) !== PHP_EOL) $content .= PHP_EOL;
    if (file_put_contents($path, $content) === false) {
        throw new Exception('Failed to write .env file');
    }
}

function handle_get_settings($db) {
    $env = env_read_assoc(__DIR__ . '/.env');
    $appTitle = isset($env['APP_TITLE']) ? $env['APP_TITLE'] : 'NAANS Props';
    $currencyCode = isset($env['CURRENCY_CODE']) ? $env['CURRENCY_CODE'] : 'AED';
    $countryCode = isset($env['COUNTRY_CODE']) ? $env['COUNTRY_CODE'] : 'AE';
    $openingBalance = isset($env['OPENING_BALANCE']) ? floatval($env['OPENING_BALANCE']) : 0.0;
    echo json_encode([
        'appTitle' => $appTitle,
        'currencyCode' => $currencyCode,
        'countryCode' => $countryCode,
        'openingBalance' => $openingBalance
    ]);
}

function handle_save_settings($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        return;
    }
    $appTitle = isset($data['appTitle']) ? trim($data['appTitle']) : null;
    $currencyCode = isset($data['currencyCode']) ? strtoupper(trim($data['currencyCode'])) : null;
    $countryCode = isset($data['countryCode']) ? strtoupper(trim($data['countryCode'])) : null;
    $openingBalance = isset($data['openingBalance']) ? floatval($data['openingBalance']) : null;

    if ($appTitle === null && $currencyCode === null && $countryCode === null && $openingBalance === null) {
        http_response_code(400);
        echo json_encode(['error' => 'No settings provided']);
        return;
    }

    // Basic validation
    if ($currencyCode !== null && !preg_match('/^[A-Z]{2,5}$/', $currencyCode)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid currency code']);
        return;
    }
    if ($countryCode !== null && !preg_match('/^[A-Z]{2,3}$/', $countryCode)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid country code']);
        return;
    }
    if ($openingBalance !== null && $openingBalance < 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Opening balance cannot be negative']);
        return;
    }

    $updates = [];
    if ($appTitle !== null) $updates['APP_TITLE'] = $appTitle;
    if ($currencyCode !== null) $updates['CURRENCY_CODE'] = $currencyCode;
    if ($countryCode !== null) $updates['COUNTRY_CODE'] = $countryCode;
    if ($openingBalance !== null) $updates['OPENING_BALANCE'] = number_format($openingBalance, 2, '.', '');

    env_write_assoc(__DIR__ . '/.env', $updates);

    echo json_encode([
        'success' => true,
        'appTitle' => $appTitle,
        'currencyCode' => $currencyCode,
        'countryCode' => $countryCode,
        'openingBalance' => $openingBalance
    ]);
}
// --- Cheques Module Handlers ---

function handle_get_cheques($db) {
    $propertyId = isset($_GET['propertyId']) && $_GET['propertyId'] !== '' ? intval($_GET['propertyId']) : null;

    if ($propertyId !== null) {
        $stmt = $db->prepare("SELECT ps.*, t.name as tenantName, p.name as propertyName 
            FROM payment_schedules ps 
            LEFT JOIN tenants t ON ps.tenantId = t.id 
            LEFT JOIN properties p ON ps.propertyId = p.id 
            WHERE ps.propertyId = ?
            ORDER BY ps.scheduledDate DESC");
        $stmt->execute([$propertyId]);
    } else {
        $stmt = $db->query("SELECT ps.*, t.name as tenantName, p.name as propertyName 
            FROM payment_schedules ps 
            LEFT JOIN tenants t ON ps.tenantId = t.id 
            LEFT JOIN properties p ON ps.propertyId = p.id 
            ORDER BY ps.scheduledDate DESC");
    }
    $cheques = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['cheques' => $cheques]);
}

function handle_add_cheque($db) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (
        !$data ||
        !isset($data['propertyId']) ||
        !isset($data['scheduledAmount']) ||
        !isset($data['scheduledDate'])
    ) {
        http_response_code(400);
        echo json_encode(['error' => 'propertyId, scheduledAmount, and scheduledDate are required']);
        return;
    }

    $stmt = $db->prepare("INSERT INTO payment_schedules (tenantId, propertyId, scheduledAmount, scheduledDate, chequeNumber, status, notes) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
    $stmt->execute([
        null, // tenantId is now optional/null
        intval($data['propertyId']),
        floatval($data['scheduledAmount']),
        $data['scheduledDate'],
        isset($data['chequeNumber']) ? $data['chequeNumber'] : null,
        isset($data['notes']) ? $data['notes'] : null
    ]);
    echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
}

function handle_update_cheque_status($db) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['id']) || !isset($data['status'])) {
        http_response_code(400);
        echo json_encode(['error' => 'id and status are required']);
        return;
    }

    $id = intval($data['id']);
    $status = strtolower(trim($data['status']));
    $allowed = ['pending', 'received', 'cleared', 'bounced'];
    if (!in_array($status, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status']);
        return;
    }

    $stmt = $db->prepare("UPDATE payment_schedules SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);

    echo json_encode(['success' => true]);
}

function handle_update_tenant($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input data
    if (!$data || !isset($data['id']) || !isset($data['name']) || !isset($data['email'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Tenant ID, name, and email are required']);
        return;
    }
    
    // Get existing tenant to preserve fields not being updated
    $stmt_get = $db->prepare("SELECT * FROM tenants WHERE id = ?");
    $stmt_get->execute([$data['id']]);
    $existing = $stmt_get->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['error' => 'Tenant not found']);
        return;
    }
    
    // Use provided values or fallback to existing values
    $name = $data['name'];
    $email = $data['email'];
    $phone = isset($data['phone']) ? $data['phone'] : $existing['phone'];
    $idNumber = isset($data['idNumber']) ? $data['idNumber'] : $existing['idNumber'];
    $propertyId = isset($data['propertyId']) ? $data['propertyId'] : $existing['propertyId'];
    $bedspace = isset($data['bedspace']) ? $data['bedspace'] : $existing['bedspace'];
    $rentAmount = isset($data['rentAmount']) ? $data['rentAmount'] : $existing['rentAmount'];
    $rentDueDate = isset($data['rentDueDate']) ? $data['rentDueDate'] : $existing['rentDueDate'];
    $securityDeposit = isset($data['securityDeposit']) ? $data['securityDeposit'] : $existing['securityDeposit'];
    $contractStartDate = isset($data['contractStartDate']) ? $data['contractStartDate'] : $existing['contractStartDate'];
    $contractEndDate = isset($data['contractEndDate']) ? $data['contractEndDate'] : $existing['contractEndDate'];
    
    $stmt = $db->prepare("UPDATE tenants SET
        name = ?,
        email = ?,
        phone = ?,
        idNumber = ?,
        propertyId = ?,
        bedspace = ?,
        rentAmount = ?,
        rentDueDate = ?,
        securityDeposit = ?,
        contractStartDate = ?,
        contractEndDate = ?
        WHERE id = ?");
    
    $stmt->execute([
        $name,
        $email,
        $phone,
        $idNumber,
        $propertyId,
        $bedspace,
        $rentAmount,
        $rentDueDate,
        $securityDeposit,
        $contractStartDate,
        $contractEndDate,
        $data['id']
    ]);
    
    echo json_encode(['success' => true]);
}

function handle_update_expense($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input data
    if (!$data || !isset($data['id']) || !isset($data['description']) || !isset($data['amount']) || !isset($data['date'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Expense ID, description, amount, and date are required']);
        return;
    }
    
    // Get existing expense to preserve fields not being updated
    $stmt_get = $db->prepare("SELECT * FROM expenses WHERE id = ?");
    $stmt_get->execute([$data['id']]);
    $existing = $stmt_get->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['error' => 'Expense not found']);
        return;
    }
    
    // Use provided values or fallback to existing values
    $description = $data['description'];
    $amount = $data['amount'];
    $expenseDate = $data['date'];
    $category = isset($data['category']) ? $data['category'] : $existing['category'];
    $propertyId = isset($data['propertyId']) ? ($data['propertyId'] === 'null' || $data['propertyId'] === '' ? null : $data['propertyId']) : $existing['propertyId'];
    $vendorName = isset($data['vendorName']) ? $data['vendorName'] : $existing['vendorName'];
    $invoiceNumber = isset($data['invoiceNumber']) ? $data['invoiceNumber'] : $existing['invoiceNumber'];
    $notes = isset($data['notes']) ? $data['notes'] : $existing['notes'];
    
    $stmt = $db->prepare("UPDATE expenses SET
        description = ?,
        amount = ?,
        expenseDate = ?,
        category = ?,
        propertyId = ?,
        vendorName = ?,
        invoiceNumber = ?,
        notes = ?
        WHERE id = ?");
    
    $stmt->execute([
        $description,
        $amount,
        $expenseDate,
        $category,
        $propertyId,
        $vendorName,
        $invoiceNumber,
        $notes,
        $data['id']
    ]);
    
    echo json_encode(['success' => true]);
}

function handle_delete_expense($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input data
    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Expense ID is required']);
        return;
    }
    
    try {
        $stmt = $db->prepare("DELETE FROM expenses WHERE id = ?");
        $stmt->execute([$data['id']]);
        echo json_encode(['success' => $stmt->rowCount() > 0]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete expense: ' . $e->getMessage()]);
    }
}
function handle_add_common_expense($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input data
    if (!$data || !isset($data['description']) || !isset($data['amount']) || !isset($data['date'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Description, amount, and date are required']);
        return;
    }
    
    $stmt = $db->prepare("INSERT INTO common_expenses (description, amount, expenseDate, category, vendorName, invoiceNumber, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['description'],
        $data['amount'],
        $data['date'],
        isset($data['category']) ? $data['category'] : 'general',
        isset($data['vendorName']) ? $data['vendorName'] : null,
        isset($data['invoiceNumber']) ? $data['invoiceNumber'] : null,
        isset($data['notes']) ? $data['notes'] : null
    ]);
    echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
}

function handle_update_common_expense($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input data
    if (!$data || !isset($data['id']) || !isset($data['description']) || !isset($data['amount']) || !isset($data['date'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Expense ID, description, amount, and date are required']);
        return;
    }
    
    // Get existing expense to preserve fields not being updated
    $stmt_get = $db->prepare("SELECT * FROM common_expenses WHERE id = ?");
    $stmt_get->execute([$data['id']]);
    $existing = $stmt_get->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['error' => 'Common expense not found']);
        return;
    }
    
    // Use provided values or fallback to existing values
    $description = $data['description'];
    $amount = $data['amount'];
    $expenseDate = $data['date'];
    $category = isset($data['category']) ? $data['category'] : $existing['category'];
    $vendorName = isset($data['vendorName']) ? $data['vendorName'] : $existing['vendorName'];
    $invoiceNumber = isset($data['invoiceNumber']) ? $data['invoiceNumber'] : $existing['invoiceNumber'];
    $notes = isset($data['notes']) ? $data['notes'] : $existing['notes'];
    
    $stmt = $db->prepare("UPDATE common_expenses SET
        description = ?,
        amount = ?,
        expenseDate = ?,
        category = ?,
        vendorName = ?,
        invoiceNumber = ?,
        notes = ?
        WHERE id = ?");
    
    $stmt->execute([
        $description,
        $amount,
        $expenseDate,
        $category,
        $vendorName,
        $invoiceNumber,
        $notes,
        $data['id']
    ]);
    
    echo json_encode(['success' => true]);
}

function handle_delete_common_expense($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input data
    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Common expense ID is required']);
        return;
    }
    
    try {
        $stmt = $db->prepare("DELETE FROM common_expenses WHERE id = ?");
        $stmt->execute([$data['id']]);
        echo json_encode(['success' => $stmt->rowCount() > 0]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete common expense: ' . $e->getMessage()]);
    }
}
