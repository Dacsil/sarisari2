<?php
$logoPath = __DIR__ . '/assets/logo.png';
$logoB64  = file_exists($logoPath)
    ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
    : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bords Motorparts; Inventory</title>
<?php if ($logoB64): ?>
<link rel="icon" type="image/png" href="assets/logo.png">
<?php endif; ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- ══ NAVBAR ══════════════════════════════════════════════════════ -->
<nav class="pos-navbar">
  <div class="nav-top">
    <?php if ($logoB64): ?>
      <img src="<?= $logoB64 ?>" alt="Logo" class="store-logo">
    <?php else: ?>
      <div class="store-logo-ph"><i class="bi bi-shop"></i></div>
    <?php endif; ?>
    <div>
      <div class="store-name">Bords Motorparts</div>
      <div class="store-sub">Inventory &amp; POS</div>
    </div>

    <div class="search-wrap mx-auto">
      <i class="bi bi-search si"></i>
      <input type="text" id="searchQ" class="search-input" placeholder="Search product...">
    </div>

    <button class="btn-addnav" onclick="App.openAdd()">
      <i class="bi bi-plus-lg"></i> Add Product
    </button>
  </div>

  <div class="cat-bar" id="catBar">
    <button class="cat-btn active" data-cat="All Items">All Items</button>
    <button class="cat-btn" data-cat="tire">tire</button>
    <button class="cat-btn" data-cat="Interior">Interior</button>
    <button class="cat-btn" data-cat="Canned &amp; Instant Foods">Canned &amp; Instant Foods</button>
    <button class="cat-btn" data-cat="Personal Care">Personal Care</button>
    <button class="cat-btn" data-cat="Household Supplies">Household Supplies</button>
  </div>
</nav>

<!-- ══ BODY ════════════════════════════════════════════════════════ -->
<div class="pos-body">

  <!-- LEFT: product grid -->
  <section class="products-section">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <span class="sec-title" id="catLabel">All Items</span>
      <span class="text-muted small" id="prodCount"></span>
    </div>
    <div id="loadingState" class="text-center py-5 text-muted">
      <div class="spinner-border spinner-border-sm me-2"></div>Loading products…
    </div>
    <div class="product-grid" id="productGrid"></div>
    <div id="emptyState" class="empty-state d-none">
      <i class="bi bi-search fs-1"></i>
      <p class="mt-2">No products found</p>
    </div>
  </section>

  <!-- RIGHT: cart -->
  <aside class="cart-panel">
    <div class="cart-head">
      <i class="bi bi-receipt me-2"></i>Order Summary
      <button class="btn-clear ms-auto" onclick="Cart.clear()" title="Clear cart">
        <i class="bi bi-trash3"></i>
      </button>
    </div>
    <div class="cart-body" id="cartBody">
      <div class="cart-empty" id="cartEmpty">
        <i class="bi bi-cart-x fs-2 text-muted d-block mb-2"></i>
        <small class="text-muted">No items yet</small>
      </div>
    </div>
    <div class="cart-foot">
      <div class="cf-row"><span>Subtotal</span><span id="cfSub">₱0.00</span></div>
      <div class="cf-row">
        <label class="d-flex align-items-center gap-2 cursor-pointer">
          <input type="checkbox" id="vatChk" onchange="Cart.recalc()"> VAT (12%)
        </label>
        <span id="cfVat">₱0.00</span>
      </div>
      <div class="cf-total">
        <span>Total</span>
        <span class="cf-total-amt" id="cfTotal">₱0.00</span>
      </div>
      <button class="btn-pay w-100" id="btnPay" disabled onclick="Payment.open()">
        <i class="bi bi-cash-coin me-2"></i>Proceed to Payment
      </button>
    </div>
  </aside>

</div><!-- /pos-body -->

<!-- ══ PRODUCT MODAL ════════════════════════════════════════════════ -->
<div class="modal fade" id="prodModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="prodModalTitle">Add Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="fId">

        <div class="mb-3">
          <label class="form-label">Product photo</label>
          <div class="img-drop" id="imgDrop" onclick="document.getElementById('fImage').click()">
            <img id="imgPreview" class="d-none" src="" alt="preview">
            <div id="imgPh">
              <i class="bi bi-camera fs-3 text-muted d-block mb-1"></i>
              <small class="text-muted">Click to upload (JPG/PNG/WEBP, max 5MB)</small>
            </div>
            <input type="file" id="fImage" accept="image/*" class="d-none" onchange="App.previewImg(this)">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Product name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="fName" placeholder="e.g. Coke Mismo">
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label">Price (₱) <span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="fPrice" min="0" step="0.01" placeholder="0.00">
          </div>
          <div class="col-6">
            <label class="form-label">Stock <span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="fStock" min="0" placeholder="0">
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label">Category</label>
          <select class="form-select" id="fCat">
            <option>Beverages</option>
            <option>Snacks</option>
            <option>Canned &amp; Instant Foods</option>
            <option>Personal Care</option>
            <option>Household Supplies</option>
            <option>Other</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="btnSave" onclick="App.save()">
          <i class="bi bi-check-lg me-1"></i><span id="btnSaveTxt">Save Product</span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ══ DELETE MODAL ═════════════════════════════════════════════════ -->
<div class="modal fade" id="delModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title text-danger">
          <i class="bi bi-exclamation-triangle me-1"></i>Delete Product
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-2">
        <p class="small text-muted mb-0">
          Delete <strong id="delName"></strong>? This cannot be undone.
        </p>
      </div>
      <div class="modal-footer border-0 pt-1">
        <button class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-sm btn-danger" id="btnDelConfirm" onclick="App.confirmDelete()">
          <i class="bi bi-trash3 me-1"></i>Delete
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ══ PAYMENT MODAL ════════════════════════════════════════════════ -->
<div class="modal fade" id="payModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title">
          <i class="bi bi-cash-coin me-2 text-success"></i>Payment
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="receipt" id="receiptBody"></div>
        <label class="form-label fw-bold mt-3">Cash Received (₱)</label>
        <input type="number" class="form-control form-control-lg text-end fw-bold"
               id="cashIn" placeholder="0.00" oninput="Payment.calcChange()">
        <div class="change-box mt-2">
          <span class="fw-bold">Change</span>
          <span class="change-amt" id="changeAmt">₱0.00</span>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success px-4" onclick="Payment.confirm()">
          <i class="bi bi-check2-circle me-1"></i>Confirm Payment
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Toast -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:9999">
  <div id="appToast" class="toast align-items-center text-white border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body fw-500" id="toastMsg"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
