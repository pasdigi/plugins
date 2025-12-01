<?php
/**
 * The template for displaying all single products
 * Updated for Block Theme (FSE) Compatibility
 */

defined( 'ABSPATH' ) || exit;

// 1. HEADER LOADER
if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
    // Jika Block Theme (FSE), load block template part 'header'
    block_template_part( 'header' );
} else {
    // Jika Classic Theme, gunakan standar get_header()
    get_header();
}
?>

<div id="primary" class="content-area pc-primary-container">
    <main id="main" class="site-main">

        <?php
        while ( have_posts() ) :
            the_post();
            
            // Konten Produk (Di-inject oleh Plugin UI)
            ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <div class="entry-content">
                    <?php the_content(); ?>
                </div>
            </article>
            <?php

        endwhile; 
        ?>

    </main>
</div>

<?php
// 2. FOOTER LOADER
if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
    block_template_part( 'footer' );
} else {
    get_footer();
}
}