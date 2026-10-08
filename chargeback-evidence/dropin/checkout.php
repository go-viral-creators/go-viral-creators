<?php
require_once 'admin/config.php';

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) {
    header("Location: store.php");
    exit;
}

$query = "SELECT * FROM vip_numbers WHERE id = ? AND status = 'available'";
$stmt = $pdo->prepare($query);
$stmt->execute([$id]);
$number = $stmt->fetch();

if (!$number) {
    header("Location: store.php?error=NumberNotAvailable");
    exit;
}

$base_price = $number['price'];
$discount_price = $number['discount_price'];
$final_price = ($discount_price > 0) ? $discount_price : $base_price;
$cat_id = $number['category_id'];

$coupon_stmt = $pdo->prepare("SELECT code, discount_percentage FROM coupons WHERE is_active = 1 AND expiry_date >= CURDATE() AND (category_id = 0 OR category_id = ?) LIMIT 3");
$coupon_stmt->execute([$cat_id]);
$active_coupons = $coupon_stmt->fetchAll(PDO::FETCH_ASSOC);

$all_active_coupons = $pdo->query("SELECT code, discount_percentage, category_id FROM coupons WHERE is_active = 1 AND expiry_date >= CURDATE()")->fetchAll(PDO::FETCH_ASSOC);
$js_coupons = json_encode($all_active_coupons);
?>

<?php include 'include/header.php'; ?>

<div class="bg-gradient-to-r from-[#1e4475] to-[#2a5b9e] text-white py-8 shadow-inner w-full overflow-hidden">
    <div class="max-w-[1200px] mx-auto px-4 text-center">
        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Secure Checkout</h1>
        <p class="mt-2 text-blue-100 text-xs sm:text-sm flex flex-col sm:flex-row items-center justify-center gap-1 sm:gap-2">
            <!--<span class="flex items-center gap-1"><i class="fa-solid fa-shield-halved"></i> Secure by SoftMaji</span>-->
            <span class="hidden sm:inline">|</span>
            <span>256-bit SSL Encryption</span>
        </p>
    </div>
</div>

