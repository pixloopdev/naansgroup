<?php
session_start();
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

// Function to read .env file
function readEnv($path) {
    if (!file_exists($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $env[$name] = trim($value);
    }
    return $env;
}

$env = readEnv(__DIR__ . '/.env');
$appTitle = $env['APP_TITLE'] ?? 'NAANS Props';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
    <title><?php echo htmlspecialchars($appTitle); ?> - Property Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        .sidebar {
            transition: transform 0.3s ease-in-out;
        }
        .main-content {
            transition: margin-left 0.3s ease-in-out;
        }
        /* Custom scrollbar for better aesthetics */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        /* Hide views by default */
        .view {
            display: none;
        }
        /* Active view should be visible */
        .view.active {
            display: block;
        }
        .submenu {
            display: none;
            padding-left: 20px;
        }
        .submenu.open {
            display: block;
        }
        .nav-toggle .fa-chevron-down {
            transition: transform 0.3s;
        }
        .nav-toggle.open .fa-chevron-down {
            transform: rotate(180deg);
        }
        .dashboard-card { cursor: pointer; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

    <!-- Sidebar -->
    <aside class="sidebar bg-white w-64 min-h-screen p-4 flex flex-col justify-between shadow-lg">
        <div>
            <div class="text-2xl font-bold text-indigo-600 mb-8 flex items-center gap-2">
                <i class="fas fa-building"></i>
                <span id="brand-app-title"><?php echo htmlspecialchars($appTitle); ?></span>
            </div>
            <nav>
                <ul>
                    <li class="mb-2"><a href="#" data-view="dashboard" class="nav-link active-link flex items-center p-2 text-gray-700 rounded-lg hover:bg-indigo-100"><i class="fas fa-tachometer-alt w-6"></i>Dashboard</a></li>
                    
                    <li class="mb-2">
                        <button class="nav-toggle w-full flex items-center justify-between p-2 text-gray-700 rounded-lg hover:bg-indigo-100">
                            <span class="flex items-center"><i class="fas fa-city w-6"></i>Properties</span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        <ul class="submenu">
                            <li class="pt-2"><a href="#" data-view="properties" class="nav-link flex items-center p-2 text-gray-700 rounded-lg hover:bg-indigo-100"><i class="fas fa-list w-6"></i>Property List</a></li>
                            <li class="pt-2"><a href="#" data-view="bedspaces" class="nav-link flex items-center p-2 text-gray-700 rounded-lg hover:bg-indigo-100"><i class="fas fa-bed w-6"></i>Bedspaces</a></li>
                            <li class="pt-2"><a href="#" data-view="rent-management" class="nav-link flex items-center p-2 text-gray-700 rounded-lg hover:bg-indigo-100"><i class="fas fa-calendar-alt w-6"></i>Rent Management</a></li>
                        </ul>
                    </li>

                    <li class="mb-2"><a href="#" data-view="tenants" class="nav-link flex items-center p-2 text-gray-700 rounded-lg hover:bg-indigo-100"><i class="fas fa-users w-6"></i>Tenants</a></li>
                    
                    <li class="mb-2">
                        <button class="nav-toggle w-full flex items-center justify-between p-2 text-gray-700 rounded-lg hover:bg-indigo-100">
                            <span class="flex items-center"><i class="fas fa-money-bill-wave w-6"></i>Finance</span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        <ul class="submenu">
                            <li class="pt-2"><a href="#" data-view="payments" class="nav-link flex items-center p-2 text-gray-700 rounded-lg hover:bg-indigo-100"><i class="fas fa-file-invoice-dollar w-6"></i>Payments</a></li>
                            <li class="pt-2"><a href="#" data-view="cheques" class="nav-link flex items-center p-2 text-gray-700 rounded-lg hover:bg-indigo-100"><i class="fas fa-money-check w-6"></i>Cheques</a></li>
                            <li class="pt-2"><a href="#" data-view="expenses" class="nav-link flex items-center p-2 text-gray-700 rounded-lg hover:bg-indigo-100"><i class="fas fa-receipt w-6"></i>Expenses</a></li>
                            <li class="pt-2"><a href="#" data-view="common-expenses" class="nav-link flex items-center p-2 text-gray-700 rounded-lg hover:bg-indigo-100"><i class="fas fa-coins w-6"></i>Common Expenses</a></li>
                            <li class="pt-2"><a href="#" data-view="reports" class="nav-link flex items-center p-2 text-gray-700 rounded-lg hover:bg-indigo-100"><i class="fas fa-chart-pie w-6"></i>Reports</a></li>
                        </ul>
                    </li>
                    <li class="mb-2"><a href="#" data-view="settings" class="nav-link flex items-center p-2 text-gray-700 rounded-lg hover:bg-indigo-100"><i class="fas fa-cog w-6"></i>Settings</a></li>
 
                    </ul>
                </nav>
        </div>
        <div>
            <button id="refresh-data" class="w-full flex items-center justify-center p-2 text-gray-700 rounded-lg hover:bg-indigo-100">
                <i class="fas fa-sync-alt w-6 mr-2"></i>Refresh Data
            </button>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content flex-1 p-6 lg:p-8 overflow-y-auto">
        <!-- Header -->
        <header class="flex justify-between items-center mb-8">
            <h1 id="view-title" class="text-3xl font-bold text-gray-800">Dashboard</h1>
            <div class="flex items-center gap-4">
                <span class="text-gray-600">Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition">Logout</a>
            </div>
        </header>

        <!-- All Views Here... -->
        
        <!-- View: Dashboard -->
        <div id="dashboard" class="view active">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-6 rounded-xl shadow-md flex items-center gap-4 dashboard-card" data-view="properties">
                    <div class="bg-blue-100 p-4 rounded-full"><i class="fas fa-building text-2xl text-blue-500"></i></div>
                    <div>
                        <p class="text-gray-500">Total Properties</p>
                        <p id="total-properties" class="text-3xl font-bold">0</p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-md flex items-center gap-4 dashboard-card" data-view="tenants">
                    <div class="bg-green-100 p-4 rounded-full"><i class="fas fa-users text-2xl text-green-500"></i></div>
                    <div>
                        <p class="text-gray-500">Total Tenants</p>
                        <p id="total-tenants" class="text-3xl font-bold">0</p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-md flex items-center gap-4 dashboard-card" data-view="reports">
                    <div class="bg-yellow-100 p-4 rounded-full"><i class="fas fa-dollar-sign text-2xl text-yellow-500"></i></div>
                    <div>
                        <p class="text-gray-500">Total Revenue</p>
                        <p id="monthly-revenue" class="text-3xl font-bold">AED 0.00</p>
                    </div>
                </div>
                 <div class="bg-white p-6 rounded-xl shadow-md flex items-center gap-4 dashboard-card" data-view="bedspaces">
                    <div class="bg-red-100 p-4 rounded-full"><i class="fas fa-bed text-2xl text-red-500"></i></div>
                    <div>
                        <p class="text-gray-500">Total Bedspaces</p>
                        <p id="total-bedspaces" class="text-3xl font-bold">0</p>
                    </div>
                </div>
            </div>

            <!-- Recent Payments & Rent Reminders -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="bg-white p-6 rounded-xl shadow-md">
                    <h2 class="text-xl font-bold mb-4">Upcoming & Overdue Rent</h2>
                    <div id="dashboard-rent-reminders" class="space-y-4">
                        <p class="text-gray-500">No upcoming rent payments.</p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-md">
                    <h2 class="text-xl font-bold mb-4">Recent Payments</h2>
                    <div id="recent-payments-list" class="space-y-4">
                        <p class="text-gray-500">No recent payments.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- View: Properties -->
        <div id="properties" class="view">
            <div class="bg-white p-6 rounded-xl shadow-md">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">Property List</h2>
                    <button id="add-property-btn" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center gap-2">
                        <i class="fas fa-plus"></i>
                        Add Property
                    </button>
                </div>
                <div id="properties-list" class="space-y-4">
                    <p class="text-gray-500">Loading properties...</p>
                </div>
            </div>
        </div>

        <!-- View: Tenants -->
        <div id="tenants" class="view">
             <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-1 bg-white p-6 rounded-xl shadow-md">
                    <h2 class="text-xl font-bold mb-4">Add New Tenant</h2>
                    <form id="add-tenant-form" class="space-y-4">
                        <div>
                            <label for="tenant-name" class="block text-sm font-medium text-gray-700">Tenant Name</label>
                            <input type="text" id="tenant-name" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="tenant-email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" id="tenant-email" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="tenant-phone" class="block text-sm font-medium text-gray-700">Mobile Number</label>
                            <input type="tel" id="tenant-phone" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="tenant-id-number" class="block text-sm font-medium text-gray-700">ID/Passport Number</label>
                            <input type="text" id="tenant-id-number" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="tenant-property" class="block text-sm font-medium text-gray-700">Property</label>
                            <select id="tenant-property" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Select a property</option>
                            </select>
                        </div>
                        <div>
                            <label for="tenant-bedspace" class="block text-sm font-medium text-gray-700">Bedspace/Room</label>
                            <select id="tenant-bedspace" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Select a bedspace or room</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="tenant-rent-amount" class="block text-sm font-medium text-gray-700">Rent Amount (AED)</label>
                                <input type="number" id="tenant-rent-amount" step="0.01" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="tenant-rent-due-date" class="block text-sm font-medium text-gray-700">Rent Due Day of Month (1-31)</label>
                                <input type="number" id="tenant-rent-due-date" min="1" max="31" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        <div>
                            <label for="tenant-security-deposit" class="block text-sm font-medium text-gray-700">Security Deposit (AED)</label>
                            <input type="number" id="tenant-security-deposit" step="0.01" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="tenant-contract-start" class="block text-sm font-medium text-gray-700">Contract Start Date</label>
                                <input type="date" id="tenant-contract-start" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="tenant-contract-end" class="block text-sm font-medium text-gray-700">Contract End Date</label>
                                <input type="date" id="tenant-contract-end" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition">Add Tenant</button>
                    </form>
                </div>
                <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-md">
                    <h2 class="text-xl font-bold mb-4">Tenant List</h2>
                    <div id="tenants-list" class="space-y-4">
                       <p class="text-gray-500">No tenants added yet.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- View: Bedspaces -->
        <div id="bedspaces" class="view">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-1 bg-white p-6 rounded-xl shadow-md h-fit">
                    <h2 class="text-xl font-bold mb-4">Add New Space</h2>
                    <div class="flex gap-4 mb-4">
                        <button type="button" id="show-bedspace-form" class="flex-1 bg-red-500 text-white py-2 px-4 rounded-lg hover:bg-red-600 transition">Add Bedspace</button>
                        <button type="button" id="show-room-form" class="flex-1 bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600 transition">Add Room</button>
                    </div>
                    <form id="add-bedspace-form" class="space-y-4">
                        <div>
                            <label for="bedspace-property" class="block text-sm font-medium text-gray-700">Select Property</label>
                            <select id="bedspace-property" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Select a property</option>
                            </select>
                        </div>
                        <div>
                            <label for="bedspace-name" class="block text-sm font-medium text-gray-700">Bedspace Name/Number</label>
                            <input type="text" id="bedspace-name" required placeholder="e.g., Bed-101A" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>

                        <button type="submit" class="w-full bg-red-500 text-white py-2 px-4 rounded-lg hover:bg-red-600 transition">Add Bedspace</button>
                    </form>
                    
                    <form id="add-room-form" class="space-y-4 hidden">
                        <div>
                            <label for="room-property" class="block text-sm font-medium text-gray-700">Select Property</label>
                            <select id="room-property" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Select a property</option>
                            </select>
                        </div>
                        <div>
                            <label for="room-name" class="block text-sm font-medium text-gray-700">Room Name/Number</label>
                            <input type="text" id="room-name" required placeholder="e.g., Room-201" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <button type="submit" class="w-full bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600 transition">Add Room</button>
                    </form>
                </div>
                <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-md">
                    <h2 class="text-xl font-bold mb-4">Bedspace List by Property</h2>
                    <div id="bedspaces-list-by-property" class="space-y-4">
                        <p class="text-gray-500">Select a property to see its bedspaces.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- View: Rent Management -->
        <div id="rent-management" class="view">
            <div class="bg-white p-6 rounded-xl shadow-md">
                <h2 class="text-xl font-bold mb-4">Rent Reminders (Next 7 Days & Overdue)</h2>
                <div id="rent-reminders-list" class="space-y-4">
                    <p class="text-gray-500">No upcoming or overdue rent payments.</p>
                </div>
            </div>
        </div>

        <!-- View: Payments -->
        <div id="payments" class="view">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-1 bg-white p-6 rounded-xl shadow-md">
                    <h2 class="text-xl font-bold mb-4">Record New Payment</h2>
                    <form id="add-payment-form" class="space-y-4">
                         <div>
                            <label for="payment-tenant" class="block text-sm font-medium text-gray-700">Select Tenant</label>
                            <select id="payment-tenant" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Select a tenant</option>
                            </select>
                        </div>
                        <div>
                            <label for="payment-amount" class="block text-sm font-medium text-gray-700">Amount (AED)</label>
                            <input type="number" id="payment-amount" step="0.01" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="payment-method" class="block text-sm font-medium text-gray-700">Payment Method</label>
                            <select id="payment-method" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="cash">Cash</option>
                                <option value="upi">UPI</option>
                                <option value="cheque">Cheque</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </select>
                        </div>
                         <div>
                            <label for="payment-date" class="block text-sm font-medium text-gray-700">Payment Date</label>
                            <input type="date" id="payment-date" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <button type="submit" class="w-full bg-orange-500 text-white py-2 px-4 rounded-lg hover:bg-orange-600 transition">Record Payment</button>
                    </form>
                </div>
                <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-md">
                    <h2 class="text-xl font-bold mb-4">All Payments</h2>
                    <div id="all-payments-list" class="space-y-4">
                       <p class="text-gray-500">No payments recorded yet.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- View: Cheques -->
        <div id="cheques" class="view">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Add Cheque Form -->
                <div class="lg:col-span-1 bg-white p-6 rounded-xl shadow-md">
                    <h2 class="text-xl font-bold mb-4">Add New Cheque</h2>
                    <form id="add-cheque-form" class="space-y-4">
                        <div>
                            <label for="cheque-property" class="block text-sm font-medium text-gray-700">Property</label>
                            <select id="cheque-property" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Select a property</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="cheque-amount" class="block text-sm font-medium text-gray-700">Amount (AED)</label>
                                <input type="number" id="cheque-amount" step="0.01" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="cheque-date" class="block text-sm font-medium text-gray-700">Scheduled Date</label>
                                <input type="date" id="cheque-date" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        <div>
                            <label for="cheque-number" class="block text-sm font-medium text-gray-700">Cheque Number</label>
                            <input type="text" id="cheque-number" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="cheque-notes" class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea id="cheque-notes" rows="2" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                        </div>
                        <button type="submit" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-lg hover:bg-indigo-700 transition">Add Cheque</button>
                    </form>
                </div>

                <!-- Cheques List -->
                <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-md">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold">Cheques</h2>
                        <div class="flex items-end gap-2">
                            <div>
                                <label for="cheque-property-filter" class="block text-sm font-medium text-gray-700">Filter by Property</label>
                                <select id="cheque-property-filter" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">All Properties</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div id="cheques-list" class="space-y-4">
                        <p class="text-gray-500">No cheque entries.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- View: Expenses -->
        <div id="expenses" class="view">
             <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-1 bg-white p-6 rounded-xl shadow-md">
                    <h2 class="text-xl font-bold mb-4">Record New Expense</h2>
                    <form id="add-expense-form" class="space-y-4">
                         <div>
                            <label for="expense-description" class="block text-sm font-medium text-gray-700">Description</label>
                            <input type="text" id="expense-description" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="expense-amount" class="block text-sm font-medium text-gray-700">Amount (AED)</label>
                                <input type="number" id="expense-amount" step="0.01" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="expense-category" class="block text-sm font-medium text-gray-700">Category</label>
                                <select id="expense-category" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="general">General</option>
                                    <option value="dewa">DEWA Utilities</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="commission">Commission</option>
                                    <option value="purchasing">Purchasing</option>
                                    <option value="cheque">Cheque Payment</option>
                                </select>
                            </div>
                        </div>
                         <div>
                            <label for="expense-date" class="block text-sm font-medium text-gray-700">Expense Date</label>
                            <input type="date" id="expense-date" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="expense-property" class="block text-sm font-medium text-gray-700">Related Property</label>
                            <select id="expense-property" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">None</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="expense-vendor" class="block text-sm font-medium text-gray-700">Vendor/Supplier</label>
                                <input type="text" id="expense-vendor" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="expense-invoice" class="block text-sm font-medium text-gray-700">Invoice Number</label>
                                <input type="text" id="expense-invoice" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        <div>
                            <label for="expense-notes" class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea id="expense-notes" rows="2" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                        </div>
                        <button type="submit" class="w-full bg-purple-600 text-white py-2 px-4 rounded-lg hover:bg-purple-700 transition">Record Expense</button>
                    </form>
                </div>
                <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-md">
                    <h2 class="text-xl font-bold mb-4">All Expenses</h2>
                    <div id="all-expenses-list" class="space-y-4">
                       <p class="text-gray-500">No expenses recorded yet.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- View: Common Expenses -->
        <div id="common-expenses" class="view">
             <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-1 bg-white p-6 rounded-xl shadow-md">
                    <h2 class="text-xl font-bold mb-4">Record Common Expense</h2>
                    <form id="add-common-expense-form" class="space-y-4">
                         <div>
                            <label for="common-expense-description" class="block text-sm font-medium text-gray-700">Description</label>
                            <input type="text" id="common-expense-description" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="common-expense-amount" class="block text-sm font-medium text-gray-700">Amount (AED)</label>
                                <input type="number" id="common-expense-amount" step="0.01" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="common-expense-category" class="block text-sm font-medium text-gray-700">Category</label>
                                <select id="common-expense-category" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="general">General</option>
                                    <option value="office">Office Expenses</option>
                                    <option value="marketing">Marketing</option>
                                    <option value="legal">Legal & Professional</option>
                                    <option value="insurance">Insurance</option>
                                    <option value="travel">Travel & Transport</option>
                                    <option value="utilities">Utilities</option>
                                    <option value="software">Software & Subscriptions</option>
                                </select>
                            </div>
                        </div>
                         <div>
                            <label for="common-expense-date" class="block text-sm font-medium text-gray-700">Expense Date</label>
                            <input type="date" id="common-expense-date" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="common-expense-vendor" class="block text-sm font-medium text-gray-700">Vendor/Supplier</label>
                                <input type="text" id="common-expense-vendor" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="common-expense-invoice" class="block text-sm font-medium text-gray-700">Invoice Number</label>
                                <input type="text" id="common-expense-invoice" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        <div>
                            <label for="common-expense-notes" class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea id="common-expense-notes" rows="2" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                        </div>
                        <button type="submit" class="w-full bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition">Record Common Expense</button>
                    </form>
                </div>
                <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-md">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold">All Common Expenses</h2>
                        <div class="flex items-end gap-2">
                            <div>
                                <label for="common-expense-category-filter" class="block text-sm font-medium text-gray-700">Filter by Category</label>
                                <select id="common-expense-category-filter" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">All Categories</option>
                                    <option value="general">General</option>
                                    <option value="office">Office Expenses</option>
                                    <option value="marketing">Marketing</option>
                                    <option value="legal">Legal & Professional</option>
                                    <option value="insurance">Insurance</option>
                                    <option value="travel">Travel & Transport</option>
                                    <option value="utilities">Utilities</option>
                                    <option value="software">Software & Subscriptions</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div id="common-expenses-list" class="space-y-4">
                       <p class="text-gray-500">No common expenses recorded yet.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- View: Reports -->
        <div id="reports" class="view">
            <div class="bg-white p-6 rounded-xl shadow-md">
                <h2 class="text-xl font-bold mb-4">Financial Report</h2>
                <!-- Date Filter -->
                <div class="p-4 border rounded-lg bg-gray-50">
                    <div class="flex items-center gap-2 mb-4">
                        <button data-range="this-month" class="report-range-btn px-3 py-1 text-sm border rounded-full hover:bg-indigo-100">This Month</button>
                        <button data-range="last-month" class="report-range-btn px-3 py-1 text-sm border rounded-full hover:bg-indigo-100">Last Month</button>
                        <button data-range="last-3-months" class="report-range-btn px-3 py-1 text-sm border rounded-full hover:bg-indigo-100">Last 3 Months</button>
                    </div>
                    <div class="flex items-end gap-4">
                        <div>
                            <label for="report-property" class="block text-sm font-medium text-gray-700">Property</label>
                            <select id="report-property" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">All Properties</option>
                            </select>
                        </div>
                        <div>
                            <label for="report-start-date" class="block text-sm font-medium text-gray-700">Start Date</label>
                            <input type="date" id="report-start-date" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="report-end-date" class="block text-sm font-medium text-gray-700">End Date</label>
                            <input type="date" id="report-end-date" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <button id="generate-report-btn" class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition">Generate Report</button>
                        <button id="export-report-btn" class="bg-indigo-600 text-white py-2 px-4 rounded-lg hover:bg-indigo-700 transition">Export CSV</button>
                        <button id="export-report-xlsx-btn" class="bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition">Export XLSX</button>
                    </div>
                </div>

                <!-- Report Summary -->
                <div id="report-summary" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 my-8" style="display: none;">
                    <div class="bg-blue-100 p-6 rounded-xl text-center">
                        <p class="text-lg font-medium text-blue-800">Opening Balance</p>
                        <p id="report-opening-balance" class="text-3xl font-bold text-blue-600">AED 0.00</p>
                    </div>
                    <div class="bg-green-100 p-6 rounded-xl text-center">
                        <p class="text-lg font-medium text-green-800">Total Revenue</p>
                        <p id="report-total-revenue" class="text-3xl font-bold text-green-600">AED 0.00</p>
                    </div>
                    <div class="bg-orange-100 p-6 rounded-xl text-center">
                        <p class="text-lg font-medium text-orange-800">Total Expenses</p>
                        <p id="report-total-expenses" class="text-3xl font-bold text-orange-600">AED 0.00</p>
                    </div>
                    <div class="bg-indigo-100 p-6 rounded-xl text-center">
                        <p class="text-lg font-medium text-indigo-800">Net Profit</p>
                        <p id="report-net-profit" class="text-3xl font-bold text-indigo-600">AED 0.00</p>
                    </div>
                </div>

                <!-- Report Details -->
                <div id="report-details" class="grid grid-cols-1 lg:grid-cols-2 gap-8" style="display: none;">
                    <div>
                        <h3 class="text-lg font-bold mb-4">Income Details</h3>
                        <div id="report-income-list" class="space-y-2"></div>
                    </div>
                     <div>
                        <h3 class="text-lg font-bold mb-4">Expense Details</h3>
                        <div id="report-expense-list" class="space-y-2"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- View: Settings -->
        <div id="settings" class="view">
            <div class="bg-white p-6 rounded-xl shadow-md max-w-2xl">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Settings</h2>
                <form id="settings-form" class="space-y-4">
                    <div>
                        <label for="settings-app-title" class="block text-sm font-medium text-gray-700">Software Title</label>
                        <input type="text" id="settings-app-title" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g., NAANS Props">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="settings-currency" class="block text-sm font-medium text-gray-700">Currency Code</label>
                            <input type="text" id="settings-currency" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm uppercase" placeholder="e.g., AED, INR, USD">
                        </div>
                        <div>
                            <label for="settings-country" class="block text-sm font-medium text-gray-700">Country Code</label>
                            <input type="text" id="settings-country" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm uppercase" placeholder="e.g., AE, IN, US">
                        </div>
                    </div>
                    <div>
                        <label for="settings-opening-balance" class="block text-sm font-medium text-gray-700">Opening Balance</label>
                        <input type="number" id="settings-opening-balance" step="0.01" min="0" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" placeholder="0.00">
                        <p class="text-xs text-gray-500 mt-1">Starting balance for financial calculations</p>
                    </div>
                    <div class="pt-2">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition-colors">Save Settings</button>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Note: Currency affects labels and display; existing numeric values remain unchanged.</p>
                </form>

                <div class="mt-8 border-t pt-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Data Management</h3>
                    <div class="space-y-4">
                        <div class="bg-white border border-gray-200 rounded-lg p-4">
                            <h4 class="text-gray-800 font-semibold mb-2">Database Backup</h4>
                            <p class="text-sm text-gray-600 mb-4">Download a backup of your current database. This includes all properties, tenants, payments, and other data.</p>
                            <button type="button" id="backup-db-btn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors">Download Backup</button>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h4 class="text-blue-800 font-semibold mb-2">Import Demo Data</h4>
                            <p class="text-sm text-blue-600 mb-4">Load sample data to see how the system works. This includes example properties, tenants, and payments.</p>
                            <button type="button" id="import-demo-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">Import Demo Data</button>
                        </div>

                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <h4 class="text-red-800 font-semibold mb-2">Reset All Data</h4>
                            <p class="text-sm text-red-600 mb-4">This action will permanently delete all properties, tenants, payments, and other data. This cannot be undone.</p>
                            <button type="button" id="reset-data-btn" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-colors">Reset All Data</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
 
         <!-- Toast Notification & Modal -->
        <div id="toast" class="fixed bottom-5 right-5 bg-green-500 text-white py-3 px-5 rounded-lg shadow-lg flex items-center transform translate-x-[calc(100%+2rem)] transition-transform duration-300">
            <p id="toast-message" class="flex-grow"></p>
            <button id="toast-close-btn" class="ml-4 text-xl font-semibold leading-none hover:text-gray-200 focus:outline-none">&times;</button>
        </div>
        <div id="confirm-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 h-full w-full flex items-center justify-center z-50" style="display: none;"><div class="relative mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white"><div class="mt-3 text-center"><div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100"><i class="fas fa-exclamation-triangle text-red-600 text-xl"></i></div><h3 class="text-lg leading-6 font-medium text-gray-900 mt-2">Delete Item?</h3><div class="mt-2 px-7 py-3"><p class="text-sm text-gray-500" id="confirm-modal-text">Are you sure you want to delete this item? This action cannot be undone.</p></div><div class="items-center px-4 py-3"><button id="modal-confirm-btn" class="px-4 py-2 bg-red-500 text-white text-base font-medium rounded-md w-auto shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-300">Delete</button><button id="modal-cancel-btn" class="px-4 py-2 bg-gray-200 text-gray-800 text-base font-medium rounded-md w-auto ml-2 shadow-sm hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-300">Cancel</button></div></div></div></div>

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const API_URL = 'api.php';
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            let appData = {
                properties: [], tenants: [], payments: [], expenses: [], cheques: [], rent_reminders: [], common_expenses: []
            };
            let toastTimeout;
            let lastReportData = null;
            let lastReportFilters = null;
            let settings = { appTitle: <?php echo json_encode($appTitle); ?>, currencyCode: 'AED', countryCode: 'AE', openingBalance: 0.0 };
 
            // --- API Communication ---
            async function apiCall(action, body = null) {
                const headers = { 'Content-Type': 'application/json' };
                if (csrfToken) headers['X-CSRF-Token'] = csrfToken;
                const options = {
                    method: body ? 'POST' : 'GET',
                    headers,
                    body: body ? JSON.stringify(body) : null
                };
                try {
                    const response = await fetch(`${API_URL}?action=${action}`, options);
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    return await response.json();
                } catch (error) {
                    console.error('API Call Error:', error);
                    showToast(`Error communicating with server: ${error.message}`, true);
                    return null;
                }
            }

            // Multipart API (for file uploads)
            async function apiCallMultipart(action, formData) {
                try {
                    const headers = {};
                    if (csrfToken) headers['X-CSRF-Token'] = csrfToken;
                    const response = await fetch(`${API_URL}?action=${action}`, {
                        method: 'POST',
                        headers,
                        body: formData
                    });
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    return await response.json();
                } catch (error) {
                    console.error('API Call Error (multipart):', error);
                    showToast(`Error communicating with server: ${error.message}`, true);
                    return null;
                }
            }

            // --- Settings helpers ---
            function formatCurrency(amount) {
                const n = Number(amount || 0);
                return `${settings.currencyCode} ${n.toFixed(2)}`;
            }

            async function handleBackupDB() {
                try {
                    const response = await fetch('api.php?action=backup_db', {
                        method: 'GET',
                        headers: { 'Accept': 'application/json' }
                    });
                    
                    if (response.ok) {
                        const blob = await response.blob();
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = `naansprop_backup_${new Date().toISOString().split('T')[0]}.sqlite`;
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(a);
                        showToast('Database backup downloaded successfully!');
                    } else {
                        throw new Error('Backup failed');
                    }
                } catch (error) {
                    showToast('Failed to backup database. Please try again.', true);
                }
            }

            async function handleImportDemo() {
                const res = await apiCall('import_demo');
                if (res && res.success) {
                    showToast('Demo data imported successfully!');
                    fetchAllData(); // Refresh the data without page reload
                } else {
                    showToast('Failed to import demo data. Please try again.', true);
                }
            }

            async function handleResetData() {
                try {
                    const res = await apiCall('reset_data');
                    if (res && res.success) {
                        showToast('All data has been reset successfully!');
                        // Clear any cached data
                        appData = {
                            properties: [],
                            tenants: [],
                            payments: [],
                            expenses: [],
                            rent_reminders: [],
                            cheques: [],
                            common_expenses: []
                        };
                        // Reload the page after a short delay to ensure toast is visible
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('Failed to reset data. Please try again.', true);
                    }
                } catch (error) {
                    console.error('Reset data error:', error);
                    showToast('Failed to reset data. Please try again.', true);
                }
            }

            async function loadSettings() {
                const res = await apiCall('get_settings');
                if (res && !res.error) {
                    settings.appTitle = res.appTitle || settings.appTitle;
                    settings.currencyCode = res.currencyCode || settings.currencyCode;
                    settings.countryCode = res.countryCode || settings.countryCode;
                    settings.openingBalance = res.openingBalance || settings.openingBalance;
                    applySettingsToUI();
                    // Populate form
                    const fApp = document.getElementById('settings-app-title');
                    const fCur = document.getElementById('settings-currency');
                    const fCty = document.getElementById('settings-country');
                    const fOpening = document.getElementById('settings-opening-balance');
                    if (fApp) fApp.value = settings.appTitle || '';
                    if (fCur) fCur.value = settings.currencyCode || '';
                    if (fCty) fCty.value = settings.countryCode || '';
                    if (fOpening) fOpening.value = settings.openingBalance || 0;
                }
            }

            function applySettingsToUI() {
                // Brand and document title
                const brandEl = document.getElementById('brand-app-title');
                if (brandEl) brandEl.textContent = settings.appTitle || 'NAANS Props';
                document.title = `${settings.appTitle || 'NAANS Props'} - Property Management`;
                // Update some dynamic labels with currency code
                const payLbl = document.querySelector('label[for="payment-amount"]');
                if (payLbl) payLbl.textContent = `Amount (${settings.currencyCode})`;
                const expLbl = document.querySelector('label[for="expense-amount"]');
                if (expLbl) expLbl.textContent = `Amount (${settings.currencyCode})`;
                const yRentLbl = document.querySelector('label[for="property-building-yearly-rent"]');
                if (yRentLbl) yRentLbl.textContent = `Building Yearly Rent (${settings.currencyCode})`;
                const totChargesLbl = document.querySelector('label[for="property-total-charges"]');
                if (totChargesLbl) totChargesLbl.textContent = `Total Charges (${settings.currencyCode})`;
                const yRentEditLbl = document.querySelector('label[for="edit-property-building-yearly-rent"]');
                if (yRentEditLbl) yRentEditLbl.textContent = `Building Yearly Rent (${settings.currencyCode})`;
                const totChargesEditLbl = document.querySelector('label[for="edit-property-total-charges"]');
                if (totChargesEditLbl) totChargesEditLbl.textContent = `Total Charges (${settings.currencyCode})`;
            }

            async function handleSaveSettings(e) {
                e.preventDefault();
                const payload = {
                    appTitle: document.getElementById('settings-app-title')?.value?.trim(),
                    currencyCode: document.getElementById('settings-currency')?.value?.trim(),
                    countryCode: document.getElementById('settings-country')?.value?.trim(),
                    openingBalance: parseFloat(document.getElementById('settings-opening-balance')?.value) || 0
                };
                const res = await apiCall('save_settings', payload);
                if (res && res.success) {
                    showToast('Settings saved');
                    await loadSettings();
                } else {
                    showToast(res?.error || 'Failed to save settings', true);
                }
            }
 
            // --- Initial Load and Refresh ---
            async function fetchAllData() {
                const data = await apiCall('get_all_data');
                if (data && !data.error) {
                    appData = data;
                    renderAll();
                    // Ensure Cheques list is refreshed even if view not active
                    if (typeof updateChequesList === 'function') {
                        updateChequesList(appData.cheques, appData.properties, appData.tenants);
                    }
                    showToast('Data refreshed successfully!');
                } else if (data && data.error) {
                    showToast(`Error fetching data: ${data.error}`, true);
                }
            }
            
            function renderAll() {
                updatePropertiesList(appData.properties);
                updatePropertyDropdowns(appData.properties);
                updateTenantsList(appData.tenants, appData.properties);
                updateTenantDropdowns(appData.tenants);
                updatePaymentsList(appData.payments, appData.tenants, appData.properties);
                updateRecentPayments(appData.payments, appData.tenants);
                updateExpensesList(appData.expenses, appData.properties);
                updateCommonExpensesList(appData.common_expenses);
                updateChequesList(appData.cheques, appData.properties, appData.tenants);
                updateDashboardCards(appData.properties, appData.tenants, appData.payments);
                updateRentRemindersList(appData.rent_reminders, appData.properties, 'rent-reminders-list');
                updateRentRemindersList(appData.rent_reminders, appData.properties, 'dashboard-rent-reminders');
                const selectedPropertyId = document.getElementById('bedspace-property').value;
                if (selectedPropertyId) {
                    updateBedspaceListView(selectedPropertyId, appData.properties);
                }
            }

            // --- UI Navigation ---
            function setupNavigation() {
                const navLinks = document.querySelectorAll('.nav-link');
                const views = document.querySelectorAll('.view');
                const viewTitle = document.getElementById('view-title');
                const navToggles = document.querySelectorAll('.nav-toggle');

                navLinks.forEach(link => {
                    link.addEventListener('click', (e) => {
                        e.preventDefault();
                        const viewId = link.getAttribute('data-view');
                        views.forEach(view => view.classList.remove('active'));
                        document.getElementById(viewId).classList.add('active');
                        navLinks.forEach(nav => nav.classList.remove('active-link', 'bg-indigo-100', 'text-indigo-600', 'font-bold'));
                        link.classList.add('active-link', 'bg-indigo-100', 'text-indigo-600', 'font-bold');
                        viewTitle.textContent = link.textContent;
                    });
                });

                navToggles.forEach(toggle => {
                    toggle.addEventListener('click', () => {
                        const submenu = toggle.nextElementSibling;
                        submenu.classList.toggle('open');
                        toggle.classList.toggle('open');
                    });
                });
            }

            // Programmatic navigation used by dashboard cards
            function navigateTo(viewId) {
                const navLinks = document.querySelectorAll('.nav-link');
                const views = document.querySelectorAll('.view');
                const viewTitle = document.getElementById('view-title');

                views.forEach(v => v.classList.remove('active'));
                const target = document.getElementById(viewId);
                if (target) target.classList.add('active');

                navLinks.forEach(nav => {
                    if (nav.getAttribute('data-view') === viewId) {
                        nav.classList.add('active-link', 'bg-indigo-100', 'text-indigo-600', 'font-bold');
                    } else {
                        nav.classList.remove('active-link', 'bg-indigo-100', 'text-indigo-600', 'font-bold');
                    }
                });

                const activeLink = Array.from(navLinks).find(l => l.getAttribute('data-view') === viewId);
                if (activeLink) viewTitle.textContent = activeLink.textContent.trim();
            }

            // Wire dashboard cards to navigate
            function setupDashboardCards() {
                document.querySelectorAll('.dashboard-card').forEach(card => {
                    card.addEventListener('click', () => {
                        const viewId = card.getAttribute('data-view');
                        if (viewId) navigateTo(viewId);
                    });
                });
            }
            
            // --- Toast & Modal ---
            function showToast(message, isError = false) {
                const toast = document.getElementById('toast');
                const toastMessage = document.getElementById('toast-message');
                if(toastTimeout) clearTimeout(toastTimeout);
                toastMessage.textContent = message;
                toast.className = `fixed bottom-5 right-5 text-white py-3 px-5 rounded-lg shadow-lg flex items-center transform transition-transform duration-300 ${isError ? 'bg-red-500' : 'bg-green-500'} translate-x-0`;
                toastTimeout = setTimeout(() => { hideToast(); }, 4000);
            }
            function hideToast() {
                const toast = document.getElementById('toast');
                if(toastTimeout) clearTimeout(toastTimeout);
                toast.className = 'fixed bottom-5 right-5 bg-green-500 text-white py-3 px-5 rounded-lg shadow-lg flex items-center transform translate-x-[calc(100%+2rem)] transition-transform duration-300';
            }
            let confirmCallback = null;
            function showConfirmModal(callback) {
                confirmCallback = callback;
                document.getElementById('confirm-modal').style.display = 'flex';
            }
            function hideConfirmModal() {
                confirmCallback = null;
                document.getElementById('confirm-modal').style.display = 'none';
            }
            function setupModalListeners() {
                // Backup Database button listener
                document.getElementById('backup-db-btn').addEventListener('click', handleBackupDB);

                // Import Demo Data button listener
                document.getElementById('import-demo-btn').addEventListener('click', () => {
                    showConfirmModal(handleImportDemo, 'Import Demo Data?', 
                        'This will add sample properties, tenants, and payments to your database. Your existing data will not be affected.');
                });

                // Reset Data button listener
                document.getElementById('reset-data-btn').addEventListener('click', () => {
                    showConfirmModal(handleResetData, 'Are you sure you want to reset all data?', 
                        'This will permanently delete all properties, tenants, payments, and other data. This action cannot be undone.');
                });

                document.getElementById('modal-confirm-btn').addEventListener('click', () => {
                    if (confirmCallback) confirmCallback();
                    hideConfirmModal();
                });
                document.getElementById('modal-cancel-btn').addEventListener('click', hideConfirmModal);
                document.getElementById('toast-close-btn').addEventListener('click', hideToast);
                
                // Property modal listeners (Add)
                document.getElementById('add-property-btn').addEventListener('click', () => {
                    document.getElementById('add-property-modal').classList.remove('hidden');
                });
                
                document.getElementById('close-property-modal').addEventListener('click', () => {
                    document.getElementById('add-property-modal').classList.add('hidden');
                });
                
                document.getElementById('cancel-property-btn').addEventListener('click', () => {
                    document.getElementById('add-property-modal').classList.add('hidden');
                });
                
                // Close Add modal when clicking outside
                document.getElementById('add-property-modal').addEventListener('click', (e) => {
                    if (e.target.id === 'add-property-modal') {
                        document.getElementById('add-property-modal').classList.add('hidden');
                    }
                });

                // Property modal listeners (Edit)
                const editModalEl = document.getElementById('edit-property-modal');
                if (editModalEl) {
                    document.getElementById('close-edit-property-modal').addEventListener('click', () => {
                        editModalEl.classList.add('hidden');
                    });
                    document.getElementById('cancel-edit-property-btn').addEventListener('click', () => {
                        editModalEl.classList.add('hidden');
                    });
                    // Close Edit modal when clicking outside
                    editModalEl.addEventListener('click', (e) => {
                        if (e.target.id === 'edit-property-modal') {
                            editModalEl.classList.add('hidden');
                        }
                    });
                }
                
                // Tenant modal listeners
                const editTenantModalEl = document.getElementById('edit-tenant-modal');
                if (editTenantModalEl) {
                    document.getElementById('close-edit-tenant-modal').addEventListener('click', () => {
                        editTenantModalEl.classList.add('hidden');
                    });
                    document.getElementById('cancel-edit-tenant-btn').addEventListener('click', () => {
                        editTenantModalEl.classList.add('hidden');
                    });
                    // Close Edit modal when clicking outside
                    editTenantModalEl.addEventListener('click', (e) => {
                        if (e.target.id === 'edit-tenant-modal') {
                            editTenantModalEl.classList.add('hidden');
                        }
                    });
                }

                // Payment modal listeners
                const editPaymentModalEl = document.getElementById('edit-payment-modal');
                if (editPaymentModalEl) {
                    document.getElementById('close-edit-payment-modal').addEventListener('click', () => {
                        editPaymentModalEl.classList.add('hidden');
                    });
                    document.getElementById('cancel-edit-payment-btn').addEventListener('click', () => {
                        editPaymentModalEl.classList.add('hidden');
                    });
                    editPaymentModalEl.addEventListener('click', (e) => {
                        if (e.target.id === 'edit-payment-modal') {
                            editPaymentModalEl.classList.add('hidden');
                        }
                    });
                }

                // Expense modal listeners
                const editExpenseModalEl = document.getElementById('edit-expense-modal');
                if (editExpenseModalEl) {
                    document.getElementById('close-edit-expense-modal').addEventListener('click', () => {
                        editExpenseModalEl.classList.add('hidden');
                    });
                    document.getElementById('cancel-edit-expense-btn').addEventListener('click', () => {
                        editExpenseModalEl.classList.add('hidden');
                    });
                    // Close Edit modal when clicking outside
                    editExpenseModalEl.addEventListener('click', (e) => {
                        if (e.target.id === 'edit-expense-modal') {
                            editExpenseModalEl.classList.add('hidden');
                        }
                    });
                }

                // Property Tenants modal listeners
                const tenantsModalEl = document.getElementById('property-tenants-modal');
                if (tenantsModalEl) {
                    document.getElementById('close-property-tenants-modal').addEventListener('click', () => tenantsModalEl.classList.add('hidden'));
                    document.getElementById('dismiss-property-tenants-btn').addEventListener('click', () => tenantsModalEl.classList.add('hidden'));
                    tenantsModalEl.addEventListener('click', (e) => { if (e.target.id === 'property-tenants-modal') tenantsModalEl.classList.add('hidden'); });
                }
            }

            // --- Form Event Listeners ---
            function attachFormListeners() {
                document.getElementById('add-property-form').addEventListener('submit', handleAddProperty);
                const editFormEl = document.getElementById('edit-property-form');
                if (editFormEl) editFormEl.addEventListener('submit', handleUpdateProperty);
                document.getElementById('add-tenant-form').addEventListener('submit', handleAddTenant);
                const editTenantFormEl = document.getElementById('edit-tenant-form');
                if (editTenantFormEl) editTenantFormEl.addEventListener('submit', handleUpdateTenant);
                const editPaymentFormEl = document.getElementById('edit-payment-form');
                if (editPaymentFormEl) editPaymentFormEl.addEventListener('submit', handleUpdatePayment);
                const editExpenseFormEl = document.getElementById('edit-expense-form');
                if (editExpenseFormEl) editExpenseFormEl.addEventListener('submit', handleUpdateExpense);
                const editCommonExpenseFormEl = document.getElementById('edit-common-expense-form');
                if (editCommonExpenseFormEl) editCommonExpenseFormEl.addEventListener('submit', handleUpdateCommonExpense);
                document.getElementById('add-bedspace-form').addEventListener('submit', handleAddBedspace);
                document.getElementById('add-room-form').addEventListener('submit', handleAddRoom);

                // Toggle between bedspace and room forms
                document.getElementById('show-bedspace-form').addEventListener('click', () => {
                    document.getElementById('add-bedspace-form').classList.remove('hidden');
                    document.getElementById('add-room-form').classList.add('hidden');
                });
                document.getElementById('show-room-form').addEventListener('click', () => {
                    document.getElementById('add-bedspace-form').classList.add('hidden');
                    document.getElementById('add-room-form').classList.remove('hidden');
                });
                document.getElementById('add-payment-form').addEventListener('submit', handleAddPayment);
                const addChequeForm = document.getElementById('add-cheque-form');
                if (addChequeForm) addChequeForm.addEventListener('submit', handleAddCheque);
                const chequeFilterSelect = document.getElementById('cheque-property-filter');
                if (chequeFilterSelect) chequeFilterSelect.addEventListener('change', () => updateChequesList(appData.cheques, appData.properties, appData.tenants));
                document.getElementById('add-expense-form').addEventListener('submit', handleAddExpense);
                document.getElementById('add-common-expense-form').addEventListener('submit', handleAddCommonExpense);
                const commonExpenseCategoryFilter = document.getElementById('common-expense-category-filter');
                if (commonExpenseCategoryFilter) commonExpenseCategoryFilter.addEventListener('change', () => updateCommonExpensesList(appData.common_expenses));
                document.getElementById('generate-report-btn').addEventListener('click', handleGenerateReport);
                const exportBtn = document.getElementById('export-report-btn');
                if (exportBtn) exportBtn.addEventListener('click', exportReportCSV);
                const exportXlsxBtn = document.getElementById('export-report-xlsx-btn');
                if (exportXlsxBtn) exportXlsxBtn.addEventListener('click', exportReportXLSX);
                const settingsForm = document.getElementById('settings-form');
                if (settingsForm) settingsForm.addEventListener('submit', handleSaveSettings);
                const reportPropertySelect = document.getElementById('report-property');
                if (reportPropertySelect) reportPropertySelect.addEventListener('change', () => {
                    const s = document.getElementById('report-start-date').value;
                    const e = document.getElementById('report-end-date').value;
                    if (s && e) { handleGenerateReport(); }
                });
                document.getElementById('tenant-property').addEventListener('change', populateBedspaceDropdownForTenantForm);
                document.getElementById('bedspace-property').addEventListener('change', (e) => updateBedspaceListView(e.target.value, appData.properties));
                document.getElementById('refresh-data').addEventListener('click', fetchAllData);
                document.querySelectorAll('.report-range-btn').forEach(btn => btn.addEventListener('click', handleReportRangeClick));
            }

            // --- Handlers (Properties, Bedspaces, Tenants, Payments) ---
            async function handleAddProperty(e) {
                e.preventDefault();
                const form = e.target;
                const fd = new FormData();
                fd.append('name', document.getElementById('property-name').value);
                fd.append('address', document.getElementById('property-address').value);
                fd.append('email', document.getElementById('property-email').value || '');
                fd.append('whatsapp', document.getElementById('property-whatsapp').value || '');
                fd.append('ownerFullName', document.getElementById('property-owner-fullname').value || '');
                fd.append('mobile', document.getElementById('property-mobile').value || '');
                fd.append('buildingYearlyRent', parseFloat(document.getElementById('property-building-yearly-rent').value) || 0);
                fd.append('totalCharges', parseFloat(document.getElementById('property-total-charges').value) || 0);
                const fileInput = document.getElementById('property-document');
                if (fileInput && fileInput.files && fileInput.files[0]) {
                    fd.append('document', fileInput.files[0]);
                }
                const res = await apiCallMultipart('add_property', fd);
                if (res && res.success) {
                    showToast('Property added successfully!');
                    form.reset();
                    document.getElementById('add-property-modal').classList.add('hidden');
                    fetchAllData();
                }
            }
            function updatePropertiesList(properties) {
                const listEl = document.getElementById('properties-list');
                if (!properties || properties.length === 0) { 
                    listEl.innerHTML = '<div class="text-center py-8"><i class="fas fa-building text-4xl text-gray-300 mb-4"></i><p class="text-gray-500">No properties added yet.</p><p class="text-sm text-gray-400">Click "Add Property" to get started.</p></div>'; 
                    return; 
                }
                listEl.innerHTML = properties.map(p => `
                    <div class="bg-gradient-to-r from-white to-gray-50 border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                        <div class="p-6">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="font-bold text-xl text-indigo-700 mb-2">${p.name}</h3>
                                    <p class="text-gray-600 flex items-center"><i class="fas fa-map-marker-alt mr-2 text-gray-400"></i>${p.address}</p>
                                </div>
                                <div class="flex gap-2">
                                    <button data-id="${p.id}" class="edit-property-btn text-indigo-600 hover:text-indigo-800 p-2 rounded hover:bg-indigo-50 transition-colors" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button data-id="${p.id}" class="delete-property-btn text-red-500 hover:text-red-700 p-2 rounded hover:bg-red-50 transition-colors" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                <div class="bg-white p-4 rounded-lg border">
                                    <h4 class="font-semibold text-sm text-gray-500 uppercase tracking-wider mb-2">Contact Info</h4>
                                    <div class="space-y-2">
                                        <p class="text-sm text-gray-700 flex items-center">
                                            <i class="fas fa-user w-4 mr-2 text-gray-400"></i>
                                            ${p.ownerFullName || 'Not provided'}
                                        </p>
                                        <p class="text-sm text-gray-700 flex items-center">
                                            <i class="fas fa-envelope w-4 mr-2 text-gray-400"></i>
                                            ${p.email || 'Not provided'}
                                        </p>
                                        <p class="text-sm text-gray-700 flex items-center">
                                            <i class="fab fa-whatsapp w-4 mr-2 text-green-500"></i>
                                            ${p.whatsapp || 'Not provided'}
                                        </p>
                                        <p class="text-sm text-gray-700 flex items-center">
                                            <i class="fas fa-phone w-4 mr-2 text-gray-400"></i>
                                            ${p.mobile || 'Not provided'}
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="bg-white p-4 rounded-lg border">
                                    <h4 class="font-semibold text-sm text-gray-500 uppercase tracking-wider mb-2">Financial Info</h4>
                                    <div class="space-y-2">
                                        <p class="text-sm text-gray-700">
                                            <span class="font-medium">Building Yearly Rent:</span>
                                            <span class="text-green-600 font-semibold">${formatCurrency((p.buildingYearlyRent || p.yearlyRent || 0))}</span>
                                        </p>
                                        <p class="text-sm text-gray-700">
                                            <span class="font-medium">Total Charges:</span>
                                            <span class="text-blue-600 font-semibold">${formatCurrency((p.totalCharges || 0))}</span>
                                        </p>
                                        <p class="text-sm text-gray-700">
                                            <span class="font-medium">Document:</span>
                                            ${p.documentPath ? `<a href="${p.documentPath}" target="_blank" class="text-indigo-600 underline">View PDF</a>` : 'Not uploaded'}
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="bg-white p-4 rounded-lg border property-bedspaces-panel cursor-pointer hover:bg-indigo-50 transition" data-property-id="${p.id}">
                                    <h4 class="font-semibold text-sm text-gray-500 uppercase tracking-wider mb-2">Bedspaces (${p.bedspaces?.length || 0})</h4>
                                    ${p.bedspaces && p.bedspaces.length > 0 ? 
                                        `<div class="space-y-1">${p.bedspaces.slice(0, 3).map(b => 
                                            `<p class="text-sm text-gray-600 flex items-center">
                                                <i class="fas ${b.type === 'room' ? 'fa-door-open text-blue-500' : 'fa-bed text-gray-400'} w-3 mr-2"></i>${b.name}
                                            </p>`).join('')}
                                        ${p.bedspaces.length > 3 ? `<p class="text-xs text-gray-500 mt-1">+${p.bedspaces.length - 3} more</p>` : ''}</div>` : 
                                        '<p class="text-sm text-gray-500">No spaces added</p>'
                                    }
                                </div>
                            </div>
                        </div>
                    </div>`).join('');
                document.querySelectorAll('.delete-property-btn').forEach(btn => btn.addEventListener('click', (e) => {
                    const id = e.currentTarget.dataset.id;
                    showConfirmModal(async () => { if (await apiCall('delete_property', { id })) { showToast('Property deleted successfully!'); fetchAllData(); } else { showToast('Failed to delete property.', true); } });
                }));
                document.querySelectorAll('.edit-property-btn').forEach(btn => btn.addEventListener('click', (e) => {
                    const id = e.currentTarget.dataset.id;
                    const property = appData.properties.find(p => p.id == id);
                    if (property) { openEditPropertyModal(property); }
                }));
                // Bedspaces click -> open tenants modal
                document.querySelectorAll('.property-bedspaces-panel').forEach(panel => panel.addEventListener('click', (e) => {
                    const propId = e.currentTarget.getAttribute('data-property-id');
                    if (propId) openPropertyTenantsModal(parseInt(propId, 10));
                }));
            }
            function updatePropertyDropdowns(properties) {
                const selects = [document.getElementById('tenant-property'), document.getElementById('bedspace-property'), document.getElementById('room-property'), document.getElementById('expense-property'), document.getElementById('report-property'), document.getElementById('cheque-property'), document.getElementById('cheque-property-filter'), document.getElementById('edit-expense-property')];
                selects.forEach(select => {
                    const currentValue = select.value;
                    const firstOption = select.firstElementChild.cloneNode(true);
                    select.innerHTML = '';
                    select.appendChild(firstOption);
                    if (!properties) return;
                    properties.forEach(p => {
                        const option = document.createElement('option');
                        option.value = p.id;
                        option.textContent = p.name;
                        if (select.id === 'tenant-property') option.dataset.bedspaces = JSON.stringify(p.bedspaces || []);
                        select.appendChild(option);
                    });
                    select.value = currentValue;
                });
            }

            // --- Edit Property Modal helpers ---
            function openEditPropertyModal(p) {
                // Set values
                document.getElementById('edit-property-id').value = p.id;
                document.getElementById('edit-property-name').value = p.name || '';
                document.getElementById('edit-property-address').value = p.address || '';
                document.getElementById('edit-property-email').value = p.email || '';
                document.getElementById('edit-property-whatsapp').value = p.whatsapp || '';
                document.getElementById('edit-property-owner-fullname').value = p.ownerFullName || '';
                document.getElementById('edit-property-mobile').value = p.mobile || '';
                document.getElementById('edit-property-building-yearly-rent').value = (p.buildingYearlyRent || p.yearlyRent || 0);
                document.getElementById('edit-property-total-charges').value = (p.totalCharges || 0);
                const fileInput = document.getElementById('edit-property-document');
                if (fileInput) { fileInput.value = ''; }
                // Open modal
                document.getElementById('edit-property-modal').classList.remove('hidden');
            }

            async function handleUpdateProperty(e) {
                e.preventDefault();
                const fd = new FormData();
                fd.append('id', document.getElementById('edit-property-id').value);
                fd.append('name', document.getElementById('edit-property-name').value);
                fd.append('address', document.getElementById('edit-property-address').value);
                fd.append('email', document.getElementById('edit-property-email').value || '');
                fd.append('whatsapp', document.getElementById('edit-property-whatsapp').value || '');
                fd.append('ownerFullName', document.getElementById('edit-property-owner-fullname').value || '');
                fd.append('mobile', document.getElementById('edit-property-mobile').value || '');
                fd.append('buildingYearlyRent', parseFloat(document.getElementById('edit-property-building-yearly-rent').value) || 0);
                fd.append('totalCharges', parseFloat(document.getElementById('edit-property-total-charges').value) || 0);
                const fileInput = document.getElementById('edit-property-document');
                if (fileInput && fileInput.files && fileInput.files[0]) {
                    fd.append('document', fileInput.files[0]);
                }
                const res = await apiCallMultipart('update_property', fd);
                if (res && res.success) {
                    showToast('Property updated successfully!');
                    document.getElementById('edit-property-form').reset();
                    document.getElementById('edit-property-modal').classList.add('hidden');
                    fetchAllData();
                }
            }

            // --- Cheques Module ---
            async function handleAddCheque(e) {
                e.preventDefault();
                const body = {
                    propertyId: document.getElementById('cheque-property').value,
                    scheduledAmount: parseFloat(document.getElementById('cheque-amount').value),
                    scheduledDate: document.getElementById('cheque-date').value,
                    chequeNumber: document.getElementById('cheque-number').value,
                    notes: document.getElementById('cheque-notes').value
                };
                const res = await apiCall('add_cheque', body);
                if (res && res.success) {
                    showToast('Cheque scheduled successfully!');
                    e.target.reset();
                    fetchAllData();
                }
            }
            async function setChequeStatus(id, status) {
                const res = await apiCall('update_cheque_status', { id, status });
                if (res && res.success) {
                    showToast('Cheque status updated!');
                    fetchAllData();
                } else {
                    showToast('Failed to update cheque status', true);
                }
            }
            function updateChequesList(cheques, properties, tenants) {
                const container = document.getElementById('cheques-list');
                if (!container) return;
                const filterProp = document.getElementById('cheque-property-filter')?.value || '';
                let list = cheques || [];
                if (filterProp) list = list.filter(c => c.propertyId == filterProp);

                if (list.length === 0) {
                    container.innerHTML = '<p class="text-gray-500">No cheque entries.</p>';
                    return;
                }
                const tenantMap = (tenants || []).reduce((acc, t) => (acc[t.id] = t, acc), {});
                const propertyMap = (properties || []).reduce((acc, p) => (acc[p.id] = p, acc), {});
                container.innerHTML = list.map(c => `
                    <div class="p-4 border rounded-lg bg-white">
                        <div class="flex justify-between items-start">
                            <div>
                                ${c.tenantId ? `<p class="text-sm text-gray-600"><span class="font-medium">Tenant:</span> ${tenantMap[c.tenantId]?.name || 'N/A'}</p>` : ''}
                                <p class="text-sm text-gray-600"><span class="font-medium">Property:</span> ${propertyMap[c.propertyId]?.name || 'N/A'}</p>
                                <p class="text-sm text-gray-600"><span class="font-medium">Cheque #:</span> ${c.chequeNumber || '-'}</p>
                                <p class="text-sm text-gray-600"><span class="font-medium">Scheduled:</span> ${new Date(c.scheduledDate).toLocaleDateString()}</p>
                                <p class="text-sm text-gray-600"><span class="font-medium">Amount:</span> ${formatCurrency(parseFloat(c.scheduledAmount))}</p>
                                ${c.notes ? `<p class="text-xs text-gray-500 mt-1">${c.notes}</p>` : ''}
                            </div>
                            <div class="text-right">
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold ${c.status === 'cleared' ? 'bg-green-100 text-green-700' : c.status === 'bounced' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'}">
                                    ${c.status}
                                </span>
                                <div class="mt-2 flex gap-2">
                                    <button class="text-xs px-2 py-1 rounded bg-blue-100 text-blue-700 hover:bg-blue-200" onclick="setChequeStatus(${c.id}, 'received')">Received</button>
                                    <button class="text-xs px-2 py-1 rounded bg-green-100 text-green-700 hover:bg-green-200" onclick="setChequeStatus(${c.id}, 'cleared')">Cleared</button>
                                    <button class="text-xs px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200" onclick="setChequeStatus(${c.id}, 'bounced')">Bounced</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('');
            }
            async function handleAddBedspace(e) {
                e.preventDefault();
                const body = { 
                    propertyId: document.getElementById('bedspace-property').value, 
                    name: document.getElementById('bedspace-name').value,
                    type: 'bedspace'
                };
                if (await apiCall('add_bedspace', body)) { showToast('Bedspace added!'); e.target.reset(); fetchAllData(); }
            }

            async function handleAddRoom(e) {
                e.preventDefault();
                const body = { 
                    propertyId: document.getElementById('room-property').value, 
                    name: document.getElementById('room-name').value,
                    type: 'room'
                };
                if (await apiCall('add_bedspace', body)) { showToast('Room added!'); e.target.reset(); fetchAllData(); }
            }
            function updateBedspaceListView(propertyId, allProperties) {
                const container = document.getElementById('bedspaces-list-by-property');
                if (!propertyId) { container.innerHTML = '<p class="text-gray-500">Select a property to see its bedspaces.</p>'; return; }
                const property = allProperties.find(p => p.id == propertyId);
                if (!property || !property.bedspaces || property.bedspaces.length === 0) { container.innerHTML = `<p class="text-gray-500">No bedspaces for ${property?.name || 'this property'}.</p>`; return; }
                container.innerHTML = `<h3 class="font-bold text-lg mb-2">${property.name}</h3><div class="space-y-2">${property.bedspaces.map((b, index) => `
                    <div class="p-3 ${b.type === 'room' ? 'bg-blue-50' : 'bg-gray-100'} rounded-md">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <i class="fas ${b.type === 'room' ? 'fa-door-open text-blue-500' : 'fa-bed text-gray-500'} w-4"></i>
                                <p class="font-semibold">${b.name}</p>
                            </div>
                            <div class="flex gap-2">
                                <button onclick="editBedspace(${property.id}, ${index}, '${b.name}', '${b.type}')" class="text-indigo-500 hover:text-indigo-700 p-1 rounded hover:bg-indigo-50 transition-colors" title="Edit Space">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deleteBedspace(${property.id}, ${index})" class="text-red-500 hover:text-red-700 p-1 rounded hover:bg-red-50 transition-colors" title="Delete Space">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>`).join('')}</div>`;
            }
            function openPropertyTenantsModal(propertyId) {
                const modal = document.getElementById('property-tenants-modal');
                const title = document.getElementById('property-tenants-title');
                const list = document.getElementById('property-tenants-list');
                const property = (appData.properties || []).find(p => p.id == propertyId);
                title.textContent = property ? `Tenants – ${property.name}` : 'Tenants';
                const tenants = (appData.tenants || []).filter(t => t.propertyId == propertyId);
                if (!tenants.length) {
                    list.innerHTML = '<p class="text-gray-500">No tenants in this property.</p>';
                } else {
                    list.innerHTML = tenants.map(t => `
                        <div class="flex justify-between items-center p-3 border rounded-lg bg-white">
                            <div>
                                <p class="font-semibold text-gray-800">${t.name}</p>
                                <p class="text-xs text-gray-500">Bedspace/Room: ${t.bedspace || '-'}</p>
                                <p class="text-xs text-gray-500">Rent: ${formatCurrency(parseFloat(t.rentAmount || 0))}</p>
                            </div>
                            <div class="text-right text-sm text-gray-600">
                                ${t.phone ? `<a href="tel:${t.phone}" class="text-indigo-600 hover:underline">Call</a>` : ''}
                            </div>
                        </div>
                    `).join('');
                }
                modal.classList.remove('hidden');
            }
            function populateBedspaceDropdownForTenantForm() {
                const propertySelect = document.getElementById('tenant-property');
                const bedspaceSelect = document.getElementById('tenant-bedspace');
                const propertyId = propertySelect.value;
                bedspaceSelect.innerHTML = '<option value="">Select a bedspace or room</option>';
                if (!propertyId) return;
                const property = appData.properties.find(p => p.id == propertyId);
                if (!property) return;
                const allBedspaceNames = (property.bedspaces || []).map(b => b.name);
                const occupiedBedspaces = appData.tenants.filter(t => t.propertyId == propertyId).map(t => t.bedspace);
                const availableBedspaces = allBedspaceNames.filter(bName => !occupiedBedspaces.includes(bName));
                if (availableBedspaces.length > 0) {
                    availableBedspaces.forEach(bName => { bedspaceSelect.innerHTML += `<option value="${bName}">${bName}</option>`; });
                } else {
                    bedspaceSelect.innerHTML = '<option value="">No available bedspaces or rooms</option>';
                }
            }
            async function handleAddTenant(e) {
                e.preventDefault();
                const body = { 
                    name: document.getElementById('tenant-name').value, 
                    email: document.getElementById('tenant-email').value, 
                    phone: document.getElementById('tenant-phone').value,
                    idNumber: document.getElementById('tenant-id-number').value,
                    propertyId: document.getElementById('tenant-property').value, 
                    bedspace: document.getElementById('tenant-bedspace').value, 
                    rentAmount: parseFloat(document.getElementById('tenant-rent-amount').value), 
                    rentDueDate: parseInt(document.getElementById('tenant-rent-due-date').value),
                    securityDeposit: parseFloat(document.getElementById('tenant-security-deposit').value) || 0,
                    contractStartDate: document.getElementById('tenant-contract-start').value,
                    contractEndDate: document.getElementById('tenant-contract-end').value
                };
                if (await apiCall('add_tenant', body)) { showToast('Tenant added!'); e.target.reset(); fetchAllData(); }
            }
            function updateTenantsList(tenants, properties) {
                const listEl = document.getElementById('tenants-list');
                if (!tenants || tenants.length === 0) { listEl.innerHTML = '<p class="text-gray-500">No tenants added yet.</p>'; return; }
                const propertyMap = properties.reduce((acc, p) => ({...acc, [p.id]: p }), {});
                listEl.innerHTML = tenants.map(t => `<div class="p-4 border rounded-lg">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="font-bold">${t.name}</h3>
                            <p class="text-sm text-gray-600">${t.email}</p>
                            <p class="text-sm text-gray-500">ID/Passport: ${t.idNumber || 'Not provided'}</p>
                            <p class="text-sm text-gray-500">Property: ${propertyMap[t.propertyId]?.name || 'N/A'}</p>
                            <p class="text-sm text-gray-500">Space: ${t.bedspace}</p>
                            <p class="text-sm text-gray-500">Rent: ${formatCurrency(parseFloat(t.rentAmount))} (Due on day ${t.rentDueDate})</p>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="editTenant(${t.id}, '${t.name}', '${t.email}', '${t.phone || ''}', '${t.idNumber || ''}', ${t.propertyId}, '${t.bedspace}', ${t.rentAmount}, ${t.rentDueDate}, ${t.securityDeposit || 0}, '${t.contractStartDate || ''}', '${t.contractEndDate || ''}')" class="text-indigo-500 hover:text-indigo-700 p-2 rounded hover:bg-indigo-50 transition-colors" title="Edit Tenant">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteTenant(${t.id})" class="text-red-500 hover:text-red-700 p-2 rounded hover:bg-red-50 transition-colors" title="Delete Tenant">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>`).join('');
            }
            function updateTenantDropdowns(tenants) {
                const select = document.getElementById('payment-tenant');
                const currentValue = select.value;
                select.innerHTML = '<option value="">Select a tenant</option>';
                if (!tenants) return;
                tenants.forEach(t => { select.innerHTML += `<option value="${t.id}">${t.name}</option>`; });
                select.value = currentValue;
            }
            async function handleAddPayment(e) {
                e.preventDefault();
                const body = {
                    tenantId: document.getElementById('payment-tenant').value,
                    amount: parseFloat(document.getElementById('payment-amount').value),
                    date: document.getElementById('payment-date').value,
                    paymentMethod: (document.getElementById('payment-method')?.value || 'cash')
                };
                if (await apiCall('add_payment', body)) { showToast('Payment recorded!'); e.target.reset(); fetchAllData(); }
            }
            async function handleUpdatePayment(e) {
                e.preventDefault();
                const body = {
                    id: document.getElementById('edit-payment-id').value,
                    tenantId: document.getElementById('edit-payment-tenant').value,
                    amount: parseFloat(document.getElementById('edit-payment-amount').value),
                    date: document.getElementById('edit-payment-date').value,
                    paymentMethod: document.getElementById('edit-payment-method').value,
                    notes: document.getElementById('edit-payment-notes').value
                };
                const res = await apiCall('update_payment', body);
                if (res && res.success) {
                    showToast('Payment updated');
                    document.getElementById('edit-payment-form').reset();
                    document.getElementById('edit-payment-modal').classList.add('hidden');
                    fetchAllData();
                } else {
                    showToast('Failed to update payment', true);
                }
            }
            function updatePaymentsList(payments, tenants, properties) {
                const listEl = document.getElementById('all-payments-list');
                if (!payments || payments.length === 0) { listEl.innerHTML = '<p class="text-gray-500">No payments recorded yet.</p>'; return; }
                const tenantMap = tenants.reduce((acc, t) => ({...acc, [t.id]: t }), {});
                const propertyMap = properties.reduce((acc, p) => ({...acc, [p.id]: p }), {});
                listEl.innerHTML = payments.map(p => `
                    <div class="p-4 border rounded-lg">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-bold text-lg text-green-600">${formatCurrency(parseFloat(p.amount))}</h3>
                                <p class="text-sm text-gray-700">From: <span class="font-semibold">${tenantMap[p.tenantId]?.name || 'N/A'}</span></p>
                                <p class="text-xs text-gray-500">Property: ${propertyMap[p.propertyId]?.name || 'N/A'}</p>
                                <p class="text-xs text-gray-500">Method: ${p.paymentMethod || 'cash'}</p>
                            </div>
                            <div class="text-right">
                                <span class="block text-sm text-gray-500 mb-2">${new Date(p.paymentDate).toLocaleDateString()}</span>
                                <div class="flex gap-2 justify-end">
                                    <button class="text-indigo-500 hover:text-indigo-700 p-2 rounded hover:bg-indigo-50" title="Edit" onclick="editPayment(${p.id})"><i class="fas fa-edit"></i></button>
                                    <button class="text-red-500 hover:text-red-700 p-2 rounded hover:bg-red-50" title="Delete" onclick="deletePayment(${p.id})"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>`).join('');
            }
            // Payments: edit/delete helpers
            function editPayment(id) {
                const p = (appData.payments || []).find(x => x.id == id);
                if (!p) { showToast('Payment not found', true); return; }
                document.getElementById('edit-payment-id').value = p.id;
                const tSel = document.getElementById('edit-payment-tenant');
                // populate tenant dropdown
                tSel.innerHTML = '<option value="">Select a tenant</option>';
                (appData.tenants || []).forEach(t => {
                    const opt = document.createElement('option');
                    opt.value = t.id; opt.textContent = t.name; tSel.appendChild(opt);
                });
                tSel.value = p.tenantId;
                document.getElementById('edit-payment-amount').value = parseFloat(p.amount || 0);
                document.getElementById('edit-payment-date').value = (p.paymentDate || '').slice(0,10);
                document.getElementById('edit-payment-method').value = p.paymentMethod || 'cash';
                document.getElementById('edit-payment-notes').value = p.notes || '';
                document.getElementById('edit-payment-modal').classList.remove('hidden');
            }
            async function deletePayment(id) {
                showConfirmModal(async () => {
                    const res = await apiCall('delete_payment', { id });
                    if (res && res.success) { showToast('Payment deleted'); fetchAllData(); }
                    else { showToast('Failed to delete payment', true); }
                });
            }
            function updateRecentPayments(payments, tenants) {
                const listEl = document.getElementById('recent-payments-list');
                if (!payments || payments.length === 0) { listEl.innerHTML = '<p class="text-gray-500">No recent payments.</p>'; return; }
                const tenantMap = tenants.reduce((acc, t) => ({...acc, [t.id]: t }), {});
                listEl.innerHTML = payments.slice(0, 5).map(p => `<div class="flex justify-between items-center p-2 rounded-lg hover:bg-gray-50"><div><p class="font-semibold">${tenantMap[p.tenantId]?.name || 'Unknown'}</p><p class="text-sm text-gray-500">Paid on ${new Date(p.paymentDate).toLocaleDateString()}</p></div><p class="font-bold text-green-600">${formatCurrency(parseFloat(p.amount))}</p></div>`).join('');
            }

            // Tenant management functions
            function editTenant(id, name, email, phone, idNumber, propertyId, bedspace, rentAmount, rentDueDate, securityDeposit, contractStartDate, contractEndDate) {
                // Populate the edit tenant form
                document.getElementById('edit-tenant-id').value = id;
                document.getElementById('edit-tenant-name').value = name || '';
                document.getElementById('edit-tenant-email').value = email || '';
                document.getElementById('edit-tenant-phone').value = phone || '';
                document.getElementById('edit-tenant-id-number').value = idNumber || '';
                document.getElementById('edit-tenant-rent-amount').value = rentAmount || 0;
                document.getElementById('edit-tenant-rent-due-date').value = rentDueDate || 1;
                document.getElementById('edit-tenant-security-deposit').value = securityDeposit || 0;
                document.getElementById('edit-tenant-contract-start').value = contractStartDate || '';
                document.getElementById('edit-tenant-contract-end').value = contractEndDate || '';
                
                // Show the modal
                document.getElementById('edit-tenant-modal').classList.remove('hidden');
            }
            
            async function handleUpdateTenant(e) {
                e.preventDefault();
                
                const body = {
                    id: document.getElementById('edit-tenant-id').value,
                    name: document.getElementById('edit-tenant-name').value,
                    email: document.getElementById('edit-tenant-email').value,
                    phone: document.getElementById('edit-tenant-phone').value,
                    idNumber: document.getElementById('edit-tenant-id-number').value,
                    rentAmount: parseFloat(document.getElementById('edit-tenant-rent-amount').value),
                    rentDueDate: parseInt(document.getElementById('edit-tenant-rent-due-date').value),
                    securityDeposit: parseFloat(document.getElementById('edit-tenant-security-deposit').value) || 0,
                    contractStartDate: document.getElementById('edit-tenant-contract-start').value,
                    contractEndDate: document.getElementById('edit-tenant-contract-end').value
                };
                
                const res = await apiCall('update_tenant', body);
                if (res && res.success) {
                    showToast('Tenant updated successfully');
                    document.getElementById('edit-tenant-modal').classList.add('hidden');
                    fetchAllData();
                } else {
                    showToast('Failed to update tenant', true);
                }
            }
            
            async function deleteTenant(id) {
                if (confirm('Are you sure you want to delete this tenant? This action cannot be undone.')) {
                    const res = await apiCall('delete_tenant', { id });
                    if (res && res.success) {
                        showToast('Tenant deleted successfully');
                        fetchAllData();
                    } else {
                        showToast('Failed to delete tenant', true);
                    }
                }
            }

            // Bedspace management functions
            async function editBedspace(propertyId, bedspaceIndex, currentName, currentType) {
                const newName = prompt('Enter new name for the space:', currentName);
                if (newName && newName !== currentName) {
                    const res = await apiCall('edit_bedspace', { 
                        propertyId, 
                        bedspaceIndex,
                        name: newName,
                        type: currentType
                    });
                    if (res && res.success) {
                        showToast('Space updated successfully');
                        fetchAllData();
                    } else {
                        showToast('Failed to update space', true);
                    }
                }
            }

            async function deleteBedspace(propertyId, bedspaceIndex) {
                if (confirm('Are you sure you want to delete this space? If it is currently occupied by a tenant, deletion will not be allowed.')) {
                    const res = await apiCall('delete_bedspace', { propertyId, bedspaceIndex });
                    if (res && res.success) {
                        showToast('Space deleted successfully');
                        fetchAllData();
                    } else {
                        showToast('Failed to delete space. It may be currently occupied by a tenant.', true);
                    }
                }
            }
            
            // --- Handlers (Expenses) ---
            async function handleAddExpense(e) {
                e.preventDefault();
                const body = { 
                    description: document.getElementById('expense-description').value, 
                    amount: parseFloat(document.getElementById('expense-amount').value), 
                    date: document.getElementById('expense-date').value, 
                    category: document.getElementById('expense-category').value,
                    propertyId: document.getElementById('expense-property').value,
                    vendorName: document.getElementById('expense-vendor').value,
                    invoiceNumber: document.getElementById('expense-invoice').value,
                    notes: document.getElementById('expense-notes').value
                };
                if (await apiCall('add_expense', body)) { showToast('Expense recorded!'); e.target.reset(); fetchAllData(); }
            }
            
            // Expense management functions
            function editExpense(id, description, amount, expenseDate, category, propertyId, vendorName, invoiceNumber, notes) {
                // Populate the edit expense form
                document.getElementById('edit-expense-id').value = id;
                document.getElementById('edit-expense-description').value = description || '';
                document.getElementById('edit-expense-amount').value = amount || 0;
                document.getElementById('edit-expense-date').value = expenseDate || '';
                document.getElementById('edit-expense-category').value = category || 'general';
                document.getElementById('edit-expense-property').value = propertyId || '';
                document.getElementById('edit-expense-vendor').value = vendorName || '';
                document.getElementById('edit-expense-invoice').value = invoiceNumber || '';
                document.getElementById('edit-expense-notes').value = notes || '';
                
                // Show the modal
                document.getElementById('edit-expense-modal').classList.remove('hidden');
            }
            
            async function handleUpdateExpense(e) {
                e.preventDefault();
                
                const body = {
                    id: document.getElementById('edit-expense-id').value,
                    description: document.getElementById('edit-expense-description').value,
                    amount: parseFloat(document.getElementById('edit-expense-amount').value),
                    date: document.getElementById('edit-expense-date').value,
                    category: document.getElementById('edit-expense-category').value,
                    propertyId: document.getElementById('edit-expense-property').value || null,
                    vendorName: document.getElementById('edit-expense-vendor').value,
                    invoiceNumber: document.getElementById('edit-expense-invoice').value,
                    notes: document.getElementById('edit-expense-notes').value
                };
                
                const res = await apiCall('update_expense', body);
                if (res && res.success) {
                    showToast('Expense updated successfully');
                    document.getElementById('edit-expense-modal').classList.add('hidden');
                    fetchAllData();
                } else {
                    showToast('Failed to update expense', true);
                }
            }
            
            async function deleteExpense(id) {
                if (confirm('Are you sure you want to delete this expense? This action cannot be undone.')) {
                    const res = await apiCall('delete_expense', { id });
                    if (res && res.success) {
                        showToast('Expense deleted successfully');
                        fetchAllData();
                    } else {
                        showToast('Failed to delete expense', true);
                    }
                }
            }
            
            // Common Expense management functions
            async function handleAddCommonExpense(e) {
                e.preventDefault();
                const body = {
                    description: document.getElementById('common-expense-description').value,
                    amount: parseFloat(document.getElementById('common-expense-amount').value),
                    date: document.getElementById('common-expense-date').value,
                    category: document.getElementById('common-expense-category').value,
                    vendorName: document.getElementById('common-expense-vendor').value,
                    invoiceNumber: document.getElementById('common-expense-invoice').value,
                    notes: document.getElementById('common-expense-notes').value
                };
                if (await apiCall('add_common_expense', body)) { showToast('Common expense recorded!'); e.target.reset(); fetchAllData(); }
            }
            
            function updateCommonExpensesList(commonExpenses) {
                const listEl = document.getElementById('common-expenses-list');
                if (!commonExpenses || commonExpenses.length === 0) { listEl.innerHTML = '<p class="text-gray-500">No common expenses recorded yet.</p>'; return; }
                
                const filterCategory = document.getElementById('common-expense-category-filter')?.value || '';
                let list = commonExpenses || [];
                if (filterCategory) list = list.filter(ex => ex.category === filterCategory);
                
                if (list.length === 0) {
                    listEl.innerHTML = '<p class="text-gray-500">No expenses found for the selected category.</p>';
                    return;
                }
                
                listEl.innerHTML = list.map(ex => `
                    <div class="p-4 border rounded-lg">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h3 class="font-bold text-lg text-orange-600">${formatCurrency(parseFloat(ex.amount))}</h3>
                                <p class="text-sm text-gray-700">${ex.description}</p>
                                <p class="text-xs text-gray-500">Category: ${ex.category}</p>
                                ${ex.vendorName ? `<p class="text-xs text-gray-500">Vendor: ${ex.vendorName}</p>` : ''}
                                ${ex.invoiceNumber ? `<p class="text-xs text-gray-500">Invoice: ${ex.invoiceNumber}</p>` : ''}
                                ${ex.notes ? `<p class="text-xs text-gray-500 mt-1">${ex.notes}</p>` : ''}
                            </div>
                            <div class="flex flex-col items-end gap-2">
                                <span class="text-sm text-gray-500">${new Date(ex.expenseDate).toLocaleDateString()}</span>
                                <div class="flex gap-2">
                                    <button onclick="editCommonExpense(${ex.id}, '${ex.description.replace(/'/g, "\\'")}', ${ex.amount}, '${ex.expenseDate}', '${ex.category || ''}', '${(ex.vendorName || '').replace(/'/g, "\\'")}', '${(ex.invoiceNumber || '').replace(/'/g, "\\'")}', '${(ex.notes || '').replace(/'/g, "\\'")}')" class="text-indigo-500 hover:text-indigo-700 p-2 rounded hover:bg-indigo-50 transition-colors" title="Edit Common Expense">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteCommonExpense(${ex.id})" class="text-red-500 hover:text-red-700 p-2 rounded hover:bg-red-50 transition-colors" title="Delete Common Expense">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('');
            }
            
            function editCommonExpense(id, description, amount, expenseDate, category, vendorName, invoiceNumber, notes) {
                // Populate the edit common expense form
                document.getElementById('edit-common-expense-id').value = id;
                document.getElementById('edit-common-expense-description').value = description || '';
                document.getElementById('edit-common-expense-amount').value = amount || 0;
                document.getElementById('edit-common-expense-date').value = expenseDate || '';
                document.getElementById('edit-common-expense-category').value = category || 'general';
                document.getElementById('edit-common-expense-vendor').value = vendorName || '';
                document.getElementById('edit-common-expense-invoice').value = invoiceNumber || '';
                document.getElementById('edit-common-expense-notes').value = notes || '';
                
                // Show the modal
                document.getElementById('edit-common-expense-modal').classList.remove('hidden');
            }
            
            async function handleUpdateCommonExpense(e) {
                e.preventDefault();
                
                const body = {
                    id: document.getElementById('edit-common-expense-id').value,
                    description: document.getElementById('edit-common-expense-description').value,
                    amount: parseFloat(document.getElementById('edit-common-expense-amount').value),
                    date: document.getElementById('edit-common-expense-date').value,
                    category: document.getElementById('edit-common-expense-category').value,
                    vendorName: document.getElementById('edit-common-expense-vendor').value,
                    invoiceNumber: document.getElementById('edit-common-expense-invoice').value,
                    notes: document.getElementById('edit-common-expense-notes').value
                };
                
                const res = await apiCall('update_common_expense', body);
                if (res && res.success) {
                    showToast('Common expense updated successfully');
                    document.getElementById('edit-common-expense-modal').classList.add('hidden');
                    fetchAllData();
                } else {
                    showToast('Failed to update common expense', true);
                }
            }
            
            async function deleteCommonExpense(id) {
                if (confirm('Are you sure you want to delete this common expense? This action cannot be undone.')) {
                    const res = await apiCall('delete_common_expense', { id });
                    if (res && res.success) {
                        showToast('Common expense deleted successfully');
                        fetchAllData();
                    } else {
                        showToast('Failed to delete common expense', true);
                    }
                }
            }
            
            function updateExpensesList(expenses, properties) {
                const listEl = document.getElementById('all-expenses-list');
                if (!expenses || expenses.length === 0) { listEl.innerHTML = '<p class="text-gray-500">No expenses recorded yet.</p>'; return; }
                const propertyMap = properties.reduce((acc, p) => ({...acc, [p.id]: p }), {});
                listEl.innerHTML = expenses.map(ex => `
                    <div class="p-4 border rounded-lg">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h3 class="font-bold text-lg text-red-600">${formatCurrency(parseFloat(ex.amount))}</h3>
                                <p class="text-sm text-gray-700">${ex.description}</p>
                                ${ex.propertyId ? `<p class="text-xs text-gray-500">Property: ${propertyMap[ex.propertyId]?.name || 'N/A'}</p>` : ''}
                                ${ex.category ? `<p class="text-xs text-gray-500">Category: ${ex.category}</p>` : ''}
                                ${ex.vendorName ? `<p class="text-xs text-gray-500">Vendor: ${ex.vendorName}</p>` : ''}
                                ${ex.invoiceNumber ? `<p class="text-xs text-gray-500">Invoice: ${ex.invoiceNumber}</p>` : ''}
                                ${ex.notes ? `<p class="text-xs text-gray-500 mt-1">${ex.notes}</p>` : ''}
                            </div>
                            <div class="flex flex-col items-end gap-2">
                                <span class="text-sm text-gray-500">${new Date(ex.expenseDate).toLocaleDateString()}</span>
                                <div class="flex gap-2">
                                    <button onclick="editExpense(${ex.id}, '${ex.description.replace(/'/g, "\\'")}', ${ex.amount}, '${ex.expenseDate}', '${ex.category || ''}', ${ex.propertyId || 'null'}, '${(ex.vendorName || '').replace(/'/g, "\\'")}', '${(ex.invoiceNumber || '').replace(/'/g, "\\'")}', '${(ex.notes || '').replace(/'/g, "\\'")}')" class="text-indigo-500 hover:text-indigo-700 p-2 rounded hover:bg-indigo-50 transition-colors" title="Edit Expense">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteExpense(${ex.id})" class="text-red-500 hover:text-red-700 p-2 rounded hover:bg-red-50 transition-colors" title="Delete Expense">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('');
            }

            // --- Handlers (Reports & Reminders) ---
            function updateRentRemindersList(reminders, properties, elementId) {
                const listEl = document.getElementById(elementId);
                if (!reminders || reminders.length === 0) { 
                    listEl.innerHTML = `<p class="text-gray-500">No upcoming or overdue rent.</p>`; 
                    return; 
                }
                
                const propertyMap = properties.reduce((acc, p) => ({...acc, [p.id]: p }), {});
                listEl.innerHTML = reminders.map(t => {
                    let statusColor;
                    if (t.status === 'Overdue') { 
                        statusColor = 'text-red-600 bg-red-50 border-red-200'; 
                    } else if (t.status === 'Due Today') { 
                        statusColor = 'text-yellow-600 bg-yellow-50 border-yellow-200'; 
                    } else { 
                        statusColor = 'text-blue-600 bg-blue-50 border-blue-200'; 
                    }
                    
                    return `
                        <div class="p-4 border rounded-lg ${statusColor}">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h4 class="font-bold text-gray-900">${t.name}</h4>
                                    <p class="text-sm text-gray-600 mt-1">
                                        <span class="font-medium">Property:</span> ${t.propertyName || 'N/A'}
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        <span class="font-medium">Address:</span> ${t.propertyAddress || 'N/A'}
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        <span class="font-medium">Phone:</span> ${t.phone || 'N/A'}
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        <span class="font-medium">Amount:</span> ${formatCurrency(parseFloat(t.monthlyRent))}
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        <span class="font-medium">Due Date:</span> ${t.rentDueDate}${getOrdinalSuffix(t.rentDueDate)} of each month
                                    </p>
                                </div>
                                <div class="text-right ml-4">
                                    <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold ${statusColor}">
                                        ${t.status}
                                    </span>
                                    <p class="text-sm mt-2 font-medium">
                                        ${t.days_info}
                                    </p>
                                </div>
                            </div>
                            <div class="mt-3 flex gap-2">
                                <button onclick="callTenant('${t.phone}')" class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded hover:bg-green-200">
                                    📞 Call
                                </button>
                                <button onclick="sendWhatsAppReminder('${t.phone}', '${t.name}', '${t.monthlyRent}', '${t.status}', '${t.propertyName}')" class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded hover:bg-green-200">
                                    💬 WhatsApp
                                </button>
                                <button onclick="markRentPaid(${t.id})" class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded hover:bg-blue-200">
                                    ✅ Mark as Paid
                                </button>
                            </div>
                        </div>
                    `;
                }).join('');
            }
            
            function getOrdinalSuffix(day) {
                if (day >= 11 && day <= 13) return 'th';
                switch (day % 10) {
                    case 1: return 'st';
                    case 2: return 'nd';
                    case 3: return 'rd';
                    default: return 'th';
                }
            }
            
            function callTenant(phone) {
                if (phone && phone !== 'N/A') {
                    window.open(`tel:${phone}`);
                } else {
                    showToast('Phone number not available', true);
                }
            }
            
            function markRentPaid(tenantId) {
                if (confirm('Are you sure you want to mark this rent as paid?')) {
                    // This would typically open a payment form or mark as paid
                    showToast('This feature will open payment form (to be implemented)', false);
                }
            }
            
            function sendWhatsAppReminder(phone, tenantName, rentAmount, status, propertyName) {
                if (!phone || phone === 'N/A') {
                    showToast('Phone number not available', true);
                    return;
                }
                
                // Clean phone number and add country code
                let cleanPhone = phone.replace(/[^\d+]/g, '');
                
                // Add country codes for UAE and India numbers
                if (cleanPhone.startsWith('05')) {
                    cleanPhone = '971' + cleanPhone.substring(1); // UAE country code
                } else if (cleanPhone.startsWith('98')) {
                    cleanPhone = '91' + cleanPhone; // India country code
                } else if (!cleanPhone.startsWith('971') && !cleanPhone.startsWith('91') && !cleanPhone.startsWith('+')) {
                    // If no country code detected, assume UAE
                    cleanPhone = '971' + cleanPhone;
                }
                
                // Create personalized message based on status
                let message = '';
                const currentDate = new Date().toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                
                if (status === 'Due Today') {
                    message = `🏠 *Rent Reminder*\n\nDear ${tenantName},\n\nThis is a friendly reminder that your rent for ${propertyName} is due today (${currentDate}).\n\n💰 Amount: AED ${parseFloat(rentAmount).toFixed(2)}\n\nPlease arrange payment at your earliest convenience.\n\nThank you!\n\n*Property Management Team*`;
                } else if (status === 'Overdue') {
                    message = `🚨 *URGENT: Overdue Rent Notice*\n\nDear ${tenantName},\n\nYour rent payment for ${propertyName} is now overdue.\n\n💰 Amount Due: AED ${parseFloat(rentAmount).toFixed(2)}\n📅 Status: ${status}\n\nPlease contact us immediately to arrange payment.\n\nThank you!\n\n*Property Management Team*`;
                } else {
                    message = `🏠 *Upcoming Rent Reminder*\n\nDear ${tenantName},\n\nYour rent for ${propertyName} will be due soon.\n\n💰 Amount: AED ${parseFloat(rentAmount).toFixed(2)}\n📅 Status: ${status}\n\nPlease prepare for the upcoming payment.\n\nThank you!\n\n*Property Management Team*`;
                }
                
                // Encode message for URL
                const encodedMessage = encodeURIComponent(message);
                
                // Create WhatsApp URL
                const whatsappUrl = `https://wa.me/${cleanPhone}?text=${encodedMessage}`;
                
                // Open WhatsApp
                window.open(whatsappUrl, '_blank');
                
                showToast(`WhatsApp reminder sent to ${tenantName} (+${cleanPhone})`, false);
            }
            async function handleGenerateReport() {
                const startDate = document.getElementById('report-start-date').value;
                const endDate = document.getElementById('report-end-date').value;
                const propertyId = document.getElementById('report-property') ? document.getElementById('report-property').value : '';
                if (!startDate || !endDate) { showToast('Please select both a start and end date.', true); return; }
                
                const payload = { startDate, endDate, propertyId };
                const reportData = await apiCall('get_report', payload);
                if (reportData) {
                    lastReportData = reportData;
                    lastReportFilters = payload;

                    document.getElementById('report-summary').style.display = 'grid';
                    document.getElementById('report-details').style.display = 'grid';
                    // Use pre-calculated values from the API
                    const {
                        openingBalance,
                        totalRevenue,
                        totalPropertyExpenses,
                        totalCommonExpenses,
                        netProfit
                    } = reportData;

                    const totalExpenses = totalPropertyExpenses + totalCommonExpenses;

                    document.getElementById('report-opening-balance').textContent = formatCurrency(openingBalance);
                    document.getElementById('report-total-revenue').textContent = formatCurrency(totalRevenue);
                    document.getElementById('report-total-expenses').textContent = formatCurrency(totalExpenses);
                    document.getElementById('report-net-profit').textContent = formatCurrency(netProfit);
                    document.getElementById('report-income-list').innerHTML = reportData.income.length > 0 ? reportData.income.map(p => {
                        const payer = (p.tenantName || '').toString();
                        const payerText = payer ? ` (${payer})` : '';
                        return `<div class="text-sm p-2 bg-gray-50 rounded">${formatCurrency(parseFloat(p.amount))} on ${new Date(p.paymentDate).toLocaleDateString()}${payerText}</div>`;
                    }).join('') : '<p class="text-gray-500">No income in this period.</p>';
                    
                    // Display expenses with separate sections for property and common expenses
                    let expenseListHTML = '';
                    if (reportData.expenses && reportData.expenses.length > 0) {
                        const propertyExpenses = reportData.expenses.filter(ex => ex.expenseType === 'property' || !ex.expenseType);
                        const commonExpenses = reportData.expenses.filter(ex => ex.expenseType === 'common');
                        
                        if (propertyExpenses.length > 0) {
                            expenseListHTML += '<h4 class="text-sm font-semibold text-gray-700 mb-2">Property Expenses</h4>';
                            expenseListHTML += propertyExpenses.map(ex => `<div class="text-sm p-2 bg-gray-50 rounded mb-1">${formatCurrency(parseFloat(ex.amount))} - ${ex.description} ${ex.propertyName ? `(${ex.propertyName})` : ''} on ${new Date(ex.expenseDate).toLocaleDateString()}</div>`).join('');
                        }
                        
                        if (commonExpenses.length > 0) {
                            if (propertyExpenses.length > 0) expenseListHTML += '<div class="mt-3"></div>';
                            expenseListHTML += '<h4 class="text-sm font-semibold text-gray-700 mb-2">Common Expenses</h4>';
                            expenseListHTML += commonExpenses.map(ex => `<div class="text-sm p-2 bg-orange-50 rounded mb-1">${formatCurrency(parseFloat(ex.amount))} - ${ex.description} on ${new Date(ex.expenseDate).toLocaleDateString()}</div>`).join('');
                        }
                    } else {
                        expenseListHTML = '<p class="text-gray-500">No expenses in this period.</p>';
                    }
                    document.getElementById('report-expense-list').innerHTML = expenseListHTML;
                }
            }
            function handleReportRangeClick(e) {
                const range = e.target.dataset.range;
                const now = new Date();
                let start, end;
                const formatDate = (date) => date.toISOString().split('T')[0];

                if (range === 'this-month') {
                    start = new Date(now.getFullYear(), now.getMonth(), 1);
                    end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                } else if (range === 'last-month') {
                    start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                    end = new Date(now.getFullYear(), now.getMonth(), 0);
                } else if (range === 'last-3-months') {
                    start = new Date(now.getFullYear(), now.getMonth() - 2, 1);
                    end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                }
                
                document.getElementById('report-start-date').value = formatDate(start);
                document.getElementById('report-end-date').value = formatDate(end);
                handleGenerateReport();
            }

            function exportReportCSV() {
                if (!lastReportData) {
                    const s = document.getElementById('report-start-date').value;
                    const e = document.getElementById('report-end-date').value;
                    if (!s || !e) {
                        showToast('Generate a report first or select dates.', true);
                        return;
                    }
                    // Auto-generate then export
                    handleGenerateReport().then(() => exportReportCSV());
                    return;
                }

                const rows = [];
                // Header
                rows.push(['Type','Date','Amount','PropertyId','PropertyName','TenantId','TenantName','Category/Method','Description','Vendor/Payee','Invoice/Cheque','Notes','ExpenseType']);

                // Income rows (payments)
                (lastReportData.income || []).forEach(p => {
                    rows.push([
                        'Income',
                        p.paymentDate || '',
                        parseFloat(p.amount || 0).toFixed(2),
                        p.propertyId || '',
                        p.propertyName || '',
                        p.tenantId || '',
                        p.tenantName || '',
                        p.paymentMethod || '',
                        (p.description || ''),
                        '', // Vendor/Payee not applicable here
                        p.chequeNumber || '',
                        p.notes || '',
                        '' // ExpenseType not applicable for income
                    ]);
                });

                // Expense rows (includes both property and common expenses)
                (lastReportData.expenses || []).forEach(ex => {
                    rows.push([
                        'Expense',
                        ex.expenseDate || '',
                        parseFloat(ex.amount || 0).toFixed(2),
                        ex.propertyId || '',
                        ex.propertyName || '',
                        '', // TenantId
                        '', // TenantName
                        ex.category || '',
                        ex.description || '',
                        ex.vendorName || '',
                        ex.invoiceNumber || '',
                        ex.notes || '',
                        ex.expenseType || 'property'
                    ]);
                });

                // Convert to CSV
                const csv = rows.map(r => r.map(field => {
                    const val = (field ?? '').toString();
                    return /[",\n]/.test(val) ? `"${val.replace(/"/g, '""')}"` : val;
                }).join(',')).join('\n');

                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                const s = lastReportFilters?.startDate || 'start';
                const e = lastReportFilters?.endDate || 'end';
                const pid = lastReportFilters?.propertyId ? `property-${lastReportFilters.propertyId}` : 'all-properties';
                a.href = url;
                a.download = `financial-report_${pid}_${s}_${e}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                showToast('Report exported as CSV');
            }
            
            function exportReportXLSX() {
                if (!lastReportData) {
                    const s = document.getElementById('report-start-date').value;
                    const e = document.getElementById('report-end-date').value;
                    if (!s || !e) {
                        showToast('Generate a report first or select dates.', true);
                        return;
                    }
                    // Auto-generate then export
                    handleGenerateReport().then(() => exportReportXLSX());
                    return;
                }

                // Build Income sheet
                const incomeRows = [
                    ['Payment Date', 'Amount (AED)', 'Property ID', 'Property Name', 'Tenant ID', 'Tenant Name', 'Payment Method', 'Cheque Number', 'Notes']
                ];
                (lastReportData.income || []).forEach(p => {
                    incomeRows.push([
                        p.paymentDate || '',
                        parseFloat(p.amount || 0).toFixed(2),
                        p.propertyId || '',
                        p.propertyName || '',
                        p.tenantId || '',
                        p.tenantName || '',
                        p.paymentMethod || '',
                        p.chequeNumber || '',
                        p.notes || ''
                    ]);
                });

                // Build separate sheets for Property and Common Expenses
                const propertyExpenseRows = [
                    ['Expense Date', 'Amount (AED)', 'Property ID', 'Property Name', 'Category', 'Description', 'Vendor/Supplier', 'Invoice Number', 'Notes']
                ];
                const commonExpenseRows = [
                    ['Expense Date', 'Amount (AED)', 'Category', 'Description', 'Vendor/Supplier', 'Invoice Number', 'Notes']
                ];

                (lastReportData.expenses || []).forEach(ex => {
                    if (ex.expenseType === 'common') {
                        commonExpenseRows.push([
                            ex.expenseDate || '',
                            parseFloat(ex.amount || 0).toFixed(2),
                            ex.category || '',
                            ex.description || '',
                            ex.vendorName || '',
                            ex.invoiceNumber || '',
                            ex.notes || ''
                        ]);
                    } else {
                        propertyExpenseRows.push([
                            ex.expenseDate || '',
                            parseFloat(ex.amount || 0).toFixed(2),
                            ex.propertyId || '',
                            ex.propertyName || '',
                            ex.category || '',
                            ex.description || '',
                            ex.vendorName || '',
                            ex.invoiceNumber || '',
                            ex.notes || ''
                        ]);
                    }
                });

                // Summary sheet
                const totalIncome = (lastReportData.income || []).reduce((sum, i) => sum + parseFloat(i.amount || 0), 0);
                const totalExpenses = (lastReportData.expenses || []).reduce((sum, i) => sum + parseFloat(i.amount || 0), 0);
                const netProfit = totalIncome - totalExpenses;
                const summaryRows = [
                    ['Metric', `Amount (${settings.currencyCode})`],
                    ['Total Income', totalIncome.toFixed(2)],
                    ['Total Expenses', totalExpenses.toFixed(2)],
                    ['Net Profit', netProfit.toFixed(2)]
                ];

                // Create workbook and sheets
                const wb = XLSX.utils.book_new();
                const wsSummary = XLSX.utils.aoa_to_sheet(summaryRows);
                const wsIncome = XLSX.utils.aoa_to_sheet(incomeRows);
                const wsPropertyExpenses = XLSX.utils.aoa_to_sheet(propertyExpenseRows);
                const wsCommonExpenses = XLSX.utils.aoa_to_sheet(commonExpenseRows);
                
                XLSX.utils.book_append_sheet(wb, wsSummary, 'Summary');
                XLSX.utils.book_append_sheet(wb, wsIncome, 'Income');
                XLSX.utils.book_append_sheet(wb, wsPropertyExpenses, 'Property Expenses');
                XLSX.utils.book_append_sheet(wb, wsCommonExpenses, 'Common Expenses');

                const s = lastReportFilters?.startDate || 'start';
                const e = lastReportFilters?.endDate || 'end';
                const pid = lastReportFilters?.propertyId ? `property-${lastReportFilters.propertyId}` : 'all-properties';

                XLSX.writeFile(wb, `financial-report_${pid}_${s}_${e}.xlsx`);
                showToast('Report exported as XLSX');
            }

            // --- Dashboard Card Updates ---
            function updateDashboardCards(properties, tenants, payments) {
                document.getElementById('total-properties').textContent = properties?.length || 0;
                document.getElementById('total-tenants').textContent = tenants?.length || 0;
                document.getElementById('total-bedspaces').textContent = properties?.reduce((sum, p) => sum + (p.bedspaces?.length || 0), 0) || 0;
                
                // Total Revenue = sum of all recorded payments (total income)
                const totalIncome = (payments || []).reduce((sum, p) => sum + (parseFloat(p.amount) || 0), 0);
                document.getElementById('monthly-revenue').textContent = formatCurrency(totalIncome);
            }

            // --- Initialize App ---
            setupNavigation();
            setupDashboardCards();
            attachFormListeners();
            setupModalListeners();
            // Expose functions used by inline onclick handlers
            window.setChequeStatus = setChequeStatus;
            window.callTenant = callTenant;
            window.sendWhatsAppReminder = sendWhatsAppReminder;
            window.markRentPaid = markRentPaid;
            window.editBedspace = editBedspace;
            window.deleteBedspace = deleteBedspace;
            window.editTenant = editTenant;
            window.deleteTenant = deleteTenant;
            window.editExpense = editExpense;
            window.deleteExpense = deleteExpense;
            window.editCommonExpense = editCommonExpense;
            window.deleteCommonExpense = deleteCommonExpense;
            window.editPayment = editPayment;
            window.deletePayment = deletePayment;
            loadSettings().then(fetchAllData);
        });
    </script>

    <!-- Property Tenants Modal -->
    <div id="property-tenants-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center p-6 border-b">
                <h3 id="property-tenants-title" class="text-xl font-bold text-gray-800">Tenants</h3>
                <button id="close-property-tenants-modal" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            <div class="p-6">
                <div id="property-tenants-list" class="space-y-2"></div>
                <div class="flex justify-end pt-4">
                    <button id="dismiss-property-tenants-btn" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Payment Modal -->
    <div id="edit-payment-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center p-6 border-b">
                <h3 class="text-xl font-bold text-gray-800">Edit Payment</h3>
                <button id="close-edit-payment-modal" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            <div class="p-6">
                <form id="edit-payment-form" class="space-y-4">
                    <input type="hidden" id="edit-payment-id">
                    <div>
                        <label for="edit-payment-tenant" class="block text-sm font-medium text-gray-700">Tenant</label>
                        <select id="edit-payment-tenant" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="edit-payment-amount" class="block text-sm font-medium text-gray-700">Amount (AED)</label>
                            <input type="number" id="edit-payment-amount" step="0.01" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="edit-payment-date" class="block text-sm font-medium text-gray-700">Payment Date</label>
                            <input type="date" id="edit-payment-date" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div>
                        <label for="edit-payment-method" class="block text-sm font-medium text-gray-700">Payment Method</label>
                        <select id="edit-payment-method" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="cash">Cash</option>
                            <option value="cheque">Cheque</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div>
                        <label for="edit-payment-notes" class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea id="edit-payment-notes" rows="2" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                    </div>
                    <div class="flex gap-3 pt-4">
                        <button type="button" id="cancel-edit-payment-btn" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                        <button type="submit" class="flex-1 bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition-colors">Update Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Property Modal -->
    <div id="add-property-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center p-6 border-b">
                <h3 class="text-xl font-bold text-gray-800">Add New Property</h3>
                <button id="close-property-modal" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            <div class="p-6">
                <form id="add-property-form" class="space-y-4">
                    <div>
                        <label for="property-name" class="block text-sm font-medium text-gray-700 mb-1">Property Name *</label>
                        <input type="text" id="property-name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="property-address" class="block text-sm font-medium text-gray-700 mb-1">Address *</label>
                        <textarea id="property-address" required rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                    </div>
                    <div>
                        <label for="property-email" class="block text-sm font-medium text-gray-700 mb-1">Contact Email</label>
                        <input type="email" id="property-email" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="property-whatsapp" class="block text-sm font-medium text-gray-700 mb-1">WhatsApp Number</label>
                        <input type="tel" id="property-whatsapp" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="property-owner-fullname" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                            <input type="text" id="property-owner-fullname" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="property-mobile" class="block text-sm font-medium text-gray-700 mb-1">Mobile Number</label>
                            <input type="tel" id="property-mobile" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="property-building-yearly-rent" class="block text-sm font-medium text-gray-700 mb-1">Building Yearly Rent (AED)</label>
                            <input type="number" id="property-building-yearly-rent" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="property-total-charges" class="block text-sm font-medium text-gray-700 mb-1">Total Charges (AED)</label>
                            <input type="number" id="property-total-charges" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div>
                        <label for="property-document" class="block text-sm font-medium text-gray-700 mb-1">Document (PDF)</label>
                        <input type="file" id="property-document" accept="application/pdf" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    </div>
                    <div class="flex gap-3 pt-4">
                        <button type="button" id="cancel-property-btn" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                        <button type="submit" class="flex-1 bg-indigo-600 text-white py-2 px-4 rounded-lg hover:bg-indigo-700 transition-colors">Add Property</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- Edit Property Modal -->
    <div id="edit-property-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center p-6 border-b">
                <h3 class="text-xl font-bold text-gray-800">Edit Property</h3>
                <button id="close-edit-property-modal" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            <div class="p-6">
                <form id="edit-property-form" class="space-y-4">
                    <input type="hidden" id="edit-property-id">
                    <div>
                        <label for="edit-property-name" class="block text-sm font-medium text-gray-700 mb-1">Property Name *</label>
                        <input type="text" id="edit-property-name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="edit-property-address" class="block text-sm font-medium text-gray-700 mb-1">Address *</label>
                        <textarea id="edit-property-address" required rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                    </div>
                    <div>
                        <label for="edit-property-email" class="block text-sm font-medium text-gray-700 mb-1">Contact Email</label>
                        <input type="email" id="edit-property-email" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="edit-property-whatsapp" class="block text-sm font-medium text-gray-700 mb-1">WhatsApp Number</label>
                        <input type="tel" id="edit-property-whatsapp" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="edit-property-owner-fullname" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                            <input type="text" id="edit-property-owner-fullname" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="edit-property-mobile" class="block text-sm font-medium text-gray-700 mb-1">Mobile Number</label>
                            <input type="tel" id="edit-property-mobile" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="edit-property-building-yearly-rent" class="block text-sm font-medium text-gray-700 mb-1">Building Yearly Rent (AED)</label>
                            <input type="number" id="edit-property-building-yearly-rent" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="edit-property-total-charges" class="block text-sm font-medium text-gray-700 mb-1">Total Charges (AED)</label>
                            <input type="number" id="edit-property-total-charges" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div>
                        <label for="edit-property-document" class="block text-sm font-medium text-gray-700 mb-1">Update Document (PDF)</label>
                        <input type="file" id="edit-property-document" accept="application/pdf" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        <p class="text-xs text-gray-500 mt-1">Leave empty to keep the current document.</p>
                    </div>
                    <div class="flex gap-3 pt-4">
                        <button type="button" id="cancel-edit-property-btn" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                        <button type="submit" class="flex-1 bg-indigo-600 text-white py-2 px-4 rounded-lg hover:bg-indigo-700 transition-colors">Update Property</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Tenant Modal -->
    <div id="edit-tenant-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center p-6 border-b">
                <h3 class="text-xl font-bold text-gray-800">Edit Tenant</h3>
                <button id="close-edit-tenant-modal" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            <div class="p-6">
                <form id="edit-tenant-form" class="space-y-4">
                    <input type="hidden" id="edit-tenant-id">
                    <div>
                        <label for="edit-tenant-name" class="block text-sm font-medium text-gray-700">Tenant Name</label>
                        <input type="text" id="edit-tenant-name" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="edit-tenant-email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" id="edit-tenant-email" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="edit-tenant-phone" class="block text-sm font-medium text-gray-700">Mobile Number</label>
                        <input type="tel" id="edit-tenant-phone" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="edit-tenant-id-number" class="block text-sm font-medium text-gray-700">ID/Passport Number</label>
                        <input type="text" id="edit-tenant-id-number" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="edit-tenant-rent-amount" class="block text-sm font-medium text-gray-700">Rent Amount (AED)</label>
                            <input type="number" id="edit-tenant-rent-amount" step="0.01" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="edit-tenant-rent-due-date" class="block text-sm font-medium text-gray-700">Rent Due Day of Month (1-31)</label>
                            <input type="number" id="edit-tenant-rent-due-date" min="1" max="31" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div>
                        <label for="edit-tenant-security-deposit" class="block text-sm font-medium text-gray-700">Security Deposit (AED)</label>
                        <input type="number" id="edit-tenant-security-deposit" step="0.01" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="edit-tenant-contract-start" class="block text-sm font-medium text-gray-700">Contract Start Date</label>
                            <input type="date" id="edit-tenant-contract-start" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="edit-tenant-contract-end" class="block text-sm font-medium text-gray-700">Contract End Date</label>
                            <input type="date" id="edit-tenant-contract-end" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div class="flex gap-3 pt-4">
                        <button type="button" id="cancel-edit-tenant-btn" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                        <button type="submit" class="flex-1 bg-indigo-600 text-white py-2 px-4 rounded-lg hover:bg-indigo-700 transition-colors">Update Tenant</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Expense Modal -->
    <div id="edit-expense-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center p-6 border-b">
                <h3 class="text-xl font-bold text-gray-800">Edit Expense</h3>
                <button id="close-edit-expense-modal" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            <div class="p-6">
                <form id="edit-expense-form" class="space-y-4">
                    <input type="hidden" id="edit-expense-id">
                    <div>
                        <label for="edit-expense-description" class="block text-sm font-medium text-gray-700">Description</label>
                        <input type="text" id="edit-expense-description" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="edit-expense-amount" class="block text-sm font-medium text-gray-700">Amount (AED)</label>
                            <input type="number" id="edit-expense-amount" step="0.01" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="edit-expense-category" class="block text-sm font-medium text-gray-700">Category</label>
                            <select id="edit-expense-category" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="general">General</option>
                                <option value="dewa">DEWA Utilities</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="commission">Commission</option>
                                <option value="purchasing">Purchasing</option>
                                <option value="cheque">Cheque Payment</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="edit-expense-date" class="block text-sm font-medium text-gray-700">Expense Date</label>
                        <input type="date" id="edit-expense-date" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="edit-expense-property" class="block text-sm font-medium text-gray-700">Related Property (Optional)</label>
                        <select id="edit-expense-property" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">None</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="edit-expense-vendor" class="block text-sm font-medium text-gray-700">Vendor/Supplier</label>
                            <input type="text" id="edit-expense-vendor" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="edit-expense-invoice" class="block text-sm font-medium text-gray-700">Invoice Number</label>
                            <input type="text" id="edit-expense-invoice" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div>
                        <label for="edit-expense-notes" class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea id="edit-expense-notes" rows="2" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                    </div>
                    <div class="flex gap-3 pt-4">
                        <button type="button" id="cancel-edit-expense-btn" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                        <button type="submit" class="flex-1 bg-purple-600 text-white py-2 px-4 rounded-lg hover:bg-purple-700 transition-colors">Update Expense</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Common Expense Modal -->
    <div id="edit-common-expense-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center p-6 border-b">
                <h3 class="text-xl font-bold text-gray-800">Edit Common Expense</h3>
                <button id="close-edit-common-expense-modal" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            <div class="p-6">
                <form id="edit-common-expense-form" class="space-y-4">
                    <input type="hidden" id="edit-common-expense-id">
                    <div>
                        <label for="edit-common-expense-description" class="block text-sm font-medium text-gray-700">Description</label>
                        <input type="text" id="edit-common-expense-description" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="edit-common-expense-amount" class="block text-sm font-medium text-gray-700">Amount (AED)</label>
                            <input type="number" id="edit-common-expense-amount" step="0.01" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="edit-common-expense-category" class="block text-sm font-medium text-gray-700">Category</label>
                            <select id="edit-common-expense-category" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="general">General</option>
                                <option value="office">Office Expenses</option>
                                <option value="marketing">Marketing</option>
                                <option value="legal">Legal & Professional</option>
                                <option value="insurance">Insurance</option>
                                <option value="travel">Travel & Transport</option>
                                <option value="utilities">Utilities</option>
                                <option value="software">Software & Subscriptions</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="edit-common-expense-date" class="block text-sm font-medium text-gray-700">Expense Date</label>
                        <input type="date" id="edit-common-expense-date" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="edit-common-expense-vendor" class="block text-sm font-medium text-gray-700">Vendor/Supplier</label>
                            <input type="text" id="edit-common-expense-vendor" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="edit-common-expense-invoice" class="block text-sm font-medium text-gray-700">Invoice Number</label>
                            <input type="text" id="edit-common-expense-invoice" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div>
                        <label for="edit-common-expense-notes" class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea id="edit-common-expense-notes" rows="2" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                    </div>
                    <div class="flex gap-3 pt-4">
                        <button type="button" id="cancel-edit-common-expense-btn" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                        <button type="submit" class="flex-1 bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition-colors">Update Common Expense</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
