<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
checkAuth();

$action = $_POST['action'] ?? '';

// ==========================================
// CATEGORY MANAGEMENT
// ==========================================

if ($action === 'add_category') {
    try {
        $stmt = $pdo->prepare("INSERT INTO categories (category_name, slug) VALUES (?, ?)");
        $slug = strtolower(str_replace(' ', '-', trim($_POST['category_name'])));
        $stmt->execute([trim($_POST['category_name']), $slug]);
        
        header("Location: categories.php?msg=Category+Added+Successfully");
    } catch (PDOException $e) {
        die("<div style='color:red; font-family:sans-serif; padding:20px;'><b>Database Error (Add Category):</b> " . $e->getMessage() . "</div>");
    }
    exit;
}

if ($action === 'update_category') {
    try {
        $id = $_POST['id'];
        $category_name = trim($_POST['category_name']);
        $slug = strtolower(str_replace(' ', '-', $category_name));
        
        $stmt = $pdo->prepare("UPDATE categories SET category_name = ?, slug = ? WHERE id = ?");
        $stmt->execute([$category_name, $slug, $id]);
        
        header("Location: categories.php?msg=Category+Updated+Successfully");
    } catch (PDOException $e) {
        die("<div style='color:red; font-family:sans-serif; padding:20px;'><b>Database Error (Update Category):</b> " . $e->getMessage() . "</div>");
    }
    exit;
}

if ($action === 'delete_category') {
    try {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        
        header("Location: categories.php?msg=Category+Deleted+Successfully");
    } catch (PDOException $e) {
        die("<div style='color:red; font-family:sans-serif; padding:20px;'><b>Database Error (Delete Category):</b> " . $e->getMessage() . "<br><br><i>Hint: If a category has VIP numbers assigned to it, you cannot delete it unless you reassign or delete those numbers first!</i></div>");
    }
    exit;
}

// ==========================================
// VIP NUMBER MANAGEMENT
// ==========================================

if ($action === 'add_number') {
    try {
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $discount_price = !empty($_POST['discount_price']) ? $_POST['discount_price'] : NULL;
        $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : NULL;

        $stmt = $pdo->prepare("INSERT INTO vip_numbers (category_id, mobile_number, sum_total, price, discount_price, tags, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $category_id,
            trim($_POST['mobile_number']),
            trim($_POST['sum_total']),
            trim($_POST['price']),
            $discount_price,
            trim($_POST['tags']),
            $is_featured
        ]);
        
        header("Location: numbers.php?msg=Number+Added+Successfully");
    } catch (PDOException $e) {
        die("<div style='color:red; font-family:sans-serif; padding:20px; border:1px solid red; background:#fff0f0;'>
                <h3>Database Error (Add Number)</h3>
                <p>" . $e->getMessage() . "</p>
                <p><i>Hint: Ensure you ran the ALTER TABLE command successfully in phpMyAdmin so the 'tags' and 'is_featured' columns exist!</i></p>
             </div>");
    }
    exit;
}

if ($action === 'update_number') {
    try {
        $id = $_POST['id'];
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $discount_price = !empty($_POST['discount_price']) ? $_POST['discount_price'] : NULL;
        $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : NULL;

        $stmt = $pdo->prepare("UPDATE vip_numbers SET category_id=?, mobile_number=?, sum_total=?, price=?, discount_price=?, tags=?, is_featured=? WHERE id=?");
        $stmt->execute([
            $category_id, trim($_POST['mobile_number']), trim($_POST['sum_total']), 
            trim($_POST['price']), $discount_price, trim($_POST['tags']), $is_featured, $id
        ]);
        
        header("Location: numbers.php?msg=Number+Updated+Successfully");
    } catch (PDOException $e) {
        die("<div style='color:red; font-family:sans-serif; padding:20px;'><b>Database Error (Update Number):</b> " . $e->getMessage() . "</div>");
    }
    exit;
}

if ($action === 'delete_number') {
    try {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM vip_numbers WHERE id = ?");
        $stmt->execute([$id]);
        
        header("Location: numbers.php?msg=Number+Deleted+Successfully");
    } catch (PDOException $e) {
        die("<div style='color:red; font-family:sans-serif; padding:20px;'><b>Database Error (Delete Number):</b> " . $e->getMessage() . "</div>");
    }
    exit;
}

if ($action === 'quick_status') {
    try {
        $id = $_POST['id'];
        $status = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE vip_numbers SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        header("Location: numbers.php?msg=Status+Updated+To+" . ucfirst($status));
    } catch (PDOException $e) {
        die("<div style='color:red; font-family:sans-serif; padding:20px;'><b>Database Error (Quick Status):</b> " . $e->getMessage() . "</div>");
    }
    exit;
}