<div class="w-full overflow-x-hidden bg-gray-50">
    <div class="max-w-[1200px] mx-auto px-4 py-8 sm:py-10">
        
        <div class="mb-6 sm:mb-8 bg-blue-50 border border-blue-100 p-4 rounded-xl flex flex-col sm:flex-row justify-between items-center text-center sm:text-left gap-4 w-full box-border">
            <div class="flex flex-col sm:flex-row items-center gap-2 sm:gap-3">
                <i class="fa-solid fa-user-circle text-blue-600 text-3xl sm:text-2xl shrink-0"></i>
                <span class="text-gray-700 font-medium text-sm sm:text-base leading-tight">Already a client? Login to track your orders.</span>
            </div>
            <a href="clients/index.php" class="w-full sm:w-auto bg-blue-600 text-white px-6 py-2.5 rounded-lg font-bold hover:bg-blue-700 transition shadow-sm text-center shrink-0">Client Login</a>
        </div>

        <form action="process_checkout.php" method="POST" id="checkoutForm" class="flex flex-col lg:flex-row gap-6 sm:gap-8 w-full box-border">
            
            <input type="hidden" name="vip_number_id" value="<?= $number['id'] ?>">
            <input type="hidden" name="applied_coupon" id="appliedCouponInput" value="">

            <div class="w-full lg:w-2/3 space-y-6 sm:space-y-8 min-w-0">
                <div class="bg-white p-4 sm:p-8 rounded-2xl shadow-sm border border-gray-100 w-full box-border">
                    
                    <h2 class="text-lg sm:text-xl font-bold text-gray-800 mb-5 sm:mb-6 flex items-center gap-3">
                        <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs sm:text-sm shrink-0">1</span>
                        <span class="truncate">Personal Details</span>
                    </h2>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5 mb-8 sm:mb-10 w-full">
                        <div class="min-w-0">
                            <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-1">First Name *</label>
                            <input type="text" name="first_name" required class="w-full border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-blue-500 outline-none transition text-sm box-border">
                        </div>
                        <div class="min-w-0">
                            <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-1">Last Name *</label>
                            <input type="text" name="last_name" required class="w-full border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-blue-500 outline-none transition text-sm box-border">
                        </div>
                        <div class="sm:col-span-2 min-w-0">
                            <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-1">Email Address *</label>
                            <input type="email" name="email" required class="w-full border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-blue-500 outline-none transition text-sm box-border">
                        </div>
                        <!-- ── Phone Number + WhatsApp OTP Verification ──────────────── -->
                        <div class="sm:col-span-2 min-w-0">
                            <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-1">
                                Phone / WhatsApp Number *
                                <span id="waVerifiedBadge" class="hidden ml-2 text-[10px] font-bold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">
                                    <i class="fa-solid fa-circle-check"></i> Verified
                                </span>
                            </label>

                            <!-- Phone input row -->
                            <!-- WhatsApp OTP verification temporarily disabled -- see guardSubmit() below.
                                 Button/OTP UI hidden (not deleted) so this can be re-enabled later by
                                 removing the "display:none" below and the otpVerified=true override in JS. -->
                            <div class="flex gap-2 w-full">
                                <input
                                    type="tel"
                                    name="phone_number"
                                    id="phoneInput"
                                    required
                                    pattern="[0-9]{10}"
                                    maxlength="10"
                                    placeholder="10-digit number"
                                    class="w-full border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-blue-500 outline-none transition text-sm box-border"
                                >
                                <button
                                    type="button"
                                    id="sendOtpBtn"
                                    onclick="sendOTP()"
                                    style="display:none"
                                    class="shrink-0 bg-[#25D366] hover:bg-green-600 text-white px-4 py-2.5 rounded-lg text-xs sm:text-sm font-bold transition flex items-center gap-1.5 whitespace-nowrap"
                                >
                                    <i class="fa-brands fa-whatsapp text-base"></i>
                                    <span id="sendOtpLabel">Send OTP</span>
                                </button>
                            </div>
                            <p class="text-[10px] text-gray-400 mt-1" style="display:none"><i class="fa-solid fa-circle-info"></i> OTP will be sent to this number via WhatsApp to verify it's active.</p>

                            <!-- OTP input row (hidden until OTP sent) -->
                            <div id="otpSection" class="hidden mt-3 flex flex-col gap-2">
                                <div class="flex gap-2 w-full">
                                    <input
                                        type="text"
                                        id="otpInput"
                                        inputmode="numeric"
                                        maxlength="6"
                                        placeholder="Enter 6-digit OTP"
                                        class="w-full border-2 border-blue-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-blue-500 outline-none transition text-sm font-mono tracking-widest box-border"
                                    >
                                    <button
                                        type="button"
                                        id="verifyOtpBtn"
                                        onclick="verifyOTP()"
                                        class="shrink-0 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg text-sm font-bold transition whitespace-nowrap"
                                    >
                                        Verify
                                    </button>
                                </div>
                                <div class="flex items-center gap-3">
                                    <p id="otpMsg" class="text-xs font-semibold"></p>
                                    <button type="button" id="resendBtn" onclick="resendOTP()" class="hidden text-xs text-blue-600 hover:underline font-semibold">
                                        Resend OTP
                                    </button>
                                </div>
                            </div>

                            <!-- Hidden verified flag submitted with form -- defaulted to "1" while
                                 verification is disabled, so server-side checks (if any) don't block checkout -->
                            <input type="hidden" name="wa_otp_verified" id="waOtpVerifiedFlag" value="1">
                        </div>

                        <!-- ── SMS OTP Verification (apitxt.com) ─────────────────────────── -->
                        <div class="sm:col-span-2 min-w-0">
                            <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-1">
                                Verify Mobile Number via SMS OTP *
                                <span id="smsVerifiedBadge" class="hidden ml-2 text-[10px] font-bold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">
                                    <i class="fa-solid fa-circle-check"></i> Verified
                                </span>
                            </label>
                            <p class="text-[10px] text-gray-400 mb-2"><i class="fa-solid fa-circle-info"></i> A 6-digit OTP will be sent to your mobile number via SMS.</p>

                            <div class="flex gap-2 w-full">
                                <button
                                    type="button"
                                    id="smsSendOtpBtn"
                                    onclick="smsSendOTP()"
                                    class="shrink-0 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg text-xs sm:text-sm font-bold transition flex items-center gap-1.5 whitespace-nowrap"
                                >
                                    <i class="fa-solid fa-mobile-screen-button text-base"></i>
                                    <span id="smsSendOtpLabel">Send SMS OTP</span>
                                </button>
                            </div>

                            <div id="smsOtpSection" class="hidden mt-3 flex flex-col gap-2">
                                <div class="flex gap-2 w-full">
                                    <input
                                        type="text"
                                        id="smsOtpInput"
                                        inputmode="numeric"
                                        maxlength="6"
                                        placeholder="Enter 6-digit OTP"
                                        class="w-full border-2 border-blue-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-blue-500 outline-none transition text-sm font-mono tracking-widest box-border"
                                    >
                                    <button
                                        type="button"
                                        id="smsVerifyOtpBtn"
                                        onclick="smsVerifyOTP()"
                                        class="shrink-0 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg text-sm font-bold transition whitespace-nowrap"
                                    >
                                        Verify
                                    </button>
                                </div>
                                <div class="flex items-center gap-3">
                                    <p id="smsOtpMsg" class="text-xs font-semibold"></p>
                                    <button type="button" id="smsResendBtn" onclick="smsResendOTP()" class="hidden text-xs text-blue-600 hover:underline font-semibold">
                                        Resend OTP
                                    </button>
                                </div>
                            </div>

                            <input type="hidden" name="sms_otp_verified" id="smsOtpVerifiedFlag" value="0">
                        </div>
                        <!-- ── /SMS OTP ────────────────────────────────────────────────── -->

                        <div class="min-w-0">
                            <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-1">WhatsApp Number <span class="text-gray-400 font-normal">(if different)</span></label>
                            <input type="tel" name="whatsapp_number" pattern="[0-9]{10}" placeholder="Optional" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-blue-500 outline-none transition text-sm box-border">
                        </div>
                    </div>

                    <h2 class="text-lg sm:text-xl font-bold text-gray-800 mb-5 sm:mb-6 flex items-center gap-3">
                        <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs sm:text-sm shrink-0">2</span>
                        <span class="truncate">Porting Preference</span>
                    </h2>

                    <div class="mb-8 sm:mb-10 w-full">
                        <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-3">
                            Which network do you want to port this number to? *
                        </label>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 w-full">
                            <?php
                            $networks = [
                                'Jio' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/b/bf/Reliance_Jio_Logo.svg/1280px-Reliance_Jio_Logo.svg.png',
                                'Airtel' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/fb/Bharti_Airtel_Logo.svg/1280px-Bharti_Airtel_Logo.svg.png',
                                'Vi' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/72/Vodafone_Idea_logo.svg/120px-Vodafone_Idea_logo.svg.png',
                                'BSNL' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/17/BSNL_logo_with_slogan.svg/1280px-BSNL_logo_with_slogan.svg.png',
                            ];
                            foreach ($networks as $name => $logo):
                            ?>
                            <label class="cursor-pointer border border-gray-200 rounded-lg p-3 text-center hover:border-blue-500 hover:bg-blue-50 transition has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50 has-[:checked]:ring-1 has-[:checked]:ring-blue-600 flex flex-col items-center justify-center h-full min-w-0 box-border">
                                <input type="radio" name="network_preference" value="<?= $name ?>" class="sr-only" required>
                                <div class="w-12 h-8 sm:w-16 sm:h-10 flex items-center justify-center mb-2 shrink-0">
                                    <img src="<?= $logo ?>" alt="<?= $name ?> Logo" class="max-h-full max-w-full object-contain">
                                </div>
                                <span class="font-semibold text-gray-700 block text-xs sm:text-sm truncate w-full"><?= $name ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <h2 class="text-lg sm:text-xl font-bold text-gray-800 mb-5 sm:mb-6 flex items-center gap-3">
                        <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs sm:text-sm shrink-0">3</span>
                        <span class="truncate">Delivery Address</span>
                    </h2>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-5 w-full">
                        <div class="sm:col-span-1 min-w-0">
                            <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-1">Indian Pincode*</label>
                            <div class="relative w-full">
                                <input type="text" name="pincode" id="pincode" required maxlength="6" pattern="[0-9]{6}" placeholder="e.g. 226001" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-blue-500 outline-none transition text-sm box-border">
                                <div id="pinLoader" class="hidden absolute right-3 top-2.5"><i class="fa-solid fa-spinner fa-spin text-blue-500"></i></div>
                            </div>
                            <p id="pinError" class="text-red-500 text-[10px] font-bold mt-1 hidden uppercase italic truncate"><i class="fa-solid fa-circle-exclamation"></i> Invalid Pincode</p>
                        </div>
                        <div class="sm:col-span-1 min-w-0">
                            <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-1">State *</label>
                            <input type="text" name="state" id="state" required readonly placeholder="Auto-filled" class="w-full border border-gray-100 bg-gray-50 rounded-lg px-3 py-2.5 text-gray-500 outline-none cursor-not-allowed text-sm truncate box-border">
                        </div>
                        <div class="sm:col-span-1 min-w-0">
                            <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-1">City / District *</label>
                            <input type="text" name="city" id="city" required readonly placeholder="Auto-filled" class="w-full border border-gray-100 bg-gray-50 rounded-lg px-3 py-2.5 text-gray-500 outline-none cursor-not-allowed text-sm truncate box-border">
                        </div>
                    </div>
                </div>
            </div>

            <div class="w-full lg:w-1/3 space-y-6 min-w-0">
                
                <div class="bg-white p-4 sm:p-6 rounded-2xl shadow-sm border border-gray-100 w-full box-border">
                    <h3 class="text-base sm:text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-ticket text-orange-500 shrink-0"></i> 
                        <span class="truncate">Coupon Discount</span>
                    </h3>
                    
                    <div class="flex flex-row gap-2 mb-4 w-full">
                        <input type="text" id="couponCode" placeholder="Enter Code" class="w-full min-w-0 border border-gray-200 rounded-lg px-3 py-2.5 uppercase outline-none focus:border-blue-500 text-sm box-border">
                        <button type="button" onclick="applyCoupon()" class="bg-slate-800 text-white px-4 py-2.5 rounded-lg text-sm font-bold hover:bg-black transition shrink-0 box-border">Apply</button>
                    </div>

                    <?php if($active_coupons): ?>
                    <div class="space-y-2 w-full">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Available Offers</p>
                        <?php foreach($active_coupons as $c): ?>
                        <div class="flex justify-between items-center bg-gray-50 border border-dashed border-gray-200 p-2.5 rounded-lg group min-w-0 w-full box-border gap-2">
                            <span class="font-mono font-bold text-blue-600 text-sm truncate min-w-0"><?= htmlspecialchars($c['code']) ?></span>
                            <button type="button" onclick="copyAndApply('<?= htmlspecialchars($c['code']) ?>')" class="text-xs text-orange-600 font-bold hover:underline p-1 shrink-0">Apply Now</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="bg-white p-4 sm:p-6 rounded-2xl shadow-xl border border-blue-100 w-full box-border lg:sticky lg:top-24">
                    <h3 class="text-lg sm:text-xl font-bold text-gray-800 mb-4 border-b pb-3 text-center">Order Summary</h3>
                    
                    <div class="mb-5 sm:mb-6 w-full">
                        <p class="text-center text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1">Selected VIP Number</p>
                        <div class="bg-blue-50 py-3 px-2 rounded-lg text-center border border-blue-100 w-full overflow-hidden box-border">
                            <h4 class="text-xl sm:text-2xl font-black text-blue-700 break-words leading-none">
                                <?= htmlspecialchars($number['mobile_number']) ?>
                            </h4>
                        </div>
                    </div>
                    
                    <div class="space-y-3 text-gray-600 mb-5 sm:mb-6 border-b pb-5 text-sm w-full">
                        <div class="flex justify-between items-center w-full min-w-0 gap-2">
                            <span class="shrink-0">Original Price</span>
                            <span class="font-bold truncate">₹<span id="displayBasePrice"><?= number_format($base_price) ?></span></span>
                        </div>
                        
                        <div id="dbDiscountRow" class="flex justify-between items-center text-emerald-600 w-full min-w-0 gap-2 <?= $discount_price > 0 ? '' : 'hidden' ?>">
                            <span class="shrink-0">Store Discount</span>
                            <span class="font-bold truncate">- ₹<?= number_format($base_price - $discount_price) ?></span>
                        </div>

                        <div id="couponDiscountRow" class="flex justify-between items-center text-blue-600 w-full min-w-0 gap-2 hidden">
                            <span class="truncate">Coupon (<span id="couponText"></span>)</span>
                            <span class="font-bold shrink-0">- ₹<span id="couponDiscountVal">0</span></span>
                        </div>

                        <div class="flex justify-between items-center text-gray-400 w-full min-w-0 gap-2">
                            <span class="shrink-0">Taxes & Fees</span>
                            <span class="italic truncate">Included</span>
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center mb-6 sm:mb-8 w-full min-w-0 gap-2">
                        <span class="text-sm sm:text-base font-bold text-gray-800 uppercase tracking-tighter shrink-0">Total Payable</span>
                        <span class="text-2xl sm:text-3xl font-black text-[#ff8318] truncate text-right">₹<span id="finalTotal"><?= number_format($final_price) ?></span></span>
                    </div>
                    
                    <!-- WhatsApp OTP verification temporarily disabled -- payLock overlay hidden by
                         default (add class="hidden" removed to re-enable, alongside the other
                         OTP re-enable steps noted near the phone input above) -->
                    <label class="flex items-start gap-2 text-xs text-gray-600 mb-3 cursor-pointer">
                        <input type="checkbox" name="accept_terms" value="1" required class="mt-0.5 shrink-0">
                        <span>I understand this is a digital product (VIP number + UPC code) and is <b>non-refundable once the UPC is delivered</b>. I accept the <a href="terms-conditions" target="_blank" class="text-blue-600 underline">Terms</a>, <a href="refund-policy" target="_blank" class="text-blue-600 underline">Refund Policy</a> and <a href="shipping-policy" target="_blank" class="text-blue-600 underline">Delivery Policy</a>.</span>
                    </label>
                    <div id="payBtnWrap" class="relative">
                        <button type="submit" id="payBtn" onclick="return smsGuardSubmit(event) && guardSubmit(event)" class="w-full bg-[#20c15a] hover:bg-green-600 text-white py-3.5 sm:py-4 rounded-xl font-black text-base sm:text-lg shadow-lg shadow-green-500/30 transition-all transform hover:-translate-y-0.5 flex justify-center items-center uppercase tracking-wider box-border">
                            <span class="truncate">Pay Securely</span> <i class="fa-solid fa-arrow-right-long ml-2 shrink-0"></i>
                        </button>
                        <div id="payLock" class="hidden absolute inset-0 bg-gray-200/70 rounded-xl flex items-center justify-center cursor-not-allowed">
                            <span class="text-gray-500 text-xs font-bold flex items-center gap-2">
                                <i class="fa-solid fa-lock"></i> Verify WhatsApp number first
                            </span>
                        </div>
                    </div>
                    
                    <div class="mt-4 flex flex-col items-center gap-2 w-full text-center">
                        <span class="text-[9px] sm:text-[10px] font-bold text-gray-400 uppercase tracking-widest flex items-center justify-center gap-1.5 w-full truncate">
                            <!--<i class="fa-solid fa-lock text-emerald-500 shrink-0"></i> Secure by SoftMaji InfoTech-->
                        </span>
                    </div>
                </div>
            </div>

        </form>
    </div>
