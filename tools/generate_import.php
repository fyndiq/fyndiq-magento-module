<?php

class MagentoGenerateSampleImport{

    var $columns = 'sku,_store,_attribute_set,_type,_category,_root_category,_product_websites,accessories_size,accessories_type,apparel_type,author_artist,bag_luggage_type,bedding_pattern,bed_bath_type,books_music_type,camera_megapixels,camera_type,color,cost,country_of_manufacture,created_at,custom_design,custom_design_from,custom_design_to,custom_layout_update,decor_type,description,electronic_type,fit,format,frame_style,gallery,gender,gendered,genre,gift_message_available,gift_wrapping_available,gift_wrapping_price,has_options,homeware_style,home_decor_type,image,image_label,jewelry_type,length,lens_type,luggage_size,luggage_style,luggage_travel_style,manufacturer,material,media_gallery,meta_description,meta_keyword,meta_title,minimal_price,msrp,msrp_display_actual_price_type,msrp_enabled,name,necklace_length,news_from_date,news_to_date,occasion,options_container,page_layout,price,required_options,shoe_size,shoe_type,short_description,size,sleeve_length,small_image,small_image_label,special_from_date,special_price,special_to_date,status,style,tax_class_id,thumbnail,thumbnail_label,updated_at,url_key,url_path,visibility,weight,width,qty,min_qty,use_config_min_qty,is_qty_decimal,backorders,use_config_backorders,min_sale_qty,use_config_min_sale_qty,max_sale_qty,use_config_max_sale_qty,is_in_stock,notify_stock_qty,use_config_notify_stock_qty,manage_stock,use_config_manage_stock,stock_status_changed_auto,use_config_qty_increments,qty_increments,use_config_enable_qty_inc,enable_qty_increments,is_decimal_divided,_links_related_sku,_links_related_position,_links_crosssell_sku,_links_crosssell_position,_links_upsell_sku,_links_upsell_position,_associated_sku,_associated_default_qty,_associated_position,_tier_price_website,_tier_price_customer_group,_tier_price_qty,_tier_price_price,_group_price_website,_group_price_customer_group,_group_price_price,_media_attribute_id,_media_image,_media_lable,_media_position,_media_is_disabled,_custom_option_store,_custom_option_type,_custom_option_title,_custom_option_is_required,_custom_option_price,_custom_option_sku,_custom_option_max_characters,_custom_option_sort_order,_custom_option_row_title,_custom_option_row_price,_custom_option_row_sku,_custom_option_row_sort,_super_products_sku,_super_attribute_code,_super_attribute_option,_super_attribute_price_corr';

    var $categories = array(
        'Men/Shirts',
        'Sale/Men',
        'Men/New Arrivals',
        'Men/Blazers',
        'Men/Tees, Knits and Polos',
        'Men/Pants & Denim',
        'Women/Tops & Blouses',
        'Women',
        'Sale',
        'Women/New Arrivals',
        'Women/Dresses & Skirts'
    );

    public function run($numberProducts)
    {
        $columns = explode(',', $this->columns);
        $emptyRow = array_fill_keys($columns, null);
        $file = fopen('php://stdout', 'w');
        $this->writeCSVRow($file, $columns);
        for($i = 0; $i < $numberProducts; $i++) {
            $row = $this->generateProductRow($emptyRow, $i+1);
            $this->writeCSVRow($file, $row);
        }
        fclose($file);
    }

    protected function writeCSVRow($file, $fields)
    {
        return fputcsv($file, $fields);
    }

    protected function generateProductRow($emptyRow, $index) {
        $emptyRow['sku'] = sprintf('sample-%08d', $index);
        $emptyRow['_attribute_set'] = 'Clothing';
        $emptyRow['_type'] = 'simple';
        $emptyRow['_category'] = $this->categories[$index % count($this->categories)];
        $emptyRow['_root_category'] = 'Default Category';
        $emptyRow['_product_websites'] = 'base';
        $emptyRow['description'] = $this->generateDescription($index);
        $emptyRow['short_description'] = $this->generateDescription($index);
        $emptyRow['image'] = $this->generateImage($index);
        $emptyRow['msrp_display_actual_price_type'] = 'Use config';
        $emptyRow['msrp_enabled'] = 'Use config';
        $emptyRow['name'] = $this->generateName($index);
        $emptyRow['status'] = 1;
        $emptyRow['tax_class_id'] = 2;
        $emptyRow['visibility'] = 1;
        $emptyRow['qty'] = $this->generateQuantity($index);
        $emptyRow['is_in_stock'] = 1;
        $emptyRow['apparel_type'] = 'Shirts';
        $emptyRow['weight'] = 0;
        $emptyRow['small_image'] = '/m/s/msj000t_1.jpg';
        $emptyRow['small_image'] = '/m/s/msj000t_1.jpg';
        $emptyRow['price'] = $this->generatePrice($index);
        return $emptyRow;
    }

    protected function generateDescription($index)
    {
        return 'description-'.$index;
    }

    protected function generateImage($index)
    {
        return '/m/s/msj000t_1.jpg';
    }

    protected function generateName($index)
    {
        return 'Sample product with a long and meaningless name ' . $index;
    }

    protected function generateQuantity($index)
    {
        return $index % 100;
    }

    protected function generatePrice($index)
    {
        return $index % 1000;
    }

}

$numberProducts = 10;
if (isset($argv[1]) && is_numeric($argv[1])) {
    $numberProducts = (int)$argv[1];
}

$magentoGenerateSampleImport = new MagentoGenerateSampleImport();
$magentoGenerateSampleImport->run($numberProducts);
