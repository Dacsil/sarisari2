'use strict';

/* ═══════════════════════════════════════════════════
   UTILITIES
═══════════════════════════════════════════════════ */
const $ = id => document.getElementById(id);

function fmt(n) {
  return '₱' + parseFloat(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function esc(s) {
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}

let _toastInst;
function toast(msg, type = 'info') {
  const el  = $('appToast');
  const map = { success: '#198754', danger: '#dc3545', info: '#1a6fc4', warning: '#f5a623' };
  el.style.background = map[type] || map.info;
  $('toastMsg').textContent = msg;
  if (!_toastInst) _toastInst = new bootstrap.Toast(el, { delay: 3000 });
  _toastInst.show();
}

/* ═══════════════════════════════════════════════════
   API HELPER — single fetch wrapper
═══════════════════════════════════════════════════ */
const API = {
  async get(action, params = {}) {
    const url = new URL('api.php', location.href);
    url.searchParams.set('action', action);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
    const r = await fetch(url);
    const data = await r.json();
    if (!data.success) throw new Error(data.message || 'Request failed');
    return data;
  },

  async post(action, formData) {
    formData.append('action', action);
    const r = await fetch('api.php', { method: 'POST', body: formData });
    const data = await r.json();
    if (!data.success) throw new Error(data.message || 'Request failed');
    return data;
  }
};

/* ═══════════════════════════════════════════════════
   PRODUCT LIST + RENDERING
═══════════════════════════════════════════════════ */
let allProducts = [];
let currentCat  = 'All Items';

async function loadProducts() {
  $('loadingState').classList.remove('d-none');
  $('productGrid').innerHTML  = '';
  $('emptyState').classList.add('d-none');
  try {
    const data = await API.get('list');
    allProducts = data.products;
    renderProducts();
  } catch (e) {
    toast('Failed to load products: ' + e.message, 'danger');
  } finally {
    $('loadingState').classList.add('d-none');
  }
}

function renderProducts() {
  const q    = $('searchQ').value.trim().toLowerCase();
  const list = allProducts.filter(p =>
    (currentCat === 'All Items' || p.category === currentCat) &&
    (!q || p.product_name.toLowerCase().includes(q))
  );

  $('prodCount').textContent  = list.length + ' item' + (list.length !== 1 ? 's' : '');
  $('catLabel').textContent   = currentCat;

  if (!list.length) {
    $('productGrid').innerHTML = '';
    $('emptyState').classList.remove('d-none');
    return;
  }
  $('emptyState').classList.add('d-none');

  $('productGrid').innerHTML = list.map(p => {
    const out = p.stock <= 0;
    const low = p.stock > 0 && p.stock <= 5;
    const sc  = out ? 'stk-out' : low ? 'stk-low' : 'stk-ok';
    const sl  = out ? 'Out of stock' : (low ? p.stock + ' left' : p.stock + ' in stock');
    const img = p.image
      ? `<img src="${esc(p.image)}" alt="${esc(p.product_name)}" loading="lazy">`
      : '🛒';
    return `
    <div class="pcard" data-id="${p.id}">
      <div class="pcard-img">${img}</div>
      <div class="pcard-body">
        <div class="pcard-name">${esc(p.product_name)}</div>
        <div class="pcard-price">${fmt(p.price)}</div>
        <span class="stk ${sc}">${sl}</span>
      </div>
      <div class="pcard-actions">
        <button class="btn-cart" onclick="Cart.add(${p.id})" ${out ? 'disabled' : ''}>
          <i class="bi bi-plus-lg"></i> Add
        </button>
        <button class="btn-ic edit" title="Edit" onclick="App.openEdit(${p.id})">
          <i class="bi bi-pencil"></i>
        </button>
        <button class="btn-ic del" title="Delete" onclick="App.openDel(${p.id})">
          <i class="bi bi-trash3"></i>
        </button>
      </div>
    </div>`;
  }).join('');
}

// Category tabs
document.getElementById('catBar').addEventListener('click', e => {
  const btn = e.target.closest('.cat-btn');
  if (!btn) return;
  document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  currentCat = btn.dataset.cat;
  renderProducts();
});

// Search
let _sq;
$('searchQ').addEventListener('input', () => { clearTimeout(_sq); _sq = setTimeout(renderProducts, 250); });

/* ═══════════════════════════════════════════════════
   CART
═══════════════════════════════════════════════════ */
const Cart = (() => {
  let items = {};    // { id: { product, qty } }

  function add(id) {
    const p = allProducts.find(x => x.id == id);
    if (!p || p.stock <= 0) return;
    if (items[id]) {
      if (items[id].qty >= p.stock) { toast(`Max stock reached (${p.stock})`, 'danger'); return; }
      items[id].qty++;
    } else {
      items[id] = { product: p, qty: 1 };
    }
    render();
    toast(`${p.product_name} added to cart`, 'success');
  }

  function changeQty(id, delta) {
    if (!items[id]) return;
    items[id].qty += delta;
    if (items[id].qty <= 0) delete items[id];
    else if (items[id].qty > items[id].product.stock) items[id].qty = items[id].product.stock;
    render();
  }

  function clear() { items = {}; render(); }

  function render() {
    const keys   = Object.keys(items);
    const body   = $('cartBody');
    const empty  = $('cartEmpty');

    if (!keys.length) {
      body.innerHTML = '';
      body.appendChild(empty);
      recalc();
      return;
    }

    body.innerHTML = keys.map(id => {
      const { product: p, qty } = items[id];
      const imgEl = p.image
        ? `<div class="ci-img"><img src="${esc(p.image)}" alt="${esc(p.product_name)}"></div>`
        : `<div class="ci-img">🛒</div>`;
      return `
      <div class="cart-item">
        ${imgEl}
        <div class="ci-info">
          <div class="ci-name">${esc(p.product_name)}</div>
          <div class="ci-price">${fmt(p.price * qty)}</div>
        </div>
        <div class="qty-ctrl">
          <button class="qty-btn" onclick="Cart.changeQty(${id},-1)">−</button>
          <span class="qty-n">${qty}</span>
          <button class="qty-btn" onclick="Cart.changeQty(${id},1)">+</button>
        </div>
      </div>`;
    }).join('');

    recalc();
  }

  function recalc() {
    const sub   = Object.values(items).reduce((s, { product: p, qty }) => s + parseFloat(p.price) * qty, 0);
    const vat   = $('vatChk').checked ? sub * 0.12 : 0;
    const total = sub + vat;
    $('cfSub').textContent   = fmt(sub);
    $('cfVat').textContent   = fmt(vat);
    $('cfTotal').textContent = fmt(total);
    $('btnPay').disabled     = keys().length === 0;
    return { sub, vat, total };
  }

  function keys()  { return Object.keys(items); }
  function getAll(){ return items; }

  return { add, changeQty, clear, render, recalc, getAll };
})();

/* ═══════════════════════════════════════════════════
   APP — ADD / EDIT / DELETE
═══════════════════════════════════════════════════ */
let _prodModal, _delModal;
let _editId   = null;
let _deleteId = null;

const App = (() => {

  function _getModal()  { return _prodModal || (_prodModal = new bootstrap.Modal($('prodModal'))); }
  function _getDelModal(){ return _delModal  || (_delModal  = new bootstrap.Modal($('delModal'))); }

  function _resetForm() {
    $('fId').value    = '';
    $('fName').value  = '';
    $('fPrice').value = '';
    $('fStock').value = '';
    $('fCat').value   = 'Beverages';
    $('imgPreview').src = '';
    $('imgPreview').classList.add('d-none');
    $('imgPh').style.display = '';
    $('fImage').value = '';
  }

  function openAdd() {
    _editId = null;
    $('prodModalTitle').textContent = 'Add Product';
    $('btnSaveTxt').textContent     = 'Save Product';
    _resetForm();
    _getModal().show();
  }

  async function openEdit(id) {
    try {
      const data = await API.get('get', { id });
      const p    = data.product;
      _editId = id;
      $('prodModalTitle').textContent = 'Edit Product';
      $('btnSaveTxt').textContent     = 'Update Product';
      $('fId').value    = p.id;
      $('fName').value  = p.product_name;
      $('fPrice').value = p.price;
      $('fStock').value = p.stock;
      $('fCat').value   = p.category;
      if (p.image) {
        $('imgPreview').src = p.image;
        $('imgPreview').classList.remove('d-none');
        $('imgPh').style.display = 'none';
      } else {
        $('imgPreview').classList.add('d-none');
        $('imgPh').style.display = '';
      }
      $('fImage').value = '';
      _getModal().show();
    } catch (e) {
      toast('Could not load product: ' + e.message, 'danger');
    }
  }

  function previewImg(input) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
      $('imgPreview').src = e.target.result;
      $('imgPreview').classList.remove('d-none');
      $('imgPh').style.display = 'none';
    };
    reader.readAsDataURL(file);
  }

  async function save() {
    const name  = $('fName').value.trim();
    const price = $('fPrice').value;
    const stock = $('fStock').value;
    if (!name)  { toast('Product name is required', 'danger'); return; }
    if (price === '') { toast('Price is required', 'danger'); return; }
    if (stock === '') { toast('Stock is required', 'danger'); return; }

    const btn = $('btnSave');
    btn.disabled = true;
    $('btnSaveTxt').textContent = 'Saving…';

    const fd = new FormData();
    fd.append('product_name', name);
    fd.append('price',  price);
    fd.append('stock',  stock);
    fd.append('category', $('fCat').value);
    if ($('fImage').files[0]) fd.append('image', $('fImage').files[0]);
    if (_editId) fd.append('id', _editId);

    try {
      const action = _editId ? 'update' : 'create';
      await API.post(action, fd);
      _getModal().hide();
      toast(_editId ? 'Product updated!' : 'Product added!', 'success');
      await loadProducts();
    } catch (e) {
      toast(e.message, 'danger');
    } finally {
      btn.disabled = false;
      $('btnSaveTxt').textContent = _editId ? 'Update Product' : 'Save Product';
    }
  }

  function openDel(id) {
    const p = allProducts.find(x => x.id == id);
    if (!p) return;
    _deleteId = id;
    $('delName').textContent = p.product_name;
    _getDelModal().show();
  }

  async function confirmDelete() {
    if (!_deleteId) return;
    const btn = $('btnDelConfirm');
    btn.disabled = true;
    const fd = new FormData();
    fd.append('id', _deleteId);
    try {
      await API.post('delete', fd);
      _getDelModal().hide();
      // Remove from cart if present
      delete Cart.getAll()[_deleteId];
      Cart.render();
      toast('Product deleted', 'info');
      await loadProducts();
    } catch (e) {
      toast(e.message, 'danger');
    } finally {
      btn.disabled = false;
      _deleteId = null;
    }
  }

  return { openAdd, openEdit, previewImg, save, openDel, confirmDelete };
})();