</div>
<script>
// ── WhatsApp OTP Verification ─────────────────────────────────────────────────

let otpVerified = true; // WhatsApp OTP verification temporarily disabled (silent) -- change back to false + unhide the button above to re-enable
let resendTimer  = null;

function getPhone() {
    return document.getElementById('phoneInput').value.replace(/\D/g, '');
}

function setOtpMsg(text, color) {
    const el = document.getElementById('otpMsg');
    el.textContent = text;
    el.style.color = color;
}

function startResendCountdown(seconds) {
    const btn = document.getElementById('resendBtn');
    btn.classList.add('hidden');
    clearInterval(resendTimer);
    let t = seconds;
    resendTimer = setInterval(() => {
        t--;
        if (t <= 0) {
            clearInterval(resendTimer);
            btn.classList.remove('hidden');
        }
    }, 1000);
}

async function sendOTP() {
    const phone = getPhone();
    if (phone.length !== 10) {
        alert('Please enter a valid 10-digit phone number first.');
        return;
    }

    const btn   = document.getElementById('sendOtpBtn');
    const label = document.getElementById('sendOtpLabel');
    btn.disabled   = true;
    label.textContent = 'Sending…';

    try {
        const fd = new FormData();
        fd.append('phone', phone);
        const res  = await fetch('send_otp.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            document.getElementById('otpSection').classList.remove('hidden');
            document.getElementById('otpInput').focus();
            setOtpMsg('✅ ' + data.message, '#15803d');
            startResendCountdown(60);
            label.textContent = 'Resend';
        } else {
            setOtpMsg('❌ ' + data.message, '#dc2626');
            document.getElementById('otpSection').classList.remove('hidden');
            document.getElementById('resendBtn').classList.remove('hidden');
            label.textContent = 'Send OTP';
        }
    } catch (e) {
        setOtpMsg('❌ Network error. Please try again.', '#dc2626');
        label.textContent = 'Send OTP';
    } finally {
        btn.disabled = false;
    }
}

