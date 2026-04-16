<?php
/**
 * Learner Dashboard - Finances Tab
 */
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$learner_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// TODO: Get financial data from payments/fees table when implemented
$outstanding_balance = 0;
$total_paid = 0;
$pending_payments = [];
?>

<div class="space-y-6">
    <!-- Financial Summary -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Outstanding Balance</p>
                    <p class="mt-2 text-2xl font-semibold text-red-600">R <?php echo number_format_i18n($outstanding_balance, 2); ?></p>
                </div>
                <div class="w-12 h-12 rounded-lg bg-red-50 flex items-center justify-center">
                    <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Paid</p>
                    <p class="mt-2 text-2xl font-semibold text-green-600">R <?php echo number_format_i18n($total_paid, 2); ?></p>
                </div>
                <div class="w-12 h-12 rounded-lg bg-green-50 flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Pending Payments</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-600"><?php echo count($pending_payments); ?></p>
                </div>
                <div class="w-12 h-12 rounded-lg bg-amber-50 flex items-center justify-center">
                    <i class="fas fa-clock text-amber-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment History -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-gray-900">Payment History</h2>
            <button class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium shadow-sm transition-colors">
                <i class="fas fa-plus mr-2"></i>
                Record Payment
            </button>
        </div>
        
        <div class="text-center py-12">
            <i class="fas fa-money-bill-wave text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-medium text-gray-900 mb-2">No Payment Records</h3>
            <p class="text-gray-600">Financial management features coming soon.</p>
        </div>
    </div>

    <!-- Fee Structure -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Fee Structure</h2>
        <div class="text-center py-12">
            <i class="fas fa-receipt text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-medium text-gray-900 mb-2">Fee Management</h3>
            <p class="text-gray-600">Configure course fees and payment plans here.</p>
        </div>
    </div>
</div>
