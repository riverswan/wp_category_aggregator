<?php
/**
 * Plugin Name:       KLEISAM.RU category aggregator
 * Plugin URI:        https://github.com/riverswan/
 * Description:       Наполняет категории товаров до одной полной страницы
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Pavel Riverswan
 * Author URI:        https://freelancehunt.com/freelancer/riverswan.html
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */


add_action('woocommerce_after_shop_loop', 'rvs_generate_related_products');
add_action('woocommerce_no_products_found', 'rvs_generate_related_products', 2);
add_filter('woocommerce_shortcode_products_query', 'rvs_woocommerce_shortcode_products_orderby');
add_filter('woocommerce_price_filter_widget_min_amount', 'rvs_price_filter_min');
add_filter('woocommerce_price_filter_widget_max_amount', 'rvs_price_filter_max');


//if (!function_exists('rvs_remove_parent_category_from_url')){
//	function rvs_remove_parent_category_from_url( $args ) {
//		$args['rewrite']['hierarchical'] = false;
//		return $args;
//	}
//
//	add_filter( 'woocommerce_taxonomy_args_product_cat', 'rvs_remove_parent_category_from_url' );
//}


function rvs_generate_related_products() {
	remove_action('woocommerce_no_products_found', 'wc_no_products_found');
	if (!is_product_category() || is_shop()) {
		return;
	}


	global $posts;
	$current_category = get_queried_object();
	$count_of_products_on_page = count($posts);
	$woo_rows_count = (int)(get_option('woocommerce_catalog_rows'));
	$woo_columns_count = (int)(get_option('woocommerce_catalog_columns'));
	$max_count_of_posts = $woo_rows_count * $woo_columns_count;
	$amount_of_products_to_add = $max_count_of_posts - $count_of_products_on_page;


	if ($amount_of_products_to_add <= 0) {
		return;
	}

	echo '<h3>Похожие товары</h3>';
	echo '<hr/>';


//	echo "<pre>";
//	print_r('COUNT OF POSTS IS ::: ' . $count_of_products_on_page);
//	echo "</pre>";
//
//	echo "<pre>";
//	print_r('WE NEED TO ADD ::: ');
//	print_r($amount_of_products_to_add);
//	echo "</pre>";

	$list_of_parent_categories = get_ancestors($current_category->term_id, 'product_cat');

	if (empty($list_of_parent_categories)) {
		$list_of_parent_categories[] = $current_category->term_id;
	}

	$list_of_products_on_current_page = rvs_get_products_ids_on_page($posts);


	$list_of_all_products_of_current_main_category = wc_get_products(
		array(
			'category' => get_term_by('id', end($list_of_parent_categories), 'product_cat'),
			'return' => 'ids',
		)
	);


	if (empty($list_of_products_on_current_page)) {
		$list_of_products_on_current_page = $list_of_all_products_of_current_main_category;
	}


	$list_of_related_products = rvs_generate_array_of_related_products($list_of_products_on_current_page);
//
//	echo "<pre>";
//	print_r('curr page products');
//	print_r($list_of_products_on_current_page);
//	echo "</pre>";
//
//	echo "<pre>";
//	print_r('main category all products');
//	print_r($list_of_all_products_of_current_main_category);
//	echo "</pre>";

//	echo "<pre>";
//	print_r('related products');
//	print_r($list_of_related_products);
//	echo "</pre>";


	// exclude from array of all products array of products on page
	$step1 = array_diff($list_of_all_products_of_current_main_category, $list_of_products_on_current_page);

	//merge result with array of related products
	$step2 = array_unique(array_merge($step1, $list_of_related_products));

	// check again if result does not contain products from page
	$step3 = array_diff($step2, $list_of_products_on_current_page);


	//reverse and normalize array of elements
	$resulting_list_of_products = array_reverse(array_values($step3));
//
//
//	echo "<pre>";
//	print_r('RESULT OF MIX');
//	print_r($resulting_list_of_products);
//	echo "</pre>";


	//if there is enough amount of products
	if (count($resulting_list_of_products) >= $amount_of_products_to_add) {
		rvs_add_resulting_products_to_page($resulting_list_of_products, $amount_of_products_to_add);
	} else {
		$random_products = rvs_generate_array_of_random_products_to_add_on_page(
			$amount_of_products_to_add - count($resulting_list_of_products)
			, $resulting_list_of_products);
		$resulting_list_of_products = array_merge($resulting_list_of_products, $random_products);
		rvs_add_resulting_products_to_page($resulting_list_of_products, $amount_of_products_to_add);
	}

}


function rvs_woocommerce_shortcode_products_orderby($args) {

	$min_price = isset($_GET['min_price']) ? esc_attr($_GET['min_price']) : false;
	$max_price = isset($_GET['max_price']) ? esc_attr($_GET['max_price']) : false;

	if ($min_price && $max_price) {
		$args['meta_query'] = array(
			array(
				'key' => '_price',
				'value' => array($min_price, $max_price),
				'compare' => 'BETWEEN',
				'type' => 'NUMERIC'
			),
		);
	}

	return $args;
}


function rvs_price_filter_min($amount) {
	return 0;
}


function rvs_price_filter_max($amount) {

	$max_price = wc_get_products(
		array(
			'limit' => -1,
			'orderby' => 'meta_value_num',
			'meta_key' => '_price',
			'order' => 'DESC'
		)
	);
	return $max_price[0]->price;
}

function rvs_get_products_ids_on_page($array_of_products) {
	if (!is_array($array_of_products)) {
		return array();
	}
	$array_of_ids = array();
	foreach ($array_of_products as $product) {
		$array_of_ids[] = $product->ID;
	}

	return $array_of_ids;
}


function rvs_add_resulting_products_to_page($array_of_products, $amount_of_products_to_add) {
	$str = implode(',', $array_of_products);
	echo do_shortcode("[products ids=$str limit=$amount_of_products_to_add orderby=post__in]");
}


function rvs_generate_array_of_random_products_to_add_on_page($amount_of_products_to_add, $list_of_products_to_exclude) {

	$args = array(
		'limit' => $amount_of_products_to_add,
		'orderby' => 'rand',
		'return' => 'ids',
		'exclude' => $list_of_products_to_exclude
	);
	return wc_get_products($args);
}


function rvs_generate_array_of_related_products($resulting_list_of_products) {
	$array_of_related_products = array();

	foreach ($resulting_list_of_products as $product) {

		$current_product_array_of_related_products = (
		array_merge(
			wc_get_product($product)->get_upsell_ids(),
			wc_get_product($product)->get_cross_sell_ids()
		)
		);

		foreach ($current_product_array_of_related_products as $item) {
			$array_of_related_products[] = $item;
		}
	}

	return $array_of_related_products;
}



//don't know if i need it
//
//
//function exclude_product_cat_children($wp_query) {
//
//	if (!is_product_category() && is_admin()) {
//		return;
//	}
//
//
//	if (isset ($wp_query->query_vars['product_cat']) && $wp_query->is_main_query()) {
//		$wp_query->set('tax_query', array(
//			array(
//				'taxonomy' => 'product_cat',
//				'field' => 'slug',
//				'terms' => $wp_query->query_vars['product_cat'],
//				'include_children' => false
//			)
//		));
//	}
//}
//
//
////add_filter('pre_get_posts', 'exclude_product_cat_children');
//