async function verifyOTP() {
    const phone = getPhone();
    const otp   = document.getElementById('otpInput').value.trim();

    if (otp.length !== 6 || !/^\d+$/.test(otp)) {
        setOtpMsg('❌ Please enter the 6-digit OTP.', '#dc2626');
        return;
    }

    const btn = document.getElementById('verifyOtpBtn');
    btn.disabled     = true;
    btn.textContent  = 'Verifying…';

    try {
        const fd = new FormData();
        fd.append('otp',   otp);
        fd.append('phone', phone);
        const res  = await fetch('verify_otp.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            otpVerified = true;
            document.getElementById('waOtpVerifiedFlag').value = '1';

            // Show verified badge, hide OTP section, remove pay lock
            document.getElementById('waVerifiedBadge').classList.remove('hidden');
            document.getElementById('otpSection').classList.add('hidden');
            document.getElementById('payLock').classList.add('hidden');

            setOtpMsg('', '');
        } else {
            setOtpMsg('❌ ' + data.message, '#dc2626');
            btn.disabled    = false;
            btn.textContent = 'Verify';
        }
    } catch (e) {
        setOtpMsg('❌ Network error. Please try again.', '#dc2626');
        btn.disabled    = false;
        btn.textContent = 'Verify';
    }
}

function resendOTP() {
    document.getElementById('otpInput').value = '';
    setOtpMsg('', '');
    sendOTP();
}

