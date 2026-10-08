<?php
require_once 'config.php';
checkAuth();

$id = $_GET['id'] ?? 0;

// Fetch massive combined dataset for this specific order
$sql = "SELECT o.*, 
               c.first_name, c.last_name, c.email, c.phone_number, c.whatsapp_number, c.state, c.city, c.pincode,
               oi.id as item_id, oi.price_at_purchase, oi.upc_code, oi.upc_valid_until, oi.network_preference,
               v.mobile_number, v.sum_total, v.tags,
               cat.category_name,
               p.transaction_id, p.payment_status as gateway_status, p.created_at as payment_date
        FROM orders o 
        JOIN customers c ON o.customer_id = c.id 
        JOIN order_items oi ON o.id = oi.order_id 
        JOIN vip_numbers v ON oi.vip_number_id = v.id 
        LEFT JOIN categories cat ON v.category_id = cat.id
        LEFT JOIN payments p ON o.id = p.order_id
        WHERE o.id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    die("<div class='p-8 text-center text-red-500 font-bold text-2xl'>Order not found! <a href='orders.php' class='text-blue-500 underline'>Go Back</a></div>");
}

include 'include/header.php'; 
?>

<div class="flex justify-between items-center mb-6">
    <a href="orders.php" class="text-slate-500 hover:text-blue-600 font-medium flex items-center gap-2 transition">
        <i class="fa-solid fa-arrow-left"></i> Back to Orders
    </a>
    <div class="flex gap-2">
        <a href="evidence_pack.php?order_id=<?= (int) $order['id'] ?>&format=pdf" class="bg-red-600 text-white px-4 py-2 rounded-lg shadow text-sm font-bold hover:bg-red-700"><i class="fa-solid fa-shield-halved"></i> Evidence PDF</a>
        <a href="invoice_print.php?order_id=<?= (int) $order['id'] ?>&format=pdf" class="bg-slate-800 text-white px-4 py-2 rounded-lg shadow text-sm font-bold hover:bg-slate-700"><i class="fa-solid fa-file-pdf"></i> Invoice PDF</a>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6 flex flex-wrap justify-between items-center gap-4">
    <div>
        <h2 class="text-2xl font-bold text-slate-800 mb-1">Order <?= $order['order_number'] ?></h2>
        <p class="text-sm text-slate-500">Placed on <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></p>
    </div>
    <div class="flex gap-4">
        <div class="text-right">
            <p class="text-xs text-slate-400 uppercase font-bold mb-1">Order Status</p>
            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-blue-100 text-blue-700"><?= str_replace('_', ' ', $order['order_status']) ?></span>
        </div>
        <div class="text-right border-l pl-4">
            <p class="text-xs text-slate-400 uppercase font-bold mb-1">Payment Status</p>
            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase <?= $order['payment_status']=='paid'?'bg-green-100 text-green-700':'bg-orange-100 text-orange-700' ?>"><?= $order['payment_status'] ?></span>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    
    <div class="card-3d p-6 bg-white lg:col-span-1 border-t-4 border-blue-500">
        <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2"><i class="fa-solid fa-user text-blue-500 mr-2"></i> Client Information</h3>
        <div class="space-y-4">
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Full Name</p>
                <p class="font-bold text-slate-700 text-lg"><?= $order['first_name'] . ' ' . $order['last_name'] ?></p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Email Address</p>
                <p class="font-medium text-slate-600"><a href="mailto:<?= $order['email'] ?>" class="text-blue-600 hover:underline"><?= $order['email'] ?></a></p>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <p class="text-xs text-slate-400 uppercase font-bold">Phone</p>
                    <p class="font-medium text-slate-600"><?= $order['phone_number'] ?></p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 uppercase font-bold">WhatsApp</p>
                    <p class="font-bold text-green-600"><i class="fa-brands fa-whatsapp"></i> <?= $order['whatsapp_number'] ?></p>
                </div>
            </div>
            <div class="bg-slate-50 p-3 rounded border border-slate-100">
                <p class="text-xs text-slate-400 uppercase font-bold mb-1">Billing Location</p>
                <p class="text-sm text-slate-700 font-medium"><?= $order['city'] ?>, <?= $order['state'] ?> - <?= $order['pincode'] ?></p>
            </div>
        </div>
    </div>

    <div class="card-3d p-6 bg-white lg:col-span-2 border-t-4 border-green-500">
        <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2"><i class="fa-solid fa-sim-card text-green-500 mr-2"></i> Purchased VIP Number</h3>
        
        <div class="flex items-center justify-between bg-blue-50 border border-blue-100 p-4 rounded-xl mb-6">
            <div>
                <h1 class="text-3xl font-bold text-blue-900 tracking-widest"><?= $order['mobile_number'] ?></h1>
                <p class="text-sm text-blue-600 font-medium mt-1"><?= $order['category_name'] ?: 'Uncategorized' ?> • Sum: <?= $order['sum_total'] ?></p>
            </div>
            <div class="text-right">
                <p class="text-xs text-slate-500 uppercase font-bold mb-1">Price Paid</p>
                <p class="text-2xl font-bold text-slate-800">₹<?= number_format($order['price_at_purchase']) ?></p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-6 mb-6">
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold mb-1">Network Porting Preference</p>
                <p class="font-bold text-slate-700 text-lg"><?= $order['network_preference'] ?: 'Any Network' ?></p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold mb-1">Customer Notes / Instructions</p>
                <p class="text-sm text-slate-600 italic bg-slate-50 p-2 rounded border border-slate-100 min-h-[40px]"><?= $order['notes'] ?: 'No notes provided.' ?></p>
            </div>
        </div>

        <div class="bg-slate-800 rounded-xl p-5 text-white">
            <h4 class="font-bold text-lg mb-3"><i class="fa-solid fa-key text-orange-400 mr-2"></i> UPC Code Management</h4>
            <form action="actions.php" method="POST" class="flex flex-col md:flex-row gap-4 items-end">
                <input type="hidden" name="action" value="update_upc">
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                <input type="hidden" name="order_item_id" value="<?= $order['item_id'] ?>">
                
                <div class="flex-1">
                    <label class="block text-xs font-semibold text-slate-400 mb-1">UPC (Port Code)</label>
                    <input type="text" name="upc_code" value="<?= $order['upc_code'] ?>" required placeholder="Enter 8-digit UPC" class="w-full bg-slate-700 border border-slate-600 p-2.5 rounded-lg text-white font-mono font-bold focus:border-orange-400 outline-none">
                </div>
                <div class="flex-1">
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Validity Date (Optional)</label>
                    <input type="date" name="upc_valid_until" value="<?= $order['upc_valid_until'] ? date('Y-m-d', strtotime($order['upc_valid_until'])) : '' ?>" class="w-full bg-slate-700 border border-slate-600 p-2.5 rounded-lg text-white focus:border-orange-400 outline-none">
                </div>
                <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2.5 px-6 rounded-lg transition shadow-lg h-[46px]">
                    <?= $order['upc_code'] ? 'Update UPC' : 'Deliver UPC' ?>
                </button>
            </form>
        </div>
    </div>
