/**
 * Pascommerce UI Script
 * Handles Gallery, Quantity, Cart AJAX, Checkout, and Dynamic Currency
 */

// --- 1. HELPER: Format Money (Dynamic based on Admin Settings) ---
function pcFormatMoney(number) {
    // Ambil setting dari PHP (wp_localize_script)
    var settings = typeof pc_vars !== 'undefined' ? pc_vars.currency : null;
    
    // Default fallback jika setting gagal load
    if (!settings) {
        return number;
    }

    var decimals = settings.decimals;
    var dec_point = settings.decimal_sep;
    var thousands_sep = settings.thousand_sep;

    // Strip non-numeric chars
    number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
    var n = !isFinite(+number) ? 0 : +number,
        prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
        sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
        dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
        s = '',
        toFixedFix = function(n, prec) {
            var k = Math.pow(10, prec);
            return '' + Math.round(n * k) / k;
        };

    // Fix floating point precision
    s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
    
    // Add thousands separator
    if (s[0].length > 3) {
        s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
    }
    
    // Add decimals
    if ((s[1] || '').length < prec) {
        s[1] = s[1] || '';
        s[1] += new Array(prec - s[1].length + 1).join('0');
    }
    
    var formattedValue = s.join(dec);

    // Apply Position
    switch (settings.position) {
        case 'left': return settings.symbol + formattedValue;
        case 'right': return formattedValue + settings.symbol;
        case 'left_space': return settings.symbol + ' ' + formattedValue;
        case 'right_space': return formattedValue + ' ' + settings.symbol;
        default: return settings.symbol + formattedValue;
    }
}

// --- 2. HELPER: Gallery Logic (Thumbnails) ---
function changeImage(thumb) {
    var main = document.getElementById('pc-main-display');
    if(main) {
        // Efek transisi halus
        main.style.opacity = 0.7;
        setTimeout(function(){ 
            main.src = thumb.dataset.src || thumb.src;
            main.style.opacity = 1;
        }, 150);

        // Update class active
        var thumbs = document.querySelectorAll('.pc-thumb');
        thumbs.forEach(t => t.classList.remove('active'));
        thumb.classList.add('active');
        
        // Scroll thumbnail container agar item aktif terlihat
        thumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    }
}

// --- 3. HELPER: Gallery Navigation (Main Arrows) ---
function navGallery(direction) {
    var activeThumb = document.querySelector('.pc-thumb.active');
    if (!activeThumb) return;

    var allThumbs = Array.from(document.querySelectorAll('.pc-thumb'));
    var currentIndex = allThumbs.indexOf(activeThumb);
    var nextIndex = currentIndex + direction;

    // Loop logic (Infinite Scroll)
    if (nextIndex >= allThumbs.length) {
        nextIndex = 0;
    } else if (nextIndex < 0) {
        nextIndex = allThumbs.length - 1;
    }

    // Trigger klik pada thumbnail target
    var targetThumb = allThumbs[nextIndex];
    if (targetThumb) {
        targetThumb.click();
    }
}

// --- 4. HELPER: Thumbnail Strip Scroll (Buttons) ---
function scrollThumbs(direction) {
    var container = document.getElementById('pc-thumbs-container');
    if(container) {
        var scrollAmount = 150; // Jarak scroll sekali klik
        container.scrollLeft += (direction * scrollAmount);
    }
}

// --- 5. HELPER: Quantity Control ---
function updateQty(change) {
    var input = document.getElementById('pc-qty');
    if(input) {
        var val = parseInt(input.value) || 1;
        var newVal = val + change;
        if(newVal >= 1) input.value = newVal;
    }
}

// --- 6. MAIN JQUERY EXECUTION ---
jQuery(document).ready(function($) {
    
    // A. Add to Cart (Single Product & Grid)
    $(document).on('click', '.pc-add-cart-single, .pc-btn-add', function(e) {
        e.preventDefault();
        var btn = $(this);
        var originalText = btn.text();
        var pid = btn.data('id');
        
        // Ambil qty jika ada input qty (Single Page), default 1 (Grid/Archive)
        var qtyInput = $('#pc-qty');
        var qty = qtyInput.length ? qtyInput.val() : 1; 
        
        // Cek Variasi (Hanya di Single Product Page)
        var variantSelect = $('#pc-variant-select');
        if(variantSelect.length > 0 && variantSelect.val() === "") {
            alert("Please select an option/variation first.");
            variantSelect.focus();
            return;
        }

        // Loading State
        btn.text('Adding...').prop('disabled', true).css('opacity', '0.7');
        
        $.post(pc_vars.ajax_url, {
            action: 'pc_add_to_cart',
            pid: pid,
            qty: qty,
            nonce: pc_vars.nonce // Security Nonce
        }, function(res) {
            if(res.success) {
                // Success Feedback
                btn.text('Added!').css('background', '#16a34a').css('opacity', '1');
                
                // Reset Button after 2 seconds
                setTimeout(function(){ 
                    btn.text(originalText).css('background', '').prop('disabled', false); 
                }, 2000);
            } else {
                alert('Error adding to cart');
                btn.text(originalText).prop('disabled', false).css('opacity', '1');
            }
        }).fail(function() {
            alert('Server Error');
            btn.text(originalText).prop('disabled', false).css('opacity', '1');
        });
    });

    // B. Dynamic Price Update (Variants)
    $('#pc-variant-select').change(function(){
        // Ambil data harga dari attribut data-price di option
        var price = $(this).find(':selected').data('price');
        
        if(price !== undefined && price !== "") {
            // Gunakan fungsi format dinamis kita
            var formattedPrice = pcFormatMoney(price);
            
            // Update elemen harga UI
            $('.pc-product-price').fadeOut(100, function() {
                $(this).text(formattedPrice).fadeIn(100);
            });
        }
    });

    // C. CHECKOUT PROCESS (Submit Handler)
    $(document).on('submit', '#pc-checkout-form', function(e) {
        e.preventDefault(); // Mencegah reload halaman
        
        var form = $(this);
        var btn = $('#pc-place-order');
        var msg = $('#pc-msg');
        var originalText = btn.text();
        
        btn.text('Processing...').prop('disabled', true);
        msg.html(''); // Bersihkan pesan lama
        
        $.post(pc_vars.ajax_url, {
            action: 'pc_process_checkout',
            nonce: pc_vars.nonce,
            form_data: form.serialize()
        }, function(res) {
            if(res.success) {
                msg.html('<div class="pc-alert" style="background:#dcfce7; color:#166534; border-color:#86efac;">Order Success! Redirecting...</div>');
                // Redirect ke halaman sukses / pembayaran
                window.location.href = res.data.redirect;
            } else {
                msg.html('<div class="pc-alert" style="background:#fee2e2; color:#991b1b; border-color:#fca5a5;">Error: ' + res.data + '</div>');
                btn.text(originalText).prop('disabled', false);
            }
        }).fail(function(){
            msg.html('<div class="pc-alert" style="background:#fee2e2; color:#991b1b; border-color:#fca5a5;">Server Connection Error.</div>');
            btn.text(originalText).prop('disabled', false);
        });
    });

    // Make functions global so inline 'onclick' HTML attributes work
    window.changeImage = changeImage;
    window.updateQty = updateQty;
    window.navGallery = navGallery;
    window.scrollThumbs = scrollThumbs;

});