/* ═══════════════════════════════════════════════════
   PAYMENT
═══════════════════════════════════════════════════ */
let _payModal;
const Payment = (() => {
  function _getModal() { return _payModal || (_payModal = new bootstrap.Modal($('payModal'))); }

  function open() {
    const { sub, vat, total } = Cart.recalc();
    const cartItems = Cart.getAll();

    $('receiptBody').innerHTML =
      Object.values(cartItems).map(({ product: p, qty }) =>
        `<div class="r-row"><span>${esc(p.product_name)} ×${qty}</span><span>${fmt(parseFloat(p.price)*qty)}</span></div>`
      ).join('') +
      `<div class="r-div"></div>
       <div class="r-row"><span class="text-muted">Subtotal</span><span>${fmt(sub)}</span></div>` +
      (vat ? `<div class="r-row"><span class="text-muted">VAT (12%)</span><span>${fmt(vat)}</span></div>` : '') +
      `<div class="r-div"></div>
       <div class="r-row r-total"><span>Total</span><span>${fmt(total)}</span></div>`;

    $('cashIn').value = '';
    $('cashIn').dataset.total = total;
    $('changeAmt').textContent = '₱0.00';
    _getModal().show();
  }

  function calcChange() {
    const total  = parseFloat($('cashIn').dataset.total || 0);
    const cash   = parseFloat($('cashIn').value || 0);
    $('changeAmt').textContent = cash >= total ? fmt(cash - total) : '—';
  }

  function confirm() {
    const total = parseFloat($('cashIn').dataset.total || 0);
    const cash  = parseFloat($('cashIn').value || 0);
    if (cash < total) { toast('Cash received is less than the total!', 'danger'); return; }
    _getModal().hide();
    Cart.clear();
    toast('Payment confirmed! Thank you 🎉', 'success');
  }

  return { open, calcChange, confirm };
})();

/* ═══════════════════════════════════════════════════
   INIT
═══════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', loadProducts);
