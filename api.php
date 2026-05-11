<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {

        // ── LIST ALL / FILTERED ────────────────────────────────────────
        case 'list':
            $cat    = trim($_GET['category'] ?? '');
            $search = trim($_GET['search']   ?? '');
            $sql    = 'SELECT * FROM products WHERE 1=1';
            $params = [];
            $types  = '';

            if ($cat && $cat !== 'All Items') {
                $sql    .= ' AND category = ?';
                $params[] = $cat;
                $types   .= 's';
            }
            if ($search !== '') {
                $sql    .= ' AND product_name LIKE ?';
                $params[] = '%' . $search . '%';
                $types   .= 's';
            }
            $sql .= ' ORDER BY category, product_name';

            $stmt = $conn->prepare($sql);
            if ($params) $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'products' => $rows]);
            break;

        // ── GET SINGLE ─────────────────────────────────────────────────
        case 'get':
            $id   = intval($_GET['id'] ?? 0);
            if (!$id) throw new Exception('Invalid ID');
            $stmt = $conn->prepare('SELECT * FROM products WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row  = $stmt->get_result()->fetch_assoc();
            if (!$row) throw new Exception('Product not found');
            echo json_encode(['success' => true, 'product' => $row]);
            break;

        // ── CREATE ──────────────────────────────────────────────────────
        case 'create':
            $name  = trim($_POST['product_name'] ?? '');
            $price = floatval($_POST['price']    ?? 0);
            $stock = intval($_POST['stock']      ?? 0);
            $cat   = trim($_POST['category']     ?? 'Other');

            if ($name === '') throw new Exception('Product name is required');
            if ($price < 0)   throw new Exception('Price cannot be negative');
            if ($stock < 0)   throw new Exception('Stock cannot be negative');

            $image = null;
            if (!empty($_FILES['image']['name'])) {
                $image = uploadImage($_FILES['image']);
            }

            $stmt = $conn->prepare(
                'INSERT INTO products (product_name, price, stock, category, image) VALUES (?,?,?,?,?)'
            );
            $stmt->bind_param('sdiss', $name, $price, $stock, $cat, $image);
            $stmt->execute();

            echo json_encode([
                'success' => true,
                'message' => 'Product added successfully',
                'id'      => $conn->insert_id
            ]);
            break;

        // ── UPDATE ──────────────────────────────────────────────────────
        case 'update':
            $id    = intval($_POST['id']           ?? 0);
            $name  = trim($_POST['product_name']   ?? '');
            $price = floatval($_POST['price']       ?? 0);
            $stock = intval($_POST['stock']         ?? 0);
            $cat   = trim($_POST['category']        ?? 'Other');

            if (!$id)         throw new Exception('Invalid product ID');
            if ($name === '') throw new Exception('Product name is required');

            // Fetch existing image path
            $s = $conn->prepare('SELECT image FROM products WHERE id = ?');
            $s->bind_param('i', $id);
            $s->execute();
            $existing = $s->get_result()->fetch_assoc();
            if (!$existing) throw new Exception('Product not found');

            $image = $existing['image'];

            // Replace image only if a new file is uploaded
            if (!empty($_FILES['image']['name'])) {
                $newImage = uploadImage($_FILES['image']);
                // Delete old file safely
                if ($image) {
                    $oldPath = UPLOAD_DIR . basename($image);
                    if (file_exists($oldPath)) @unlink($oldPath);
                }
                $image = $newImage;
            }

            $stmt = $conn->prepare(
                'UPDATE products SET product_name=?, price=?, stock=?, category=?, image=? WHERE id=?'
            );
            $stmt->bind_param('sdissi', $name, $price, $stock, $cat, $image, $id);
            $stmt->execute();

            if ($stmt->affected_rows === 0 && $conn->errno !== 0) {
                throw new Exception('Update failed');
            }

            echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
            break;

        // ── DELETE ──────────────────────────────────────────────────────
        case 'delete':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) throw new Exception('Invalid product ID');

            // Get image before deleting
            $s = $conn->prepare('SELECT image FROM products WHERE id = ?');
            $s->bind_param('i', $id);
            $s->execute();
            $row = $s->get_result()->fetch_assoc();
            if (!$row) throw new Exception('Product not found');

            $stmt = $conn->prepare('DELETE FROM products WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();

            if ($stmt->affected_rows < 1) throw new Exception('Delete failed');

            // Remove image file
            if (!empty($row['image'])) {
                $filePath = UPLOAD_DIR . basename($row['image']);
                if (file_exists($filePath)) @unlink($filePath);
            }

            echo json_encode(['success' => true, 'message' => 'Product deleted']);
            break;

        default:
            throw new Exception('Unknown action: ' . htmlspecialchars($action));
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ── IMAGE UPLOAD HELPER ────────────────────────────────────────────────
function uploadImage(array $file): string {
    global $allowedTypes;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => 'File too large (server limit)',
            UPLOAD_ERR_FORM_SIZE  => 'File too large (form limit)',
            UPLOAD_ERR_PARTIAL    => 'File only partially uploaded',
            UPLOAD_ERR_NO_FILE    => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by extension',
        ];
        throw new Exception($errors[$file['error']] ?? 'Upload error code ' . $file['error']);
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('Image too large. Maximum is 5MB.');
    }

    // Verify MIME by reading file magic bytes — not just the extension
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, $allowedTypes, true)) {
        throw new Exception('Invalid file type. Allowed: JPG, PNG, GIF, WEBP.');
    }

    if (!is_dir(UPLOAD_DIR)) {
        if (!mkdir(UPLOAD_DIR, 0755, true)) {
            throw new Exception('Cannot create uploads directory');
        }
    }
    if (!is_writable(UPLOAD_DIR)) {
        throw new Exception('Uploads directory is not writable. Run: chmod 755 uploads/');
    }

    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'prod_' . uniqid('', true) . '.' . $ext;
    $dest     = UPLOAD_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new Exception('Failed to save uploaded file');
    }

    return UPLOAD_URL . $filename;
}
?>
