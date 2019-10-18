<?php
class EvoProductFilter
{
    private $model;

    public function __construct($model)
    {
        $this->model = $model;
    }

    public function filter_woocommerce_get_query_vars($this_query_vars )
    {
        $this_query_vars[] = "evo-cat";
        $this_query_vars[] = "evo-min-price";
        $this_query_vars[] = "evo-max-price";
        $this_query_vars[] = "evo-size";
        $this_query_vars[] = "size-type";
        $this_query_vars[] = "evo-query";
        return $this_query_vars;
    }

    public function action_woocommerce_product_query($q, $instance)
    {
        $tax_query = $q->get('tax_query');
        $meta_query = $q->get('meta_query');
        $post__in = $q->get('post__in');

        if($evoQuery = get_query_var('evo-query'))
        {
            $q->set('s', esc_attr($evoQuery));
        }

        if($brandID = get_query_var('evo-cat'))
        {
            $tax_query[] = array(
                array(
                    'field' => 'term_taxonomy_id',
                    'terms' => esc_attr($brandID)
                )
            );
        }

        if($minPrice = get_query_var('evo-min-price') && $maxPrice = get_query_var('evo-max-price'))
        {
            $meta_query[] = array(
                'key'       => '_price',
                'value'     => array(esc_attr($minPrice), esc_attr($maxPrice)),
                'compare'   => 'BETWEEN',
                'type'      => 'DECIMAL'
            );
        }

        if($size = get_query_var('evo-size'))
        {
            $sizeType = 'eu_size'; // todo fetch this value
            
            $allSizes = $this->model->get_all_sizes_in_pluggi_size(esc_attr($sizeType), $size);
            foreach($allSizes as &$as)
            {
                $as = '\'' . $as . '\'';
            }
            $allSizes = implode(',', $allSizes);
            
            $post__in = $this->model->get_parent_ids_for_filter_size_query($allSizes);
            
            if(empty($post__in)) $q->set('post__in', array(0));
            else $q->set('post__in', $post__in); 
        }

        $q->set('tax_query', (array) $tax_query);
        $q->set('meta_query', $meta_query);
        return $q;
    } 

    /**
     * Used to filter order_by_price_desc_post_clauses
     *
     * @link https://docs.woocommerce.com/wc-apidocs/source-class-WC_Query.html#536
     * @see PLUG-180
     * @return void
     */
    public function sorting_price_high_to_low($args)
    {
        $args['join'] =  str_replace('max', 'min', $args['join']);
        return $args;
    }

    // public function default_orderby($option)
    // {
    //     return $option = 'price-desc';
    // }

    public function order_by_stock_status($posts_clauses)
    {
        global $wpdb;
        // only change query on WooCommerce loops
        if (is_woocommerce() && (is_shop() || is_product_category() || is_product_tag())) {
            $posts_clauses['join'] .= " INNER JOIN $wpdb->postmeta istockstatus ON ($wpdb->posts.ID = istockstatus.post_id) ";
            $posts_clauses['orderby'] = " istockstatus.meta_value ASC, " . $posts_clauses['orderby'];
            $posts_clauses['where'] = " AND istockstatus.meta_key = '_stock_status' AND istockstatus.meta_value <> '' " . $posts_clauses['where'];
        }
        return $posts_clauses;
    }

    private function filter_sizes()
    {
        $args['cat'] = get_query_var('evo-cat');
        $args['size-type'] = get_query_var('size-type');

        return $this->model->get_sizes($args);
    }

    private function filter_brands()
    {
        return $this->model->get_brands();
    }

    private function filter_prices()
    {
        return $this->model->get_overall_min_max_shoe_prices();
    }

    public function filter()
    {
        ob_start();
        $this->trigger_script();
        include('views/filter/filter.php');
        return ob_get_clean();
    }

    public function trigger_script(){
        add_action('wp_footer', array($this, 'add_filter_js'));
    }

    public function add_filter_js()
    {
        wp_enqueue_script( 'evo-filter', get_template_directory_uri() . '/assets/filter.js', array('jquery') );
        wp_localize_script( 'evo-filter', 'ajax_object',
                array('ajax_url' => admin_url('admin-ajax.php')));
    }
}