// --- QUICK ORDER / PAYMENT STATUS UPDATE ---
if ($action === 'update_order_state') {
    try {
        $order_id = $_POST['order_id'];
        $status = $_POST['status'];
        $type = $_POST['type']; // 'order' or 'payment'
        
        if ($type === 'order') {
            $stmt = $pdo->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
            $msg = "Order status updated to " . strtoupper($status);
        } else if ($type === 'payment') {
            $stmt = $pdo->prepare("UPDATE orders SET payment_status = ? WHERE id = ?");
            $msg = "Payment status updated to " . strtoupper($status);
        }

        $stmt->execute([$status, $order_id]);
        header("Location: orders.php?msg=" . urlencode($msg));
    } catch (PDOException $e) {
        die("<b>Database Error (Order State):</b> " . $e->getMessage());
    }
    exit;
}

// ==========================================
// ORDER & SYSTEM MANAGEMENT
// ==========================================

if ($action === 'update_upc') {
    try {
        $order_item_id = $_POST['order_item_id'];
        $upc_code = trim($_POST['upc_code']);
        $order_id = $_POST['order_id'];

        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE order_items SET upc_code = ? WHERE id = ?");
        $stmt->execute([$upc_code, $order_item_id]);
        
        $stmt2 = $pdo->prepare("UPDATE orders SET order_status = 'upc_delivered' WHERE id = ?");
        $stmt2->execute([$order_id]);
        
        $pdo->commit();

        // Chargeback evidence + notify customer (email/WhatsApp link). Must never block delivery.
        try {
            require_once __DIR__ . '/../evidence.php';
            require_once __DIR__ . '/../mail/email_queue.php';
            ev_deliver_upc($pdo, (int) $order_id, (int) $order_item_id);
        } catch (Throwable $evErr) {
            error_log('[evidence] deliver failed: ' . $evErr->getMessage());
        }
        header("Location: orders.php?msg=UPC+Delivered+Successfully");
    } catch (Exception $e) {
        $pdo->rollBack();
        die("<div style='color:red; font-family:sans-serif; padding:20px;'><b>Database Error (Update UPC):</b> " . $e->getMessage() . "</div>");
    }
    exit;
}

if ($action === 'update_settings') {
    try {
        $settings = $_POST['settings'] ?? [];
        
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value, $value]);
        }
        
        $pdo->commit();
        header("Location: settings.php?msg=Global+Settings+Updated+Successfully");
    } catch (PDOException $e) {
        $pdo->rollBack();
        die("<div style='color:red; font-family:sans-serif; padding:20px;'><b>Database Error (Update Settings):</b> " . $e->getMessage() . "</div>");
    }
    exit;
}

// ==========================================
// ADMIN ROLES & USERS MANAGEMENT
// ==========================================

if ($action === 'add_admin') {
    try {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        
        // Hash the password securely
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO admin_users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $password_hash, $role]);
        
        header("Location: admin_roles.php?msg=New+Administrator+Created+Successfully");
    } catch (PDOException $e) {
        die("<div style='color:red; font-family:sans-serif; padding:20px;'><b>Database Error (Add Admin):</b> " . $e->getMessage() . " <br><i>Hint: Ensure the email is not already in use.</i></div>");
    }
    exit;
}

if ($action === 'update_admin') {
    try {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $password = $_POST['password'];

        if (!empty($password)) {
            // Update everything including a new password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admin_users SET name = ?, email = ?, password_hash = ?, role = ? WHERE id = ?");
            $stmt->execute([$name, $email, $password_hash, $role, $id]);
        } else {
            // Update everything EXCEPT password
            $stmt = $pdo->prepare("UPDATE admin_users SET name = ?, email = ?, role = ? WHERE id = ?");
            $stmt->execute([$name, $email, $role, $id]);
        }
        
        // If the user updated their own name, refresh the session name
        if ($id == $_SESSION['admin_id']) {
            $_SESSION['admin_name'] = $name;
        }

        header("Location: admin_roles.php?msg=Administrator+Updated+Successfully");
    } catch (PDOException $e) {
        die("<div style='color:red; font-family:sans-serif; padding:20px;'><b>Database Error (Update Admin):</b> " . $e->getMessage() . "</div>");
    }
    exit;
}

if ($action === 'delete_admin') {
    try {
        $id = $_POST['id'];
        
        // Security check: Prevent superadmin from deleting themselves!
        if ($id == $_SESSION['admin_id']) {
            die("<div style='color:red; font-family:sans-serif; padding:20px;'><b>Security Error:</b> You cannot delete your own account!</div>");
        }

        $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id = ?");
        $stmt->execute([$id]);
        
        header("Location: admin_roles.php?msg=Administrator+Access+Revoked");
    } catch (PDOException $e) {
        die("<div style='color:red; font-family:sans-serif; padding:20px;'><b>Database Error (Delete Admin):</b> " . $e->getMessage() . "</div>");
    }
    exit;
}


