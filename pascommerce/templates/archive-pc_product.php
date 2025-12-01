<?php
/**
 * The template for displaying product archives
 * Updated for Block Theme (FSE) Compatibility
 */

defined( 'ABSPATH' ) || exit;

// 1. HEADER LOADER
if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
    block_template_part( 'header' );
} else {
    get_header();
}
?>

<div class="pc-container" style="padding: 40px 20px;">
    <header class="page-header" style="margin-bottom: 30px;">
        <h1 class="page-title"><?php post_type_archive_title(); ?></h1>
        <?php the_archive_description( '<div class="archive-description">', '</div>' ); ?>
    </header>

    <?php if ( have_posts() ) : ?>
        
        <?php echo do_shortcode('[pc_shop limit="12" cols="4"]'); ?>

        <div class="pc-pagination" style="margin-top: 30px; text-align: center;">
            <?php
            echo paginate_links( array(
                'prev_text' => '&laquo; Prev',
                'next_text' => 'Next &raquo;',
            ) );
            ?>
        </div>

    <?php else : ?>
        <p><?php _e( 'No products found.', 'pascommerce' ); ?></p>
    <?php endif; ?>
</div>

<?php
// 2. FOOTER LOADER
if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
    block_template_part( 'footer' );
} else {
    get_footer();
}
}