function guardSubmit(e) {
    if (!otpVerified) {
        e.preventDefault();
        alert('Please verify your WhatsApp number via OTP before proceeding to payment.');
        document.getElementById('phoneInput').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }
    return true;
}

// ── Pincode autofill ──────────────────────────────────────────────────────────
const pincodeInput = document.getElementById('pincode');
const pinLoader = document.getElementById('pinLoader');
const pinError = document.getElementById('pinError');
const stateInput = document.getElementById('state');
const cityInput = document.getElementById('city');

let timer;

pincodeInput.addEventListener('input', function (e) {

    const pin = e.target.value.replace(/\D/g, '').slice(0, 6);

    e.target.value = pin;

    clearTimeout(timer);

    stateInput.value = '';
    cityInput.value = '';

    pinError.classList.add('hidden');

    if (pin.length !== 6) {
        pinLoader.classList.add('hidden');
        return;
    }

    timer = setTimeout(async () => {

        pinLoader.classList.remove('hidden');

        try {

            const response = await fetch(`get-pincode.php?pincode=${pin}`);

            const data = await response.json();

            pinLoader.classList.add('hidden');

            if (data.status) {

                stateInput.value = data.state;
                cityInput.value = data.city;

            } else {

                pinError.classList.remove('hidden');

            }

        } catch (error) {

            pinLoader.classList.add('hidden');
            pinError.classList.remove('hidden');

        }

    }, 300);

});