// ==========================================
// LEAD MANAGEMENT (CRM)
// ==========================================

if ($action === 'add_lead') {
    try {
        $stmt = $pdo->prepare("INSERT INTO leads (name, phone, number_interested, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            trim($_POST['name']),
            trim($_POST['phone']),
            trim($_POST['number_interested']),
            $_POST['status']
        ]);
        header("Location: leads.php?msg=Lead+Added+Successfully");
    } catch (PDOException $e) {
        die("<div style='color:red; font-family:sans-serif; padding:20px;'><b>Database Error (Add Lead):</b> " . $e->getMessage() . "</div>");
    }
    exit;
}

if ($action === 'update_lead') {
    try {
        $stmt = $pdo->prepare("UPDATE leads SET name=?, phone=?, number_interested=?, status=? WHERE id=?");
        $stmt->execute([
            trim($_POST['name']),
            trim($_POST['phone']),
            trim($_POST['number_interested']),
            $_POST['status'],
            $_POST['id']
        ]);
        header("Location: leads.php?msg=Lead+Updated+Successfully");
    } catch (PDOException $e) {
        die("<div style='color:red; font-family:sans-serif; padding:20px;'><b>Database Error (Update Lead):</b> " . $e->getMessage() . "</div>");
    }
    exit;
}

if ($action === 'quick_lead_status') {
    try {
        $stmt = $pdo->prepare("UPDATE leads SET status=? WHERE id=?");
        $stmt->execute([$_POST['status'], $_POST['id']]);
        header("Location: leads.php?msg=Lead+Status+Updated");
    } catch (PDOException $e) {
        die("<div style='color:red; font-family:sans-serif; padding:20px;'><b>Database Error (Update Status):</b> " . $e->getMessage() . "</div>");
    }
    exit;
}

if ($action === 'delete_lead') {
    try {
        $stmt = $pdo->prepare("DELETE FROM leads WHERE id=?");
        $stmt->execute([$_POST['id']]);
        header("Location: leads.php?msg=Lead+Deleted+Successfully");
    } catch (PDOException $e) {
        die("<div style='color:red; font-family:sans-serif; padding:20px;'><b>Database Error (Delete Lead):</b> " . $e->getMessage() . "</div>");
    }
    exit;
}

// ==========================================
// HERO SLIDER MANAGEMENT
// ==========================================

if ($action === 'add_slide') {
    try {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $button_text = trim($_POST['button_text']);
        $button_link = trim($_POST['button_link']);
        $show_whatsapp = isset($_POST['show_whatsapp']) ? 1 : 0;
        
        // Handle File Upload
        $image_name = '';
        if (isset($_FILES['slide_image']) && $_FILES['slide_image']['error'] == 0) {
            $upload_dir = '../uploads/sliders/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true); // Create directory if not exists
            
            // Generate unique file name
            $file_ext = pathinfo($_FILES['slide_image']['name'], PATHINFO_EXTENSION);
            $image_name = 'slide_' . time() . '.' . $file_ext;
            $target_file = $upload_dir . $image_name;
            
            // Allow only images
            $check = getimagesize($_FILES["slide_image"]["tmp_name"]);
            if($check !== false) {
                move_uploaded_file($_FILES["slide_image"]["tmp_name"], $target_file);
            } else {
                die("File is not an image.");
            }
        } else {
            die("Please upload a valid image.");
        }

        $stmt = $pdo->prepare("INSERT INTO sliders (title, description, show_whatsapp, button_text, button_link, image_path) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $show_whatsapp, $button_text, $button_link, $image_name]);
        
        header("Location: sliders.php?msg=Slide+Uploaded+Successfully");
    } catch (PDOException $e) {
        die("<b>Database Error (Add Slide):</b> " . $e->getMessage());
    }
    exit;
}

if ($action === 'toggle_slide') {
    try {
        $id = $_POST['id'];
        $new_status = $_POST['current_status'] == 1 ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE sliders SET is_active = ? WHERE id = ?");
        $stmt->execute([$new_status, $id]);
        header("Location: sliders.php?msg=Slide+Visibility+Updated");
    } catch (PDOException $e) {
        die("<b>Database Error:</b> " . $e->getMessage());
    }
    exit;
}

if ($action === 'delete_slide') {
    try {
        $id = $_POST['id'];
        $image_path = $_POST['image_path'];
        
        // Delete physical file if it's not an external URL (unsplash link)
        if (strpos($image_path, 'http') === false && file_exists('../uploads/sliders/' . $image_path)) {
            unlink('../uploads/sliders/' . $image_path);
        }

        $stmt = $pdo->prepare("DELETE FROM sliders WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: sliders.php?msg=Slide+Deleted+Successfully");
    } catch (PDOException $e) {
        die("<b>Database Error:</b> " . $e->getMessage());
    }
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Fallback if no matching action
header("Location: dashboard.php");
exit;
?>