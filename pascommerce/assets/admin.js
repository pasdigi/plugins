jQuery(document).ready(function($){
    // 1. Tabs Switching
    $('.pc-meta-tabs li').click(function(){
        $('.pc-meta-tabs li').removeClass('active');
        $(this).addClass('active');
        $('.pc-tab-pane').hide();
        $('#pc-tab-' + $(this).data('tab')).show();
    });

    // 2. Product Type Switching
    $('#pc_type_selector').change(function(){
        var type = $(this).val();
        if(type === 'variable') {
            $('#pc-tab-trigger-variants').show();
            $('#pc-variants-wrapper').show();
            $('#pc-variants-warning').hide();
            $('.pc-pricing-group').hide(); // Hide simple price
        } else {
            $('#pc-variants-wrapper').hide();
            $('#pc-variants-warning').show();
            $('.pc-pricing-group').show();
        }
    }).change();

    // 3. Add Variant (JS based repeater)
    $('#pc-add-variant').click(function(){
        var index = $('.pc-variant-item').length;
        var html = `
        <div class="pc-variant-item">
            <div class="pc-var-header">
                <strong>New Variation</strong>
                <button type="button" class="button pc-remove-var">&times;</button>
            </div>
            <div class="pc-var-body">
                <div><label>Name</label><input type="text" name="pc_vars[${index}][name]" class="widefat" placeholder="Size: M"></div>
                <div><label>Price</label><input type="number" name="pc_vars[${index}][price]" class="widefat"></div>
                <div><label>Stock</label><input type="number" name="pc_vars[${index}][stock]" class="widefat"></div>
            </div>
        </div>`;
        $('#pc-variants-list').append(html);
    });

    $(document).on('click', '.pc-remove-var', function(){
        $(this).closest('.pc-variant-item').remove();
    });

    // 4. Gallery (WP Media Uploader)
    var frame;
    $('#pc-add-gallery').click(function(e){
        e.preventDefault();
        if(frame) { frame.open(); return; }
        
        frame = wp.media({
            title: 'Select Product Images',
            button: { text: 'Add to Gallery' },
            multiple: true
        });

        frame.on('select', function(){
            var selection = frame.state().get('selection');
            selection.map(function(attachment){
                attachment = attachment.toJSON();
                $('.pc-gallery-preview').append(`
                    <div class="pc-gal-item">
                        <img src="${attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url}">
                        <span class="pc-gal-remove">&times;</span>
                        <input type="hidden" name="pc_gallery[]" value="${attachment.id}">
                    </div>
                `);
            });
        });
        frame.open();
    });

    $(document).on('click', '.pc-gal-remove', function(){
        $(this).closest('.pc-gal-item').remove();
    });
});