<?php
$data = json_decode(file_get_contents('updated_data.json'), true);
$totalRevenue = 0;
echo "August 2025 Payments:\n";
foreach($data['payments'] as $payment) {
    if(strpos($payment['paymentDate'], '2025-08') === 0) {
        echo "- {$payment['notes']}: AED {$payment['amount']}\n";
        $totalRevenue += $payment['amount'];
    }
}
echo "\nTotal Monthly Revenue for August 2025: AED $totalRevenue\n";

echo "\nAugust 2025 Expenses:\n";
$totalExpenses = 0;
foreach($data['expenses'] as $expense) {
    if(strpos($expense['expenseDate'], '2025-08') === 0) {
        echo "- {$expense['description']}: AED {$expense['amount']}\n";
        $totalExpenses += $expense['amount'];
    }
}
echo "\nTotal Monthly Expenses for August 2025: AED $totalExpenses\n";
echo "Net Monthly Profit: AED " . ($totalRevenue - $totalExpenses) . "\n";
?>
