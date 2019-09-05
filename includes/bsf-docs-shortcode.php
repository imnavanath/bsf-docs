<?php
/**
 * Functions related to shortcode for live search
 *
 * @package Documentation/Shortcode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'doc_wp_live_search', 'bsf_doc_render_search_box' );
add_shortcode( 'doc_wp_category_list', 'bsf_render_category_list' );
add_action( 'wp_ajax_bsf_load_search_results', 'bsf_load_search_results' );
add_action( 'wp_ajax_nopriv_bsf_load_search_results', 'bsf_load_search_results' );

/**
 * For rendering the search box.
 *
 * @param int $atts Get attributes for the search field.
 * @param int $content Get content to search from.
 */
function bsf_doc_render_search_box( $atts, $content = null ) {
	wp_enqueue_style( 'bsf-frontend-style' );
	wp_enqueue_script( 'bsf-live-search' );
	wp_enqueue_script( 'bsf-searchbox-script' );
	ob_start();
	$args = shortcode_atts(
		array(
			'placeholder' => __( 'Search for answers...', 'bsf-docs' ),
		),
		$atts
	);

	$selected_post_types = get_option( 'bsf_search_post_types', array( 'docs' ) );

	?>

	<div id="bsf-live-search">
		<div class="bsf-search-container">
			<div id="bsf-search-wrap">
				<form role="search" method="get" id="bsf-searchform" class="clearfix" action="<?php echo home_url(); ?>">

				<?php
				foreach ( $selected_post_types as $key => $post_type ) {
					echo '<input type="hidden" name="post_type[]" value="' . esc_attr( $post_type ) . '">';
				}
				?>
					<input type="text" placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>" onfocus="if (this.value == '') {this.value = '';}" onblur="if (this.value == '')  {this.value = '';}" value="" name="s" id="bsf-sq" autocapitalize="off" autocorrect="off" autocomplete="off">
					<div class="spinner live-search-loading bsf-search-loader">
						<img src="<?php echo esc_url( admin_url( 'images/spinner-2x.gif' ) ); ?>" >
					</div>
				</form>
		</div>
		</div>
	</div>

	<?php

	return ob_get_clean();
}

/**
 * Get the category list of docs.
 *
 * @param int $atts Get attributes for the categories.
 * @param int $content Get content to category.
 */
function bsf_render_category_list( $atts, $content = null ) {
	wp_enqueue_style( 'bsf-frontend-style' );

	ob_start();

	$get_args = shortcode_atts(
		array(
			'category' => 'docs_category',
		),
		$atts
	);

	$taxonomy_objects = get_terms(
		$get_args['category'],
		array(
			'hide_empty' => false,
			'pad_counts' => 1,
		)
	);

	?>

	<?php
	$doc_title = get_option( 'bsf_doc_title' );

	if ( '' != $doc_title ) {// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		?>
		<h1 class="docs-title"><?php echo esc_attr( $doc_title ); ?></h1>
		<?php
	}

	if ( $taxonomy_objects && ! is_wp_error( $taxonomy_objects ) ) :
		?>

	<div class="bsf-categories-wrap clearfix">

		<?php

		foreach ( $taxonomy_objects as $key => $object ) {

			if ( '0' == ( $object->count && $object->parent ) ) {// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison

				?>
			<div class="bsf-cat-col" >
				<a class="bsf-cat-link" href="<?php echo esc_url( get_term_link( $object->slug, $object->taxonomy ) ); ?>">
					<h4><?php echo esc_html( $object->name ); ?></h4>
					<span class="bsf-cat-count">
						<?php /* translators: %s: article count term */ ?>
						<?php printf( __( '%1$s Articles', 'bsf-docs' ), $object->count ); ?>
					</span>
				</a>
			</div>

				<?php
			}
		}
		?>
	</div>

		<?php
	endif;

	return ob_get_clean();
}

/**
 * To load search results.
 */
function bsf_load_search_results() {

	$query               = sanitize_text_field( $_GET['query'] );
	$selected_post_types = get_option( 'bsf_search_post_types' );
	$docs_post_type		 = get_option( 'bsf_search_post_types', 'docs' );
	$selected_post_types = ! $selected_post_types ? array( 'post', 'page' ) : $selected_post_types;
	$cat_array 			 = array();
	$cat_slugs			 = array();
	$post_search_data	 = array();
	$taxonomies 		 = get_object_taxonomies( $docs_post_type );

	foreach( $taxonomies as $taxonomy ) { // Gets every "category" (term) in this taxonomy to get the respective posts
		$terms = get_terms( $taxonomy );
		foreach( $terms as $term ) {
			$cat_array[ $term->slug ] = $term->name;
		}
	}

	if( ! empty( $cat_array ) ) {
		foreach( $cat_array as $cat_slug => $cat_name ) {
			if( ( strpos( $cat_slug, $query ) !== false ) || ( strpos( $cat_name, $query ) !== false ) ) {
				$cat_slugs[ $cat_slug ] = $cat_slug;
			}
		}
	}

	$args = array(
		'post_type'   => $selected_post_types,
		'post_status' => 'publish',
		's'           => $query,
	);

	$post_search_query = new WP_Query( $args );

	$tax_args = array(
		'post_type' => $docs_post_type,
		'tax_query' => array(
			'relation' => 'OR',
			array(
				'taxonomy' => 'docs_category',
				'field'    => 'slug',
				'terms'    => $cat_slugs,
			),
			array(
				'taxonomy' => 'docs_tag',
				'field'    => 'slug',
				'terms'    => $cat_slugs,
			),
		),
	);

	$tax_search_query = new WP_Query( $tax_args );

	$flag = false;

	ob_start();

	?>

	<ul id="bsf-search-result">

	<?php

		if ( $post_search_query->have_posts() ) {

			while ( $post_search_query->have_posts() ) {

				$post_search_query->the_post();
				$searched_post_id = get_the_ID();
				$searched_post_title = get_the_title();

				$post_search_data[ $searched_post_id ] = $searched_post_title;

				?>
					<li>
						<a href="<?php the_permalink(); ?>"><?php echo $searched_post_title; ?></a>
					</li>
				<?php
			}

			$flag = true;
		}

		if ( $tax_search_query->have_posts() ) {

			while ( $tax_search_query->have_posts() ) {

				$tax_search_query->the_post();
				$tax_search_id = get_the_ID();
				$tax_search_title = get_the_title();

				if( ! array_key_exists( $tax_search_id, $post_search_data ) ) {
					?>
						<li>
							<a href="<?php the_permalink(); ?>"><?php echo $tax_search_title; ?></a>
						</li>
					<?php
				}
			}

			$flag = true;
		}

		if( ! $flag ) { ?>
			<li class="nothing-here"><?php _e( 'Sorry, no docs were found.', 'bsf-docs' ); ?></li>
			<?php
		}
	?>
	</ul> 
	<?php

	wp_reset_postdata();

	$content = ob_get_clean();

	echo $content;
	die();
}