let currentFinalPrice = <?= $final_price ?>;
let productCategoryId = <?= $cat_id ?>;
const allCoupons = <?= $js_coupons ?>;

function copyAndApply(code) {
    document.getElementById('couponCode').value = code;
    applyCoupon();
}

function applyCoupon() {
    const codeInput = document.getElementById('couponCode').value.trim().toUpperCase();
    if(!codeInput) return;

    let validCoupon = null;
    
    for (let i = 0; i < allCoupons.length; i++) {
        if (allCoupons[i].code === codeInput) {
            if (allCoupons[i].category_id == 0 || allCoupons[i].category_id == productCategoryId) {
                validCoupon = allCoupons[i];
                break;
            }
        }
    }

    if (!validCoupon) {
        alert("Invalid Coupon Code or not applicable to this category.");
        return;
    }

    const discountAmount = Math.round(currentFinalPrice * (parseFloat(validCoupon.discount_percentage) / 100));
    const newTotal = currentFinalPrice - discountAmount;

    document.getElementById('couponText').innerText = validCoupon.code;
    document.getElementById('couponDiscountVal').innerText = discountAmount.toLocaleString('en-IN');
    document.getElementById('couponDiscountRow').classList.remove('hidden');
    document.getElementById('finalTotal').innerText = newTotal.toLocaleString('en-IN');
    document.getElementById('appliedCouponInput').value = validCoupon.code;
}