</div>

<div class="card-3d p-6 bg-white border-t-4 border-slate-700">
    <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2"><i class="fa-solid fa-money-bill-transfer text-slate-700 mr-2"></i> Transaction Details</h3>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div>
            <p class="text-xs text-slate-400 uppercase font-bold mb-1">Payment Method</p>
            <p class="font-bold text-slate-700 text-lg"><?= ucfirst($order['payment_method']) ?></p>
        </div>
        <div>
            <p class="text-xs text-slate-400 uppercase font-bold mb-1">Gateway Txn ID</p>
            <p class="font-mono font-bold text-blue-600"><?= $order['transaction_id'] ?: 'N/A' ?></p>
        </div>
        <div>
            <p class="text-xs text-slate-400 uppercase font-bold mb-1">Gateway Status</p>
            <p class="font-bold text-slate-700"><?= $order['gateway_status'] ? strtoupper($order['gateway_status']) : 'N/A' ?></p>
        </div>
        <div>
            <p class="text-xs text-slate-400 uppercase font-bold mb-1">Transaction Date</p>
            <p class="font-bold text-slate-700"><?= $order['payment_date'] ? date('d M Y, h:i A', strtotime($order['payment_date'])) : 'N/A' ?></p>
        </div>
    </div>
</div>

<?php include 'include/footer.php'; ?>