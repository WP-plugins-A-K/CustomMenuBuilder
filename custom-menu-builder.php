<?php
    /**
     * Plugin Name: Custom Menu Builder
     * Description: Builds a WordPress navigation menu from WooCommerce product categories, preserving the category hierarchy.
     * Version: 1.0.0
     * Author: Alex Káčerík
     */

    if (!defined('ABSPATH')) {
        return;
    }

    // Generate a menu from product categories.
    function cmb_generate_menu_from_categories(string $menu_name = 'Categories Menu'): void {
        $menu = wp_get_nav_menu_object($menu_name);
        $menu_id = $menu ? $menu->term_id : wp_create_nav_menu($menu_name);

        $existing_items = [];
        foreach (wp_get_nav_menu_items($menu_id) as $item) {
            if ($item->object === 'product_cat') {
                $existing_items[(int)$item->object_id] = $item->ID;
            }
        }

        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'parent'
        ]);

        if (is_wp_error($categories) || empty($categories)) {
            return;
        }

        $categories_by_parent = [];

        foreach ($categories as $category) {
            $categories_by_parent[$category->parent][] = $category;
        }

        $process_category = function (WP_Term $category, int $parent_menu_item_id = 0) use (
            &$process_category, $menu_id, &$existing_items, &$categories_by_parent
        ) {
            $args = [
                'menu-item-title'     => $category->name,
                'menu-item-object'    => 'product_cat',
                'menu-item-object-id' => $category->term_id,
                'menu-item-type'      => 'taxonomy',
                'menu-item-url'       => get_term_link($category),
                'menu-item-status'    => 'publish',
                'menu-item-parent-id' => $parent_menu_item_id,
            ];

            $existing_id = $existing_items[$category->term_id] ?? 0;
            $menu_item_id = wp_update_nav_menu_item($menu_id, $existing_id, $args);

            if (!empty($categories_by_parent[$category->term_id])) {
                foreach ($categories_by_parent[$category->term_id] as $child_category) {
                    $process_category($child_category, $menu_item_id);
                }
            }
        };

        if (!empty($categories_by_parent[0])) {
            foreach ($categories_by_parent[0] as $top_level_category) {
                $process_category($top_level_category);
            }
        }
    }

    // Add action to the hook.
    add_action('init', function () {
        if (get_option('cmb_menu_built') || !taxonomy_exists('product_cat')) {
            return;
        }
        cmb_generate_menu_from_categories();
        update_option('cmb_menu_built', true);
    });

    // Add action to the hook.
    add_action('wp_footer', function () {
        ?>
            <script>
                window.addEventListener('click', function (e) {
                    if (!window.matchMedia('(hover: hover)').matches) return;

                    if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;

                    const a = e.target.closest('li.menu-item-has-children > a');
                    if (!a || !a.href || a.getAttribute('href') === '#') return;

                    e.stopImmediatePropagation();
                    window.location.href = a.href;
                }, true);
            </script>
        <?php
    }, 100);