// ── SMS OTP Verification (apitxt.com) ────────────────────────────────────────

let smsOtpVerified = false;
let smsResendTimer = null;

function setSmsOtpMsg(text, color) {
    const el = document.getElementById('smsOtpMsg');
    el.textContent = text;
    el.style.color = color;
}

function startSmsResendCountdown(seconds) {
    const btn = document.getElementById('smsResendBtn');
    btn.classList.add('hidden');
    clearInterval(smsResendTimer);
    let t = seconds;
    smsResendTimer = setInterval(() => {
        t--;
        if (t <= 0) {
            clearInterval(smsResendTimer);
            btn.classList.remove('hidden');
        }
    }, 1000);
}

async function smsSendOTP() {
    const phone = document.getElementById('phoneInput').value.replace(/\D/g, '');
    if (phone.length !== 10) {
        alert('Please enter a valid 10-digit phone number first.');
        return;
    }

    const btn   = document.getElementById('smsSendOtpBtn');
    const label = document.getElementById('smsSendOtpLabel');
    btn.disabled       = true;
    label.textContent  = 'Sending…';

    try {
        const fd = new FormData();
        fd.append('phone',   phone);
        fd.append('context', 'checkout');
        const res  = await fetch('sms_otp_handler.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            document.getElementById('smsOtpSection').classList.remove('hidden');
            document.getElementById('smsOtpInput').focus();
            setSmsOtpMsg('✅ ' + data.message, '#15803d');
            startSmsResendCountdown(60);
            label.textContent = 'Resend OTP';
        } else {
            setSmsOtpMsg('❌ ' + data.message, '#dc2626');
            document.getElementById('smsOtpSection').classList.remove('hidden');
            document.getElementById('smsResendBtn').classList.remove('hidden');
            label.textContent = 'Send SMS OTP';
        }
    } catch (e) {
        setSmsOtpMsg('❌ Network error. Please try again.', '#dc2626');
        label.textContent = 'Send SMS OTP';
    } finally {
        btn.disabled = false;
    }
}

async function smsVerifyOTP() {
    const phone = document.getElementById('phoneInput').value.replace(/\D/g, '');
    const otp   = document.getElementById('smsOtpInput').value.trim();

    if (otp.length !== 6 || !/^\d+$/.test(otp)) {
        setSmsOtpMsg('❌ Please enter the 6-digit OTP.', '#dc2626');
        return;
    }

    const btn = document.getElementById('smsVerifyOtpBtn');
    btn.disabled    = true;
    btn.textContent = 'Verifying…';

    try {
        const fd = new FormData();
        fd.append('otp',     otp);
        fd.append('phone',   phone);
        fd.append('context', 'checkout');
        const res  = await fetch('sms_otp_handler.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            smsOtpVerified = true;
            document.getElementById('smsOtpVerifiedFlag').value = '1';
            document.getElementById('smsVerifiedBadge').classList.remove('hidden');
            document.getElementById('smsOtpSection').classList.add('hidden');
            setSmsOtpMsg('', '');
        } else {
            setSmsOtpMsg('❌ ' + data.message, '#dc2626');
            btn.disabled    = false;
            btn.textContent = 'Verify';
        }
    } catch (e) {
        setSmsOtpMsg('❌ Network error. Please try again.', '#dc2626');
        btn.disabled    = false;
        btn.textContent = 'Verify';
    }
}

function smsResendOTP() {
    document.getElementById('smsOtpInput').value = '';
    setSmsOtpMsg('', '');
    smsSendOTP();
}

function smsGuardSubmit(e) {
    if (!smsOtpVerified) {
        e.preventDefault();
        alert('Please verify your mobile number via SMS OTP before proceeding to payment.');
        document.getElementById('smsSendOtpBtn').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }
    return true;
}
</script>

<?php include 'include/footer.php'; ?>